<?php

namespace Tests\Feature\Schola;

use App\Jobs\ExtractTeachingDocumentJob;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingDocument;
use App\Models\Topic;
use App\Services\Schola\TeachingDocumentExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TeachingDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function prof(): Student
    {
        return Student::create([
            'name' => 'Prof', 'email' => 'prof+' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'professor',
            'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function makeDoc(Student $p, string $type, array $attrs = []): TeachingDocument
    {
        return TeachingDocument::create(array_merge([
            'teacher_id' => $p->id, 'title' => 'Doc', 'source_type' => $type, 'status' => 'pending',
        ], $attrs));
    }

    private function runExtraction(TeachingDocument $doc): void
    {
        (new ExtractTeachingDocumentJob($doc->id))->handle(app(TeachingDocumentExtractor::class));
    }

    // ===== Job per source_type =====

    public function test_audio_extraction_success(): void
    {
        Storage::fake('local');
        Http::fake([
            '*/api/audio/transcribe' => Http::response(['job_id' => 'j1'], 200),
            '*/api/audio/j1' => Http::response(['status' => 'completed', 'transcript' => 'Ciao mondo', 'language' => 'it', 'duration_seconds' => 12], 200),
        ]);
        $doc = $this->makeDoc($this->prof(), 'audio');
        Storage::disk('local')->put('td/source.mp3', 'AUDIOBYTES');
        $doc->update(['source_files' => ['td/source.mp3']]);

        $this->runExtraction($doc);
        $doc->refresh();

        $this->assertSame('ready', $doc->status);
        $this->assertStringContainsString('Ciao mondo', $doc->extracted_text);
        $this->assertSame('whisper', $doc->extraction_meta['method']);
    }

    public function test_audio_extraction_failure_sets_failed(): void
    {
        Storage::fake('local');
        Http::fake(['*/api/audio/transcribe' => Http::response('boom', 500)]);
        $doc = $this->makeDoc($this->prof(), 'audio');
        Storage::disk('local')->put('td/source.mp3', 'AUDIOBYTES');
        $doc->update(['source_files' => ['td/source.mp3']]);

        $this->runExtraction($doc);
        $doc->refresh();

        $this->assertSame('failed', $doc->status);
        $this->assertNotEmpty($doc->failure_reason);
    }

    public function test_youtube_extraction_success(): void
    {
        Http::fake([
            '*/api/youtube/transcribe' => Http::response(['job_id' => 'y1'], 200),
            '*/api/youtube/y1' => Http::response(['status' => 'completed', 'transcript' => 'Lezione video', 'method' => 'native_transcript'], 200),
        ]);
        $doc = $this->makeDoc($this->prof(), 'youtube', ['source_url' => 'https://youtu.be/abc']);

        $this->runExtraction($doc);
        $doc->refresh();

        $this->assertSame('ready', $doc->status);
        $this->assertStringContainsString('Lezione video', $doc->extracted_text);
        $this->assertSame('native_transcript', $doc->extraction_meta['method']);
    }

    public function test_photos_extraction_vision_per_image(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '# Pagina trascritta']],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
            ], 200),
            // L'estrazione ora crea anche la trascrizione e la indicizza
            // (teacher_private): l'embeddings va fakeato per non uscire in rete.
            '*/api/embeddings' => Http::response([
                'embeddings' => [array_fill(0, 768, 0.01)], 'model' => 'm', 'dimensions' => 768,
            ], 200),
        ]);
        $doc = $this->makeDoc($this->prof(), 'photos');
        Storage::disk('local')->put('td/photo_00.jpg', 'IMG1');
        Storage::disk('local')->put('td/photo_01.jpg', 'IMG2');
        $doc->update(['source_files' => ['td/photo_00.jpg', 'td/photo_01.jpg']]);

        $this->runExtraction($doc);
        $doc->refresh();

        $this->assertSame('ready', $doc->status);
        $this->assertSame('vision', $doc->extraction_meta['method']);
        $this->assertSame(2, $doc->extraction_meta['pages']);
        // Una chiamata vision per immagine (le chiamate embeddings sono a parte).
        $visionCalls = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'api.anthropic.com'))
            ->count();
        $this->assertSame(2, $visionCalls);
    }

    public function test_text_extraction_passthrough(): void
    {
        Storage::fake('local');
        $doc = $this->makeDoc($this->prof(), 'text');
        Storage::disk('local')->put('td/source.md', "# Titolo\n\nContenuto incollato.");
        $doc->update(['source_files' => ['td/source.md']]);

        $this->runExtraction($doc);
        $doc->refresh();

        $this->assertSame('ready', $doc->status);
        $this->assertStringContainsString('Contenuto incollato', $doc->extracted_text);
        $this->assertSame('passthrough', $doc->extraction_meta['method']);
    }

    public function test_docx_extraction_via_pandoc(): void
    {
        if (!$this->binaryExists('pandoc')) {
            $this->markTestSkipped('pandoc non disponibile');
        }
        Storage::fake('local');

        // Crea un .docx reale da markdown via pandoc
        $md = tempnam(sys_get_temp_dir(), 'md');
        file_put_contents($md, "# Lezione Uno\n\nParagrafo di prova.");
        $docx = $md . '.docx';
        (new Process(['pandoc', $md, '-o', $docx]))->run();

        $doc = $this->makeDoc($this->prof(), 'docx');
        Storage::disk('local')->put('td/source.docx', file_get_contents($docx));
        $doc->update(['source_files' => ['td/source.docx']]);

        $this->runExtraction($doc);
        $doc->refresh();
        @unlink($md); @unlink($docx);

        $this->assertSame('ready', $doc->status);
        $this->assertStringContainsString('Lezione Uno', $doc->extracted_text);
        $this->assertSame('pandoc', $doc->extraction_meta['method']);
    }

    public function test_pdf_text_layer_extraction(): void
    {
        if (!$this->binaryExists('pdftotext')) {
            $this->markTestSkipped('poppler-utils (pdftotext) non disponibile su questo host');
        }
        Storage::fake('local');

        // PDF con layer testo reale, generato via TCPDF (già in composer).
        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 14);
        $pdf->Write(0, 'Lezione di prova sul moto rettilineo uniforme.');
        $bytes = $pdf->Output('', 'S');

        $doc = $this->makeDoc($this->prof(), 'pdf');
        Storage::disk('local')->put('td/source.pdf', $bytes);
        $doc->update(['source_files' => ['td/source.pdf']]);

        $this->runExtraction($doc);
        $doc->refresh();

        $this->assertSame('ready', $doc->status);
        $this->assertStringContainsString('moto rettilineo', $doc->extracted_text);
        $this->assertSame('pdftext', $doc->extraction_meta['method']);
    }

    // ===== Controller: validazioni, policy, retry =====

    public function test_store_audio_requires_file(): void
    {
        Bus::fake();
        $this->asProf($this->prof())->post(route('docente.materials.store'), [
            'title' => 'Lezione', 'source_type' => 'audio',
        ])->assertSessionHasErrors('file');
        Bus::assertNothingDispatched();
    }

    public function test_store_photos_max_20(): void
    {
        Bus::fake();
        Storage::fake('local');
        $photos = [];
        for ($i = 0; $i < 21; $i++) $photos[] = UploadedFile::fake()->image("p{$i}.jpg");

        $this->asProf($this->prof())->post(route('docente.materials.store'), [
            'title' => 'Foto', 'source_type' => 'photos', 'photos' => $photos,
        ])->assertSessionHasErrors('photos');
    }

    public function test_store_youtube_rejects_non_youtube_url(): void
    {
        Bus::fake();
        $this->asProf($this->prof())->post(route('docente.materials.store'), [
            'title' => 'Video', 'source_type' => 'youtube', 'source_url' => 'https://vimeo.com/123',
        ])->assertSessionHasErrors('source_url');
    }

    public function test_store_audio_creates_document_and_dispatches_job(): void
    {
        Bus::fake();
        Storage::fake('local');
        $prof = $this->prof();

        $this->asProf($prof)->post(route('docente.materials.store'), [
            'title' => 'Lezione audio', 'source_type' => 'audio',
            'file' => UploadedFile::fake()->create('lez.mp3', 500, 'audio/mpeg'),
        ])->assertRedirect();

        $this->assertDatabaseHas('teaching_documents', [
            'teacher_id' => $prof->id, 'title' => 'Lezione audio', 'source_type' => 'audio', 'status' => 'pending',
        ]);
        Bus::assertDispatchedAfterResponse(ExtractTeachingDocumentJob::class);
    }

    public function test_store_m4a_with_mp4_mime_is_accepted(): void
    {
        Bus::fake();
        Storage::fake('local');
        $prof = $this->prof();

        // m4a = contenitore MP4: PHP rileva spesso audio/mp4 (o video/mp4).
        // Con la vecchia regola `mimes:mp3,m4a,wav,ogg` veniva rifiutato.
        $this->asProf($prof)->post(route('docente.materials.store'), [
            'title' => 'Lezione m4a', 'source_type' => 'audio',
            'file' => UploadedFile::fake()->create('lez.m4a', 500, 'audio/mp4'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('teaching_documents', [
            'teacher_id' => $prof->id, 'title' => 'Lezione m4a', 'source_type' => 'audio',
        ]);
        Bus::assertDispatchedAfterResponse(ExtractTeachingDocumentJob::class);
    }

    public function test_store_accepts_video_container_mp4(): void
    {
        Bus::fake();
        Storage::fake('local');
        $prof = $this->prof();

        // Contenitore video: si trascrive la traccia audio. Accettato dal 5.
        $this->asProf($prof)->post(route('docente.materials.store'), [
            'title' => 'Lezione video', 'source_type' => 'audio',
            'file' => UploadedFile::fake()->create('lez.mp4', 500, 'video/mp4'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        Bus::assertDispatchedAfterResponse(ExtractTeachingDocumentJob::class);
    }

    public function test_store_audio_rejects_non_media_extension(): void
    {
        Bus::fake();
        Storage::fake('local');

        $this->asProf($this->prof())->post(route('docente.materials.store'), [
            'title' => 'Falso', 'source_type' => 'audio',
            'file' => UploadedFile::fake()->create('malware.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('file');
        Bus::assertNothingDispatched();
    }

    public function test_owner_only_policy(): void
    {
        $a = $this->prof();
        $b = $this->prof();
        $doc = $this->makeDoc($a, 'text', ['source_files' => ['td/source.md']]);

        $this->asProf($b)->get(route('docente.materials.show', $doc))->assertForbidden();
        $this->asProf($b)->get(route('docente.materials.status', $doc))->assertForbidden();
        $this->asProf($b)->get(route('docente.materials.download', [$doc, 0]))->assertForbidden();
        $this->asProf($b)->patch(route('docente.materials.update', $doc), ['title' => 'Hack'])->assertForbidden();
        $this->asProf($b)->delete(route('docente.materials.destroy', $doc))->assertForbidden();
        $this->asProf($b)->post(route('docente.materials.retry', $doc))->assertForbidden();
    }

    public function test_download_source_owner_only_via_controller(): void
    {
        Storage::fake('local');
        $prof = $this->prof();
        $doc = $this->makeDoc($prof, 'pdf');
        Storage::disk('local')->put('td/source.pdf', '%PDF-1.4 fake');
        $doc->update(['source_files' => ['td/source.pdf']]);

        $this->asProf($prof)->get(route('docente.materials.download', [$doc, 0]))->assertOk();
    }

    public function test_retry_only_failed(): void
    {
        Bus::fake();
        $prof = $this->prof();

        $ready = $this->makeDoc($prof, 'text', ['status' => 'ready']);
        $this->asProf($prof)->post(route('docente.materials.retry', $ready))->assertStatus(422);
        Bus::assertNothingDispatched();

        $failed = $this->makeDoc($prof, 'text', ['status' => 'failed', 'failure_reason' => 'x']);
        $this->asProf($prof)->post(route('docente.materials.retry', $failed))->assertRedirect();
        Bus::assertDispatchedAfterResponse(ExtractTeachingDocumentJob::class);
        $this->assertSame('pending', $failed->fresh()->status);
    }

    // ===== Upload context-aware: dalla Lezione e dal pool Argomento =====

    private function topicWithLesson(Student $p): array
    {
        $subject = Subject::create(['name' => 'Fisica ' . uniqid(), 'is_custom' => true]);
        $topic = Topic::create([
            'teacher_id' => $p->id, 'subject_id' => $subject->id, 'name' => 'Meccanica', 'position' => 1,
        ]);
        $lesson = Lesson::create([
            'topic_id' => $topic->id, 'teacher_id' => $p->id, 'title' => 'Lez 1',
            'position' => 1, 'generation_status' => 'draft',
        ]);

        return [$subject, $topic, $lesson];
    }

    public function test_store_from_lesson_links_lesson_and_inherits_subject(): void
    {
        Bus::fake();
        Storage::fake('local');
        $prof = $this->prof();
        [$subject, , $lesson] = $this->topicWithLesson($prof);

        $this->asProf($prof)->post(route('docente.materials.store'), [
            'title' => 'Da lezione', 'source_type' => 'text', 'text_content' => 'Contenuto',
            'lesson_id' => $lesson->id,
        ])->assertRedirect(route('docente.lessons.show', $lesson))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('teaching_documents', [
            'teacher_id' => $prof->id, 'title' => 'Da lezione',
            'lesson_id' => $lesson->id, 'subject_id' => $subject->id, // materia ereditata dall'argomento
        ]);
        Bus::assertDispatchedAfterResponse(ExtractTeachingDocumentJob::class);
    }

    public function test_store_from_topic_stays_in_pool_and_inherits_subject(): void
    {
        Bus::fake();
        Storage::fake('local');
        $prof = $this->prof();
        [$subject, $topic] = $this->topicWithLesson($prof);

        $this->asProf($prof)->post(route('docente.materials.store'), [
            'title' => 'Nel pool', 'source_type' => 'text', 'text_content' => 'Contenuto',
            'topic_id' => $topic->id,
        ])->assertRedirect(route('docente.topics.show', $topic))->assertSessionHasNoErrors();

        $doc = TeachingDocument::where('title', 'Nel pool')->firstOrFail();
        $this->assertNull($doc->lesson_id);                 // resta nel pool
        $this->assertSame($subject->id, $doc->subject_id);  // materia ereditata
    }

    public function test_store_from_others_lesson_is_forbidden(): void
    {
        Bus::fake();
        Storage::fake('local');
        $owner = $this->prof();
        $other = $this->prof();
        [, , $lesson] = $this->topicWithLesson($owner);

        $this->asProf($other)->post(route('docente.materials.store'), [
            'title' => 'Intruso', 'source_type' => 'text', 'text_content' => 'x',
            'lesson_id' => $lesson->id,
        ])->assertForbidden();
        Bus::assertNothingDispatched();
    }

    private function binaryExists(string $bin): bool
    {
        $p = new Process(['which', $bin]);
        $p->run();
        return $p->isSuccessful();
    }
}
