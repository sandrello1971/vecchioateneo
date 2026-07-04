<?php

namespace Tests\Feature\Schola;

use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\Schola\VideoIndexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * R2 — indicizzazione dei video generati col testo noto (copione + spec) via videoai
 * /index_chunks. Nessun Whisper/Vision. videoai è fakeato (Http::fake): i test NON
 * toccano un'istanza reale (l'endpoint vive nel codice videoai del branch, non in prod).
 */
class VideoIndexServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.videoai.url' => 'http://127.0.0.1:8001', 'services.videoai.token' => 'tok']);
    }

    private function video(array $meta, array $script, ?array $spec = null)
    {
        $prof = Student::create(['name' => 'P', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'name' => 'T', 'position' => 0]);
        $lesson = Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => 'Le cause', 'position' => 0, 'generation_status' => 'ready', 'content' => '## x']);
        $pres = LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready', 'published_at' => now(),
            'spec' => $spec ?? ['slides' => [
                ['layout' => 'cover', 'title' => 'Introduzione', 'subtitle' => 'Sub'],
                ['layout' => 'bullets_clean', 'title' => 'Attrezzi', 'bullets' => ['cacciavite', 'martello']],
            ]]]);

        return $lesson->videos()->create([
            'presentation_id' => $pres->id, 'status' => 'ready', 'script_status' => 'confirmed',
            'script' => $script, 'generation_meta' => $meta,
        ]);
    }

    private function okScenario()
    {
        return $this->video(
            ['slide_timings' => [
                ['slide_number' => 1, 'start_sec' => 0, 'end_sec' => 4],
                ['slide_number' => 2, 'start_sec' => 4, 'end_sec' => 10],
            ]],
            [['slide_number' => 1, 'text' => 'Benvenuti'], ['slide_number' => 2, 'text' => 'Parliamo di attrezzi']],
        );
    }

    public function test_indicizza_chunk_noti_e_salva_video_ai_id(): void
    {
        Http::fake(['*/index_chunks' => Http::response(['indexed_chunks' => 4], 200)]);
        $video = $this->okScenario();

        $res = (new VideoIndexService())->indexGenerated($video);
        $video->refresh();

        $this->assertSame("gen_lessonvideo_{$video->id}", $video->video_ai_id);
        $this->assertNotNull($video->indexed_at);
        $this->assertSame(4, $res['chunks']); // 2 slide × (parlato + visivo)

        Http::assertSent(function ($request) use ($video) {
            if (!str_contains($request->url(), "/api/videos/gen_lessonvideo_{$video->id}/index_chunks")) {
                return false;
            }
            $chunks = $request->data()['chunks'];
            $types = array_column($chunks, 'type');
            // parlato + visivo, con timestamp da slide_timings
            return in_array('transcript', $types, true) && in_array('frame', $types, true)
                && $chunks[0]['type'] === 'transcript' && (float) $chunks[0]['start'] === 0.0
                && $request->hasHeader('X-Internal-Token', 'tok');
        });
    }

    public function test_chunk_visivo_contiene_testo_a_schermo(): void
    {
        Http::fake(['*/index_chunks' => Http::response(['indexed_chunks' => 4], 200)]);
        $video = $this->okScenario();

        (new VideoIndexService())->indexGenerated($video);

        Http::assertSent(function ($request) {
            foreach ($request->data()['chunks'] as $c) {
                // "cacciavite" è nei bullet della slide 2 (visivo), non nel parlato
                if ($c['type'] === 'frame' && str_contains($c['text'], 'cacciavite')) {
                    return true;
                }
            }

            return false;
        });
    }

    public function test_manca_slide_timings_errore_niente_chiamata(): void
    {
        Http::fake();
        $video = $this->video([], [['slide_number' => 1, 'text' => 'x']]); // niente slide_timings

        try {
            (new VideoIndexService())->indexGenerated($video);
            $this->fail('Attesa eccezione.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('timings', strtolower($e->getMessage()));
        }
        Http::assertNothingSent();
        $this->assertNull($video->refresh()->indexed_at);
    }

    public function test_videoai_irraggiungibile_non_segna_indicizzato(): void
    {
        Http::fake(['*/index_chunks' => Http::response('boom', 500)]);
        $video = $this->okScenario();

        $this->expectException(\RuntimeException::class);
        try {
            (new VideoIndexService())->indexGenerated($video);
        } finally {
            $video->refresh();
            $this->assertNull($video->indexed_at, 'nessuno stato falso "indicizzato"');
            $this->assertNull($video->video_ai_id);
        }
    }

    public function test_costo_zero_solo_endpoint_index_chunks(): void
    {
        Http::fake(['*/index_chunks' => Http::response(['indexed_chunks' => 4], 200)]);
        $video = $this->okScenario();
        (new VideoIndexService())->indexGenerated($video);

        // Nessuna chiamata a provider a pagamento (Whisper/Vision/TTS): solo index_chunks.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/index_chunks'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'anthropic.com')
            || str_contains($r->url(), 'elevenlabs.io')
            || str_contains($r->url(), 'groq.com')
            || str_contains($r->url(), '/api/videos/ingest'));
    }
}
