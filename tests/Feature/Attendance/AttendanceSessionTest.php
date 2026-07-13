<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Module;
use App\Models\Student;
use App\Services\AttendanceRegisterPdfBuilder;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSessionTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $course = Course::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $a = Student::create(['name' => 'Anna', 'email' => 'a' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true]);
        $b = Student::create(['name' => 'Bruno', 'email' => 'b' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true]);
        $course->students()->attach([$a->id => ['enrolled_at' => now(), 'is_active' => true], $b->id => ['enrolled_at' => now(), 'is_active' => true]]);
        $session = CourseSession::create([
            'course_id' => $course->id, 'title' => 'Lezione 1', 'scheduled_at' => now(),
            'duration_minutes' => 120, 'modality' => 'in_person',
        ]);

        return [$course, $a, $b, $session];
    }

    public function test_mark_session_registra_presenti_e_rimuove_assenti(): void
    {
        [$course, $a, $b, $session] = $this->scenario();
        $svc = app(AttendanceService::class);

        // Anna presente con 2h esplicite, Bruno presente con ore vuote (→ durata 2h).
        $count = $svc->markSessionAttendance($session, [$a->id => '2', $b->id => null]);
        $this->assertSame(2, $count);
        $this->assertEquals(2.0, (float) AttendanceRecord::where('student_id', $a->id)->where('source', 'instructor_mark')->value('hours_credited'));
        $this->assertEquals(2.0, (float) AttendanceRecord::where('student_id', $b->id)->where('source', 'instructor_mark')->value('hours_credited'));

        // Ri-salvo con solo Anna: Bruno (assente) viene rimosso, nessun duplicato per Anna.
        $count = $svc->markSessionAttendance($session, [$a->id => '1.5']);
        $this->assertSame(1, $count);
        $this->assertSame(1, AttendanceRecord::where('course_session_id', $session->id)->count());
        $this->assertEquals(1.5, (float) AttendanceRecord::where('student_id', $a->id)->where('source', 'instructor_mark')->value('hours_credited'));
    }

    public function test_course_register_somma_sincrono_e_fad(): void
    {
        [$course, $a, $b, $session] = $this->scenario();
        $svc = app(AttendanceService::class);
        $module = Module::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 0, 'content' => '<p>x</p>', 'duration_minutes' => 60]);

        $svc->markSessionAttendance($session, [$a->id => '2']);           // Anna: 2h sincrono
        AttendanceRecord::create([                                        // Anna: 1h FAD
            'student_id' => $a->id, 'course_id' => $course->id, 'type' => 'async_activity',
            'source' => 'module_completion', 'module_id' => $module->id, 'occurred_at' => now(), 'hours_credited' => 1.0,
        ]);

        $rows = $svc->courseRegister($course);
        $anna = $rows->firstWhere('student.id', $a->id);
        $bruno = $rows->firstWhere('student.id', $b->id);

        $this->assertEquals(2.0, $anna['sync_hours']);
        $this->assertEquals(1.0, $anna['async_hours']);
        $this->assertEquals(3.0, $anna['total_hours']);
        $this->assertEquals(1, $anna['modules_completed']);
        $this->assertEquals(0.0, $bruno['total_hours']);
    }

    public function test_pdf_registro_si_genera(): void
    {
        [$course, $a, $b, $session] = $this->scenario();
        $svc = app(AttendanceService::class);
        $svc->markSessionAttendance($session, [$a->id => '2']);

        $bytes = app(AttendanceRegisterPdfBuilder::class)->buildCourseRegister(
            $course, $svc->courseRegister($course), collect([$session])
        );

        $this->assertNotEmpty($bytes);
        $this->assertStringStartsWith('%PDF', $bytes);
    }
}
