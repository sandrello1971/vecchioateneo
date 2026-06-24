<?php

namespace Tests\Feature\Schola;

use App\Models\DocumentRag;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    use RefreshDatabase;

    private const DIM = 768;

    private function fakeEmbeddings(int $dim = self::DIM): void
    {
        Http::fake(['*/api/embeddings' => function ($request) use ($dim) {
            $texts = $request->data()['texts'] ?? [];
            return Http::response([
                'embeddings' => array_map(fn () => array_fill(0, $dim, 0.01), $texts),
                'model' => 'paraphrase-multilingual-mpnet-base-v2',
                'dimensions' => $dim,
            ], 200);
        }]);
    }

    public function test_embed_returns_one_vector_per_text(): void
    {
        $this->fakeEmbeddings();
        $svc = new EmbeddingService();

        $out = $svc->embed(['uno', 'due', 'tre']);

        $this->assertCount(3, $out);
        $this->assertCount(self::DIM, $out[0]);
    }

    public function test_embed_splits_into_batches(): void
    {
        config(['services.embeddings.batch' => 1]);
        $this->fakeEmbeddings();
        $svc = new EmbeddingService();

        $out = $svc->embed(['a', 'b', 'c']);

        $this->assertCount(3, $out);
        Http::assertSentCount(3); // un batch per testo
    }

    public function test_embed_throws_on_dimension_mismatch(): void
    {
        $this->fakeEmbeddings(384); // dim diversa da quella attesa (768)
        $svc = new EmbeddingService();

        $this->expectException(RuntimeException::class);
        $svc->embed(['x']);
    }

    public function test_embed_throws_when_service_fails(): void
    {
        Http::fake(['*/api/embeddings' => Http::response('boom', 500)]);
        $svc = new EmbeddingService();

        $this->expectException(RuntimeException::class);
        $svc->embed(['x']);
    }

    public function test_empty_input_returns_empty_without_call(): void
    {
        Http::fake();
        $svc = new EmbeddingService();

        $this->assertSame([], $svc->embed([]));
        Http::assertNothingSent();
    }

    // ===== Comando backfill =====

    public function test_backfill_command_embeds_pending_chunks(): void
    {
        $this->fakeEmbeddings();

        DocumentRag::create(['title' => 'A', 'content' => 'alpha', 'chunk_index' => 0]);
        DocumentRag::create(['title' => 'B', 'content' => 'beta', 'chunk_index' => 0]);
        // azzera eventuali embedding scritti da embed-on-create per partire "pending"
        DB::statement('UPDATE documents_rag SET embedding = NULL');

        $this->assertSame(2, (int) DB::table('documents_rag')->whereNull('embedding')->count());

        $this->artisan('schola:backfill-embeddings')->assertExitCode(0);

        $this->assertSame(0, (int) DB::table('documents_rag')->whereNull('embedding')->count());
    }

    public function test_backfill_dry_run_changes_nothing(): void
    {
        $this->fakeEmbeddings();
        DocumentRag::create(['title' => 'A', 'content' => 'alpha', 'chunk_index' => 0]);
        DB::statement('UPDATE documents_rag SET embedding = NULL');

        $this->artisan('schola:backfill-embeddings --dry-run')->assertExitCode(0);

        $this->assertSame(1, (int) DB::table('documents_rag')->whereNull('embedding')->count());
    }
}
