<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Module;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceModalityTest extends TestCase
{
    use RefreshDatabase;

    private function courseWithHours(?string $modality): array
    {
        $course = Course::create([
            'name' => 'C', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1,
            'modality' => $modality,
        ]);
        $student = Student::create(['name' => 'Anna', 'email' => 'a' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'is_active' => true]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 0, 'content' => '<p>x</p>', 'duration_minutes' => 60]);
        $session = CourseSession::create(['course_id' => $course->id, 'title' => 'L', 'scheduled_at' => now(), 'duration_minutes' => 120, 'modality' => 'in_person']);

        // 2h sincrono + 1h FAD per Anna
        app(AttendanceService::class)->markSessionAttendance($session, [$student->id => '2']);
        AttendanceRecord::create([
            'student_id' => $student->id, 'course_id' => $course->id, 'type' => 'async_activity',
            'source' => 'module_completion', 'module_id' => $module->id, 'occurred_at' => now(), 'hours_credited' => 1.0,
        ]);

        return [$course, $student];
    }

    public function test_totale_async_conta_solo_fad(): void
    {
        [$course, $student] = $this->courseWithHours('async');
        $row = app(AttendanceService::class)->courseRegister($course)->firstWhere('student.id', $student->id);
        $this->assertEquals(2.0, $row['sync_hours']);
        $this->assertEquals(1.0, $row['async_hours']);
        $this->assertEquals(1.0, $row['total_hours']); // solo FAD
    }

    public function test_totale_sync_conta_solo_presenze(): void
    {
        [$course, $student] = $this->courseWithHours('sync');
        $row = app(AttendanceService::class)->courseRegister($course)->firstWhere('student.id', $student->id);
        $this->assertEquals(2.0, $row['total_hours']); // solo presenze
    }

    public function test_totale_non_impostata_somma_entrambi(): void
    {
        [$course, $student] = $this->courseWithHours(null);
        $row = app(AttendanceService::class)->courseRegister($course)->firstWhere('student.id', $student->id);
        $this->assertEquals(3.0, $row['total_hours']); // entrambi
    }

    public function test_corso_sync_salta_il_gate_di_completamento(): void
    {
        $course = Course::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1, 'modality' => 'sync']);
        $module = Module::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 0, 'content' => '<p>x</p>', 'duration_minutes' => 60]);
        $student = Student::create(['name' => 'S', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true, 'must_change_password' => false]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'is_active' => true]);

        // Nessun tempo tracciato: in un corso SYNC il completamento NON è bloccato.
        $this->withSession(['student_id' => $student->id, 'student_email' => $student->email, 'student_name' => $student->name])
            ->post(route('student.module.complete', [$course->slug, $module]))
            ->assertRedirect();

        $this->assertDatabaseHas('student_module_progress', [
            'student_id' => $student->id, 'module_id' => $module->id, 'status' => 'completed',
        ]);
    }
}
