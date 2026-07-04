<?php

namespace App\Services\Schola;

use App\Jobs\EmbedDocumentChunksJob;
use App\Models\DocumentRag;
use App\Models\Lesson;
use App\Models\LessonPublication;
use App\Services\EmbeddingService;
use App\Support\PgVector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ingestion RAG di una LEZIONE pubblicata su una classe (P20a), scope='class'
 * in documents_rag. Sorgenti del knowledge base di lezione:
 *  - il CORPO composto (lessons.content) → chunk testuali (type 'lesson');
 *  - i MATERIALI audio/video collegati con segments (P19) → chunk allineati al
 *    minutaggio (type 'transcript', start/end + source) per ricerca video/citazioni;
 *  - gli ARTEFATTI di lezione pronti (teaching_artifacts.lesson_id) → chunk testuali.
 *
 * Tutti i chunk portano metadata.lesson_id e metadata.publication_id, così che
 * il retrieval di classe (RagService::searchClassScoped) possa filtrare per
 * lezione (P20b) e il ritiro possa fare purge idempotente per pubblicazione.
 *
 * Vincolo §5: nessun chunk esce dallo scope 'class' della classe pubblicata.
 * Riusa RagChunker (nessuna logica di chunking duplicata).
 */
class LessonRagIngestor
{
    public function __construct(
        private EmbeddingService $embeddings,
        private RagChunker $chunker,
    ) {}

    /**
     * Indicizza una pubblicazione di lezione. Idempotente sul publication_id
     * (ripubblicazione = sostituzione). Aggiorna rag_status per il feedback UX.
     */
    public function ingestPublication(LessonPublication $publication): int
    {
        $publication->update(['rag_status' => 'indexing', 'rag_failure_reason' => null]);

        try {
            $lesson = $publication->lesson()->with('topic')->first();
            if (!$lesson) {
                throw new \RuntimeException('Lezione della pubblicazione non trovata.');
            }

            $this->purgePublication($publication->id);

            $chunks = $this->buildChunks($lesson);
            $rows = $this->persistChunks($lesson, $publication, $chunks);

            $this->embedBestEffort($rows);

            $publication->update(['rag_status' => 'ready', 'rag_failure_reason' => null]);

            return $rows->count();
        } catch (Throwable $e) {
            Log::warning('[schola] ingestion lezione fallita', [
                'publication_id' => $publication->id,
                'error' => $e->getMessage(),
            ]);
            $publication->update(['rag_status' => 'failed', 'rag_failure_reason' => $e->getMessage()]);

            return 0;
        }
    }

    /** Rimuove i chunk class di una pubblicazione di lezione (ritiro). Idempotente. */
    public function purgePublication(string $publicationId): int
    {
        return DocumentRag::query()
            ->where('scope', 'class')
            ->where('metadata->lesson_publication_id', $publicationId)
            ->delete();
    }

    // ===== Costruzione chunk =====

    /**
     * @return list<array{content: string, metadata: array}>
     */
    private function buildChunks(Lesson $lesson): array
    {
        $chunks = [];

        // 1) Corpo della lezione composta.
        foreach ($this->chunker->chunkChars((string) $lesson->content) as $c) {
            $chunks[] = ['content' => $c, 'metadata' => ['type' => 'lesson']];
        }

        // 2) Materiali audio/video collegati con segments → chunk con minutaggio.
        $materials = $lesson->teachingDocuments()
            ->where('status', 'ready')
            ->orderBy('created_at')
            ->get();
        foreach ($materials as $doc) {
            $segments = $doc->extraction_meta['segments'] ?? null;
            if (!is_array($segments) || empty($segments)) {
                continue; // il testo è già nel corpo; qui servono i timing per il video
            }
            $source = array_merge(['document_id' => $doc->id, 'type' => 'transcript'], $this->sourceRef($doc));
            foreach ($this->chunker->chunkSegments($segments, $source) as $chunk) {
                $chunks[] = $chunk;
            }
        }

        // 3) Artefatti di lezione pronti (riassunto/scaletta/mindmap/conceptmap/quiz testuali).
        $artifacts = $lesson->teachingArtifacts()
            ->where('status', 'ready')
            ->whereNotNull('content')
            ->get();
        foreach ($artifacts as $artifact) {
            foreach ($this->chunker->chunkChars((string) $artifact->content) as $c) {
                $chunks[] = ['content' => $c, 'metadata' => ['type' => $artifact->type, 'artifact_id' => $artifact->id]];
            }
        }

        return $chunks;
    }

    /** Sorgente per le citazioni con minutaggio: url youtube oppure nome file. */
    private function sourceRef($doc): array
    {
        if ($doc->source_url) {
            return ['source_url' => $doc->source_url];
        }
        $files = $doc->source_files ?? [];
        if (!empty($files)) {
            return ['source_file' => basename($files[0])];
        }

        return [];
    }

    // ===== Persistenza + embedding =====

    /**
     * @param  list<array{content: string, metadata: array}>  $chunks
     */
    private function persistChunks(Lesson $lesson, LessonPublication $publication, array $chunks): Collection
    {
        $rows = collect();
        foreach ($chunks as $i => $chunk) {
            $rows->push(DocumentRag::create([
                'scope' => 'class',
                'school_class_id' => $publication->school_class_id,
                'teacher_id' => $lesson->teacher_id,
                'subject_id' => $lesson->topic?->subject_id, // materia della lezione (via argomento)
                'title' => $lesson->title,
                'content' => $chunk['content'],
                'chunk_index' => $i,
                'is_instructor_only' => false,
                'metadata' => array_merge([
                    'lesson_id' => $lesson->id,
                    'lesson_publication_id' => $publication->id,
                ], $chunk['metadata']),
            ]));
        }

        return $rows;
    }

    /**
     * Embedding best-effort dei chunk creati; in caso di errore accoda il
     * recupero asincrono. Non solleva: l'ingestion non deve mai bloccarsi.
     */
    private function embedBestEffort(Collection $rows): void
    {
        if ($rows->isEmpty() || !PgVector::available()) {
            return;
        }

        try {
            $vectors = $this->embeddings->embed($rows->map(fn ($r) => (string) $r->content)->all());
            DB::transaction(function () use ($rows, $vectors) {
                foreach ($rows->values() as $i => $row) {
                    DB::update(
                        'UPDATE documents_rag SET embedding = ?::vector WHERE id = ?',
                        [PgVector::toLiteral($vectors[$i]), $row->id]
                    );
                }
            });
        } catch (Throwable $e) {
            Log::warning('[schola] embedding ingestion lezione fallito, accodo recupero', [
                'count' => $rows->count(),
                'error' => $e->getMessage(),
            ]);
            EmbedDocumentChunksJob::dispatch($rows->pluck('id')->all());
        }
    }
}
