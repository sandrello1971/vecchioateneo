<?php

namespace Tests\Feature\Schola;

use App\Models\ClassAnnouncement;
use App\Models\ClassConversation;
use App\Models\ClassMessage;
use App\Models\ClassStudent;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Notifications\ClassConversationCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

// Fase 3 (P22) — messaggistica didattica di classe: thread studente↔docente,
// annunci broadcast, isolamento cross-classe, segreteria esclusa, notifiche.
class ClassMessagingTest extends TestCase
{
    use RefreshDatabase;

    private Subject $storia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storia = Subject::firstOrCreate(['name' => 'Storia']);
    }

    private function prof(?School $school = null): Student
    {
        return Student::create(['name' => 'Prof', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'school_id' => $school?->id, 'is_active' => true, 'must_change_password' => false]);
    }

    private function student(?School $school = null): Student
    {
        return Student::create(['name' => 'Stu', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'student', 'school_id' => $school?->id, 'is_active' => true, 'must_change_password' => false]);
    }

    private function freeClass(Student $teacher): SchoolClass
    {
        return SchoolClass::create(['school_id' => null, 'teacher_id' => $teacher->id, 'name' => '3A',
            'subject_id' => $this->storia->id, 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => true,
            'requires_approval' => false, 'is_archived' => false]);
    }

    private function enroll(SchoolClass $class, Student $s, string $status = 'active'): void
    {
        ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $s->id,
            'status' => $status, 'approved_at' => $status === 'active' ? now() : null]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function asUser(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    // ===== Thread studente↔docente =====

    public function test_student_starts_thread_teacher_replies(): void
    {
        Notification::fake();
        $prof = $this->prof();
        $class = $this->freeClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);

        // Lo studente apre il thread col docente della sua classe.
        $this->asUser($student)->post(route('student.classi.messaggi.store', $class), [
            'teacher_id' => $prof->id, 'subject' => 'Dubbio sulla lezione', 'body' => 'Non ho capito il 1789.',
        ])->assertRedirect();

        $conv = ClassConversation::where('school_class_id', $class->id)->first();
        $this->assertNotNull($conv);
        $this->assertSame($student->id, $conv->student_id);
        $this->assertSame($prof->id, $conv->teacher_id);
        $this->assertSame(1, $conv->messages()->count());

        // Notifica al docente alla creazione (mirror corsi).
        Notification::assertSentTo($prof, ClassConversationCreatedNotification::class);

        // Il docente risponde.
        $this->asProf($prof)->post(route('docente.classi.messaggi.reply', [$class, $conv]), ['body' => 'Te lo spiego.'])
            ->assertRedirect();
        $this->assertSame(2, $conv->fresh()->messages()->count());
    }

    public function test_single_thread_per_pair_reused(): void
    {
        Notification::fake();
        $prof = $this->prof();
        $class = $this->freeClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);

        $this->asUser($student)->post(route('student.classi.messaggi.store', $class), [
            'teacher_id' => $prof->id, 'subject' => 'Oggetto A', 'body' => 'primo',
        ]);
        $this->asUser($student)->post(route('student.classi.messaggi.store', $class), [
            'teacher_id' => $prof->id, 'subject' => 'Oggetto B', 'body' => 'secondo',
        ]);

        // Un solo thread per coppia (studente, docente, classe); 2 messaggi.
        $this->assertSame(1, ClassConversation::where('school_class_id', $class->id)->count());
        $this->assertSame(2, ClassMessage::count());
        // La seconda apertura NON rigenera una notifica (solo alla creazione).
        Notification::assertSentToTimes($prof, ClassConversationCreatedNotification::class, 1);
    }

    public function test_show_marks_read_for_recipient(): void
    {
        Notification::fake();
        $prof = $this->prof();
        $class = $this->freeClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);

        $this->asUser($student)->post(route('student.classi.messaggi.store', $class), [
            'teacher_id' => $prof->id, 'subject' => 'Oggetto A', 'body' => 'ciao',
        ]);
        $conv = ClassConversation::first();

        $this->assertSame(1, $conv->unreadCountFor($prof));
        $this->asProf($prof)->get(route('docente.classi.messaggi.show', [$class, $conv]))->assertOk();
        $this->assertSame(0, $conv->fresh()->unreadCountFor($prof));
    }

    // ===== Annuncio broadcast =====

    public function test_teacher_broadcasts_announcement_students_read_only(): void
    {
        $prof = $this->prof();
        $class = $this->freeClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);

        $this->asProf($prof)->post(route('docente.classi.annunci.store', $class), [
            'subject' => 'Verifica giovedì', 'body' => 'Portate il libro.',
        ])->assertRedirect();

        $ann = ClassAnnouncement::where('school_class_id', $class->id)->first();
        $this->assertNotNull($ann);

        // Lo studente vede l'annuncio; l'apertura segna la lettura.
        $this->asUser($student)->get(route('student.classi.annunci.show', [$class, $ann]))
            ->assertOk()->assertSee('Portate il libro');
        $this->assertTrue($ann->isReadBy($student));
        $this->assertSame(1, $ann->readsCount());

        // Niente rotta studente per creare annunci (sola lettura).
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('student.classi.annunci.store'));
    }

    // ===== Isolamento cross-classe =====

    public function test_student_cannot_message_teacher_of_other_class(): void
    {
        $profA = $this->prof();
        $profB = $this->prof();
        $classA = $this->freeClass($profA);
        $classB = $this->freeClass($profB);
        $student = $this->student();
        $this->enroll($classB, $student); // iscritto solo a B

        // Prova a scrivere al docente di A dalla classe B → 403 (non insegna in B).
        $this->asUser($student)->post(route('student.classi.messaggi.store', $classB), [
            'teacher_id' => $profA->id, 'subject' => 'Oggetto', 'body' => 'testo',
        ])->assertForbidden();

        // Prova ad accedere alla messaggistica della classe A (non sua) → 403.
        $this->asUser($student)->get(route('student.classi.messaggi.index', $classA))->assertForbidden();
        $this->assertSame(0, ClassConversation::count());
    }

    public function test_teacher_cannot_access_other_class_messaging(): void
    {
        $profA = $this->prof();
        $profB = $this->prof();
        $classB = $this->freeClass($profB);

        $this->asProf($profA)->get(route('docente.classi.messaggi.index', $classB))->assertForbidden();
        $this->asProf($profA)->get(route('docente.classi.annunci.index', $classB))->assertForbidden();
        $this->asProf($profA)->post(route('docente.classi.annunci.store', $classB), ['subject' => 'x', 'body' => 'y'])
            ->assertForbidden();
    }

    public function test_conversation_not_visible_to_other_student(): void
    {
        Notification::fake();
        $prof = $this->prof();
        $class = $this->freeClass($prof);
        $a = $this->student();
        $b = $this->student();
        $this->enroll($class, $a);
        $this->enroll($class, $b);

        $this->asUser($a)->post(route('student.classi.messaggi.store', $class), [
            'teacher_id' => $prof->id, 'subject' => 'privato', 'body' => 'segreto',
        ]);
        $conv = ClassConversation::first();

        // Lo studente B non può aprire il thread di A.
        $this->asUser($b)->get(route('student.classi.messaggi.show', [$class, $conv]))->assertForbidden();
    }

    // ===== Segreteria esclusa =====

    public function test_secretary_excluded_from_messaging(): void
    {
        $school = School::create(['name' => 'Liceo', 'slug' => 'l-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
        $prof = $this->prof($school);
        $schoolClass = SchoolClass::create(['school_id' => $school->id, 'teacher_id' => null, 'name' => '3B',
            'subject_id' => null, 'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => false, 'requires_approval' => false, 'is_archived' => false]);
        TeachingAssignment::create(['school_id' => $school->id, 'teacher_id' => $prof->id,
            'subject_id' => $this->storia->id, 'school_class_id' => $schoolClass->id, 'school_year' => '2026/2027']);

        $secretary = Student::create(['name' => 'Segr', 'email' => 'sa' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => null, 'is_secretary' => true, 'school_id' => $school->id, 'is_active' => true, 'must_change_password' => false]);

        // La segreteria non è docente (gate professor) → niente area messaggi docente.
        $this->asProf($secretary)->get(route('docente.classi.messaggi.index', $schoolClass))->assertForbidden();

        // E non è studente attivo → niente messaggistica studente.
        $this->asUser($secretary)->get(route('student.classi.messaggi.index', $schoolClass))->assertForbidden();

        // Un docente non può messaggiare la segreteria (non è studente attivo della classe).
        $this->asProf($prof)->post(route('docente.classi.messaggi.store', $schoolClass), [
            'student_id' => $secretary->id, 'subject' => 'Oggetto', 'body' => 'testo',
        ])->assertForbidden();
    }

    // ===== School class: cattedra richiesta =====

    public function test_school_class_requires_cattedra_to_message(): void
    {
        $school = School::create(['name' => 'Liceo', 'slug' => 'l-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
        $withCattedra = $this->prof($school);
        $withoutCattedra = $this->prof($school);
        $schoolClass = SchoolClass::create(['school_id' => $school->id, 'teacher_id' => null, 'name' => '3C',
            'subject_id' => null, 'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => false, 'requires_approval' => false, 'is_archived' => false]);
        TeachingAssignment::create(['school_id' => $school->id, 'teacher_id' => $withCattedra->id,
            'subject_id' => $this->storia->id, 'school_class_id' => $schoolClass->id, 'school_year' => '2026/2027']);
        $student = $this->student($school);
        $this->enroll($schoolClass, $student);

        // Docente con cattedra: accede.
        $this->asProf($withCattedra)->get(route('docente.classi.messaggi.index', $schoolClass))->assertOk();
        // Docente senza cattedra: 403.
        $this->asProf($withoutCattedra)->get(route('docente.classi.messaggi.index', $schoolClass))->assertForbidden();

        // Lo studente può scrivere solo al docente con cattedra.
        $this->asUser($student)->post(route('student.classi.messaggi.store', $schoolClass), [
            'teacher_id' => $withoutCattedra->id, 'subject' => 'Oggetto', 'body' => 'testo',
        ])->assertForbidden();
        $this->asUser($student)->post(route('student.classi.messaggi.store', $schoolClass), [
            'teacher_id' => $withCattedra->id, 'subject' => 'Dubbio', 'body' => 'aiuto',
        ])->assertRedirect();
    }

    // ===== Regressione messaggistica corsi =====

    public function test_course_messaging_untouched(): void
    {
        // Le tabelle del mondo corsi restano separate: nessuna riga Schola le tocca.
        Notification::fake();
        $prof = $this->prof();
        $class = $this->freeClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $this->asUser($student)->post(route('student.classi.messaggi.store', $class), [
            'teacher_id' => $prof->id, 'subject' => 'Oggetto A', 'body' => 'ciao',
        ]);

        $this->assertSame(1, ClassConversation::count());
        $this->assertSame(0, \App\Models\Conversation::count());   // corsi invariato
        $this->assertSame(0, \App\Models\Message::count());
    }
}
