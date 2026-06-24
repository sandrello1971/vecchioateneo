<?php

namespace Tests\Feature\Schola;

use App\Jobs\EmbedDocumentChunksJob;
use App\Models\DocumentRag;
use App\Models\Student;
use App\Services\EmbeddingService;
use App\Services\RagService;
use App\Support\PgVector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RagVectorTest extends TestCase
{
    use RefreshDatabase;

    private const DIM = 768;

    protected function setUp(): void
    {
        parent::setUp();
        // Soglia di similarità deterministica per i test.
        atheneum_setting_put('schola.rag_min_similarity', 0.5);
    }

    /** Vettore unità con 1.0 alla posizione $i (768 dim). */
    private function unit(int $i): array
    {
        $v = array_fill(0, self::DIM, 0.0);
        $v[$i] = 1.0;

        return $v;
    }

    private function prof(): Student
    {
        return Student::create([
            'name' => 'Prof', 'email' => 'prof+' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'professor',
            'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function chunk(array $attrs, ?array $vector = null): DocumentRag
    {
        $row = DocumentRag::create(array_merge([
            'title' => 'Materiale', 'content' => 'contenuto', 'chunk_index' => 0,
        ], $attrs));

        if ($vector !== null) {
            DB::update('UPDATE documents_rag SET embedding = ?::vector WHERE id = ?', [
                PgVector::toLiteral($vector), $row->id,
            ]);
        }

        return $row;
    }

    private function ragWith(EmbeddingService $svc): RagService
    {
        $this->instance(EmbeddingService::class, $svc);

        return app(RagService::class);
    }

    // ===== Soglia (sopra/sotto) =====

    public function test_vector_retrieval_applies_similarity_threshold(): void
    {
        $prof = $this->prof();
        // A: identico alla query (cos=1.0) → sopra soglia 0.5
        $a = $this->chunk(['scope' => 'teacher_private', 'teacher_id' => $prof->id, 'title' => 'Alpha', 'content' => 'alpha'], $this->unit(0));
        // B: ortogonale alla query (cos=0.0) → sotto soglia
        $this->chunk(['scope' => 'teacher_private', 'teacher_id' => $prof->id, 'title' => 'Beta', 'content' => 'beta'], $this->unit(1));

        $svc = Mockery::mock(EmbeddingService::class);
        $svc->shouldReceive('embedOne')->once()->andReturn($this->unit(0));

        $results = $this->ragWith($svc)->searchClassScoped('domanda', [], $prof->id, 5);

        $this->assertCount(1, $results, 'Solo il chunk sopra soglia deve essere restituito');
        $this->assertSame($a->id, $results->first()->id);
    }

    public function test_vector_empty_when_all_below_threshold_is_the_gate(): void
    {
        $prof = $this->prof();
        $this->chunk(['scope' => 'teacher_private', 'teacher_id' => $prof->id, 'content' => 'beta'], $this->unit(1));

        $svc = Mockery::mock(EmbeddingService::class);
        $svc->shouldReceive('embedOne')->once()->andReturn($this->unit(0)); // ortogonale → nessuno passa

        $results = $this->ragWith($svc)->searchClassScoped('domanda', [], $prof->id, 5);

        // Gate §5: vuoto (NON fallback ILIKE) quando il vettoriale è praticabile.
        $this->assertCount(0, $results);
    }

    // ===== Fallback ILIKE quando il vettoriale non è praticabile =====

    public function test_falls_back_to_ilike_when_embedding_service_down(): void
    {
        $prof = $this->prof();
        // Chunk SENZA embedding ma con testo che match-a la query.
        $c = $this->chunk([
            'scope' => 'teacher_private', 'teacher_id' => $prof->id,
            'title' => 'Biologia', 'content' => 'La fotosintesi avviene nei cloroplasti',
        ], null);

        $svc = Mockery::mock(EmbeddingService::class);
        $svc->shouldReceive('embedOne')->once()->andThrow(new RuntimeException('videoai down'));

        $results = $this->ragWith($svc)->searchClassScoped('fotosintesi', [], $prof->id, 5);

        $this->assertTrue($results->contains('id', $c->id), 'Con videoai giù si usa ILIKE');
    }

    // ===== Separazione dei mondi: flag corsi OFF (default) non usa il vettoriale =====

    public function test_corsi_search_ignores_vector_when_flag_off(): void
    {
        // Nessun setting → rag_vector_enabled_corsi default FALSE.
        $hit = $this->chunk(['scope' => 'platform', 'course_id' => null, 'title' => 'Corso', 'content' => 'regressione corsi'], $this->unit(0));

        // EmbeddingService NON deve essere mai invocato per il mondo corsi.
        $svc = Mockery::mock(EmbeddingService::class);
        $svc->shouldNotReceive('embedOne');
        $svc->shouldNotReceive('embed');

        $results = $this->ragWith($svc)->searchForUser('regressione', [], false, 5);

        $this->assertTrue($results->contains('id', $hit->id), 'Il mondo corsi resta su ILIKE');
    }

    public function test_corsi_vector_used_when_flag_on(): void
    {
        atheneum_setting_put('rag_vector_enabled_corsi', true);
        $a = $this->chunk(['scope' => 'platform', 'course_id' => null, 'title' => 'A', 'content' => 'alpha'], $this->unit(0));
        $this->chunk(['scope' => 'platform', 'course_id' => null, 'title' => 'B', 'content' => 'beta'], $this->unit(1));

        $svc = Mockery::mock(EmbeddingService::class);
        $svc->shouldReceive('embedOne')->once()->andReturn($this->unit(0));

        $results = $this->ragWith($svc)->searchForUser('q', [], false, 5);

        $this->assertCount(1, $results);
        $this->assertSame($a->id, $results->first()->id);
    }

    // ===== Embed-on-create + coda di recupero =====

    public function test_index_document_embeds_new_chunks_inline(): void
    {
        $svc = Mockery::mock(EmbeddingService::class);
        $svc->shouldReceive('embed')->once()->andReturnUsing(
            fn (array $texts) => array_map(fn () => $this->unit(0), $texts)
        );

        $this->ragWith($svc)->indexDocument('Testo breve da indicizzare.', 'Doc', null, null, null);

        $this->assertSame(0, (int) DB::table('documents_rag')->whereNull('embedding')->count());
    }

    public function test_index_document_queues_recovery_when_service_down(): void
    {
        Bus::fake();

        $svc = Mockery::mock(EmbeddingService::class);
        $svc->shouldReceive('embed')->once()->andThrow(new RuntimeException('videoai down'));

        $this->ragWith($svc)->indexDocument('Testo che non si riesce a embeddare.', 'Doc', null, null, null);

        // Chunk creati ma senza embedding + job di recupero accodato (ingestion non bloccata).
        $this->assertGreaterThan(0, DB::table('documents_rag')->count());
        $this->assertGreaterThan(0, DB::table('documents_rag')->whereNull('embedding')->count());
        Bus::assertDispatched(EmbedDocumentChunksJob::class);
    }

    // ===== Migrazione sicura senza estensione =====

    public function test_pgvector_guard_false_on_non_pgvector_connection(): void
    {
        // Connessione sqlite in memoria: niente pgvector → la migrazione salterebbe il DDL.
        config(['database.connections.sqlite_probe' => ['driver' => 'sqlite', 'database' => ':memory:']]);

        $this->assertFalse(PgVector::available('sqlite_probe'), 'Senza pgsql/estensione il guard è false (migrazione skip)');
        $this->assertTrue(PgVector::available(), 'Sul DB di test (pgvector creato) il guard è true');
    }

    public function test_embedding_column_and_hnsw_index_exist_on_test_db(): void
    {
        $col = DB::selectOne("SELECT format_type(atttypid, atttypmod) AS t FROM pg_attribute WHERE attrelid='documents_rag'::regclass AND attname='embedding'");
        $this->assertNotNull($col);
        $this->assertSame('vector(768)', $col->t);

        $idx = DB::selectOne("SELECT indexdef FROM pg_indexes WHERE tablename='documents_rag' AND indexname='documents_rag_embedding_idx'");
        $this->assertNotNull($idx);
        $this->assertStringContainsString('hnsw', strtolower($idx->indexdef));
        $this->assertStringContainsString('vector_cosine_ops', strtolower($idx->indexdef));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
