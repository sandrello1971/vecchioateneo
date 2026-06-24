<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthAndSettingsTest extends TestCase
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

    // ============================================================
    // Login
    // ============================================================

    public function test_login_with_db_admin_active_succeeds(): void
    {
        $admin = $this->makeAdmin(['email' => 'mario@ente.it']);

        $this->post(route('admin.login.post'), [
            'email'    => 'mario@ente.it',
            'password' => 'AStrongPwd!2026',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(session('admin_logged_in'));
        $this->assertSame('mario@ente.it', session('admin_email'));
    }

    public function test_login_with_db_admin_disabled_is_rejected(): void
    {
        $this->makeAdmin(['email' => 'dis@ente.it', 'is_active' => false]);

        $this->post(route('admin.login.post'), [
            'email'    => 'dis@ente.it',
            'password' => 'AStrongPwd!2026',
        ])->assertSessionHasErrors('email');

        $this->assertNull(session('admin_logged_in'));
    }

    public function test_login_with_env_break_glass_still_works(): void
    {
        Config::set('admin.email', 'breakglass@ente.it');
        Config::set('admin.password_hash', Hash::make('EnvSecret!2026'));

        $this->post(route('admin.login.post'), [
            'email'    => 'breakglass@ente.it',
            'password' => 'EnvSecret!2026',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(session('admin_logged_in'));
        $this->assertSame('breakglass@ente.it', session('admin_email'));
    }

    public function test_login_with_wrong_password_rejected_for_both_sources(): void
    {
        Config::set('admin.email', 'env@ente.it');
        Config::set('admin.password_hash', Hash::make('correct-env-pw-12'));
        $this->makeAdmin(['email' => 'db@ente.it']);

        $this->post(route('admin.login.post'), [
            'email' => 'db@ente.it', 'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->post(route('admin.login.post'), [
            'email' => 'env@ente.it', 'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->assertNull(session('admin_logged_in'));
    }

    public function test_email_is_case_insensitive(): void
    {
        $this->makeAdmin(['email' => 'mixed@ente.it']);

        $this->post(route('admin.login.post'), [
            'email'    => 'MIXED@ENTE.IT',
            'password' => 'AStrongPwd!2026',
        ])->assertRedirect(route('admin.dashboard'));
    }

    // ============================================================
    // Bootstrap command
    // ============================================================

    public function test_artisan_admin_create_works_with_zero_admins(): void
    {
        $this->assertSame(0, Admin::count());

        Artisan::call('atheneum:admin-create', [
            '--email'    => 'boot@ente.it',
            '--name'     => 'Boot Admin',
            '--password' => 'BootStrong!2026',
        ]);

        $this->assertSame(1, Admin::count());

        $this->post(route('admin.login.post'), [
            'email' => 'boot@ente.it', 'password' => 'BootStrong!2026',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_artisan_admin_create_rejects_duplicate_email(): void
    {
        $this->makeAdmin(['email' => 'dupe@ente.it']);

        $exit = Artisan::call('atheneum:admin-create', [
            '--email'    => 'dupe@ente.it',
            '--name'     => 'Dupe',
            '--password' => 'WhatEver!12345',
        ]);

        $this->assertNotSame(0, $exit);
        $this->assertSame(1, Admin::where('email', 'dupe@ente.it')->count());
    }

    // ============================================================
    // Anti-lockout
    // ============================================================

    public function test_only_active_admin_cannot_disable_self(): void
    {
        $only = $this->makeAdmin(['email' => 'only@ente.it']);

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'only@ente.it'])
            ->patch(route('admin.admins.toggle', $only->id))
            ->assertSessionHas('error');

        $this->assertTrue((bool) $only->fresh()->is_active);
    }

    public function test_self_can_disable_when_another_active_admin_exists(): void
    {
        $a = $this->makeAdmin(['email' => 'a@ente.it']);
        $this->makeAdmin(['email' => 'b@ente.it']);

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@ente.it'])
            ->patch(route('admin.admins.toggle', $a->id))
            ->assertRedirect();

        $this->assertFalse((bool) $a->fresh()->is_active);
    }

    public function test_disabled_admin_login_then_reactivated_login(): void
    {
        $a = $this->makeAdmin(['email' => 'tog@ente.it', 'is_active' => false]);

        $this->post(route('admin.login.post'), [
            'email' => 'tog@ente.it', 'password' => 'AStrongPwd!2026',
        ])->assertSessionHasErrors('email');

        $a->update(['is_active' => true]);

        $this->post(route('admin.login.post'), [
            'email' => 'tog@ente.it', 'password' => 'AStrongPwd!2026',
        ])->assertRedirect(route('admin.dashboard'));
    }

    // ============================================================
    // Settings store
    // ============================================================

    public function test_setting_resolve_returns_default_for_missing_key(): void
    {
        $this->assertSame('zzz', Setting::resolve('nope', 'zzz'));
    }

    public function test_setting_put_then_resolve_returns_new_value(): void
    {
        Setting::put('foo', 'bar');
        $this->assertSame('bar', Setting::resolve('foo'));

        // Update + cache bust
        Setting::put('foo', 'baz');
        $this->assertSame('baz', Setting::resolve('foo'));
    }

    public function test_session_email_key_is_preserved_after_db_login(): void
    {
        $this->makeAdmin(['email' => 'sess@ente.it']);

        $this->post(route('admin.login.post'), [
            'email' => 'sess@ente.it', 'password' => 'AStrongPwd!2026',
        ]);

        $this->assertSame('sess@ente.it', session('admin_email'),
            'session(admin_email) must be set after login — read by audit logs throughout the app');
    }
}
