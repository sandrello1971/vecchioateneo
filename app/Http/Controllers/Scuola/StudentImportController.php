<?php

namespace App\Http\Controllers\Scuola;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use App\Models\ImportBatch;
use App\Services\Schola\StudentImportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Import studenti a gate: preview (dry-run) → commit (con conferma creazione
// classi) → risultato con credenziali generate (una tantum). Tutto scoped scuola.
class StudentImportController extends Controller
{
    use ResolvesSchoolAccess;

    public function __construct(private StudentImportService $service) {}

    private function credKey(ImportBatch $batch): string
    {
        return 'student_import_credentials_' . $batch->id;
    }

    public function create()
    {
        $this->currentSchool();
        return view('scuola.studenti.import', ['batch' => null]);
    }

    public function preview(Request $request)
    {
        $school = $this->currentSchool();
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:4096']);

        $content = file_get_contents($request->file('file')->getRealPath());
        $analysis = $this->service->analyze($content, $school);

        if ($analysis['header_error']) {
            return back()->with('error', $analysis['header_error']);
        }

        $batch = ImportBatch::create([
            'school_id' => $school->id, 'created_by' => session('student_id'),
            'type' => 'students', 'status' => 'previewed',
            'source_filename' => $request->file('file')->getClientOriginalName(),
            'summary' => $analysis['summary'], 'rows' => $analysis['rows'],
        ]);

        return view('scuola.studenti.import', ['batch' => $batch]);
    }

    public function commit(Request $request)
    {
        $school = $this->currentSchool();
        $data = $request->validate([
            'batch_id' => 'required|uuid',
            'duplicate_action' => 'required|in:skip,update',
            'create_missing_classes' => 'sometimes|boolean',
        ]);

        $batch = ImportBatch::findOrFail($data['batch_id']);
        $this->assertSameSchool($batch);
        abort_unless($batch->type === 'students' && $batch->status === 'previewed', 422, 'Batch non applicabile.');

        $result = $this->service->commit(
            $batch, $school,
            $request->boolean('create_missing_classes'),
            $data['duplicate_action'],
        );

        $generated = $result['generated'];
        unset($result['generated']);

        $batch->update(['status' => 'committed', 'summary' => array_merge($batch->summary ?? [], ['result' => $result])]);

        // Credenziali in chiaro SOLO in sessione del committente, una tantum.
        if ($generated) {
            session([$this->credKey($batch) => $generated]);
        }

        return redirect()->route('scuola.studenti.import.result', $batch)
            ->with('success', "Import completato: {$result['created']} creati, {$result['updated']} aggiornati, {$result['skipped']} saltati, {$result['classes_created']} classi create.");
    }

    public function result(ImportBatch $batch)
    {
        $this->assertSameSchool($batch);

        return view('scuola.studenti.import-result', [
            'batch' => $batch,
            'credentials' => session($this->credKey($batch), []),
        ]);
    }

    public function credentialsDownload(ImportBatch $batch): StreamedResponse
    {
        $this->assertSameSchool($batch);
        $credentials = session($this->credKey($batch), []);
        abort_if(empty($credentials), 404, 'Credenziali non più disponibili.');

        // Una tantum: consumate al download.
        session()->forget($this->credKey($batch));

        return response()->streamDownload(function () use ($credentials) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['nome', 'username', 'password_temporanea']);
            foreach ($credentials as $c) {
                fputcsv($out, [$c['name'], $c['username'], $c['password']]);
            }
            fclose($out);
        }, 'credenziali-studenti-' . $batch->id . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function discard(ImportBatch $batch)
    {
        $this->assertSameSchool($batch);
        abort_unless($batch->status === 'previewed', 422);

        $batch->update(['status' => 'discarded']);

        return redirect()->route('scuola.studenti.import.create')->with('success', 'Anteprima scartata.');
    }
}
