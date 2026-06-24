<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;

class CertificateVerifyController extends Controller
{
    public function show(string $code)
    {
        $cert = Certificate::with([
                'student:id,name,email',
                'course:id,name,certification_name',
            ])
            ->where('code', $code)
            ->first();

        return view('certificate.verify', [
            'cert' => $cert,
            'code' => $code,
        ]);
    }

    /**
     * Endpoint pubblico per scaricare il PDF firmato di un certificato
     * tramite il suo codice.
     *
     * Necessario per consentire a chi verifica un certificato (datore di
     * lavoro, ente di controllo) di scaricare la versione "ufficiale" del
     * PDF firmato direttamente da Officina, senza affidarsi al file
     * eventualmente fornito dal candidato (che potrebbe essere stato
     * manomesso).
     *
     * Restituisce 404 se il codice non esiste o se il certificato non è
     * ancora firmato (in tal caso la pagina verify mostra già le info,
     * e il signed PDF è il vero atto "ufficiale" verificabile).
     *
     * Rate limit: stesso del verify (throttle:certificate-verify).
     */
    public function downloadSigned(string $code)
    {
        $cert = Certificate::where('code', $code)->first();

        abort_unless($cert, 404, 'Certificato non trovato.');
        abort_unless($cert->isSigned(), 404, 'Questo certificato non è ancora firmato digitalmente.');

        $absolutePath = Storage::disk('local')->path($cert->signed_pdf_path);

        return response()->file($absolutePath, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
