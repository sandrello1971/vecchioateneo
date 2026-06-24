<?php

namespace Tests\Feature\Freshness;

use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CourseSource;
use App\Models\FreshnessClaim;
use App\Models\Module;
use App\Models\UpdateProposal;
use App\Services\Freshness\FreshnessAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P25.B-a.2 — run UNICA che analizza formatore + studente, con proposte INDIPENDENTI (D2)
 * governate da toggle separati. B-a.2 NON applica nulla (solo claim + proposte pending).
 */
class StudentSourceRunTest extends TestCase
{
    use RefreshDatabase;

    private function setupCourse(bool $studentEnabled): Course
    {
        $course = Course::create(['name' => 'INTERFERENZA', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        // Formatore: sorgente strutturato.
        CourseSource::create(['course_id' => $course->id, 'version' => '2.0', 'blocks' => [
            ['id' => 'b1', 'type' => 'P', 'text' => 'Il dato formatore del 2025 è questo.'],
        ]]);
        // Studente: modulo HTML.
        Module::create(['course_id' => $course->id, 'title' => 'M1', 'content' => '<p>Il dato studente è 1,8 miliardi nel 2025.</p>', 'sort_order' => 0]);
        CourseFreshnessConfig::create([
            'course_id' => $course->id, 'web_search_enabled' => true, 'primary_sources' => [],
            'audience' => 'adult', 'proposals_enabled' => true, 'student_proposals_enabled' => $studentEnabled,
        ]);
        return $course;
    }

    /** Distingue Fase1 formatore ("Blocchi del corso") da Fase1 studente ("Moduli del corso"). */
    private function fakeAgent(): void
    {
        Http::fake(function ($request) {
            $system = $request['system'] ?? '';
            $user = $request['messages'][0]['content'] ?? '';

            if (str_contains($system, 'analista di obsolescenza')) {
                $claims = str_contains($user, 'Moduli del corso')
                    ? ['claims' => [['module_id' => $this->moduleId, 'quote' => 'Il dato studente è 1,8 miliardi nel 2025', 'category' => 'prezzo']]]
                    : ['claims' => [['block_id' => 'b1', 'quote' => 'Il dato formatore del 2025', 'category' => 'data']]];
                return Http::response(['content' => [['type' => 'text', 'text' => json_encode($claims, JSON_UNESCAPED_UNICODE)]]], 200);
            }
            if (str_contains($system, 'editor didattico')) {
                return Http::response(['content' => [['type' => 'text', 'text' => json_encode(['after' => 'aggiornato al 2026', 'reason' => 'x'])]]], 200);
            }
            // Fase 2: tutto obsoleto.
            return Http::response(['content' => [['type' => 'text', 'text' => json_encode(['verdict' => 'obsoleto', 'source_url' => 'https://x', 'source_type' => 'web', 'source_date' => '2026-01-01', 'confidence' => 0.8])]]], 200);
        });
    }

    private string $moduleId = '';

    public function test_run_unica_genera_proposte_formatore_e_studente_indipendenti(): void
    {
        $course = $this->setupCourse(studentEnabled: true);
        $this->moduleId = $course->modules()->first()->id;
        $this->fakeAgent();

        $run = app(FreshnessAgent::class)->run($course);

        // 2 claim (1 instructor + 1 student), 2 proposte (taggate per sorgente).
        $this->assertSame(2, $run->claims_found);
        $this->assertSame(2, $run->proposals_created);

        $instr = UpdateProposal::where('course_id', $course->id)->where('content_source', 'instructor')->first();
        $stud = UpdateProposal::where('course_id', $course->id)->where('content_source', 'student')->first();

        $this->assertNotNull($instr);
        $this->assertSame('b1', $instr->block_id);
        $this->assertNull($instr->module_id);

        $this->assertNotNull($stud);
        $this->assertSame($this->moduleId, $stud->module_id);
        $this->assertNull($stud->block_id);
        $this->assertSame('pending', $stud->status); // B-a.2 non applica nulla
        $this->assertStringContainsString('1,8 miliardi', $stud->before); // verbatim dal modulo

        // Claim taggati per sorgente.
        $this->assertSame(1, FreshnessClaim::where('content_source', 'student')->count());
        $this->assertSame(1, FreshnessClaim::where('content_source', 'instructor')->count());
    }

    public function test_student_disabilitato_nessuna_proposta_studente(): void
    {
        $course = $this->setupCourse(studentEnabled: false);
        $this->moduleId = $course->modules()->first()->id;
        $this->fakeAgent();

        $run = app(FreshnessAgent::class)->run($course);

        // Solo formatore analizzato: nessun claim/proposta studente.
        $this->assertSame(1, $run->claims_found);
        $this->assertSame(1, $run->proposals_created);
        $this->assertSame(0, FreshnessClaim::where('content_source', 'student')->count());
        $this->assertSame(0, UpdateProposal::where('content_source', 'student')->count());
        // Il materiale studente NON è stato toccato (B-a.2 non applica).
        $this->assertSame('<p>Il dato studente è 1,8 miliardi nel 2025.</p>', $course->modules()->first()->content);
    }
}
