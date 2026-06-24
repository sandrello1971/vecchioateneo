<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Firmatari dei certificati configurabili da UI: il privilegio è ora
 * per-account (admins.can_sign_certificates) e gestibile da più
 * amministratori, non più legato a una singola env.
 */
class CertificateSignerPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $attrs = []): Admin
    {
        return Admin::create(array_merge([
            'name'      => 'Admin Test',
            'email'     => 'a' . uniqid() . '@ente.it',
            'password'  => 'AStrongPwd!2026',
            'is_active' => true,
        ], $attrs));
    }

    private function loginAs(Admin $admin): self
    {
        $this->withSession([
            'admin_logged_in' => true,
            'admin_email'     => $admin->email,
        ]);

        return $this;
    }

    // ============================================================
    // Middleware EnsureLegalRepresentative (flag DB)
    // ============================================================

    public function test_admin_senza_flag_non_puo_accedere_alla_firma(): void
    {
        $admin = $this->makeAdmin(['can_sign_certificates' => false]);

        $this->loginAs($admin)
            ->get(route('admin.certificates.signatures.index'))
            ->assertForbidden(); // 403
    }

    public function test_admin_con_flag_puo_accedere_alla_firma(): void
    {
        $admin = $this->makeAdmin(['can_sign_certificates' => true]);

        $this->loginAs($admin)
            ->get(route('admin.certificates.signatures.index'))
            ->assertOk();
    }

    // ============================================================
    // Toggle da UI + anti-lockout
    // ============================================================

    public function test_toggle_abilita_e_poi_disabilita_la_firma(): void
    {
        $manager = $this->makeAdmin(['can_sign_certificates' => true]); // firmatario sempre presente
        $target  = $this->makeAdmin(['can_sign_certificates' => false]);

        $this->loginAs($manager)
            ->patch(route('admin.admins.signature', $target))
            ->assertRedirect();
        $this->assertTrue($target->fresh()->can_sign_certificates);

        $this->loginAs($manager)
            ->patch(route('admin.admins.signature', $target))
            ->assertRedirect();
        $this->assertFalse($target->fresh()->can_sign_certificates);
    }

    public function test_anti_lockout_non_si_puo_rimuovere_lultimo_firmatario(): void
    {
        $onlySigner = $this->makeAdmin(['can_sign_certificates' => true]);

        $this->loginAs($onlySigner)
            ->patch(route('admin.admins.signature', $onlySigner))
            ->assertSessionHas('error');

        $this->assertTrue($onlySigner->fresh()->can_sign_certificates,
            'L\'ultimo firmatario non deve poter essere rimosso (anti-lockout).');
    }
}
