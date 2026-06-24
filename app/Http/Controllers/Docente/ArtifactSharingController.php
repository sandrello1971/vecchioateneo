<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TeachingArtifact;
use Illuminate\Http\Request;

// Toggle condivisione di un artefatto in Biblioteca docenti (§6).
// Blocco copyright sui transcript da photos/pdf; checkbox responsabilità alla
// PRIMA condivisione del docente (persistita su students.library_rights_ack_at).
class ArtifactSharingController extends Controller
{
    public function update(Request $request, TeachingArtifact $artifact)
    {
        abort_unless($artifact->teacher_id === session('student_id'), 403);

        $share = $request->boolean('shared');

        // Rimozione dalla biblioteca: i fork già creati dai colleghi NON vengono
        // toccati (sono righe indipendenti).
        if (!$share) {
            $artifact->update(['shared_with_teachers' => false]);
            return back()->with('success', 'Artefatto rimosso dalla biblioteca. Le copie già duplicate dai colleghi restano loro.');
        }

        // BLOCCO HARD copyright: trascrizione integrale di materiale protetto.
        if (self::isCopyrightBlocked($artifact)) {
            return back()->with('error', 'Trascrizione integrale di materiale potenzialmente protetto: resta nel perimetro delle tue classi.');
        }

        // Prima condivisione in assoluto: presa di responsabilità sui diritti.
        $teacher = Student::findOrFail(session('student_id'));
        if (!$teacher->library_rights_ack_at) {
            if (!$request->boolean('rights_ack')) {
                return back()
                    ->with('error', 'Per condividere conferma di avere i diritti sul contenuto.')
                    ->with('needs_ack', true);
            }
            $teacher->update(['library_rights_ack_at' => now()]);
        }

        $artifact->update(['shared_with_teachers' => true]);

        return back()->with('success', 'Artefatto condiviso in Biblioteca docenti.');
    }

    /** Transcript da foto/PDF: non condivisibile (distribuzione di testo protetto). */
    public static function isCopyrightBlocked(TeachingArtifact $artifact): bool
    {
        if ($artifact->type !== 'transcript') {
            return false;
        }
        $doc = $artifact->teachingDocument;

        return $doc && in_array($doc->source_type, ['photos', 'pdf'], true);
    }
}
