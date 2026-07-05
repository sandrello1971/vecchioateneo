<?php

namespace Tests\Feature\Schola;

use App\Jobs\IngestArtifactTeacherPrivateJob;
use App\Jobs\IngestUploadedVideoJob;
use App\Models\Lesson;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\Topic;
use App\Models\UploadedVideo;
use App\Services\VideoAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class UploadedVideoTest extends TestCase
{
    use RefreshDatabase;

    private function school(bool $withDpa = true): School
    {
        return School::create([
            'name' => 'Liceo', 'slug' => 'l-' . uniqid(), 'type' => 'liceo', 'status' => 'active',
            'video_ai_dpa_accepted_at' => $withDpa ? now() : null,
        ]);
    }

    private function prof(?School $school = null): Student
    {
        return Student::create([
            'name' => 'Prof', 'email' => 'prof+' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'professor', 'school_id' => $school?->id,
            'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function lesson(Student $p): Lesson
    {
        $subject = Subject::create(['name' => 'Scienze ' . uniqid(), 'is_custom' => true]);
        $topic = Topic::create(['teacher_id' => $p->id, 'subject_id' => $subject->id, 'name' => 'Acqua', 'position' => 0]);

        return Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $p->id, 'title' => 'Il ciclo',
            'position' => 0, 'generation_status' => 'ready', 'content' => 'Il ciclo dell\'acqua.']);
    }

    private function videoFile(): UploadedFile
    {
        return UploadedFile::fake()->create('lezione.mp4', 2000, 'video/mp4');
    }

    public function test_upload_from_lesson_creates_processing_record_and_dispatches_ingest(): void
    {
        Bus::fake();
        Storage::fake('local');
        $school = $this->school(withDpa: true);
        $prof = $this->prof($school);
        $lesson = $this->lesson($prof);

        $res = $this->asProf($prof)->post(route('docente.videos.store'), [
            'title' => 'Video del ciclo', 'lesson_id' => $lesson->id, 'file' => $this->videoFile(),
        ]);

        $res->assertRedirect(route('docente.lessons.show', $lesson));
        $video = UploadedVideo::first();
        $this->assertNotNull($video);
        $this->assertSame('processing', $video->status);
        $this->assertSame($lesson->id, $video->lesson_id);
        $this->assertSame($lesson->topic->subject_id, $video->subject_id);
        $this->assertNotNull($video->file_path);
        Storage::disk('local')->assertExists($video->file_path);
        Bus::assertDispatched(IngestUploadedVideoJob::class);
    }

    public function test_dpa_missing_blocks_upload(): void
    {
        Bus::fake();
        Storage::fake('local');
        $school = $this->school(withDpa: false);
        $prof = $this->prof($school);
        $lesson = $this->lesson($prof);

        $res = $this->asProf($prof)->post(route('docente.videos.store'), [
            'title' => 'X', 'lesson_id' => $lesson->id, 'file' => $this->videoFile(),
        ]);

        $res->assertSessionHas('error');
        $this->assertSame(0, UploadedVideo::count());
        Bus::assertNotDispatched(IngestUploadedVideoJob::class);
    }

    public function test_non_owner_cannot_manage_video(): void
    {
        $school = $this->school();
        $owner = $this->prof($school);
        $other = $this->prof($school);
        $video = UploadedVideo::create([
            'teacher_id' => $owner->id, 'title' => 'V', 'status' => 'ready', 'video_ai_id' => 'vid_x',
        ]);

        $this->asProf($other)->get(route('docente.videos.status', $video))->assertForbidden();
        $this->asProf($other)->delete(route('docente.videos.destroy', $video))->assertForbidden();
    }

    public function test_publish_requires_ready_and_lesson(): void
    {
        $school = $this->school();
        $prof = $this->prof($school);

        // Materiale (senza lezione) → non pubblicabile agli studenti.
        $material = UploadedVideo::create([
            'teacher_id' => $prof->id, 'title' => 'Mat', 'status' => 'ready', 'video_ai_id' => 'v1',
        ]);
        $this->asProf($prof)->post(route('docente.videos.publish', $material))->assertStatus(422);

        // Video di lezione ancora in analisi → non pubblicabile.
        $lesson = $this->lesson($prof);
        $processing = UploadedVideo::create([
            'teacher_id' => $prof->id, 'lesson_id' => $lesson->id, 'title' => 'P', 'status' => 'processing',
        ]);
        $this->asProf($prof)->post(route('docente.videos.publish', $processing))->assertStatus(422);
    }

    public function test_ingest_job_marks_ready_and_creates_transcript_artifact_for_minerva(): void
    {
        Bus::fake();
        Storage::fake('local');
        $prof = $this->prof($this->school());
        $lesson = $this->lesson($prof);

        $video = UploadedVideo::create([
            'teacher_id' => $prof->id, 'lesson_id' => $lesson->id, 'subject_id' => $lesson->topic->subject_id,
            'title' => 'Ciclo', 'status' => 'processing', 'file_path' => 'uploaded-videos/x/source.mp4',
        ]);
        Storage::disk('local')->put($video->file_path, 'fakebytes');

        $mock = Mockery::mock(VideoAIService::class);
        $mock->shouldReceive('ingestVideo')->once()->andReturn(['video_id' => 'vid_999', 'status' => 'processing']);
        $mock->shouldReceive('getStatus')->andReturn(['status' => 'ready', 'progress' => 100, 'duration' => 42]);
        $mock->shouldReceive('getChunksText')->once()->andReturn([
            ['text' => 'Nel diagramma si vede il ciclo dell\'acqua', 'type' => 'frame', 'start' => 5.0, 'timestamp_str' => '0:05'],
        ]);

        (new IngestUploadedVideoJob($video->id))->handle($mock);

        $video->refresh();
        $this->assertSame('ready', $video->status);
        $this->assertSame('vid_999', $video->video_ai_id);
        $this->assertNotNull($video->indexed_at);
        $this->assertNotNull($video->artifact_id);

        $artifact = TeachingArtifact::find($video->artifact_id);
        $this->assertNotNull($artifact);
        $this->assertSame('transcript', $artifact->type);
        $this->assertStringContainsString('diagramma', $artifact->content);
        $this->assertSame($lesson->topic->subject_id, $artifact->subject_id);
        Bus::assertDispatched(IngestArtifactTeacherPrivateJob::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
