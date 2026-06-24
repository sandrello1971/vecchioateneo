<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Services\CertificatePdfBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class CertificateSignatureController extends Controller
{
    public function __construct(
        private CertificatePdfBuilder $pdfBuilder,
    ) {
    }

    /**
     * Pagina principale dell'admin UI firma certificati.
     * Mostra cert in attesa + cert firmati di recente + form batch.
     */
    public function index()
    {
        $pending = Certificate::whereNotNull('unsigned_pdf_path')
            ->whereNull('signed_pdf_path')
            ->with(['student', 'course'])
            ->orderBy('issued_at', 'asc')
            ->get();

        $recentlySigned = Certificate::whereNotNull('signed_pdf_path')
            ->with(['student', 'course'])
            ->orderByDesc('signed_at')
            ->limit(10)
            ->get();

        return view('admin.certificates.signatures', [
            'pending' => $pending,
            'recentlySigned' => $recentlySigned,
        ]);
    }

    /**
     * Scarica il PDF non firmato di un certificato specifico.
     *
     * Usato dal legale rappresentante per ottenere il file da firmare
     * nel proprio software firma remota qualificata (Aruba Firma,
     * Namirial Sign, InfoCert GoSign, ecc.).
     *
     * Se il PDF unsigned non esiste sul disco (caso edge: observer
     * fallito alla creazione del cert oppure cert legacy pre-refactor),
     * lo rigenera al volo via CertificatePdfBuilder e lo salva sul disco
     * prima di servirlo, così alla prossima volta sarà già lì.
     */
    public function download(Certificate $certificate)
    {
        if (!$certificate->unsigned_pdf_path
            || !Storage::disk('local')->exists($certificate->unsigned_pdf_path)) {

            try {
                $path = $this->pdfBuilder->saveUnsigned($certificate);
                $certificate->unsigned_pdf_path = $path;
                $certificate->saveQuietly();
            } catch (\Throwable $e) {
                Log::error('Generazione unsigned PDF fallita per download admin', [
                    'certificate_id' => $certificate->id,
                    'message' => $e->getMessage(),
                ]);
                abort(500, 'Impossibile generare il PDF del certificato.');
            }
        }

        $absolutePath = Storage::disk('local')->path($certificate->unsigned_pdf_path);

        // Naming consistente con il batch (Step 2.3) per facilitare
        // l'identificazione quando il legale rappresentante firma.
        $filename = "certificato-{$certificate->code}-da-firmare.pdf";

        return response()->download($absolutePath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Riceve il PDF firmato per uno specifico certificato e lo salva
     * in storage/app/certificates/signed/.
     *
     * Validazione:
     *  - File presente e di tipo PDF (validation rule + magic header check)
     *  - Size massimo 10 MB (un certificato firmato non dovrebbe superare 1-2 MB)
     *  - Best-effort sanity: il PDF caricato non deve essere bit-per-bit
     *    identico all'unsigned (sarebbe segno di upload sbagliato/non firmato)
     *
     * Se il cert era già firmato, sovrascrive il signed precedente.
     * Utile per scenari di ri-firma (es. firma scaduta, errore di firma).
     */
    public function upload(Request $request, Certificate $certificate)
    {
        $request->validate([
            'signed_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'signed_pdf.required' => 'Devi selezionare un file PDF.',
            'signed_pdf.file' => 'Il file caricato non è valido.',
            'signed_pdf.mimes' => 'Il file deve essere un PDF.',
            'signed_pdf.max' => 'Il file non può superare 10 MB.',
        ]);

        $uploadedFile = $request->file('signed_pdf');

        // Magic header check: alcuni client mandano file con extension .pdf
        // ma contenuto diverso. Più paranoico del mimes:pdf di Laravel.
        $head = file_get_contents($uploadedFile->getRealPath(), false, null, 0, 4);
        if ($head !== '%PDF') {
            return back()
                ->withErrors(['signed_pdf' => 'Il file non sembra essere un PDF valido.'])
                ->withInput();
        }

        // Sanity check: se il legale rappresentante ha caricato per errore lo
        // STESSO file unsigned (senza firmarlo), l'hash sarebbe identico al
        // PDF già su disco. Avvisalo invece di accettare silenziosamente.
        if ($certificate->unsigned_pdf_path
            && Storage::disk('local')->exists($certificate->unsigned_pdf_path)) {

            $unsignedHash = hash_file('sha256', Storage::disk('local')->path($certificate->unsigned_pdf_path));
            $uploadedHash = hash_file('sha256', $uploadedFile->getRealPath());

            if ($unsignedHash === $uploadedHash) {
                return back()
                    ->withErrors([
                        'signed_pdf' => 'Il file caricato è identico al PDF non firmato. ' .
                                        'Probabilmente non l\'hai firmato. Apri il PDF nel tuo ' .
                                        'software di firma qualificata, applica la firma, e ricaricalo.',
                    ])
                    ->withInput();
            }
        }

        $signedPath = $this->pdfBuilder->signedPathFor($certificate);
        Storage::disk('local')->put($signedPath, file_get_contents($uploadedFile->getRealPath()));

        $certificate->signed_pdf_path = $signedPath;
        $certificate->signed_at = now();
        $certificate->signed_by = session('admin_email') ?? config('atheneum.legal_representative_email');
        $certificate->saveQuietly();

        Log::info('Certificato firmato caricato', [
            'certificate_id' => $certificate->id,
            'certificate_code' => $certificate->code,
            'signed_by' => $certificate->signed_by,
            'file_size_kb' => round($uploadedFile->getSize() / 1024, 1),
        ]);

        return back()->with('success', "Firma caricata correttamente per il certificato {$certificate->code}.");
    }

    /**
     * Genera al volo uno ZIP con tutti i PDF unsigned dei certificati
     * in attesa di firma. Usato dal legale rappresentante per scaricare
     * il "lotto" da firmare in massa con il proprio software firma
     * qualificata (Aruba Firma Massiva, Namirial Firma Massiva, ecc.).
     *
     * Naming convention dei file dentro lo ZIP: {cert_code}.pdf
     * Questo naming viene mantenuto dal software firma e usato da
     * uploadBatch() per risalire ai certificati al momento del re-upload.
     *
     * Hard cap: max 100 cert per batch (evita ZIP enormi).
     * Se ci sono >100 pending, vengono inclusi i 100 più vecchi e l'admin
     * dovrà fare più batch successivi.
     */
    public function downloadBatch()
    {
        $maxBatchSize = 100;

        $pending = Certificate::whereNotNull('unsigned_pdf_path')
            ->whereNull('signed_pdf_path')
            ->orderBy('issued_at', 'asc')
            ->limit($maxBatchSize)
            ->get();

        if ($pending->isEmpty()) {
            return back()->with('info', 'Nessun certificato in attesa di firma.');
        }

        $tmpZipPath = tempnam(sys_get_temp_dir(), 'cert-batch-') . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Log::error('Impossibile creare ZIP per batch download', ['tmp_path' => $tmpZipPath]);
            abort(500, 'Errore nella creazione del ZIP.');
        }

        $included = 0;
        $skipped = 0;
        foreach ($pending as $cert) {
            // Self-heal: se l'unsigned PDF manca su disco, rigeneralo
            if (!Storage::disk('local')->exists($cert->unsigned_pdf_path)) {
                try {
                    $this->pdfBuilder->saveUnsigned($cert);
                } catch (\Throwable $e) {
                    Log::warning('Skip cert in batch (rigenerazione fallita)', [
                        'certificate_id' => $cert->id,
                        'message' => $e->getMessage(),
                    ]);
                    $skipped++;
                    continue;
                }
            }

            $absolutePath = Storage::disk('local')->path($cert->unsigned_pdf_path);
            $zip->addFile($absolutePath, "{$cert->code}.pdf");
            $included++;
        }

        $zip->close();

        Log::info('Batch ZIP certificati pending generato', [
            'included' => $included,
            'skipped' => $skipped,
            'total_pending' => $pending->count(),
        ]);

        $filename = 'certificati-da-firmare-' . now()->format('Y-m-d-His') . '.zip';

        return response()->download($tmpZipPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Riceve uno ZIP contenente N certificati firmati e li processa
     * in batch.
     *
     * Per ogni file PDF dentro lo ZIP:
     *  1. Estrae il cert_code dal filename (formato atteso: {code}.pdf)
     *  2. Cerca il Certificate corrispondente in DB
     *  3. Valida (PDF magic header, non identico al unsigned)
     *  4. Salva in storage/app/certificates/signed/
     *  5. Aggiorna campi signed_*
     *
     * Tollerante: file orfani, PDF invalidi o cert non trovati vengono
     * skippati con log + entry nel report. Niente abort globale.
     */
    public function uploadBatch(Request $request)
    {
        $request->validate([
            'signed_zip' => ['required', 'file', 'mimetypes:application/zip,application/x-zip-compressed', 'max:102400'],
        ], [
            'signed_zip.required' => 'Devi selezionare un file ZIP.',
            'signed_zip.file' => 'Il file caricato non è valido.',
            'signed_zip.mimetypes' => 'Il file deve essere un archivio ZIP.',
            'signed_zip.max' => 'Il file non può superare 100 MB.',
        ]);

        $uploadedZip = $request->file('signed_zip');
        $tmpZipPath = $uploadedZip->getRealPath();

        $zip = new ZipArchive();
        if ($zip->open($tmpZipPath) !== true) {
            return back()
                ->withErrors(['signed_zip' => 'Impossibile aprire il file ZIP. Verifica che non sia corrotto.'])
                ->withInput();
        }

        $report = [
            'success' => [],
            'orphan' => [],
            'invalid_pdf' => [],
            'unsigned_match' => [],
            'errors' => [],
        ];

        $signedBy = session('admin_email') ?? config('atheneum.legal_representative_email');

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $internalName = $stat['name'];

            if (str_ends_with($internalName, '/') || !str_ends_with(strtolower($internalName), '.pdf')) {
                continue;
            }

            // Gestisce eventuali sub-directory: usa basename
            $basename = basename($internalName);
            $certCode = preg_replace('/\.pdf$/i', '', $basename);

            $cert = Certificate::where('code', $certCode)->first();
            if (!$cert) {
                $report['orphan'][] = $internalName;
                Log::info('Batch upload: file orfano', ['filename' => $internalName, 'extracted_code' => $certCode]);
                continue;
            }

            $pdfContent = $zip->getFromIndex($i);
            if ($pdfContent === false) {
                $report['errors'][$certCode] = 'Impossibile estrarre il file dal ZIP';
                continue;
            }

            if (substr($pdfContent, 0, 4) !== '%PDF') {
                $report['invalid_pdf'][] = $certCode;
                Log::warning('Batch upload: PDF magic header invalido', ['code' => $certCode]);
                continue;
            }

            // Sanity check: il PDF caricato non deve essere identico all'unsigned
            if ($cert->unsigned_pdf_path
                && Storage::disk('local')->exists($cert->unsigned_pdf_path)) {

                $unsignedHash = hash_file('sha256', Storage::disk('local')->path($cert->unsigned_pdf_path));
                $uploadedHash = hash('sha256', $pdfContent);

                if ($unsignedHash === $uploadedHash) {
                    $report['unsigned_match'][] = $certCode;
                    Log::warning('Batch upload: PDF identico al unsigned (probabilmente non firmato)', [
                        'code' => $certCode,
                    ]);
                    continue;
                }
            }

            try {
                $signedPath = $this->pdfBuilder->signedPathFor($cert);
                Storage::disk('local')->put($signedPath, $pdfContent);

                $cert->signed_pdf_path = $signedPath;
                $cert->signed_at = now();
                $cert->signed_by = $signedBy;
                $cert->saveQuietly();

                $report['success'][] = $certCode;
            } catch (\Throwable $e) {
                $report['errors'][$certCode] = $e->getMessage();
                Log::error('Batch upload: salvataggio fallito', [
                    'code' => $certCode,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $zip->close();

        Log::info('Batch upload certificati firmati', [
            'success_count' => count($report['success']),
            'orphan_count' => count($report['orphan']),
            'invalid_pdf_count' => count($report['invalid_pdf']),
            'unsigned_match_count' => count($report['unsigned_match']),
            'error_count' => count($report['errors']),
            'signed_by' => $signedBy,
        ]);

        $messages = [];
        if (count($report['success']) > 0) {
            $messages[] = count($report['success']) . " certificati firmati con successo";
        }
        if (count($report['orphan']) > 0) {
            $messages[] = count($report['orphan']) . " file ignorati (codice cert non trovato)";
        }
        if (count($report['invalid_pdf']) > 0) {
            $messages[] = count($report['invalid_pdf']) . " file con PDF non valido";
        }
        if (count($report['unsigned_match']) > 0) {
            $messages[] = count($report['unsigned_match']) . " file probabilmente non firmati (identici al unsigned)";
        }
        if (count($report['errors']) > 0) {
            $messages[] = count($report['errors']) . " errori durante il salvataggio";
        }

        $summary = empty($messages) ? 'Nessun file processato.' : implode(', ', $messages) . '.';

        // Report dettagliato in session per la vista (Step 2.4)
        session()->flash('batch_report', $report);

        return back()->with('success', $summary);
    }
}
