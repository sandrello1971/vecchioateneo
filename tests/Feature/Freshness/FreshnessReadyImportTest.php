<?php

namespace Tests\Feature\Freshness;

use App\Models\Course;
use App\Models\CourseChangelog;
use App\Models\CourseSource;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\UpdateProposal;
use App\Services\CourseSourceExtractor;
use App\Services\Freshness\CoordinatedMatchService;
use App\Services\InstructorManualService;
use App\Services\InstructorManualSplitterService;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * F-a — Aggancio dell'estrazione course_sources all'import del manuale formatore
 * (InstructorManualService.import / regenerateHtml). Caso semplice: corso SENZA storia
 * di apply. Append-only: v1.0 se assente, bump MAGGIORE se pristino, skip se ha storia
 * (rinviato a F-b). 0 blocchi o eccezione NON rompono l'import del manuale (additività).
 *
 * I test che convertono il .docx richiedono pandoc → auto-skip se assente (come
 * CourseSourceRecoverTest). Lo splitter lavora su content_html (no pandoc).
 */
class FreshnessReadyImportTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): string
    {
        return base_path('tests/Fixtures/p25/mini-course.docx');
    }

    private function requirePandoc(): void
    {
        try {
            app(CourseSourceExtractor::class)->assertPandocAvailable();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('pandoc non disponibile: ' . $e->getMessage());
        }
    }

    private function makeCourse(): Course
    {
        return Course::create([
            'name' => 'INTERFERENZA', 'slug' => 'c-' . uniqid(),
            'is_active' => true, 'sort_order' => 1,
        ]);
    }

    /** Servizio con RagService neutralizzato (niente embeddings HTTP nei test). */
    private function service(CourseSourceExtractor $extractor): InstructorManualService
    {
        $rag = Mockery::mock(RagService::class);
        $rag->shouldReceive('indexDocument')->andReturnNull();

        return new InstructorManualService(
            $rag,
            app(InstructorManualSplitterService::class),
            $extractor,
            app(CoordinatedMatchService::class),
            app(\App\Services\CourseDocumentParser::class)
        );
    }

    /** Corso con storia di apply dell'agente (changelog instructor) + sorgente corrente. */
    private function courseWithHistory(string $currentVersion = '2.0'): Course
    {
        $course = $this->makeCourse();
        CourseSource::create([
            'course_id' => $course->id, 'version' => $currentVersion,
            'blocks' => [['id' => 'b1', 'type' => 'P', 'text' => 'dato aggiornato dall agente']],
        ]);
        CourseChangelog::create([
            'course_id' => $course->id, 'kind' => 'apply', 'content_source' => 'instructor',
            'version_from' => '1.0', 'version_to' => $currentVersion, 'summary' => 'aggiornamento agente',
        ]);

        return $course;
    }

    private function instructorProposal(Course $course, string $status, string $blockId): UpdateProposal
    {
        return UpdateProposal::create([
            'course_id' => $course->id, 'content_source' => 'instructor', 'block_id' => $blockId,
            'before' => 'vecchio dato', 'after' => 'nuovo dato', 'audience' => 'adult', 'status' => $status,
        ]);
    }

    /** Estrattore controllabile: registra se è stato chiamato, restituisce blocchi fissi o lancia. */
    private function fakeExtractor(array $blocks = [], bool $shouldThrow = false): CourseSourceExtractor
    {
        return new class($blocks, $shouldThrow) extends CourseSourceExtractor {
            public bool $called = false;
            public function __construct(public array $blocksToReturn, public bool $shouldThrow) {}
            public function extractFromDocx(string $docxPath): array
            {
                $this->called = true;
                if ($this->shouldThrow) {
                    throw new \RuntimeException('estrazione fallita (test)');
                }
                return ['blocks' => $this->blocksToReturn, 'warnings' => [], 'frontmatter' => []];
            }
        };
    }

    // ---- Gate 1: corso nuovo → sezioni (splitter) E course_sources v1.0 ----

    public function test_corso_nuovo_genera_sezioni_e_sorgente_v1(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();

        $this->service(app(CourseSourceExtractor::class))
            ->import($this->fixture(), $course, 'Manuale formatore');

        // splitter: sezioni del manuale create accanto
        $this->assertGreaterThan(0, InstructorManualSection::where('course_id', $course->id)->count());
        // F-a: sorgente strutturato alla prima versione
        $this->assertDatabaseHas('course_sources', ['course_id' => $course->id, 'version' => '1.0']);
        $this->assertCount(9, CourseSource::where('course_id', $course->id)->first()->blocks);
    }

    // ---- Gate 2: corso pristino con sorgente, senza apply → bump MAGGIORE, vecchia preservata ----

    public function test_corso_pristino_riimport_fa_bump_maggiore(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();
        CourseSource::create([
            'course_id' => $course->id, 'version' => '1.0',
            'blocks' => [['id' => 'x', 'type' => 'P', 'text' => 'vecchio']],
        ]);

        $this->service(app(CourseSourceExtractor::class))
            ->import($this->fixture(), $course, 'Manuale formatore');

        // append-only: la v1.0 resta, nasce la v2.0 (bump maggiore)
        $this->assertDatabaseHas('course_sources', ['course_id' => $course->id, 'version' => '1.0']);
        $this->assertDatabaseHas('course_sources', ['course_id' => $course->id, 'version' => '2.0']);
        $this->assertSame(2, CourseSource::where('course_id', $course->id)->count());
        // corrente = ultima per created_at/id = 2.0, coi blocchi del docx
        $current = CourseSource::where('course_id', $course->id)
            ->orderByDesc('created_at')->orderByDesc('id')->first();
        $this->assertSame('2.0', $current->version);
        $this->assertCount(9, $current->blocks);
    }

    // ---- Gate 3: corso CON storia di apply → estrazione SALTATA (rinviata a F-b), import ok ----

    public function test_corso_con_storia_apply_salta_estrazione_ma_importa(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();
        CourseSource::create([
            'course_id' => $course->id, 'version' => '2.0',
            'blocks' => [['id' => 'x', 'type' => 'P', 'text' => 'live']],
        ]);
        CourseChangelog::create([
            'course_id' => $course->id, 'kind' => 'apply', 'content_source' => 'instructor',
            'version_from' => '1.0', 'version_to' => '2.0', 'summary' => 'aggiornamento agente',
        ]);

        $spy = $this->fakeExtractor([['id' => 'n', 'type' => 'P', 'text' => 'nuovo']]);
        $this->service($spy)->import($this->fixture(), $course, 'Manuale formatore');

        // estrazione mai eseguita, nessuna nuova versione
        $this->assertFalse($spy->called);
        $this->assertSame(1, CourseSource::where('course_id', $course->id)->count());
        $this->assertNull(CourseSource::where('course_id', $course->id)->where('version', '3.0')->first());
        // ma il manuale è stato importato comunque (Material + sezioni)
        $this->assertSame(1, Material::where('course_id', $course->id)->where('is_instructor_only', true)->count());
        $this->assertGreaterThan(0, InstructorManualSection::where('course_id', $course->id)->count());
    }

    // ---- Gate 4: 0 blocchi → sezioni sì, sorgente no, import non fallisce ----

    public function test_zero_blocchi_non_blocca_import(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();

        $this->service($this->fakeExtractor([]))
            ->import($this->fixture(), $course, 'Manuale formatore');

        $this->assertSame(0, CourseSource::where('course_id', $course->id)->count()); // sorgente NON generato
        $this->assertGreaterThan(0, InstructorManualSection::where('course_id', $course->id)->count()); // sezioni sì
        $this->assertSame(1, Material::where('course_id', $course->id)->count()); // manuale importato
    }

    // ---- Gate 5: eccezione estrattore → import del manuale comunque riuscito (additività) ----

    public function test_eccezione_estrattore_non_rompe_import(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();

        $material = $this->service($this->fakeExtractor([], true))
            ->import($this->fixture(), $course, 'Manuale formatore');

        $this->assertNotNull($material->id); // import riuscito nonostante l'eccezione
        $this->assertSame(0, CourseSource::where('course_id', $course->id)->count());
        $this->assertGreaterThan(0, InstructorManualSection::where('course_id', $course->id)->count());
    }

    // ===================== F-b: caso con storia di apply =====================

    // ---- Gate 1: storia + NO conferma → sorgente intatto, manuale comunque importato ----

    public function test_storia_senza_conferma_non_sovrascrive(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->courseWithHistory('2.0');

        $spy = $this->fakeExtractor([['id' => 'n', 'type' => 'P', 'text' => 'nuovo']]);
        $this->service($spy)->import($this->fixture(), $course, 'Manuale formatore'); // niente conferma

        $this->assertFalse($spy->called); // estrazione NON eseguita
        $this->assertSame(1, CourseSource::where('course_id', $course->id)->count()); // sorgente intatto
        $this->assertNull(CourseSource::where('course_id', $course->id)->where('version', '3.0')->first());
        // ma manuale importato (additività)
        $this->assertSame(1, Material::where('course_id', $course->id)->where('is_instructor_only', true)->count());
        $this->assertGreaterThan(0, InstructorManualSection::where('course_id', $course->id)->count());
    }

    // ---- Gate 2: storia + conferma → bump maggiore (3.0), vecchie preservate, corrente = nuova ----

    public function test_storia_con_conferma_bump_maggiore(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->courseWithHistory('2.0');

        $this->service(app(CourseSourceExtractor::class))
            ->import($this->fixture(), $course, 'Manuale formatore', null, null, confirmOverwrite: true);

        // append-only: la 2.0 resta, nasce la 3.0 (bump maggiore)
        $this->assertDatabaseHas('course_sources', ['course_id' => $course->id, 'version' => '2.0']);
        $this->assertDatabaseHas('course_sources', ['course_id' => $course->id, 'version' => '3.0']);
        $this->assertSame(2, CourseSource::where('course_id', $course->id)->count());
        $current = CourseSource::where('course_id', $course->id)
            ->orderByDesc('created_at')->orderByDesc('id')->first();
        $this->assertSame('3.0', $current->version);
        $this->assertCount(9, $current->blocks); // dal docx
    }

    // ---- Gate 3: conferma → proposte instructor aperte rifiutate + motivo tracciato ----

    public function test_conferma_rifiuta_proposte_instructor_aperte(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->courseWithHistory('2.0');
        $pending = $this->instructorProposal($course, 'pending', 'old-1');
        $approved = $this->instructorProposal($course, 'approved', 'old-2');

        $this->service(app(CourseSourceExtractor::class))
            ->import($this->fixture(), $course, 'Manuale formatore', null, null, confirmOverwrite: true);

        $this->assertSame('rejected', $pending->refresh()->status);
        $this->assertStringContainsString('ri-estrazione manuale v3.0', $pending->apply_error);
        $this->assertSame('rejected', $approved->refresh()->status);
        $this->assertStringContainsString('ri-estrazione manuale v3.0', $approved->apply_error);
    }

    // ---- Gate 4: conferma → cascata B-b sulle figlie studente coordinate ----

    public function test_conferma_propaga_cascata_bb_alle_figlie_studente(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->courseWithHistory('2.0');
        $parent = $this->instructorProposal($course, 'approved', 'old-parent');

        // Figlie studente coordinate del padre formatore.
        $childMatched = UpdateProposal::create([
            'course_id' => $course->id, 'content_source' => 'student', 'origin' => 'coordinated',
            'parent_proposal_id' => $parent->id, 'before' => 'x', 'after' => null,
            'audience' => 'adult', 'status' => 'matched',
        ]);
        $childApplied = UpdateProposal::create([
            'course_id' => $course->id, 'content_source' => 'student', 'origin' => 'coordinated',
            'parent_proposal_id' => $parent->id, 'before' => 'y', 'after' => 'z',
            'audience' => 'adult', 'status' => 'applied',
        ]);

        $this->service(app(CourseSourceExtractor::class))
            ->import($this->fixture(), $course, 'Manuale formatore', null, null, confirmOverwrite: true);

        // Padre formatore rifiutato.
        $this->assertSame('rejected', $parent->refresh()->status);
        // Figlia matched → rejected + orphaned.
        $childMatched->refresh();
        $this->assertSame('rejected', $childMatched->status);
        $this->assertNotNull($childMatched->orphaned_at);
        $this->assertStringContainsString('ri-estrazione', $childMatched->orphan_reason);
        // Figlia applied → resta applied (live) + orphaned (segnalata).
        $childApplied->refresh();
        $this->assertSame('applied', $childApplied->status);
        $this->assertNotNull($childApplied->orphaned_at);
    }

    // ---- Gate 5: corso SENZA storia → F-a invariato (nessuna conferma richiesta) ----

    public function test_senza_storia_estrae_senza_conferma_e_non_tocca_proposte(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();
        // Proposta aperta su un corso pristino: F-a non la tocca (gestione orfani è solo F-b).
        $pending = $this->instructorProposal($course, 'pending', 'old-1');

        $this->service(app(CourseSourceExtractor::class))
            ->import($this->fixture(), $course, 'Manuale formatore'); // nessuna conferma

        // Estrazione avvenuta (v1.0), senza bisogno di conferma.
        $this->assertDatabaseHas('course_sources', ['course_id' => $course->id, 'version' => '1.0']);
        // Primo import (nessuna versione precedente): nessuna ancora da invalidare → proposta intatta.
        $this->assertSame('pending', $pending->refresh()->status);
    }

    // ---- xF: bump pristino F-a che SOSTITUISCE una versione → invalida le ancore aperte + cascata B-b ----

    public function test_bump_pristino_invalida_proposte_aperte_e_propaga_cascata(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();
        // Corso PRISTINO (nessuna storia di apply) ma con sorgente già esistente → il ri-import farà 1.0→2.0.
        CourseSource::create([
            'course_id' => $course->id, 'version' => '1.0',
            'blocks' => [['id' => 'b1', 'type' => 'P', 'text' => 'vecchio']],
        ]);
        // Proposta instructor aperta ancorata ai vecchi block_id + figlie studente coordinate.
        $parent = $this->instructorProposal($course, 'pending', 'old-1');
        $childMatched = UpdateProposal::create([
            'course_id' => $course->id, 'content_source' => 'student', 'origin' => 'coordinated',
            'parent_proposal_id' => $parent->id, 'before' => 'x', 'after' => null,
            'audience' => 'adult', 'status' => 'matched',
        ]);
        $childApplied = UpdateProposal::create([
            'course_id' => $course->id, 'content_source' => 'student', 'origin' => 'coordinated',
            'parent_proposal_id' => $parent->id, 'before' => 'y', 'after' => 'z',
            'audience' => 'adult', 'status' => 'applied',
        ]);

        // Ri-import F-a: nessuna conferma richiesta (corso senza storia), bump 1.0→2.0.
        $this->service(app(CourseSourceExtractor::class))
            ->import($this->fixture(), $course, 'Manuale formatore');

        $this->assertDatabaseHas('course_sources', ['course_id' => $course->id, 'version' => '2.0']);
        // Proposta padre rifiutata, motivo tracciato (ancore invalidate dal bump 2.0).
        $parent->refresh();
        $this->assertSame('rejected', $parent->status);
        $this->assertStringContainsString('ri-estrazione manuale v2.0', $parent->apply_error);
        // Cascata B-b: figlia matched → rejected+orphaned; figlia applied → resta+orphaned+segnalata.
        $childMatched->refresh();
        $this->assertSame('rejected', $childMatched->status);
        $this->assertNotNull($childMatched->orphaned_at);
        $childApplied->refresh();
        $this->assertSame('applied', $childApplied->status);
        $this->assertNotNull($childApplied->orphaned_at);
    }
}
