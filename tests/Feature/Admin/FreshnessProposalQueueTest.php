<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseSource;
use App\Models\Module;
use App\Models\UpdateProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * P25.3b — Coda HITL. Verifica: mostra solo pending col diff e la fonte; approve/reject
 * cambiano SOLO lo status (mai il contenuto del corso — l'applicazione è 3c); audit
 * (reviewed_by/at); "Modifica" = after editato + flag; selezione massiva.
 */
class FreshnessProposalQueueTest extends TestCase
{
    use RefreshDatabase;

    private string $adminEmail = 'rev@ente.it';

    private function admin(): Admin
    {
        return Admin::create([
            'name' => 'Revisore', 'email' => $this->adminEmail,
            'password' => 'secret-pw', 'is_active' => true,
        ]);
    }

    private function actingAdmin()
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => $this->adminEmail]);
    }

    private function course(): Course
    {
        return Course::create(['name' => 'INTERFERENZA', 'slug' => 'consilium-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function proposal(Course $course, array $attrs = []): UpdateProposal
    {
        return UpdateProposal::create(array_merge([
            'course_id' => $course->id,
            'block_id' => 'p1-cap3-sec1-p2',
            'sentence_ref' => 0,
            'before' => 'Il mercato AI italiano vale 1,8 miliardi di euro nel 2025',
            'after' => 'Il mercato AI italiano vale 2,3 miliardi di euro nel 2026',
            'reason' => 'Dato aggiornato al 2026',
            'source' => 'https://www.istat.it/',
            'source_type' => 'web',
            'confidence' => 0.86,
            'audience' => 'adult',
            'status' => 'pending',
        ], $attrs));
    }

    public function test_coda_mostra_solo_pending_con_diff_e_fonte(): void
    {
        $this->admin();
        $course = $this->course();
        $pending = $this->proposal($course);
        $approved = $this->proposal($course, ['status' => 'approved', 'before' => 'TESTO-APPROVATO-NON-VISIBILE']);

        $res = $this->actingAdmin()->get(route('admin.freshness.proposals.index'));

        $res->assertOk();
        // Diff (before/after) + fonte mostrati per la proposta pending → superficie di approvazione = diff visibile
        $res->assertSee('1,8 miliardi di euro nel 2025');
        $res->assertSee('2,3 miliardi di euro nel 2026');
        $res->assertSee('https://www.istat.it/');
        $res->assertSee('adulti'); // badge audience
        // La proposta NON pending non compare
        $res->assertDontSee('TESTO-APPROVATO-NON-VISIBILE');
    }

    public function test_approva_setta_status_audit_e_non_tocca_il_corso(): void
    {
        $admin = $this->admin();
        $course = $this->course();
        // Contenuti che NON devono cambiare (HITL: nessuna applicazione in 3b)
        $source = CourseSource::create(['course_id' => $course->id, 'version' => '2.0', 'blocks' => [
            ['id' => 'p1-cap3-sec1-p2', 'type' => 'P', 'text' => 'Il mercato AI italiano vale 1,8 miliardi di euro nel 2025'],
        ]]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'M1', 'content' => '<p>Il mercato vale 1,8 miliardi nel 2025</p>', 'sort_order' => 0]);
        $p = $this->proposal($course);

        $this->actingAdmin()->patch(route('admin.freshness.proposals.approve', $p))->assertRedirect();

        $p->refresh();
        $this->assertSame('approved', $p->status);
        $this->assertSame($admin->id, $p->reviewed_by);
        $this->assertNotNull($p->reviewed_at);
        $this->assertFalse($p->after_edited_by_human);

        // Il CONTENUTO del corso è intatto (sorgente + modulo live).
        $this->assertSame('Il mercato AI italiano vale 1,8 miliardi di euro nel 2025', $source->refresh()->blocks[0]['text']);
        $this->assertSame('<p>Il mercato vale 1,8 miliardi nel 2025</p>', $module->refresh()->content);
    }

    public function test_modifica_after_setta_flag(): void
    {
        $this->admin();
        $course = $this->course();
        $p = $this->proposal($course);

        $this->actingAdmin()->patch(route('admin.freshness.proposals.approve', $p), [
            'after' => 'Il mercato AI italiano vale 2,5 miliardi di euro nel 2026 (rettificato a mano)',
        ])->assertRedirect();

        $p->refresh();
        $this->assertSame('approved', $p->status);
        $this->assertTrue($p->after_edited_by_human);
        $this->assertStringContainsString('rettificato a mano', $p->after);
    }

    public function test_approva_senza_modifica_non_setta_flag(): void
    {
        $this->admin();
        $course = $this->course();
        $p = $this->proposal($course);

        $this->actingAdmin()->patch(route('admin.freshness.proposals.approve', $p), [
            'after' => $p->after, // invariato
        ])->assertRedirect();

        $this->assertFalse($p->refresh()->after_edited_by_human);
    }

    public function test_rifiuta_setta_rejected(): void
    {
        $admin = $this->admin();
        $course = $this->course();
        $p = $this->proposal($course);

        $this->actingAdmin()->patch(route('admin.freshness.proposals.reject', $p))->assertRedirect();

        $p->refresh();
        $this->assertSame('rejected', $p->status);
        $this->assertSame($admin->id, $p->reviewed_by);
        $this->assertNotNull($p->reviewed_at);
    }

    public function test_bulk_approva_solo_le_selezionate(): void
    {
        $this->admin();
        $course = $this->course();
        $a = $this->proposal($course);
        $b = $this->proposal($course);
        $c = $this->proposal($course);

        $this->actingAdmin()->post(route('admin.freshness.proposals.bulk'), [
            'action' => 'approve',
            'ids' => [$a->id, $b->id],
        ])->assertRedirect();

        $this->assertSame('approved', $a->refresh()->status);
        $this->assertSame('approved', $b->refresh()->status);
        $this->assertSame('pending', $c->refresh()->status); // non selezionata
    }

    public function test_non_si_approva_una_proposta_gia_decisa(): void
    {
        $this->admin();
        $course = $this->course();
        $p = $this->proposal($course, ['status' => 'rejected']);

        $this->actingAdmin()->patch(route('admin.freshness.proposals.approve', $p))->assertStatus(422);
        $this->assertSame('rejected', $p->refresh()->status);
    }

    public function test_hitl_approve_reject_non_applicano_al_contenuto(): void
    {
        // approve/reject cambiano SOLO lo status (provato altrove che non toccano il corso).
        $this->assertTrue(Route::has('admin.freshness.proposals.approve'));
        $this->assertTrue(Route::has('admin.freshness.proposals.reject'));
        // L'applicazione esiste (P25.3c/3e) ma è un passo SEPARATO, gated, che consuma solo
        // 'approved' (mai 'pending') — vedi ProposalApplicatorTest e MinorGateTest.
        $this->assertTrue(Route::has('admin.freshness.proposals.apply'));
    }
}
