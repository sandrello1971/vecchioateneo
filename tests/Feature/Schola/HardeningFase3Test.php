<?php

namespace Tests\Feature\Schola;

use App\Models\ClassStudent;
use App\Models\Lesson;
use App\Models\LessonPublication;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentGeneratedArtifact;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

// Fase 3 (P23) — indurimento: XSS/UGC, rate limit AI, plus-addressing, IDOR
// cross-scuola sulle superfici nuove di fase 3. (L'IDOR per singola feature è
// già coperto nei test dedicati P18–P22; qui si consolida il taglio multi-scuola.)
class HardeningFase3Test extends TestCase
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

    private function schoolAdmin(School $school): Student
    {
        return Student::create(['name' => 'Segr', 'email' => 'sa' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => null, 'is_secretary' => true, 'school_id' => $school->id, 'is_active' => true, 'must_change_password' => false]);
    }

    private function freeClass(Student $teacher): SchoolClass
    {
        return SchoolClass::create(['school_id' => null, 'teacher_id' => $teacher->id, 'name' => '3A',
            'subject_id' => $this->storia->id, 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => true,
            'requires_approval' => false, 'is_archived' => false]);
    }

    private function enroll(SchoolClass $class, Student $s): void
    {
        ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $s->id, 'status' => 'active', 'approved_at' => now()]);
    }

    private function readyLesson(Student $prof, string $content): Lesson
    {
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => $this->storia->id, 'name' => 'Arg', 'position' => 0]);

        return Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => 'Lez',
            'position' => 0, 'generation_status' => 'ready', 'content' => $content]);
    }

    private function asUser(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    // ===== XSS / sanitizzazione UGC =====

    public function test_lesson_body_strips_dangerous_html_and_js_links(): void
    {
        $prof = $this->prof();
        $class = $this->freeClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);

        $payload = "## Titolo\n\n<script>alert('xss')</script>\n\nVedi [qui](javascript:alert('x')) e <img src=x onerror=alert(1)>.";
        $lesson = $this->readyLesson($prof, $payload);
        LessonPublication::create(['lesson_id' => $lesson->id, 'school_class_id' => $class->id,
            'students_can_generate' => false, 'rag_status' => 'ready', 'published_at' => now()]);

        $html = $this->asUser($student)->get(route('student.classes.lesson.show', [$class, $lesson]))
            ->assertOk()->getContent();

        // Estrae il solo corpo lezione per non confondere con lo script Alpine/KaTeX della pagina.
        $body = '';
        if (preg_match('/<div class="lesson-body">(.*?)<\/div>\s*<\/div>/s', $html, $m)) {
            $body = $m[1];
        }
        $this->assertStringContainsString('Titolo', $body);
        $this->assertStringNotContainsString('<script>alert', $body);
        $this->assertStringNotContainsString('javascript:alert', $body);
        $this->assertStringNotContainsString('onerror', $body);
    }

    public function test_message_and_announcement_bodies_are_escaped(): void
    {
        // I corpi di messaggi/annunci sono resi con {{ }} (escape Blade): il
        // payload appare come testo, mai come markup eseguibile.
        $prof = $this->prof();
        $class = $this->freeClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);

        \App\Models\ClassAnnouncement::create(['school_class_id' => $class->id, 'teacher_id' => $prof->id,
            'subject' => 'Avviso', 'body' => "<script>alert('a')</script>"]);
        $ann = \App\Models\ClassAnnouncement::first();

        $html = $this->asUser($student)->get(route('student.classi.annunci.show', [$class, $ann]))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString("<script>alert('a')", $html); // escaped → &lt;script&gt;
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ===== Rate limit generazioni AI (throttle:schola-generate) =====

    public function test_ai_generation_is_rate_limited_per_user(): void
    {
        Bus::fake();
        atheneum_setting_put('schola.student_daily_generations', 1000); // isola il throttle dal limite giornaliero
        $prof = $this->prof();
        $class = $this->freeClass($prof);
        $student = $this->student();
        $this->enroll($class, $student);
        $lesson = $this->readyLesson($prof, 'Corpo lezione.');
        LessonPublication::create(['lesson_id' => $lesson->id, 'school_class_id' => $class->id,
            'students_can_generate' => true, 'rag_status' => 'ready', 'published_at' => now()]);

        // Il limiter schola-generate è 8/minuto per utente: risponde con un
        // redirect-back + errore (non 429). La 9ª richiesta è bloccata → non crea.
        $last = null;
        for ($i = 0; $i < 9; $i++) {
            $last = $this->asUser($student)->post(route('student.classes.lesson.generate', [$class, $lesson]), ['type' => 'quiz']);
        }

        // Esattamente 8 generazioni create; la 9ª è throttlata con messaggio d'errore.
        $this->assertSame(8, StudentGeneratedArtifact::where('student_id', $student->id)->count());
        $last->assertSessionHas('error');
    }

    // ===== Plus-addressing nell'aggiunta studente (verifica) =====

    public function test_student_added_with_plus_addressed_email(): void
    {
        $school = School::create(['name' => 'Liceo', 'slug' => 'l-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
        $admin = $this->schoolAdmin($school);
        $class = SchoolClass::create(['school_id' => $school->id, 'teacher_id' => null, 'name' => '1A',
            'subject_id' => null, 'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => false, 'requires_approval' => false, 'is_archived' => false]);

        $this->asUser($admin)->post(route('scuola.studenti.store'), [
            'nome' => 'Mario', 'cognome' => 'Rossi',
            'email' => 'stefano.andrello+studente@gmail.com',
            'data_nascita' => '2008-01-01', 'class_id' => $class->id,
        ])->assertRedirect();

        // L'account è creato con l'email plus-addressed esatta.
        $this->assertDatabaseHas('students', [
            'email' => 'stefano.andrello+studente@gmail.com', 'school_id' => $school->id, 'role' => 'student',
        ]);
    }

    // ===== IDOR cross-scuola sulle superfici di fase 3 =====

    public function test_cross_school_teacher_cannot_touch_other_lesson(): void
    {
        $a = School::create(['name' => 'A', 'slug' => 'a-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
        $b = School::create(['name' => 'B', 'slug' => 'b-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
        $profA = $this->prof($a);
        $profB = $this->prof($b);
        $lessonA = $this->readyLesson($profA, 'Corpo A.');

        // Docente di B non vede/edita/genera/pubblica/presenta la lezione di A.
        $this->asUser($profB)->get(route('docente.lessons.show', $lessonA))->assertForbidden();
        $this->asUser($profB)->post(route('docente.lessons.generate', $lessonA))->assertForbidden();
        $this->asUser($profB)->patch(route('docente.lessons.content', $lessonA), ['content' => 'x'])->assertForbidden();
        $this->asUser($profB)->post(route('docente.lessons.presentation.generate', $lessonA))->assertForbidden();
        $this->asUser($profB)->get(route('docente.lessons.presentation.download', $lessonA))->assertForbidden();
        $this->asUser($profB)->post(route('docente.lessons.teacher-notes.save', $lessonA), ['anchor' => 'p-1', 'content' => 'x'])->assertForbidden();
    }

    public function test_cross_class_student_cannot_reach_other_class_lesson(): void
    {
        $profA = $this->prof();
        $profB = $this->prof();
        $classA = $this->freeClass($profA);
        $classB = $this->freeClass($profB);
        $sB = $this->student();
        $this->enroll($classB, $sB); // iscritto solo a B
        $lessonA = $this->readyLesson($profA, 'Corpo A.');
        LessonPublication::create(['lesson_id' => $lessonA->id, 'school_class_id' => $classA->id,
            'students_can_generate' => true, 'rag_status' => 'ready', 'published_at' => now()]);

        // Studente di B non raggiunge la lezione/presentazione/Minerva di A.
        $this->asUser($sB)->get(route('student.classes.lesson.show', [$classB, $lessonA]))->assertForbidden();
        $this->asUser($sB)->get(route('student.classes.lesson.presentation', [$classB, $lessonA]))->assertForbidden();
        $this->asUser($sB)->postJson(route('student.minerva.ask'), [
            'question' => 'x', 'school_class_id' => $classB->id, 'lesson_id' => $lessonA->id,
        ])->assertForbidden();
    }
}
