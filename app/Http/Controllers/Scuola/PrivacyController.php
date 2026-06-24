<?php

namespace App\Http\Controllers\Scuola;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use App\Jobs\ExportSchoolDataJob;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// GDPR scuola (P16): stato DPA, export dati, audit import. Tutto scoped sulla
// PROPRIA scuola.
class PrivacyController extends Controller
{
    use ResolvesSchoolAccess;

    public function index()
    {
        $school = $this->currentSchool();

        $batches = ImportBatch::forSchool($school->id)
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $exportReady = Storage::disk('local')->exists(ExportSchoolDataJob::path($school->id));

        return view('scuola.privacy', compact('school', 'batches', 'exportReady'));
    }

    /** Marca il DPA come firmato (o lo revoca). */
    public function markDpa()
    {
        $school = $this->currentSchool();
        $school->update(['dpa_signed_at' => $school->dpa_signed_at ? null : now()]);

        return redirect()->route('scuola.privacy.index')->with('success',
            $school->dpa_signed_at ? 'DPA marcato come firmato.' : 'DPA revocato.');
    }

    /** Avvia la generazione dell'export (job asincrono). */
    public function export()
    {
        $school = $this->currentSchool();
        ExportSchoolDataJob::dispatch($school->id)->afterResponse();

        return redirect()->route('scuola.privacy.index')->with('success',
            'Export in preparazione: tra poco sarà disponibile per il download.');
    }

    /** Scarica l'export (una volta): consumato al download. */
    public function download(): BinaryFileResponse
    {
        $school = $this->currentSchool();
        $path = ExportSchoolDataJob::path($school->id);
        abort_unless(Storage::disk('local')->exists($path), 404, 'Export non disponibile.');

        return response()->download(Storage::disk('local')->path($path),
            'export-' . $school->slug . '.json')->deleteFileAfterSend(true);
    }
}
