<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\IngestMaterialSharedJob;
use App\Models\Student;
use App\Models\TeachingDocument;
use App\Services\Schola\ArtifactRagIngestor;
use Illuminate\Http\Request;

// Toggle condivisione di un MATERIALE (TeachingDocument) con altri docenti, con
// AMBITO: 'subject' (stessa materia + stessa scuola) | 'all' (tutti) | 'none' (privato).
// Ricalca ArtifactSharingController: blocco copyright su photos/pdf + ack diritti alla
// prima condivisione (students.library_rights_ack_at).
class MaterialSharingController extends Controller
{
    public function update(Request $request, TeachingDocument $document)
    {
        abort_unless($document->teacher_id === session('student_id'), 403);

        $data = $request->validate([
            'scope' => 'required|in:none,subject,all',
        ]);
        $scope = $data['scope'];

        // Rimozione dalla condivisione: i chunk RAG condivisi vengono rimossi;
        // le copie già importate dai colleghi NON vengono toccate (righe indipendenti).
        if ($scope === 'none') {
            $document->update(['share_scope' => null, 'shared_school_id' => null]);
            app(ArtifactRagIngestor::class)->purgeTeacherShared($document->id);

            return back()->with('success', 'Materiale reso privato. Le copie già importate dai colleghi restano loro.');
        }

        // Serve il testo estratto (transcript) per l'indicizzazione condivisa.
        if ($document->status !== 'ready') {
            return back()->with('error', 'Il materiale non è ancora pronto: attendi la fine dell’estrazione prima di condividerlo.');
        }

        $teacher = Student::findOrFail(session('student_id'));

        // Ambito materia: serve una materia assegnata e una scuola (il perimetro).
        if ($scope === 'subject' && (!$document->subject_id || !$teacher->school_id)) {
            return back()->with('error', 'Per condividere con la stessa materia servono una materia assegnata al materiale e una scuola.');
        }

        // BLOCCO HARD copyright: distribuzione di testo integrale da foto/PDF.
        if (in_array($document->source_type, ['photos', 'pdf'], true)) {
            return back()->with('error', 'Trascrizione integrale di materiale potenzialmente protetto: non condivisibile con altri docenti.');
        }

        // Prima condivisione in assoluto: presa di responsabilità sui diritti.
        if (!$teacher->library_rights_ack_at) {
            if (!$request->boolean('rights_ack')) {
                return back()
                    ->with('error', 'Per condividere conferma di avere i diritti sul contenuto.')
                    ->with('needs_ack', true);
            }
            $teacher->update(['library_rights_ack_at' => now()]);
        }

        $document->update([
            'share_scope' => $scope,
            'shared_school_id' => $scope === 'subject' ? $teacher->school_id : null,
        ]);

        IngestMaterialSharedJob::dispatch($document->id)->afterResponse();

        $label = $scope === 'all' ? 'tutti i docenti' : 'i docenti della stessa materia';

        return back()->with('success', "Materiale condiviso con {$label}.");
    }
}
