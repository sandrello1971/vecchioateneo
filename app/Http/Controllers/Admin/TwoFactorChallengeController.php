<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FA\Google2FA;

/**
 * 2FA challenge step: dopo password OK, prima del session admin_logged_in.
 * Officina usa session-based auth (no Laravel guard).
 *
 * Pre-condizione: session('admin_2fa_pending_id') deve essere settato da
 * AdminAuthController (login email+password OK ma 2FA attivo).
 */
class TwoFactorChallengeController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function show(Request $request)
    {
        if (!$request->session()->get('admin_2fa_pending_id')) {
            return redirect()->route('admin.login');
        }
        return view('admin.auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|min:6|max:10',
            'recovery' => 'nullable|boolean',
        ]);

        $pendingId = $request->session()->get('admin_2fa_pending_id');
        if (!$pendingId) {
            return redirect()->route('admin.login')
                ->with('error', 'Sessione scaduta. Riprova il login.');
        }

        $admin = Admin::find($pendingId);
        if (!$admin || !$admin->hasTwoFactorEnabled()) {
            return redirect()->route('admin.login')
                ->with('error', 'Admin non trovato o 2FA non attivo.');
        }

        $useRecovery = !empty($data['recovery']);
        $verified = false;

        if ($useRecovery) {
            // Recovery code: confronta + rimuovi dalla lista (monouso)
            $codes = $admin->two_factor_recovery_codes;
            $codeUpper = strtoupper(trim($data['code']));
            if (in_array($codeUpper, $codes, true)) {
                $verified = true;
                $codes = array_values(array_diff($codes, [$codeUpper]));
                $admin->two_factor_recovery_codes = $codes;
                $admin->save();

                Log::warning('[admin] 2FA recovery code used', [
                    'admin_id' => $admin->id,
                    'email' => $admin->email,
                    'ip' => $request->ip(),
                    'remaining_codes' => count($codes),
                ]);
            }
        } else {
            $verified = $this->google2fa->verifyKey($admin->two_factor_secret, $data['code']);
        }

        if (!$verified) {
            Log::warning('[admin] 2FA verification failed', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'ip' => $request->ip(),
                'used_recovery' => $useRecovery,
            ]);
            return back()->with('error', 'Codice non valido.');
        }

        // 2FA OK → login completo (pattern session-based Officina)
        $request->session()->forget('admin_2fa_pending_id');
        $request->session()->put([
            'admin_logged_in' => true,
            'admin_email' => $admin->email,
        ]);

        Log::info('[admin] 2FA login successful', [
            'admin_id' => $admin->id,
            'email' => $admin->email,
            'ip' => $request->ip(),
            'used_recovery' => $useRecovery,
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }
}
