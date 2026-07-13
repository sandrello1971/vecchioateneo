<?php

namespace Tests\Feature\Attendance;

use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(Student $s): self
    {
        return $this->withSession([
            'student_id' => $s->id, 'student_email' => $s->email, 'student_name' => $s->name,
        ]);
    }

    private function course(): Course
    {
        return Course::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_formatore_del_corso_accede_a_sessioni_e_registro(): void
    {
        $course = $this->course();
        $instructor = Student::create(['name' => 'Doc', 'email' => 'd' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true, 'role' => 'instructor', 'must_change_password' => false]);
        $instructor->taughtCourses()->attach($course->id);

        $this->asUser($instructor)->get(route('student.course.sessions.index', $course->slug))->assertOk();
        $this->asUser($instructor)->get(route('student.course.register', $course->slug))->assertOk();
    }

    public function test_studente_non_formatore_riceve_403(): void
    {
        $course = $this->course();
        $altro = $this->course();
        $instructor = Student::create(['name' => 'Doc', 'email' => 'd' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true, 'role' => 'instructor', 'must_change_password' => false]);
        $instructor->taughtCourses()->attach($altro->id); // insegna un ALTRO corso

        $this->asUser($instructor)->get(route('student.course.sessions.index', $course->slug))->assertForbidden();

        $studente = Student::create(['name' => 'Stu', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true, 'role' => 'student', 'must_change_password' => false]);
        $studente->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
        $this->asUser($studente)->get(route('student.course.register', $course->slug))->assertForbidden();
    }

    public function test_formatore_crea_sessione_e_segna_presenza(): void
    {
        $course = $this->course();
        $instructor = Student::create(['name' => 'Doc', 'email' => 'd' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true, 'role' => 'instructor', 'must_change_password' => false]);
        $instructor->taughtCourses()->attach($course->id);
        $discente = Student::create(['name' => 'Anna', 'email' => 'a' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true, 'must_change_password' => false]);
        $course->students()->attach($discente->id, ['enrolled_at' => now(), 'is_active' => true]);

        $this->asUser($instructor)->post(route('student.course.sessions.store', $course->slug), [
            'title' => 'Lezione 1', 'scheduled_at' => now()->format('Y-m-d H:i'),
            'duration_minutes' => 120, 'modality' => 'in_person',
        ])->assertRedirect();

        $session = CourseSession::where('course_id', $course->id)->firstOrFail();
        $this->asUser($instructor)->post(route('student.course.sessions.mark', [$course->slug, $session]), [
            'present' => [$discente->id], 'hours' => [$discente->id => '2'],
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_records', [
            'course_session_id' => $session->id, 'student_id' => $discente->id,
            'source' => 'instructor_mark', 'hours_credited' => 2,
        ]);
    }
}
