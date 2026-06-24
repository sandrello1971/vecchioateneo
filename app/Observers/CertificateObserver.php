<?php

namespace App\Observers;

use App\Models\Certificate;
use App\Services\CertificatePdfBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CertificateObserver
{
    public function __construct(
        private CertificatePdfBuilder $pdfBuilder,
    ) {
    }

    /**
     * Hook: dopo la creazione di un Certificate, genera e salva su disco
     * il PDF non firmato. Best-effort.
     *
     * Se la generazione fallisce (disco pieno, errore DomPDF, view mancante,
     * qualsiasi cosa), logga ma NON solleva: il Certificate è valido anche
     * senza PDF persistito. Il fallback nel controller rigenererà
     * on-the-fly come faceva prima del refactor.
     */
    public function created(Certificate $cert): void
    {
        try {
            $path = $this->pdfBuilder->saveUnsigned($cert);

            // saveQuietly evita di triggerare di nuovo gli observer
            // (saved/updated event), prevenendo loop.
            $cert->unsigned_pdf_path = $path;
            $cert->saveQuietly();
        } catch (\Throwable $e) {
            Log::error('Generazione PDF certificato fallita', [
                'certificate_id' => $cert->id,
                'certificate_code' => $cert->code,
                'message' => $e->getMessage(),
            ]);
            // NON sollevare: la creazione del Certificate prosegue normalmente.
        }
    }

    /**
     * Hook: alla cancellazione del Certificate, rimuove anche i file PDF
     * dal disco per non lasciare file orfani.
     *
     * Idempotente: se il file non esiste su disco (già rimosso o mai creato)
     * non solleva errori.
     */
    public function deleted(Certificate $cert): void
    {
        foreach ([$cert->unsigned_pdf_path, $cert->signed_pdf_path] as $path) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
