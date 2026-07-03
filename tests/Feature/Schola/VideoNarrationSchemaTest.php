<?php

namespace Tests\Feature\Schola;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Models\LessonVideo;
use App\Models\Module;
use App\Models\ModulePresentation;
use App\Models\ModuleVideo;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * V0 — fondazione schema video narration: tabelle lesson_videos/module_videos,
 * copione json `script`, script_status, relazioni e CHECK sugli enum.
 */
class VideoNarrationSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function lessonWithPresentation(): array
    {
        $prof = Student::create(['name' => 'P', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'name' => 'T', 'position' => 0]);
        $lesson = Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => 'L', 'position' => 0, 'generation_status' => 'ready', 'content' => '## x']);
        $pres = LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready', 'published_at' => now()]);

        return [$lesson, $pres];
    }

    private function moduleWithPresentation(): array
    {
        $course = Course::create(['name' => 'C', 'slug' => 'c-' . Str::lower(Str::random(8)), 'is_active' => true]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'M', 'content' => '## x', 'sort_order' => 0, 'is_active' => true]);
        $pres = ModulePresentation::create(['module_id' => $module->id, 'status' => 'ready', 'published_at' => now()]);

        return [$module, $pres];
    }

    public function test_lesson_video_persiste_con_script_e_relazioni(): void
    {
        [$lesson, $pres] = $this->lessonWithPresentation();

        $video = LessonVideo::create([
            'lesson_id' => $lesson->id,
            'presentation_id' => $pres->id,
            'status' => 'ready',
            'script_status' => 'confirmed',
            'script' => [['slide_number' => 1, 'text' => 'Benvenuti'], ['slide_number' => 2, 'text' => 'Capitolo 1']],
            'generation_meta' => ['voice' => 'it-IT', 'seconds' => 42],
        ])->refresh();

        $this->assertIsArray($video->script);
        $this->assertSame('Benvenuti', $video->script[0]['text']);
        $this->assertSame(2, $video->script[1]['slide_number']);
        $this->assertTrue($video->lesson->is($lesson));
        $this->assertTrue($video->presentation->is($pres));
        $this->assertSame(1, $lesson->videos()->count());
    }

    public function test_module_video_persiste_con_script_e_relazioni(): void
    {
        [$module, $pres] = $this->moduleWithPresentation();

        $video = ModuleVideo::create([
            'module_id' => $module->id,
            'presentation_id' => $pres->id,
            'status' => 'pending',
            'script_status' => 'draft',
            'script' => [['slide_number' => 1, 'text' => 'Intro']],
        ])->refresh();

        $this->assertIsArray($video->script);
        $this->assertSame('Intro', $video->script[0]['text']);
        $this->assertTrue($video->module->is($module));
        $this->assertTrue($video->presentation->is($pres));
        $this->assertSame(1, $module->videos()->count());
    }

    public function test_check_status_invalido_respinto(): void
    {
        [$lesson] = $this->lessonWithPresentation();
        $this->expectException(QueryException::class);
        DB::table('lesson_videos')->insert([
            'id' => Str::uuid(), 'lesson_id' => $lesson->id, 'status' => 'bogus', 'script_status' => 'none',
        ]);
    }

    public function test_check_script_status_invalido_respinto(): void
    {
        [$lesson] = $this->lessonWithPresentation();
        $this->expectException(QueryException::class);
        DB::table('lesson_videos')->insert([
            'id' => Str::uuid(), 'lesson_id' => $lesson->id, 'status' => 'pending', 'script_status' => 'bogus',
        ]);
    }
}
