<?php

namespace App\Http\Controllers\Scuola;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use App\Models\ImportBatch;
use App\Services\Schola\TeacherImportService;
use Illuminate\Http\Request;

// Import docenti a gate: preview (dry-run, NON scrive) → commit su conferma →
// discard. Tutto scoped sulla scuola della segreteria.
class TeacherImportController extends Controller
{
    use ResolvesSchoolAccess;

    public function __construct(private TeacherImportService $service) {}

    public function create()
    {
        $this->currentSchool(); // gate tenancy
        return view('scuola.docenti.import', ['batch' => null]);
    }

    public function preview(Request $request)
    {
        $school = $this->currentSchool();
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $content = file_get_contents($request->file('file')->getRealPath());
        $analysis = $this->service->analyze($content, $school);

        if ($analysis['header_error']) {
            return back()->with('error', $analysis['header_error']);
        }

        $batch = ImportBatch::create([
            'school_id' => $school->id,
            'created_by' => session('student_id'),
            'type' => 'professors',
            'status' => 'previewed',
            'source_filename' => $request->file('file')->getClientOriginalName(),
            'summary' => $analysis['summary'],
            'rows' => $analysis['rows'],
        ]);

        return view('scuola.docenti.import', ['batch' => $batch]);
    }

    public function commit(Request $request)
    {
        $school = $this->currentSchool();
        $data = $request->validate([
            'batch_id' => 'required|uuid',
            'duplicate_action' => 'required|in:skip,update',
        ]);

        $batch = ImportBatch::findOrFail($data['batch_id']);
        $this->assertSameSchool($batch);
        abort_unless($batch->type === 'professors' && $batch->status === 'previewed', 422, 'Batch non applicabile.');

        $result = $this->service->commit($batch, $school, $data['duplicate_action']);

        $batch->update([
            'status' => 'committed',
            'summary' => array_merge($batch->summary ?? [], ['result' => $result, 'duplicate_action' => $data['duplicate_action']]),
        ]);

        return redirect()->route('scuola.docenti.index')->with('success',
            "Import completato: {$result['created']} creati, {$result['updated']} aggiornati, {$result['skipped']} saltati.");
    }

    public function discard(ImportBatch $batch)
    {
        $this->assertSameSchool($batch);
        abort_unless($batch->status === 'previewed', 422);

        $batch->update(['status' => 'discarded']);

        return redirect()->route('scuola.docenti.import.create')->with('success', 'Anteprima scartata.');
    }
}
