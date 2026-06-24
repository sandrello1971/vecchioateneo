<?php

namespace Tests\Feature\Freshness;

use App\Models\Course;
use App\Models\CourseChangelog;
use App\Models\CourseSource;
use App\Models\FormatoreSnapshot;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\Module;
use App\Models\UpdateProposal;
use App\Services\Freshness\ProposalApplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P25.3c — Applicazione (variante A: solo formatore) con versioning + rollback.
 * Verbatim o niente; HITL (solo approved); studente (modules.content) MAI toccato.
 */
class ProposalApplicatorTest extends TestCase
{
    use RefreshDatabase;

    private const BEFORE = 'Il mercato AI italiano vale 1,8 miliardi di euro nel 2025';
    private const AFTER = 'Il mercato AI italiano vale 2,3 miliardi di euro nel 2026';
    private const BLOCK = 'p1-cap3-sec1-p2';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function course(): Course
    {
        return Course::create(['name' => 'INTERFERENZA', 'slug' => 'consilium-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function source(Course $course, string $version, string $blockText): CourseSource
    {
        return CourseSource::create(['course_id' => $course->id, 'version' => $version, 'blocks' => [
            ['id' => self::BLOCK, 'type' => 'P', 'text' => $blockText],
        ]]);
    }

    private function section(Course $course, string $contentHtml): InstructorManualSection
    {
        $material = Material::create(['course_id' => $course->id, 'title' => 'Manuale formatore', 'is_instructor_only' => true]);
        return InstructorManualSection::create([
            'material_id' => $material->id, 'course_id' => $course->id,
            'title' => 'Sezione', 'anchor' => 'sez-' . uniqid(), 'heading_level' => 2, 'sort_order' => 0,
            'content_html' => $contentHtml,
        ]);
    }

    private function approved(Course $course, array $attrs = []): UpdateProposal
    {
        return UpdateProposal::create(array_merge([
            'course_id' => $course->id, 'block_id' => self::BLOCK, 'sentence_ref' => 0,
            'before' => self::BEFORE, 'after' => self::AFTER, 'reason' => 'dato aggiornato',
            'source' => 'https://istat.it', 'source_type' => 'web', 'confidence' => 0.86,
            'audience' => 'adult', 'status' => 'approved', 'reviewed_at' => now(),
        ], $attrs));
    }

    public function test_apply_crea_nuova_versione_aggiorna_live_salva_backup_e_non_tocca_studente(): void
    {
        $course = $this->course();
        $this->source($course, '2.0', self::BEFORE);
        $section = $this->section($course, '<p>' . self::BEFORE . ', con una crescita del 58%.</p>');
        $studentModule = Module::create(['course_id' => $course->id, 'title' => 'M1', 'content' => '<p>versione studente riformulata</p>', 'sort_order' => 0]);
        $this->approved($course);

        $res = app(ProposalApplicator::class)->apply($course);

        $this->assertSame(1, $res['applied']);
        $this->assertSame('2.0', $res['version_from']);
        $this->assertSame('2.1', $res['version_to']);

        // Nuova versione col testo aggiornato; vecchia INTATTA.
        $this->assertSame(self::AFTER, CourseSource::where('course_id', $course->id)->where('version', '2.1')->first()->blocks[0]['text']);
        $this->assertSame(self::BEFORE, CourseSource::where('course_id', $course->id)->where('version', '2.0')->first()->blocks[0]['text']);

        // Formatore live aggiornato (after), coda '58%' preservata.
        $this->assertStringContainsString(self::AFTER, $section->refresh()->content_html);
        $this->assertStringContainsString('crescita del 58%', $section->content_html);
        $this->assertStringNotContainsString(self::BEFORE, $section->content_html);

        // Backup del contenuto PRE-applicazione salvato.
        $snap = FormatoreSnapshot::where('course_id', $course->id)->where('version', '2.1')->first();
        $this->assertNotNull($snap);
        $this->assertStringContainsString(self::BEFORE, $snap->content_html);

        // Proposta applicata.
        $p = UpdateProposal::where('course_id', $course->id)->first();
        $this->assertSame('applied', $p->status);
        $this->assertNotNull($p->applied_at);

        // STUDENTE non toccato.
        $this->assertSame('<p>versione studente riformulata</p>', $studentModule->refresh()->content);

        // PDF rigenerato.
        Storage::disk('local')->assertExists("course-sources/{$course->id}/v2.1.pdf");
    }

    public function test_changelog_scritto_con_versioni(): void
    {
        $course = $this->course();
        $this->source($course, '2.0', self::BEFORE);
        $this->section($course, '<p>' . self::BEFORE . '.</p>');
        $p = $this->approved($course);

        app(ProposalApplicator::class)->apply($course);

        $this->assertDatabaseHas('course_changelog', [
            'course_id' => $course->id, 'proposal_id' => $p->id,
            'version_from' => '2.0', 'version_to' => '2.1', 'kind' => 'apply',
        ]);
    }

    public function test_before_non_trovato_nel_live_fallisce_pulito(): void
    {
        $course = $this->course();
        $this->source($course, '2.0', self::BEFORE);
        // Il live è riformulato: NON contiene il before verbatim.
        $section = $this->section($course, '<p>In Italia il mercato AI ha raggiunto 1,8 miliardi nel 2025.</p>');
        $p = $this->approved($course);

        $res = app(ProposalApplicator::class)->apply($course);

        $this->assertSame(0, $res['applied']);
        $this->assertSame('2.0', $res['version_from']);
        $this->assertNull($res['version_to']); // nessuna nuova versione
        $this->assertCount(1, $res['failed']);

        // Nessuna modifica: proposta resta approved con apply_error; live intatto.
        $p->refresh();
        $this->assertSame('approved', $p->status);
        $this->assertStringContainsString('formatore', $p->apply_error);
        $this->assertStringContainsString('1,8 miliardi nel 2025', $section->refresh()->content_html);
        $this->assertSame(1, CourseSource::where('course_id', $course->id)->count()); // niente v2.1
    }

    public function test_before_non_unico_nel_live_fallisce_pulito(): void
    {
        $course = $this->course();
        $this->source($course, '2.0', self::BEFORE);
        // Il before compare DUE volte → non univoco → fallimento (non si sceglie a caso).
        $this->section($course, '<p>' . self::BEFORE . '. Ripetuto: ' . self::BEFORE . '.</p>');
        $p = $this->approved($course);

        $res = app(ProposalApplicator::class)->apply($course);

        $this->assertSame(0, $res['applied']);
        $this->assertStringContainsString('non unico', $p->refresh()->apply_error);
        $this->assertSame('approved', $p->status);
        $this->assertSame(1, CourseSource::where('course_id', $course->id)->count());
    }

    public function test_apply_normalizza_apostrofi_tipografici(): void
    {
        $course = $this->course();
        $before = "L'adozione dell'AI nelle imprese è cresciuta";
        $after = "L'adozione dell'AI nelle imprese è in forte crescita";
        $this->source($course, '2.0', $before);
        // Live con apostrofi CURVI: la normalizzazione 1:1 deve comunque ritrovarlo.
        $section = $this->section($course, '<p>L’adozione dell’AI nelle imprese è cresciuta molto.</p>');
        $this->approved($course, ['before' => $before, 'after' => $after]);

        $res = app(ProposalApplicator::class)->apply($course);

        $this->assertSame(1, $res['applied']);
        $this->assertStringContainsString('in forte crescita', $section->refresh()->content_html);
    }

    public function test_rollback_ripristina_sorgente_e_formatore(): void
    {
        $course = $this->course();
        $this->source($course, '2.0', self::BEFORE);
        $section = $this->section($course, '<p>' . self::BEFORE . ', con una crescita del 58%.</p>');
        $this->approved($course);

        app(ProposalApplicator::class)->apply($course); // → v2.1, live aggiornato
        $this->assertStringContainsString(self::AFTER, $section->refresh()->content_html);

        $res = app(ProposalApplicator::class)->rollback($course);

        $this->assertTrue($res['rolled_back']);
        $this->assertSame('2.1', $res['version_from']);
        $this->assertSame('2.2', $res['version_to']);
        $this->assertSame('2.0', $res['restored_to']);

        // Sorgente: nuova versione 2.2 = copia di 2.0 (testo before ripristinato).
        $v22 = CourseSource::where('course_id', $course->id)->where('version', '2.2')->first();
        $this->assertNotNull($v22);
        $this->assertSame(self::BEFORE, $v22->blocks[0]['text']);

        // Formatore live ripristinato al contenuto pre-applicazione.
        $this->assertStringContainsString(self::BEFORE, $section->refresh()->content_html);
        $this->assertStringNotContainsString(self::AFTER, $section->content_html);

        $this->assertDatabaseHas('course_changelog', [
            'course_id' => $course->id, 'version_from' => '2.1', 'version_to' => '2.2', 'kind' => 'rollback',
        ]);
    }

    public function test_solo_approved_si_applicano_pending_mai(): void
    {
        $course = $this->course();
        $this->source($course, '2.0', self::BEFORE);
        $section = $this->section($course, '<p>' . self::BEFORE . '.</p>');
        $pending = $this->approved($course, ['status' => 'pending']); // NON approvata

        $res = app(ProposalApplicator::class)->apply($course);

        // Nessuna applicazione: la pending non è mai consumata.
        $this->assertSame(0, $res['applied']);
        $this->assertSame('pending', $pending->refresh()->status);
        $this->assertNull($pending->applied_at);
        $this->assertSame(1, CourseSource::where('course_id', $course->id)->count());
        $this->assertStringContainsString(self::BEFORE, $section->refresh()->content_html); // live intatto
    }

    // ---- Blocchi LISTA (BUL/NUM): il contenuto vive in `items`, non in `text` ----

    public function test_apply_su_blocco_lista_aggiorna_item_giusto(): void
    {
        $course = $this->course();
        // Sorgente con un blocco LISTA (BUL): nessun `text`, il contenuto è negli `items`.
        CourseSource::create(['course_id' => $course->id, 'version' => '2.0', 'blocks' => [
            ['id' => 'bul1', 'type' => 'BUL', 'items' => [
                'Prima voce generica.',
                self::BEFORE,
                'Terza voce generica.',
            ]],
        ]]);
        // Live: il before compare come <li> una sola volta.
        $section = $this->section($course, '<ul><li>Prima voce generica.</li><li>' . self::BEFORE . '</li><li>Terza voce generica.</li></ul>');
        $this->approved($course, ['block_id' => 'bul1']);

        $res = app(ProposalApplicator::class)->apply($course);

        $this->assertSame(1, $res['applied']);
        // Nuova versione: SOLO l'item bersaglio aggiornato, gli altri intatti.
        $v21 = CourseSource::where('course_id', $course->id)->where('version', '2.1')->first();
        $this->assertSame(self::AFTER, $v21->blocks[0]['items'][1]);
        $this->assertSame('Prima voce generica.', $v21->blocks[0]['items'][0]);
        $this->assertSame('Terza voce generica.', $v21->blocks[0]['items'][2]);
        // Vecchia versione INTATTA.
        $v20 = CourseSource::where('course_id', $course->id)->where('version', '2.0')->first();
        $this->assertSame(self::BEFORE, $v20->blocks[0]['items'][1]);
        // Formatore live aggiornato.
        $this->assertStringContainsString(self::AFTER, $section->refresh()->content_html);
        $this->assertStringNotContainsString(self::BEFORE, $section->content_html);
        $this->assertSame('applied', UpdateProposal::where('course_id', $course->id)->first()->status);
    }

    public function test_apply_lista_before_non_unico_negli_item_fallisce_pulito(): void
    {
        $course = $this->course();
        // Il before è in DUE item → non univoco sulla lista → fallimento pulito (niente scelta a caso).
        CourseSource::create(['course_id' => $course->id, 'version' => '2.0', 'blocks' => [
            ['id' => 'bul1', 'type' => 'BUL', 'items' => [
                self::BEFORE,
                'Voce intermedia.',
                self::BEFORE,
            ]],
        ]]);
        $this->section($course, '<ul><li>' . self::BEFORE . '</li></ul>');
        $p = $this->approved($course, ['block_id' => 'bul1']);

        $res = app(ProposalApplicator::class)->apply($course);

        $this->assertSame(0, $res['applied']);
        $this->assertSame('approved', $p->refresh()->status);
        $this->assertStringContainsString('item della lista', $p->apply_error);
        $this->assertSame(1, CourseSource::where('course_id', $course->id)->count()); // niente v2.1
    }
}
