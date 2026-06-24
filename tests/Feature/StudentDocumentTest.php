<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(array $attrs = []): Student
    {
        return Student::create(array_merge([
            'name'                 => 'Mario Rossi',
            'email'                => 'mario+' . uniqid() . '@example.com',
            'password'             => bcrypt('secret-pw'),
            'is_active'            => true,
            'is_demo'              => false,
            'must_change_password' => false,
        ], $attrs));
    }

    private function makeCourse(): Course
    {
        return Course::create([
            'name'       => 'Corso AI',
            'slug'       => 'corso-ai-' . uniqid(),
            'is_active'  => true,
            'sort_order' => 1,
        ]);
    }

    private function enroll(Student $student, Course $course): void
    {
        $student->courses()->attach($course->id, [
            'enrolled_at' => now(),
            'is_active'   => true,
        ]);
    }

    private function actingAsStudent(Student $student): self
    {
        return $this->withSession([
            'student_id'    => $student->id,
            'student_email' => $student->email,
            'student_name'  => $student->name,
        ]);
    }

    public function test_student_can_upload_and_see_own_document(): void
    {
        Storage::fake('local');

        $student = $this->makeStudent();
        $course  = $this->makeCourse();
        $this->enroll($student, $course);

        $file = UploadedFile::fake()->create('appunti.pdf', 100, 'application/pdf');

        $response = $this->actingAsStudent($student)
            ->post(route('student.documents.store'), [
                'title'      => 'Appunti corso AI',
                'course_id'  => $course->id,
                'visibility' => 'private',
                'file'       => $file,
            ]);

        $response->assertRedirect(route('student.documents.index'));

        $doc = StudentDocument::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('Appunti corso AI', $doc->title);
        $this->assertSame('private', $doc->visibility);
        $this->assertSame('appunti.pdf', $doc->original_filename);
        Storage::disk('local')->assertExists($doc->file_path);

        $this->actingAsStudent($student)
            ->get(route('student.documents.index'))
            ->assertOk()
            ->assertSee('Appunti corso AI');
    }

    public function test_student_cannot_download_other_students_document(): void
    {
        Storage::fake('local');

        $owner   = $this->makeStudent();
        $other   = $this->makeStudent();
        $course  = $this->makeCourse();
        $this->enroll($owner, $course);

        $this->actingAsStudent($owner)->post(route('student.documents.store'), [
            'title'      => 'Privato',
            'course_id'  => $course->id,
            'visibility' => 'private',
            'file'       => UploadedFile::fake()->create('p.pdf', 10),
        ]);

        $doc = StudentDocument::where('student_id', $owner->id)->firstOrFail();

        $this->actingAsStudent($other)
            ->get(route('student.documents.download', $doc->id))
            ->assertForbidden();
    }

    public function test_private_document_is_not_visible_or_downloadable_by_instructor(): void
    {
        Storage::fake('local');

        $student    = $this->makeStudent();
        $instructor = $this->makeStudent(['role' => 'instructor']);
        $course     = $this->makeCourse();
        $this->enroll($student, $course);

        $this->actingAsStudent($student)->post(route('student.documents.store'), [
            'title'      => 'Documento riservato',
            'course_id'  => $course->id,
            'visibility' => 'private',
            'file'       => UploadedFile::fake()->create('riservato.pdf', 10),
        ]);

        $doc = StudentDocument::where('student_id', $student->id)->firstOrFail();

        $list = $this->actingAsStudent($instructor)
            ->get(route('student.instructor_documents.index'))
            ->assertOk();
        $list->assertDontSee('Documento riservato');

        $this->actingAsStudent($instructor)
            ->get(route('student.instructor_documents.download', $doc->id))
            ->assertForbidden();
    }

    public function test_shared_document_is_visible_and_downloadable_by_instructor(): void
    {
        Storage::fake('local');

        $student    = $this->makeStudent();
        $instructor = $this->makeStudent(['role' => 'instructor']);
        $course     = $this->makeCourse();
        $this->enroll($student, $course);

        $this->actingAsStudent($student)->post(route('student.documents.store'), [
            'title'      => 'Esercizio condiviso',
            'course_id'  => $course->id,
            'visibility' => 'instructors',
            'file'       => UploadedFile::fake()->create('shared.pdf', 10),
        ]);

        $doc = StudentDocument::where('student_id', $student->id)->firstOrFail();

        $this->actingAsStudent($instructor)
            ->get(route('student.instructor_documents.index'))
            ->assertOk()
            ->assertSee('Esercizio condiviso');

        $this->actingAsStudent($instructor)
            ->get(route('student.instructor_documents.download', $doc->id))
            ->assertOk();
    }

    public function test_demo_account_cannot_upload(): void
    {
        Storage::fake('local');

        $demo = $this->makeStudent(['is_demo' => true]);

        $this->actingAsStudent($demo)
            ->post(route('student.documents.store'), [
                'title'      => 'tentativo',
                'visibility' => 'private',
                'file'       => UploadedFile::fake()->create('x.pdf', 10),
            ])
            ->assertForbidden();

        $this->assertSame(0, StudentDocument::count());
    }

    public function test_plain_student_cannot_access_instructor_documents_area(): void
    {
        $student = $this->makeStudent(['role' => 'student']);

        $this->actingAsStudent($student)
            ->get(route('student.instructor_documents.index'))
            ->assertForbidden();
    }

    public function test_destroy_soft_deletes_and_hides_from_list(): void
    {
        Storage::fake('local');

        $student = $this->makeStudent();
        $course  = $this->makeCourse();
        $this->enroll($student, $course);

        $this->actingAsStudent($student)->post(route('student.documents.store'), [
            'title'      => 'Da eliminare',
            'course_id'  => $course->id,
            'visibility' => 'private',
            'file'       => UploadedFile::fake()->create('x.pdf', 10),
        ]);

        $doc = StudentDocument::where('student_id', $student->id)->firstOrFail();

        $this->actingAsStudent($student)
            ->delete(route('student.documents.destroy', $doc->id))
            ->assertRedirect(route('student.documents.index'));

        $this->assertSoftDeleted('student_documents', ['id' => $doc->id]);

        $this->actingAsStudent($student)
            ->get(route('student.documents.index'))
            ->assertOk()
            ->assertDontSee('Da eliminare');
    }
}
