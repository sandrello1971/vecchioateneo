<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\Course;
use App\Models\DocumentRag;
use App\Models\Student;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinervaScopingTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(array $attrs = []): Student
    {
        return Student::create(array_merge([
            'name' => 'Tizio ' . uniqid(),
            'email' => 'tizio+' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pw'),
            'is_active' => true, 'is_demo' => false, 'must_change_password' => false,
        ], $attrs));
    }

    private function makeCourse(?string $slug = null): Course
    {
        return Course::create([
            'name' => 'Corso ' . ($slug ?? uniqid()),
            'slug' => $slug ?? ('corso-' . uniqid()),
            'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function actingAsStudent(Student $s): self
    {
        return $this->withSession([
            'student_id' => $s->id, 'student_email' => $s->email, 'student_name' => $s->name,
        ]);
    }

    public function test_searchScoped_excludes_docs_of_non_navigable_course(): void
    {
        $courseA = $this->makeCourse('a');
        $courseB = $this->makeCourse('b');

        DocumentRag::create([
            'course_id' => $courseA->id, 'title' => 'Doc A', 'content' => 'argomento foo',
            'chunk_index' => 0, 'is_instructor_only' => false,
        ]);
        DocumentRag::create([
            'course_id' => $courseB->id, 'title' => 'Doc B', 'content' => 'argomento foo',
            'chunk_index' => 0, 'is_instructor_only' => false,
        ]);

        $rag = app(RagService::class);
        $found = $rag->searchScoped('foo', [$courseA->id], []);

        $titles = $found->pluck('title')->all();
        $this->assertContains('Doc A', $titles);
        $this->assertNotContains('Doc B', $titles, 'Doc B (non navigabile) must NOT appear');
    }

    public function test_searchScoped_excludes_instructor_only_unless_taught(): void
    {
        $taught = $this->makeCourse('taught');
        $other  = $this->makeCourse('other');

        DocumentRag::create([
            'course_id' => $taught->id, 'title' => 'Manuale Taught',
            'content' => 'argomento bar', 'chunk_index' => 0, 'is_instructor_only' => true,
        ]);
        DocumentRag::create([
            'course_id' => $other->id, 'title' => 'Manuale Other',
            'content' => 'argomento bar', 'chunk_index' => 0, 'is_instructor_only' => true,
        ]);

        $rag = app(RagService::class);
        // courseIds: entrambi navigabili; ma instructor-only ammesso SOLO per "taught"
        $found = $rag->searchScoped('bar', [$taught->id, $other->id], [$taught->id]);

        $titles = $found->pluck('title')->all();
        $this->assertContains('Manuale Taught', $titles);
        $this->assertNotContains('Manuale Other', $titles,
            'instructor-only of NON-taught course must be excluded');
    }

    public function test_searchScoped_includes_platform_docs(): void
    {
        $course = $this->makeCourse();

        DocumentRag::create([
            'course_id' => null, 'title' => 'Piattaforma FAQ',
            'content' => 'argomento baz', 'chunk_index' => 0, 'is_instructor_only' => false,
        ]);

        $rag = app(RagService::class);
        $found = $rag->searchScoped('baz', [$course->id], []);

        $titles = $found->pluck('title')->all();
        $this->assertContains('Piattaforma FAQ', $titles,
            'Platform docs (course_id NULL) must be included — decisione §8.3');
    }

    public function test_sendMessage_403_when_no_longer_enrolled(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeCourse();
        // Iscrizione INATTIVA (es. disiscritto)
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => false]);

        $conv = ChatConversation::create([
            'student_id' => $student->id, 'course_id' => $course->id,
            'is_active' => true, 'title' => 'Chat',
        ]);

        $this->actingAsStudent($student)
            ->postJson(route('student.chat.message'), [
                'conversation_id' => $conv->id,
                'message' => 'ciao',
            ])
            ->assertForbidden();
    }
}
