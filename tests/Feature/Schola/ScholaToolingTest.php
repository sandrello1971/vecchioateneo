<?php

namespace Tests\Feature\Schola;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholaToolingTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_command_runs(): void
    {
        $this->artisan('schola:status')->assertExitCode(0);
    }

    public function test_demo_seeder_is_idempotent(): void
    {
        $this->artisan('db:seed', ['--class' => 'ScholaDemoSeeder'])->assertExitCode(0);
        $this->artisan('db:seed', ['--class' => 'ScholaDemoSeeder'])->assertExitCode(0);

        $this->assertSame(1, Student::where('email', 'prof.demo@schola.demo')->count());
        $this->assertSame(8, Student::where('email', 'like', '%@schola.demo')->where('role', 'student')->count());
        $this->assertSame(2, SchoolClass::whereHas('teacher', fn ($q) => $q->where('email', 'prof.demo@schola.demo'))->count());
        // Dati per il cruscotto presenti
        $this->assertGreaterThan(0, \App\Models\ArtifactPublication::count());
        $this->assertGreaterThan(0, \App\Models\UnansweredQuestion::count());
        $this->assertGreaterThan(0, \App\Models\QuizAttempt::count());
    }

    public function test_demo_seeder_skips_on_prod_database(): void
    {
        config(['database.connections.' . config('database.default') . '.database' => 'atheneum_db']);

        $this->artisan('db:seed', ['--class' => 'ScholaDemoSeeder'])->assertExitCode(0);

        $this->assertSame(0, Student::where('email', 'prof.demo@schola.demo')->count());
    }
}
