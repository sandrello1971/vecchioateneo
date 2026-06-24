<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        if (session('admin_logged_in')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = strtolower(trim((string) $request->email));

        // 1) Sorgente primaria: tabella admins
        $admin = Admin::where('email', $email)->first();
        if ($admin && $admin->is_active && Hash::check($request->password, $admin->password)) {
            // Se 2FA attivo: intercetta prima del login session.
            // Salva l'admin ID nella session "pending" e redirect al challenge.
            if ($admin->hasTwoFactorEnabled()) {
                $request->session()->put('admin_2fa_pending_id', $admin->id);
                Log::info('[admin] login password OK, 2FA challenge required', [
                    'admin_id' => $admin->id,
                    'email' => $admin->email,
                ]);
                return redirect()->route('admin.2fa.challenge');
            }
            session(['admin_logged_in' => true, 'admin_email' => $admin->email]);
            Log::info('[admin] login via account DB', ['email' => $admin->email]);
            return redirect()->route('admin.dashboard');
        }

        // 2) BREAK-GLASS: credenziale .env legacy.
        // NON rimuovere: serve come canale di emergenza se il DB admins è
        // vuoto/corrotto o le credenziali sono perse.
        if ($email === strtolower((string) config('admin.email'))
            && config('admin.password_hash')
            && Hash::check($request->password, config('admin.password_hash'))) {
            session(['admin_logged_in' => true, 'admin_email' => $email]);
            Log::warning('[admin] login via credenziale .env break-glass', ['email' => $email]);
            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors(['email' => 'Credenziali non valide.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        session()->forget(['admin_logged_in', 'admin_email']);
        return redirect()->route('admin.login');
    }
}
