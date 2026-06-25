<?php

namespace Tests\Feature\Schola;

use App\Jobs\GenerateLessonPresentationJob;
use App\Models\ClassStudent;
use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Models\LessonPublication;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Blocco A — pubblicazione + bi-versione presentazioni LEZIONI. Guardrail: lo
 * studente vede SOLO la pubblicata; correggere/rigenerare lavora su una BOZZA
 * senza toccare ciò che vede lo studente, finché non si pubblica.
 */
class LessonPresentationPublishTest extends TestCase
{
    use RefreshDatabase;

    private function prof(): Student
    {
        return Student::create(['name' => 'Prof', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function student(): Student
    {
        return Student::create(['name' => 'Stu', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'student', 'is_active' => true, 'must_change_password' => false]);
    }

    private function schoolClass(Student $teacher): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $teacher->id, 'name' => '3A',
            'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => true,
            'requires_approval' => false, 'is_archived' => false]);
    }

    private function lesson(Student $prof): Lesson
    {
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'name' => 'Riv', 'position' => 0]);

        return Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => 'Le cause',
            'position' => 0, 'generation_status' => 'ready', 'content' => '## Crisi']);
    }

    /** Studente iscritto attivo + lezione pubblicata sulla classe (accesso allo studente). */
    private function enrolledStudentOnPublishedLesson(Student $prof, Lesson $lesson): array
    {
        $class = $this->schoolClass($prof);
        $student = $this->student();
        ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $student->id, 'status' => 'active', 'approved_at' => now()]);
        LessonPublication::create(['lesson_id' => $lesson->id, 'school_class_id' => $class->id,
            'students_can_generate' => true, 'rag_status' => 'ready', 'published_at' => now()]);

        return [$class, $student];
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function asUser(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    /** Crea un record presentazione con file (e opzionale PNG cache) su disco fake. */
    private function makePresentation(Lesson $lesson, array $attrs = [], bool $withCachePng = false): LessonPresentation
    {
        $pres = LessonPresentation::create(array_merge([
            'lesson_id' => $lesson->id, 'status' => 'ready', 'source' => 'generated',
            'spec' => ['theme' => [], 'slides' => [['layout' => 'cover']]],
            'generation_meta' => ['filename' => 'le-cause.pptx', 'slides' => 1],
        ], $attrs));
        $path = "lesson-presentations/{$lesson->id}/{$pres->id}.pptx";
        Storage::disk('local')->put($path, 'PPTX-' . $pres->id);
        if ($withCachePng) {
            Storage::disk('local')->put("lesson-presentations/{$lesson->id}/{$pres->id}/slide_1.png", 'PNG');
        }
        $pres->update(['file_path' => $path]);

        return $pres->refresh();
    }

    // ===== 1. generata = BOZZA → studente NON la vede =====
    public function test_bozza_non_visibile_allo_studente(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        [$class, $student] = $this->enrolledStudentOnPublishedLesson($prof, $lesson);

        $this->makePresentation($lesson, ['published_at' => null]); // BOZZA

        $this->asUser($student)->get(route('student.classes.lesson.presentation', [$class, $lesson]))
            ->assertNotFound();
    }

    // ===== 2. pubblico → studente la vede =====
    public function test_pubblicata_visibile_allo_studente(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        [$class, $student] = $this->enrolledStudentOnPublishedLesson($prof, $lesson);

        $this->makePresentation($lesson, ['published_at' => null]); // bozza pronta

        // Pubblica via controller docente.
        $this->asProf($prof)->post(route('docente.lessons.presentation.publish', $lesson))->assertRedirect();

        $this->assertSame(1, $lesson->presentations()->whereNotNull('published_at')->count());
        $this->asUser($student)->get(route('student.classes.lesson.presentation', [$class, $lesson]))
            ->assertOk()->assertDownload('le-cause.pptx');
    }

    // ===== 3. correggo la pubblicata → nasce BOZZA; studente vede ANCORA la vecchia (KEY) =====
    public function test_correzione_non_tocca_la_pubblicata_vista_dallo_studente(): void
    {
        Bus::fake(); // l'edit dispatcha il job: non lo eseguiamo (niente LLM)
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        [$class, $student] = $this->enrolledStudentOnPublishedLesson($prof, $lesson);

        $published = $this->makePresentation($lesson, ['published_at' => now()]);

        // Correggo: deve CLONARE in una bozza, senza toccare la pubblicata.
        $this->asProf($prof)->post(route('docente.lessons.presentation.edit', $lesson), ['instruction' => 'Nella slide 1 aggiungi X'])
            ->assertRedirect();

        // Nasce 1 bozza, la pubblicata resta invariata.
        $this->assertSame(1, $lesson->presentations()->whereNull('published_at')->count(), 'è nata una bozza');
        $this->assertSame(1, $lesson->presentations()->whereNotNull('published_at')->count(), 'resta 1 sola pubblicata');
        $this->assertTrue($lesson->presentations()->whereNotNull('published_at')->first()->is($published));

        // Lo studente continua a scaricare la VECCHIA pubblicata, non la bozza.
        $this->asUser($student)->get(route('student.classes.lesson.presentation', [$class, $lesson]))
            ->assertOk()->assertDownload('le-cause.pptx');
        Bus::assertDispatchedAfterResponse(GenerateLessonPresentationJob::class);
    }

    // ===== 4. pubblico la bozza → studente vede la nuova; la vecchia rimossa (file+cache) =====
    public function test_pubblicare_bozza_sostituisce_ed_elimina_la_vecchia(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        [$class, $student] = $this->enrolledStudentOnPublishedLesson($prof, $lesson);

        $old = $this->makePresentation($lesson, ['published_at' => now()], withCachePng: true);
        $draft = $this->makePresentation($lesson, ['published_at' => null]);

        $this->asProf($prof)->post(route('docente.lessons.presentation.publish', $lesson))->assertRedirect();

        // Resta 1 sola pubblicata = ex bozza; la vecchia eliminata (record + file + cache).
        $this->assertSame(1, $lesson->presentations()->count());
        $this->assertTrue($lesson->presentations()->whereNotNull('published_at')->first()->is($draft->refresh()));
        $this->assertDatabaseMissing('lesson_presentations', ['id' => $old->id]);
        Storage::disk('local')->assertMissing($old->file_path);
        Storage::disk('local')->assertMissing("lesson-presentations/{$lesson->id}/{$old->id}/slide_1.png");

        // Lo studente ora scarica la nuova.
        $this->asUser($student)->get(route('student.classes.lesson.presentation', [$class, $lesson]))->assertOk();
    }

    // ===== 5. ritiro → studente non vede più nulla =====
    public function test_ritiro_nasconde_allo_studente(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        [$class, $student] = $this->enrolledStudentOnPublishedLesson($prof, $lesson);

        $this->makePresentation($lesson, ['published_at' => now()]);

        $this->asProf($prof)->post(route('docente.lessons.presentation.unpublish', $lesson))->assertRedirect();

        $this->assertSame(0, $lesson->presentations()->whereNotNull('published_at')->count());
        $this->asUser($student)->get(route('student.classes.lesson.presentation', [$class, $lesson]))
            ->assertNotFound();
    }
}
