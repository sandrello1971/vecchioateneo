<?php

namespace Tests\Feature\Freshness;

use App\Jobs\RewriteStudentProposalJob;
use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CourseSource;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\Module;
use App\Models\UpdateProposal;
use App\Services\Freshness\CoordinatedMatchService;
use App\Services\Freshness\ProposalApplicator;
use App\Services\Freshness\StudentRewriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P25.B-b.3 — riscrittura conservativa, conferma/scarto candidate, orfananza (D1) nei due
 * casi (pending→rejected+orphaned; applied→applied+orphaned), applicazione coordinata
 * (riusa applyStudent, changelog con parent), gate minori.
 */
class CoordinatedRewriteTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Course
    {
        return Course::create(['name' => 'INTERFERENZA', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function parent(Course $course, string $status = 'approved'): UpdateProposal
    {
        return UpdateProposal::create(['course_id' => $course->id, 'content_source' => 'instructor', 'block_id' => 'b1',
            'before' => 'Il mercato vale 1,8 miliardi di euro nel 2025', 'after' => 'Il mercato vale 2,3 miliardi di euro nel 2026',
            'audience' => 'adult', 'status' => $status, 'reviewed_at' => now()]);
    }

    private function child(Course $course, UpdateProposal $parent, array $attrs = []): UpdateProposal
    {
        return UpdateProposal::create(array_merge([
            'course_id' => $course->id, 'content_source' => 'student', 'origin' => 'coordinated',
            'parent_proposal_id' => $parent->id, 'module_id' => null,
            'before' => 'Il dato è 1,8 miliardi nel 2025', 'after' => null,
            'audience' => 'adult', 'status' => 'matched', 'match_confidence' => 0.95, 'match_trust' => 'high',
        ], $attrs));
    }

    private function fakeRewrite(string $after): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['after' => $after, 'reason' => 'dato aggiornato'])]],
        ], 200)]);
    }

    // ---- Riscrittura conservativa ----

    public function test_riscrittura_conservativa_cambia_solo_il_dato(): void
    {
        $this->fakeRewrite('Il dato è 2,3 miliardi nel 2026');

        $r = app(StudentRewriter::class)->rewrite('Il dato è 1,8 miliardi nel 2025', 'Il mercato vale 1,8 miliardi nel 2025', 'Il mercato vale 2,3 miliardi nel 2026');

        $this->assertStringContainsString('2,3 miliardi nel 2026', $r['after']);
        $this->assertStringContainsString('Il dato è', $r['after']); // struttura/linguaggio preservati
        $this->assertFalse($r['divergent']); // cambia il minimo
    }

    public function test_riscrittura_stravolgente_flaggata_divergente(): void
    {
        $this->fakeRewrite('Frase completamente differente senza alcuna relazione col testo originale studente.');

        $r = app(StudentRewriter::class)->rewrite('Il dato è 1,8 miliardi nel 2025', 'x', 'y');

        $this->assertTrue($r['divergent']);
    }

    // ---- Conferma → job → matched diventa pending ----

    public function test_job_riscrive_e_porta_matched_a_pending(): void
    {
        $course = $this->course();
        $parent = $this->parent($course);
        $cand = $this->child($course, $parent);
        $this->fakeRewrite('Il dato è 2,3 miliardi nel 2026');

        (new RewriteStudentProposalJob($cand->id))->handle(app(StudentRewriter::class));

        $cand->refresh();
        $this->assertSame('pending', $cand->status);
        $this->assertStringContainsString('2,3 miliardi nel 2026', $cand->after);
        $this->assertSame('Il dato è 1,8 miliardi nel 2025', $cand->before); // before verbatim invariato (ancorabile)
    }

    public function test_confirm_dispatcha_job_e_scarta_imposta_rejected(): void
    {
        Queue::fake();
        $course = $this->course();
        $cand = $this->child($course, $this->parent($course));

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@ente.it'])
            ->patch(route('admin.freshness.proposals.confirm', $cand))->assertRedirect();
        Queue::assertPushed(RewriteStudentProposalJob::class, fn ($j) => $j->proposalId === $cand->id);

        $cand2 = $this->child($course, $this->parent($course));
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@ente.it'])
            ->patch(route('admin.freshness.proposals.reject', $cand2))->assertRedirect();
        $this->assertSame('rejected', $cand2->refresh()->status);
    }

    // ---- Orfananza (D1) ----

    public function test_orfananza_due_casi(): void
    {
        $course = $this->course();
        $parent = $this->parent($course);
        $childPending = $this->child($course, $parent, ['status' => 'pending', 'after' => 'x']);
        $childApplied = $this->child($course, $parent, ['status' => 'applied', 'after' => 'y']);

        $res = app(CoordinatedMatchService::class)->orphanChildrenOf($parent, 'Padre formatore rollbackato');

        $this->assertSame(1, $res['discarded']);
        $this->assertSame(1, $res['flagged']);

        // pending → rejected + orphaned
        $childPending->refresh();
        $this->assertSame('rejected', $childPending->status);
        $this->assertNotNull($childPending->orphaned_at);
        $this->assertStringContainsString('rollbackato', $childPending->orphan_reason);

        // applied → RESTA applied (live) + orphaned (segnalata, mai scartata)
        $childApplied->refresh();
        $this->assertSame('applied', $childApplied->status);
        $this->assertNotNull($childApplied->orphaned_at);
    }

    public function test_reject_padre_formatore_orfana_le_figlie(): void
    {
        $course = $this->course();
        $parent = $this->parent($course, status: 'pending');
        $child = $this->child($course, $parent, ['status' => 'pending', 'after' => 'x']);

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@ente.it'])
            ->patch(route('admin.freshness.proposals.reject', $parent))->assertRedirect();

        $child->refresh();
        $this->assertSame('rejected', $child->status);
        $this->assertNotNull($child->orphaned_at);
    }

    // ---- Applicazione coordinata: riusa applyStudent + changelog con parent ----

    public function test_applicazione_coordinata_riusa_applyStudent_e_traccia_parent(): void
    {
        $course = $this->course();
        $m = Module::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0, 'content' => '<p>Il dato è 1,8 miliardi nel 2025.</p>']);
        $parent = $this->parent($course);
        $cand = $this->child($course, $parent, ['status' => 'approved', 'module_id' => $m->id,
            'before' => 'Il dato è 1,8 miliardi nel 2025', 'after' => 'Il dato è 2,3 miliardi nel 2026', 'reviewed_at' => now()]);

        $res = app(ProposalApplicator::class)->applyStudent($course);

        $this->assertSame(1, $res['applied']);
        $this->assertStringContainsString('2,3 miliardi nel 2026', $m->refresh()->content); // in-place
        $this->assertSame('applied', $cand->refresh()->status);
        // Changelog studente con tracciabilità del padre formatore.
        $this->assertDatabaseHas('course_changelog', [
            'course_id' => $course->id, 'content_source' => 'student',
            'proposal_id' => $cand->id, 'parent_proposal_id' => $parent->id,
        ]);
    }

    public function test_rollback_formatore_orfana_figlia_applied(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $fact = 'Il mercato vale 1,8 miliardi di euro nel 2025';
        CourseSource::create(['course_id' => $course->id, 'version' => '2.0', 'blocks' => [['id' => 'b1', 'type' => 'P', 'text' => $fact]]]);
        $mat = Material::create(['course_id' => $course->id, 'title' => 'Man', 'is_instructor_only' => true]);
        InstructorManualSection::create(['material_id' => $mat->id, 'course_id' => $course->id, 'title' => 'S',
            'anchor' => 'a-' . uniqid(), 'heading_level' => 2, 'sort_order' => 0, 'content_html' => "<p>{$fact}.</p>"]);
        $parent = $this->parent($course); // approved instructor, block_id b1
        // Figlia già applicata (live)
        $child = $this->child($course, $parent, ['status' => 'applied', 'after' => 'z']);

        // Applica formatore (→ v2.1, changelog con proposal_id=parent)
        app(ProposalApplicator::class)->apply($course);

        // Rollback formatore via controller → orfana le figlie dei padri della versione annullata
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@ente.it'])
            ->post(route('admin.freshness.proposals.rollback', $course), ['content_source' => 'instructor'])
            ->assertRedirect();

        $child->refresh();
        $this->assertSame('applied', $child->status); // resta live
        $this->assertNotNull($child->orphaned_at);     // ma segnalata
        $this->assertStringContainsString('rollbackato', $child->orphan_reason);
    }

    public function test_gate_minori_su_proposta_coordinata(): void
    {
        $course = $this->course();
        CourseFreshnessConfig::create(['course_id' => $course->id, 'web_search_enabled' => true, 'primary_sources' => [], 'audience' => 'minor']);
        $m = Module::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0, 'content' => '<p>Il dato è 1,8 miliardi nel 2025.</p>']);
        $this->child($course, $this->parent($course), ['status' => 'approved', 'module_id' => $m->id, 'audience' => 'minor',
            'before' => 'Il dato è 1,8 miliardi nel 2025', 'after' => 'Il dato è 2,3 miliardi nel 2026', 'reviewed_at' => now()]);

        $blocked = app(ProposalApplicator::class)->applyStudent($course); // senza conferma
        $this->assertSame('minor_confirmation_required', $blocked['blocked']);
        $this->assertStringContainsString('1,8 miliardi nel 2025', $m->refresh()->content); // intatto
    }
}
