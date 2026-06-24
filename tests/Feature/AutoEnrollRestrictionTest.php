<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutoEnrollRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(array $attrs = []): Student
    {
        return Student::create(array_merge([
            'name'                 => 'Tizio ' . uniqid(),
            'email'                => 'tizio+' . uniqid() . '@example.com',
            'password'             => bcrypt('secret-pw'),
            'is_active'            => true,
            'is_demo'              => false,
            'must_change_password' => false,
        ], $attrs));
    }

    private function makeCourse(): Course
    {
        return Course::create([
            'name'       => 'Corso ' . uniqid(),
            'slug'       => 'corso-' . uniqid(),
            'is_active'  => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * Esegue la stessa logica di reset della migration di remediation,
     * usando la whitelist corrente.
     */
    private function runRemediation(): int
    {
        $admins = array_map('strtolower', (array) config('atheneum.admins', []));

        return DB::table('students')
            ->where('auto_enroll_all_courses', true)
            ->when(!empty($admins), fn ($q) =>
                $q->whereNotIn(DB::raw('lower(email)'), $admins))
            ->update(['auto_enroll_all_courses' => false]);
    }

    public function test_new_student_default_is_false(): void
    {
        $student = $this->makeStudent();
        $this->assertFalse((bool) $student->fresh()->auto_enroll_all_courses);
    }

    public function test_remediation_resets_non_admin_legacy_true_to_false(): void
    {
        Config::set('atheneum.admins', ['sandrello@noscite.it']);

        $legacy = $this->makeStudent([
            'email' => 'qualcuno@example.com',
            'role'  => 'instructor',
            'auto_enroll_all_courses' => true,
        ]);

        $count = $this->runRemediation();

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertFalse((bool) $legacy->fresh()->auto_enroll_all_courses);
    }

    public function test_remediation_keeps_admin_in_whitelist(): void
    {
        Config::set('atheneum.admins', ['sandrello@noscite.it']);

        $admin = $this->makeStudent([
            'email' => 'sandrello@noscite.it',
            'role'  => 'instructor',
            'auto_enroll_all_courses' => true,
        ]);

        $this->runRemediation();

        $this->assertTrue((bool) $admin->fresh()->auto_enroll_all_courses,
            'Admin in whitelist must keep auto_enroll_all_courses=true');
    }

    public function test_remediation_case_insensitive_admin_match(): void
    {
        // Whitelist lowercase ma email mista — la migration usa lower(email)
        Config::set('atheneum.admins', ['sandrello@noscite.it']);

        $admin = $this->makeStudent([
            'email' => 'Sandrello@Noscite.IT',
            'role'  => 'instructor',
            'auto_enroll_all_courses' => true,
        ]);

        $this->runRemediation();

        $this->assertTrue((bool) $admin->fresh()->auto_enroll_all_courses,
            'Email matching must be case-insensitive');
    }

    public function test_remediation_is_idempotent(): void
    {
        Config::set('atheneum.admins', ['sandrello@noscite.it']);

        $admin = $this->makeStudent([
            'email' => 'sandrello@noscite.it',
            'auto_enroll_all_courses' => true,
        ]);
        $other = $this->makeStudent([
            'email' => 'altro@example.com',
            'auto_enroll_all_courses' => false,
        ]);

        $first = $this->runRemediation();
        $second = $this->runRemediation();

        $this->assertSame(0, $second, 'Second pass must not change any row');
        $this->assertTrue((bool) $admin->fresh()->auto_enroll_all_courses);
        $this->assertFalse((bool) $other->fresh()->auto_enroll_all_courses);
    }

    public function test_non_enrolled_student_without_auto_enroll_gets_403(): void
    {
        $student = $this->makeStudent(['auto_enroll_all_courses' => false]);
        $course = $this->makeCourse();

        $this->withSession([
                'student_id'    => $student->id,
                'student_email' => $student->email,
                'student_name'  => $student->name,
            ])
            ->get(route('student.course.show', $course))
            ->assertForbidden();
    }

    public function test_enrolled_student_accesses_course(): void
    {
        $student = $this->makeStudent(['auto_enroll_all_courses' => false]);
        $course = $this->makeCourse();
        $student->courses()->attach($course->id, [
            'enrolled_at' => now(),
            'is_active'   => true,
        ]);

        $this->withSession([
                'student_id'    => $student->id,
                'student_email' => $student->email,
                'student_name'  => $student->name,
            ])
            ->get(route('student.course.show', $course))
            ->assertOk();
    }
}
