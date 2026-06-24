<?php

namespace App\Http\Controllers\Scuola;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// Serve il logo della scuola da storage PRIVATO (mai URL diretto). Accessibile
// a chi appartiene a quella scuola (segreteria/docenti/studenti) o al platform
// admin: il logo è branding mostrato nei loro layout.
class BrandingController extends Controller
{
    public function logo(Request $request, School $school): BinaryFileResponse
    {
        $studentId = session('student_id');
        $student = $studentId ? Student::find($studentId) : null;

        $belongs = $student && $student->school_id === $school->id;
        abort_unless($belongs || session('admin_logged_in'), 403);

        $path = $school->setting('logo_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }
}
