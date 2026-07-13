<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Module;
use App\Models\Student;
use App\Models\StudentModuleProgress;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    private function make(int $durationMin): array
    {
        $course = Course::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 0, 'content' => '<p>x</p>', 'duration_minutes' => $durationMin]);
        $student = Student::create(['name' => 'S', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true]);
        return [$course, $module, $student];
    }

    private function progress(Student $s, Module $m): StudentModuleProgress
    {
        return StudentModuleProgress::where('student_id', $s->id)->where('module_id', $m->id)->firstOrFail();
    }

    public function test_heartbeat_accredita_solo_il_tempo_reale_con_cap(): void
    {
        [$course, $module, $student] = $this->make(10); // 600s, cap ping 90s
        $svc = app(AttendanceService::class);

        // primo ping: nessun last → accredita 0
        $svc->heartbeat($student, $module);
        $this->assertSame(0, $this->progress($student, $module)->tracked_seconds);

        // simulo 60s dall'ultimo ping → accredita 60
        StudentModuleProgress::where('student_id', $student->id)->update(['last_heartbeat_at' => now()->subSeconds(60)]);
        $svc->heartbeat($student, $module);
        $this->assertSame(60, $this->progress($student, $module)->tracked_seconds);

        // simulo 200s → accredita solo il cap (90) → 150
        StudentModuleProgress::where('student_id', $student->id)->update(['last_heartbeat_at' => now()->subSeconds(200)]);
        $svc->heartbeat($student, $module);
        $this->assertSame(150, $this->progress($student, $module)->tracked_seconds);
    }

    public function test_gate_completamento_su_soglia(): void
    {
        [$course, $module, $student] = $this->make(10); // required = 80% * 600 = 480s
        $svc = app(AttendanceService::class);

        $svc->heartbeat($student, $module);
        $p = $this->progress($student, $module);

        $p->update(['tracked_seconds' => 400]);
        $this->assertFalse($svc->minCompletionReached($p->fresh(), $module));

        $p->update(['tracked_seconds' => 480]);
        $this->assertTrue($svc->minCompletionReached($p->fresh(), $module));
    }

    public function test_credito_completamento_ore_effettive_e_idempotente(): void
    {
        [$course, $module, $student] = $this->make(60); // 3600s
        $svc = app(AttendanceService::class);

        StudentModuleProgress::create([
            'student_id' => $student->id, 'module_id' => $module->id,
            'status' => 'in_progress', 'tracked_seconds' => 3600,
        ]);

        $svc->creditModuleCompletion($student, $course, $module);
        $svc->creditModuleCompletion($student, $course, $module); // idempotente

        $records = AttendanceRecord::where('student_id', $student->id)->where('source', 'module_completion')->get();
        $this->assertCount(1, $records);
        $this->assertEquals(1.0, (float) $records->first()->hours_credited); // 3600s = 1h
    }
}
