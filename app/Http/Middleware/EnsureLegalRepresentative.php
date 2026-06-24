<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegalRepresentative
{
    /**
     * Verifica che l'admin attualmente loggato sia abilitato alla firma dei
     * certificati. L'abilitazione è ora un privilegio per-account
     * (admins.can_sign_certificates), gestibile da più amministratori dalla
     * UI /admin/admins; non più una singola email da env.
     *
     * Da applicare DOPO il middleware admin.auth, perché presume
     * che session('admin_email') sia già popolata.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $adminEmail = session('admin_email');

        $admin = $adminEmail
            ? Admin::where('email', strtolower($adminEmail))->first()
            : null;

        if (!$admin || !$admin->can_sign_certificates) {
            abort(403, 'Solo gli amministratori abilitati possono firmare i certificati.');
        }

        return $next($request);
    }
}
