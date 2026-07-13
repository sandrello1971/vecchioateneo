<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Module;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessioni_e_record_di_presenza_persistono_con_relazioni(): void
    {
        $course = Course::create(['name' => 'CORSO', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0, 'content' => '<p>x</p>', 'duration_minutes' => 120]);
        $student = Student::create(['name' => 'Mario', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true]);

        $session = CourseSession::create([
            'course_id' => $course->id, 'title' => 'Aula 1',
            'scheduled_at' => now(), 'duration_minutes' => 240, 'modality' => 'in_person',
        ]);

        // asincrono: completamento modulo con ore accreditate
        $async = AttendanceRecord::create([
            'student_id' => $student->id, 'course_id' => $course->id,
            'type' => 'async_activity', 'source' => 'module_completion',
            'module_id' => $module->id, 'occurred_at' => now(), 'hours_credited' => 2.0,
        ]);

        // sincrono: presenza marcata dal docente
        $sync = AttendanceRecord::create([
            'student_id' => $student->id, 'course_id' => $course->id,
            'type' => 'sync_session', 'source' => 'instructor_mark',
            'course_session_id' => $session->id, 'occurred_at' => now(), 'hours_credited' => 4.0,
        ]);

        $this->assertSame($module->id, $async->module->id);
        $this->assertSame($session->id, $sync->session->id);
        $this->assertSame($student->id, $sync->student->id);

        $total = AttendanceRecord::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->sum('hours_credited');
        $this->assertEquals(6.0, (float) $total);
    }
}
