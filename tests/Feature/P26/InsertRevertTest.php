<?php

namespace Tests\Feature\P26;

use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CourseSource;
use App\Models\CoverageGap;
use App\Models\GapDraft;
use App\Models\GapInsertion;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\Module;
use App\Models\StudentSourceVersion;
use App\Services\GapInserter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P26 Fasi C+D — inserimento coordinato append-only e REVERSIBILE. Il test di reversibilità
 * (inserisci → annulla → corso identico) è il vincolo di sicurezza chiave.
 */
class InsertRevertTest extends TestCase
{
    use RefreshDatabase;

    private string $adminEmail = 'rev@ente.it';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.p26.enabled' => true]);
        Admin::create(['name' => 'Rev', 'email' => $this->adminEmail, 'password' => 'pw', 'is_active' => true]);
    }

    private function actingAdmin()
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => $this->adminEmail]);
    }

    /** Corso con sorgente, sezione live, modulo, gap accettato e bozza approvata + posizione confermata. */
    private function scenario(string $audience = 'adult'): array
    {
        $course = Course::create(['name' => 'FREQUENZA', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        CourseFreshnessConfig::create(['course_id' => $course->id, 'web_search_enabled' => true,
            'primary_sources' => [], 'audience' => $audience, 'topic' => 'agenti-ai']);

        CourseSource::create(['course_id' => $course->id, 'version' => '1.0', 'blocks' => [
            ['id' => 'p1', 'type' => 'PART', 'text' => 'Parte Prima'],
            ['id' => 'p1-cap1', 'type' => 'H1', 'text' => 'Capitolo 1'],
            ['id' => 'p1-cap1-sec2', 'type' => 'H2', 'text' => '1.2 MCP — Model Context Protocol'],
            ['id' => 'p1-cap1-sec2-p1', 'type' => 'P', 'text' => 'MCP collega gli agenti agli strumenti.'],
        ]]);

        $mat = Material::create(['course_id' => $course->id, 'title' => 'Manuale', 'is_instructor_only' => true]);
        InstructorManualSection::create(['material_id' => $mat->id, 'course_id' => $course->id, 'title' => 'Cap 1',
            'anchor' => 'a-' . uniqid(), 'heading_level' => 2, 'sort_order' => 0,
            'content_html' => '<h2>1.2 MCP — Model Context Protocol</h2><p>MCP collega gli agenti agli strumenti.</p>']);

        $module = Module::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0,
            'content' => '<h2>Strumenti</h2><p>Gli agenti usano strumenti tramite MCP.</p><p>Fine del modulo.</p>']);

        $gap = CoverageGap::create(['course_id' => $course->id, 'topic' => 'agenti-ai', 'title' => 'Protocollo A2A',
            'rationale' => 'agent-to-agent', 'status' => 'accepted']);
        $draft = GapDraft::create(['coverage_gap_id' => $gap->id, 'status' => 'approved',
            'formatore_html' => '<h3>A2A in aula</h3><p>Guida formatore al protocollo A2A.</p><ul><li>punto uno</li></ul>',
            'studente_html' => '<h3>Il protocollo A2A</h3><p>Versione studente del protocollo A2A.</p>',
            'place_formatore_block_id' => 'p1-cap1-sec2',
            'place_student_module_id' => $module->id,
            'place_student_anchor' => 'Gli agenti usano strumenti tramite MCP.',
            'placement_confirmed' => true]);

        return compact('course', 'gap', 'draft', 'module');
    }

    // ---- Insert formatore: nuova versione, id non collidente + meta, esistenti invariati ----

    public function test_insert_formatore_nuova_versione_id_e_meta(): void
    {
        ['course' => $course, 'draft' => $draft] = $this->scenario();

        app(GapInserter::class)->insert($draft);

        $v11 = CourseSource::where('course_id', $course->id)->where('version', '1.1')->first();
        $this->assertNotNull($v11);
        $ids = array_column($v11->blocks, 'id');

        // Blocco inserito subito DOPO l'ancora, id fuori schema, meta.origin marcato.
        $pos = array_search('p1-cap1-sec2', $ids, true);
        $this->assertSame('p1-cap1-sec2-ins1', $ids[$pos + 1]);
        $ins = collect($v11->blocks)->firstWhere('id', 'p1-cap1-sec2-ins1');
        $this->assertSame('gap_insert', $ins['meta']['origin']);

        // Versione precedente intatta; tutti i block_id esistenti ancora presenti (no orfananza).
        $this->assertCount(4, CourseSource::where('course_id', $course->id)->where('version', '1.0')->first()->blocks);
        foreach (['p1', 'p1-cap1', 'p1-cap1-sec2', 'p1-cap1-sec2-p1'] as $id) {
            $this->assertContains($id, $ids);
        }
    }

    // ---- Insert studente: splice nel punto giusto, versionato ----

    public function test_insert_studente_splice_e_versione(): void
    {
        ['course' => $course, 'draft' => $draft, 'module' => $module] = $this->scenario();

        app(GapInserter::class)->insert($draft);

        $html = $module->refresh()->content;
        $this->assertStringContainsString('Versione studente del protocollo A2A', $html);
        // il frammento sta DOPO l'ancora e PRIMA della frase successiva (ordine corretto)
        $this->assertTrue(mb_strpos($html, 'tramite MCP') < mb_strpos($html, 'protocollo A2A'));
        $this->assertTrue(mb_strpos($html, 'protocollo A2A') < mb_strpos($html, 'Fine del modulo'));
        // markup valido: frase originale ancora presente integra
        $this->assertStringContainsString('Fine del modulo.', $html);
        $this->assertGreaterThanOrEqual(1, StudentSourceVersion::where('course_id', $course->id)->count());
    }

    // ---- REVERSIBILITÀ (obbligatorio): inserisci → annulla → identico ----

    public function test_reversibilita_corso_identico_dopo_annulla(): void
    {
        ['course' => $course, 'draft' => $draft] = $this->scenario();

        $blocksBefore = CourseSource::where('course_id', $course->id)->orderByDesc('created_at')->orderByDesc('id')->first()->blocks;
        $modulesBefore = Module::where('course_id', $course->id)->orderBy('id')->pluck('content', 'id')->toArray();
        $sectionsBefore = InstructorManualSection::where('course_id', $course->id)->orderBy('id')->pluck('content_html', 'id')->toArray();

        $insertion = app(GapInserter::class)->insert($draft);
        // (sanity: durante l'inserimento qualcosa è cambiato)
        $this->assertNotEquals($modulesBefore, Module::where('course_id', $course->id)->orderBy('id')->pluck('content', 'id')->toArray());

        app(GapInserter::class)->revert($insertion);

        // Stato CORRENTE identico a prima.
        $current = CourseSource::where('course_id', $course->id)->orderByDesc('created_at')->orderByDesc('id')->first();
        $this->assertEquals($blocksBefore, $current->blocks);
        $this->assertSame($modulesBefore, Module::where('course_id', $course->id)->orderBy('id')->pluck('content', 'id')->toArray());
        $this->assertSame($sectionsBefore, InstructorManualSection::where('course_id', $course->id)->orderBy('id')->pluck('content_html', 'id')->toArray());
        $this->assertSame('reverted', $insertion->refresh()->status);
    }

    // ---- gate minori ----

    public function test_gate_minori_blocca_senza_conferma(): void
    {
        ['draft' => $draft, 'course' => $course] = $this->scenario('minor');

        try {
            app(GapInserter::class)->insert($draft); // senza conferma
            $this->fail('Atteso blocco minori.');
        } catch (\RuntimeException $e) {
            $this->assertSame('minor_confirmation_required', $e->getMessage());
        }
        $this->assertSame(1, CourseSource::where('course_id', $course->id)->count()); // nulla inserito

        app(GapInserter::class)->insert($draft, true); // con conferma → inserisce
        $this->assertSame(2, CourseSource::where('course_id', $course->id)->count());
    }

    // ---- solo da bozza approvata ----

    public function test_insert_solo_da_bozza_approvata(): void
    {
        ['draft' => $draft] = $this->scenario();
        $draft->update(['status' => 'draft']);
        $this->expectException(\RuntimeException::class);
        app(GapInserter::class)->insert($draft);
    }

    // ---- gating + place confirm via controller ----

    public function test_confirm_place_e_gating(): void
    {
        ['gap' => $gap, 'draft' => $draft, 'module' => $module] = $this->scenario();
        $draft->update(['placement_confirmed' => false, 'place_formatore_block_id' => null]);

        $this->actingAdmin()->put(route('admin.coverage.place.confirm', $gap), [
            'place_formatore_block_id' => 'p1-cap1-sec2',
            'place_student_module_id' => $module->id,
            'place_student_anchor' => 'Gli agenti usano strumenti tramite MCP.',
        ])->assertRedirect();
        $this->assertTrue($draft->refresh()->placement_confirmed);

        config(['services.p26.enabled' => false]);
        $this->actingAdmin()->post(route('admin.coverage.insert', $gap))->assertNotFound();
    }

    // ---- isolamento proposta posizione (errore LLM) ----

    public function test_propose_place_errore_llm_isolato(): void
    {
        ['gap' => $gap, 'draft' => $draft] = $this->scenario();
        $draft->update(['place_formatore_block_id' => null, 'placement_confirmed' => false]);
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'boom']], 400)]);

        $this->actingAdmin()->post(route('admin.coverage.place.propose', $gap))->assertRedirect()->assertSessionHas('error');
        $this->assertNull($draft->refresh()->place_formatore_block_id); // invariato
    }
}
