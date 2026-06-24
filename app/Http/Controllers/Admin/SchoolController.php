<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SchoolAdminInviteMail;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// Platform admin: CRUD scuole (l'unico attore che attraversa le scuole, §2) +
// nomina del primo school_admin (segreteria). L'area /scuola arriva in P12.
class SchoolController extends Controller
{
    public function index()
    {
        $schools = School::withCount(['schoolAdmins', 'teachers', 'students', 'schoolClasses'])
            ->orderBy('name')
            ->get();

        return view('admin.scuole.index', compact('schools'));
    }

    public function create()
    {
        return view('admin.scuole.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|alpha_dash|unique:schools,slug',
            'type' => 'required|in:liceo,istituto_tecnico,altro',
            'city' => 'nullable|string|max:255',
            'allow_professor_create_classes' => 'sometimes|boolean',
        ]);

        $school = School::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? Str::slug($data['name'])),
            'type' => $data['type'],
            'city' => $data['city'] ?? null,
            'allow_professor_create_classes' => (bool) ($data['allow_professor_create_classes'] ?? false),
            'status' => 'active',
        ]);

        return redirect()->route('admin.scuole.show', $school)
            ->with('success', 'Scuola creata. Ora nomina la segreteria.');
    }

    public function show(School $school)
    {
        $school->loadCount(['schoolAdmins', 'teachers', 'students', 'schoolClasses']);
        $admins = $school->schoolAdmins()->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active', 'must_change_password', 'last_login_at', 'school_id', 'role']);

        return view('admin.scuole.show', compact('school', 'admins'));
    }

    public function update(Request $request, School $school)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:liceo,istituto_tecnico,altro',
            'city' => 'nullable|string|max:255',
            'status' => 'required|in:active,suspended',
            'allow_professor_create_classes' => 'sometimes|boolean',
            'dpa_signed' => 'sometimes|boolean',
        ]);

        $school->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'city' => $data['city'] ?? null,
            'status' => $data['status'],
            'allow_professor_create_classes' => (bool) ($data['allow_professor_create_classes'] ?? false),
            'dpa_signed_at' => $request->boolean('dpa_signed')
                ? ($school->dpa_signed_at ?? now())
                : null,
        ]);

        return redirect()->route('admin.scuole.show', $school)
            ->with('success', 'Scuola aggiornata.');
    }

    /**
     * Aggiunge una segreteria. Segreteria = CAPACITÀ (flag is_secretary):
     * - email NUOVA → crea account (role null) + flag + password temporanea
     *   SEMPRE mostrata una volta (+ email opzionale);
     * - email ESISTENTE senza scuola o di QUESTA scuola → AGGANCIA il flag,
     *   preservando role/professore/iscrizioni e la password attuale;
     * - email di un'ALTRA scuola → bloccata.
     */
    public function nominateAdmin(Request $request, School $school)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'send_email' => 'sometimes|boolean',
        ]);

        $existing = Student::where('email', $data['email'])->first();

        if ($existing) {
            if ($existing->school_id && $existing->school_id !== $school->id) {
                return redirect()->route('admin.scuole.show', $school)
                    ->with('error', "Impossibile: {$data['email']} appartiene già a un'altra scuola.");
            }
            // Aggancio: NON tocca password/role/iscrizioni dell'account esistente.
            $existing->update(['school_id' => $school->id, 'is_secretary' => true, 'is_active' => true]);

            return redirect()->route('admin.scuole.show', $school)
                ->with('success', "Segreteria agganciata all'account esistente {$data['email']} (mantiene la sua password e gli altri ruoli).");
        }

        $tempPassword = $this->generateTempPassword();
        $admin = Student::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $tempPassword,           // cast 'hashed' su Student
            'role' => null,                        // segreteria = flag, non role
            'school_id' => $school->id,
            'is_secretary' => true,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        if ($request->boolean('send_email')) {
            Mail::to($admin->email)->queue(new SchoolAdminInviteMail($admin, $tempPassword, $school));
        }

        return redirect()->route('admin.scuole.show', $school)
            ->with('success', "Segreteria aggiunta: {$data['email']}")
            ->with('temp_password', $tempPassword)
            ->with('temp_password_for', $admin->email);
    }

    /** Reimposta la password (mostrata una volta; email opzionale). */
    public function resetAdminPassword(Request $request, School $school, Student $admin)
    {
        $this->authorizeAdmin($school, $admin);
        $temp = $this->issueTempPassword($admin, $request->boolean('send_email'), $school);

        return redirect()->route('admin.scuole.show', $school)
            ->with('success', "Password reimpostata per {$admin->email}. Annotala ora: non sarà più mostrata.")
            ->with('temp_password', $temp)
            ->with('temp_password_for', $admin->email);
    }

    /** Reinvia l'invito: nuova password temporanea, email inviata, mostrata una volta. */
    public function resendInvite(School $school, Student $admin)
    {
        $this->authorizeAdmin($school, $admin);
        $temp = $this->issueTempPassword($admin, true, $school);

        return redirect()->route('admin.scuole.show', $school)
            ->with('success', "Invito reinviato a {$admin->email} (email + password qui sotto, annotala).")
            ->with('temp_password', $temp)
            ->with('temp_password_for', $admin->email);
    }

    /** Attiva/disattiva l'account di segreteria. */
    public function toggleAdminActive(School $school, Student $admin)
    {
        $this->authorizeAdmin($school, $admin);
        $admin->update(['is_active' => !$admin->is_active]);

        return redirect()->route('admin.scuole.show', $school)
            ->with('success', $admin->is_active ? "Segreteria riattivata: {$admin->email}" : "Segreteria disattivata: {$admin->email}");
    }

    private function authorizeAdmin(School $school, Student $admin): void
    {
        abort_unless($admin->school_id === $school->id && $admin->is_secretary, 404);
    }

    private function generateTempPassword(): string
    {
        return atheneum_temp_password();
    }

    private function issueTempPassword(Student $admin, bool $sendEmail, School $school): string
    {
        $temp = $this->generateTempPassword();
        $admin->update(['password' => $temp, 'must_change_password' => true, 'is_active' => true]);

        if ($sendEmail) {
            Mail::to($admin->email)->queue(new SchoolAdminInviteMail($admin, $temp, $school));
        }

        return $temp;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'scuola';
        $candidate = $slug;
        $i = 1;
        while (School::where('slug', $candidate)->exists()) {
            $candidate = $slug . '-' . (++$i);
        }

        return $candidate;
    }
}
