<?php

namespace Tests\Feature\Freshness;

use App\Jobs\FindStudentMatchesJob;
use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\FreshnessClaim;
use App\Models\FreshnessRun;
use App\Models\Module;
use App\Models\UpdateProposal;
use App\Services\Freshness\CoordinatedMatchService;
use App\Services\Freshness\StudentMatchFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P25.B-b.2 — matching formatore→discente all'approvazione. Riproduce i casi del probe:
 * dato numerico → match (anche multiplo); norma → NESSUNA; prodotto → match_trust=low.
 * Nessuna riscrittura (after null), nessuna applicazione.
 */
class CoordinatedMatchTest extends TestCase
{
    use RefreshDatabase;

    private function course(bool $studentEnabled = true): Course
    {
        $course = Course::create(['name' => 'INTERFERENZA', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        CourseFreshnessConfig::create(['course_id' => $course->id, 'web_search_enabled' => true, 'primary_sources' => [],
            'audience' => 'adult', 'student_proposals_enabled' => $studentEnabled]);
        return $course;
    }

    private function fakeMatch(array $matches): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['matches' => $matches, 'none' => empty($matches)], JSON_UNESCAPED_UNICODE)]],
        ], 200)]);
    }

    // ---- Finder (servizio puro) ----

    public function test_finder_trova_match_verbatim_su_piu_moduli(): void
    {
        $course = $this->course();
        $m1 = Module::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0, 'content' => '<p>Il dato è 1,8 miliardi nel 2025, in crescita.</p>']);
        $m2 = Module::create(['course_id' => $course->id, 'title' => 'M2', 'sort_order' => 1, 'content' => '<p>Ribadiamo: 1,8 miliardi nel 2025 di mercato.</p>']);

        $this->fakeMatch([
            ['module_id' => $m1->id, 'sentence' => '1,8 miliardi nel 2025', 'confidence' => 0.95],
            ['module_id' => $m2->id, 'sentence' => '1,8 miliardi nel 2025', 'confidence' => 0.93],
        ]);

        $res = app(StudentMatchFinder::class)->find('Il mercato vale 1,8 miliardi di euro nel 2025', $course->modules()->get());

        $this->assertFalse($res['none']);
        $this->assertCount(2, $res['candidates']);
        $this->assertSame('1,8 miliardi nel 2025', $res['candidates'][0]['before']); // verbatim
    }

    public function test_finder_nessuna(): void
    {
        $course = $this->course();
        Module::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0, 'content' => '<p>Testo che non parla del fatto.</p>']);

        $this->fakeMatch([]); // NESSUNA

        $res = app(StudentMatchFinder::class)->find('disposizioni transitorie AI Act Art. 111', $course->modules()->get());

        $this->assertTrue($res['none']);
        $this->assertCount(0, $res['candidates']);
    }

    public function test_finder_scarta_frase_non_verbatim(): void
    {
        $course = $this->course();
        $m = Module::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0, 'content' => '<p>Il dato reale del modulo.</p>']);

        // L'LLM "inventa" una frase non presente verbatim → scartata.
        $this->fakeMatch([['module_id' => $m->id, 'sentence' => 'frase inventata non nel modulo', 'confidence' => 0.9]]);

        $res = app(StudentMatchFinder::class)->find('un fatto', $course->modules()->get());

        $this->assertCount(0, $res['candidates']);
        $this->assertCount(1, $res['rejected']);
    }

    // ---- Servizio di coordinamento (persistenza candidate 'matched') ----

    private function claim(Course $course, string $category): FreshnessClaim
    {
        $run = FreshnessRun::create(['course_id' => $course->id, 'status' => 'completed', 'started_at' => now()]);
        return FreshnessClaim::create(['run_id' => $run->id, 'course_id' => $course->id, 'content_source' => 'instructor',
            'block_id' => 'b1', 'claim_text' => 'fatto', 'category' => $category]);
    }

    private function approvedInstructor(Course $course, string $category): UpdateProposal
    {
        $claim = $this->claim($course, $category);
        return UpdateProposal::create(['course_id' => $course->id, 'content_source' => 'instructor', 'block_id' => 'b1',
            'freshness_claim_id' => $claim->id, 'before' => 'Il mercato vale 1,8 miliardi nel 2025', 'after' => 'Il mercato vale 2,3 miliardi nel 2026',
            'audience' => 'adult', 'status' => 'approved', 'reviewed_at' => now(), 'source' => 'https://istat.it', 'source_type' => 'web']);
    }

    public function test_crea_candidate_matched_prezzo_high_trust(): void
    {
        $course = $this->course();
        $m = Module::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0, 'content' => '<p>Il dato è 1,8 miliardi nel 2025.</p>']);
        $parent = $this->approvedInstructor($course, 'prezzo');
        $this->fakeMatch([['module_id' => $m->id, 'sentence' => '1,8 miliardi nel 2025', 'confidence' => 0.95]]);

        $created = app(CoordinatedMatchService::class)->matchForApprovedProposal($parent);

        $this->assertSame(1, $created);
        $cand = UpdateProposal::coordinated()->first();
        $this->assertSame('student', $cand->content_source);
        $this->assertSame('coordinated', $cand->origin);
        $this->assertSame($parent->id, $cand->parent_proposal_id);
        $this->assertSame($m->id, $cand->module_id);
        $this->assertSame('matched', $cand->status);
        $this->assertNull($cand->after); // niente riscrittura ancora
        $this->assertStringContainsString('1,8 miliardi', $cand->before);
        $this->assertSame(0.95, $cand->match_confidence);
        $this->assertSame('high', $cand->match_trust);
    }

    public function test_fatto_prodotto_marcato_low_trust(): void
    {
        $course = $this->course();
        $m = Module::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0, 'content' => '<p>Usiamo Claude per l\'analisi.</p>']);
        $parent = $this->approvedInstructor($course, 'prodotto');
        $this->fakeMatch([['module_id' => $m->id, 'sentence' => 'Usiamo Claude per l\'analisi', 'confidence' => 0.85]]);

        app(CoordinatedMatchService::class)->matchForApprovedProposal($parent);

        $this->assertSame('low', UpdateProposal::coordinated()->first()->match_trust);
    }

    public function test_student_disabilitato_nessuna_candidate(): void
    {
        $course = $this->course(studentEnabled: false);
        Module::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0, 'content' => '<p>Il dato è 1,8 miliardi nel 2025.</p>']);
        $parent = $this->approvedInstructor($course, 'prezzo');

        $created = app(CoordinatedMatchService::class)->matchForApprovedProposal($parent);

        $this->assertSame(0, $created);
        $this->assertSame(0, UpdateProposal::coordinated()->count());
    }

    public function test_idempotente_non_duplica(): void
    {
        $course = $this->course();
        $m = Module::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0, 'content' => '<p>Il dato è 1,8 miliardi nel 2025.</p>']);
        $parent = $this->approvedInstructor($course, 'prezzo');
        $this->fakeMatch([['module_id' => $m->id, 'sentence' => '1,8 miliardi nel 2025', 'confidence' => 0.95]]);

        app(CoordinatedMatchService::class)->matchForApprovedProposal($parent);
        app(CoordinatedMatchService::class)->matchForApprovedProposal($parent); // seconda volta

        $this->assertSame(1, UpdateProposal::coordinated()->count());
    }

    // ---- Trigger all'approvazione (controller → job async) ----

    public function test_approvazione_formatore_dispatcha_il_job(): void
    {
        Queue::fake();
        $course = $this->course();
        $claim = $this->claim($course, 'prezzo');
        $p = UpdateProposal::create(['course_id' => $course->id, 'content_source' => 'instructor', 'block_id' => 'b1',
            'freshness_claim_id' => $claim->id, 'before' => 'x', 'after' => 'y', 'audience' => 'adult', 'status' => 'pending']);

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@ente.it'])
            ->patch(route('admin.freshness.proposals.approve', $p))->assertRedirect();

        Queue::assertPushed(FindStudentMatchesJob::class, fn ($j) => $j->proposalId === $p->id);
    }

    public function test_approvazione_studente_non_dispatcha(): void
    {
        Queue::fake();
        $course = $this->course();
        $p = UpdateProposal::create(['course_id' => $course->id, 'content_source' => 'student', 'module_id' => null,
            'before' => 'x', 'after' => 'y', 'audience' => 'adult', 'status' => 'pending']);

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@ente.it'])
            ->patch(route('admin.freshness.proposals.approve', $p))->assertRedirect();

        Queue::assertNotPushed(FindStudentMatchesJob::class);
    }
}
