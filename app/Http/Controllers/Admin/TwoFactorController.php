<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Gestione 2FA admin. Officina usa session-auth (admin_logged_in + admin_email),
 * NON Laravel guard, quindi qui recuperiamo l'admin dalla session manualmente.
 */
class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    private function currentAdmin(): Admin
    {
        $email = session('admin_email');
        if (!$email) {
            abort(403, 'Session admin non valida.');
        }
        return Admin::where('email', $email)->firstOrFail();
    }

    /**
     * Pagina di gestione 2FA: mostra stato attuale + setup wizard o disable.
     */
    public function show(Request $request)
    {
        $admin = $this->currentAdmin();
        return view('admin.security.two-factor', [
            'admin' => $admin,
            'enabled' => $admin->hasTwoFactorEnabled(),
        ]);
    }

    /**
     * Setup STEP 1: genera secret + mostra QR code (NON ancora confermato).
     */
    public function enable(Request $request)
    {
        $admin = $this->currentAdmin();

        if ($admin->hasTwoFactorEnabled()) {
            return redirect()->route('admin.security.2fa.show')
                ->with('error', '2FA gia attivo.');
        }

        $secret = $this->google2fa->generateSecretKey();
        $admin->two_factor_secret = $secret;
        $admin->two_factor_confirmed_at = null;
        $admin->save();

        $appName = atheneum_setting('instance_name', 'Atheneum');
        $qrUrl = $this->google2fa->getQRCodeUrl($appName, $admin->email, $secret);

        $qrSvg = Builder::create()
            ->writer(new SvgWriter())
            ->data($qrUrl)
            ->size(300)
            ->margin(10)
            ->build()
            ->getString();

        return view('admin.security.two-factor-setup', [
            'admin' => $admin,
            'secret' => $secret,
            'qrSvg' => $qrSvg,
        ]);
    }

    /**
     * Setup STEP 2: verifica codice TOTP → conferma + genera recovery codes.
     */
    public function confirm(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $admin = $this->currentAdmin();

        if (empty($admin->two_factor_secret)) {
            return redirect()->route('admin.security.2fa.show')
                ->with('error', 'Setup 2FA non avviato. Riprova.');
        }

        if (!$this->google2fa->verifyKey($admin->two_factor_secret, $data['code'])) {
            return back()->with('error', 'Codice non valido. Riprova.');
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $admin->two_factor_recovery_codes = $recoveryCodes;
        $admin->two_factor_confirmed_at = now();
        $admin->save();

        Log::warning('[admin] 2FA enabled', [
            'admin_id' => $admin->id,
            'email' => $admin->email,
            'ip' => $request->ip(),
        ]);

        return view('admin.security.two-factor-recovery-codes', [
            'admin' => $admin,
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    /**
     * Disabilita 2FA. Richiede codice TOTP corrente come anti-takeover.
     */
    public function disable(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $admin = $this->currentAdmin();

        if (!$admin->hasTwoFactorEnabled()) {
            return redirect()->route('admin.security.2fa.show')
                ->with('error', '2FA non e attivo.');
        }

        if (!$this->google2fa->verifyKey($admin->two_factor_secret, $data['code'])) {
            return back()->with('error', 'Codice non valido. Disattivazione bloccata.');
        }

        $admin->two_factor_secret = null;
        $admin->two_factor_recovery_codes = null;
        $admin->two_factor_confirmed_at = null;
        $admin->save();

        Log::warning('[admin] 2FA disabled', [
            'admin_id' => $admin->id,
            'email' => $admin->email,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('admin.security.2fa.show')
            ->with('success', '2FA disattivato.');
    }

    /**
     * Rigenera 8 nuovi recovery codes (invalida quelli precedenti).
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $admin = $this->currentAdmin();

        if (!$admin->hasTwoFactorEnabled()) {
            return back()->with('error', '2FA non attivo.');
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        $admin->two_factor_recovery_codes = $recoveryCodes;
        $admin->save();

        Log::warning('[admin] 2FA recovery codes regenerated', [
            'admin_id' => $admin->id,
        ]);

        return view('admin.security.two-factor-recovery-codes', [
            'admin' => $admin,
            'recoveryCodes' => $recoveryCodes,
            'regenerated' => true,
        ]);
    }

    /**
     * 8 codici formato XXXX-XXXX (8 char alfanumerici uppercase + dash).
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4));
        }
        return $codes;
    }
}
