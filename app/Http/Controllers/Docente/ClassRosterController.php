<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class ClassRosterController extends Controller
{
    public function update(Request $request, SchoolClass $class, ClassStudent $enrollment)
    {
        // Confine §3 (P15): il roster delle classi di SCUOLA è gestito SOLO
        // dalla segreteria; il docente le vede in sola lettura. Sulle classi
        // LIBERE resta la gestione del proprietario (flusso codici invito).
        abort_unless($class->school_id === null, 403, 'Il roster delle classi di scuola è gestito dalla segreteria.');
        abort_unless($class->teacher_id === session('student_id'), 403);
        abort_unless($enrollment->school_class_id === $class->id, 404);

        $data = $request->validate([
            'action' => 'required|in:approve,remove',
        ]);

        if ($data['action'] === 'approve') {
            $enrollment->update(['status' => 'active', 'approved_at' => now()]);
            $msg = 'Studente approvato.';
        } else {
            $enrollment->update(['status' => 'removed']);
            $msg = 'Studente rimosso dalla classe.';
        }

        return redirect()->route('docente.classes.show', $class)->with('success', $msg);
    }
}
