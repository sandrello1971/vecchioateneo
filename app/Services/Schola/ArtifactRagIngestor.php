<?php

namespace App\Services\Schola;

use App\Jobs\EmbedDocumentChunksJob;
use App\Models\ArtifactPublication;
use App\Models\DocumentRag;
use App\Models\TeachingArtifact;
use App\Services\EmbeddingService;
use App\Support\PgVector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ingestion RAG degli artefatti Schola in documents_rag.
 *
 * Due scope:
 *  - teacher_private: alla creazione di OGNI artefatto (la Minerva del docente
 *    vede anche il non pubblicato);
 *  - class: alla pubblicazione su una classe (i chunk si AGGIUNGONO ai
 *    teacher_private). Rimossi al ritiro.
 *
 * CHUNKING SCHOLA: target ~420 caratteri (cap 480), allineato alla finestra di
 * 128 token del modello di embedding (i chunk da ~1100 char venivano troncati
 * al 40%). Per le trascrizioni con segments, i chunk seguono i segment così da
 * portare il minutaggio (start/end) nel metadata per le citazioni.
 */
class ArtifactRagIngestor
{
    public function __construct(
        private EmbeddingService $embeddings,
        private RagChunker $chunker,
    ) {}

    // ===== teacher_private =====

    /**
     * (Re)indicizza un artefatto come teacher_private. Idempotente: rimuove i
     * chunk teacher_private esistenti di questo artefatto prima di ricrearli.
     */
    public function ingestTeacherPrivate(TeachingArtifact $artifact): int
    {
        DocumentRag::query()
            ->where('scope', 'teacher_private')
            ->where('teacher_id', $artifact->teacher_id)
            ->where('metadata->artifact_id', $artifact->id)
            ->delete();

        $chunks = $this->buildChunks($artifact);
        if (empty($chunks)) {
            return 0;
        }

        $rows = $this->persistChunks($artifact, $chunks, [
            'scope' => 'teacher_private',
            'school_class_id' => null,
            'teacher_id' => $artifact->teacher_id,
        ], []);

        $this->embedBestEffort($rows);

        return $rows->count();
    }

    // ===== class (pubblicazione) =====

    /**
     * Indicizza una pubblicazione come scope='class'. Idempotente sul
     * publication_id (ripubblicazione = sostituzione). Aggiorna rag_status.
     */
    public function ingestPublication(ArtifactPublication $publication): int
    {
        $publication->update(['rag_status' => 'indexing', 'rag_failure_reason' => null]);

        try {
            $artifact = $publication->artifact;
            if (!$artifact) {
                throw new \RuntimeException('Artefatto della pubblicazione non trovato.');
            }

            $this->purgePublication($publication->id);

            $chunks = $this->buildChunks($artifact);
            $rows = $this->persistChunks($artifact, $chunks, [
                'scope' => 'class',
                'school_class_id' => $publication->school_class_id,
                'teacher_id' => $artifact->teacher_id,
            ], ['publication_id' => $publication->id]);

            $this->embedBestEffort($rows);

            $publication->update(['rag_status' => 'ready', 'rag_failure_reason' => null]);

            return $rows->count();
        } catch (Throwable $e) {
            Log::warning('[schola] ingestion pubblicazione fallita', [
                'publication_id' => $publication->id,
                'error' => $e->getMessage(),
            ]);
            $publication->update(['rag_status' => 'failed', 'rag_failure_reason' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * Rimuove i chunk class di una pubblicazione (ritiro). Idempotente.
     */
    public function purgePublication(string $publicationId): int
    {
        return DocumentRag::query()
            ->where('scope', 'class')
            ->where('metadata->publication_id', $publicationId)
            ->delete();
    }

    // ===== teacher_shared (condivisione materiale tra docenti) =====

    /**
     * Indicizza il transcript di un materiale CONDIVISO come scope='teacher_shared'.
     * L'ambito (share_scope + subject_id + school_id) viaggia nel metadata: il
     * retrieval decide chi lo pesca. Idempotente sul documento (re-share sostituisce).
     */
    public function ingestTeacherShared(
        TeachingArtifact $transcript,
        string $documentId,
        string $shareScope,
        ?string $subjectId,
        ?string $sharedSchoolId
    ): int {
        $this->purgeTeacherShared($documentId);

        $chunks = $this->buildChunks($transcript);
        if (empty($chunks)) {
            return 0;
        }

        $rows = $this->persistChunks($transcript, $chunks, [
            'scope' => 'teacher_shared',
            'school_class_id' => null,
            'teacher_id' => $transcript->teacher_id,
        ], [
            'document_id' => $documentId,
            'share_scope' => $shareScope,
            'subject_id' => $subjectId,
            'school_id' => $sharedSchoolId,
        ]);

        $this->embedBestEffort($rows);

        return $rows->count();
    }

    /**
     * Rimuove i chunk teacher_shared di un materiale (unshare / re-share). Idempotente.
     */
    public function purgeTeacherShared(string $documentId): int
    {
        return DocumentRag::query()
            ->where('scope', 'teacher_shared')
            ->where('metadata->document_id', $documentId)
            ->delete();
    }

    // ===== Costruzione chunk =====

    /**
     * @return list<array{content: string, metadata: array}>
     */
    private function buildChunks(TeachingArtifact $artifact): array
    {
        // Trascrizione con segments → chunk allineati al minutaggio (citazioni).
        if ($artifact->type === 'transcript') {
            $doc = $artifact->teachingDocument;
            $segments = $doc?->extraction_meta['segments'] ?? null;
            if (is_array($segments) && !empty($segments)) {
                return $this->chunker->chunkSegments($segments, $this->sourceRef($doc));
            }
        }

        $text = $this->artifactToText($artifact);

        return array_map(
            fn (string $c) => ['content' => $c, 'metadata' => []],
            $this->chunker->chunkChars($text)
        );
    }

    /**
     * Rappresentazione testuale dell'artefatto per il RAG, per tipo.
     */
    private function artifactToText(TeachingArtifact $artifact): string
    {
        switch ($artifact->type) {
            case 'conceptmap':
                return $this->conceptMapToText($artifact->content);

            case 'quiz':
                return $this->quizToText($artifact);

            default: // transcript | summary | outline | mindmap
                return trim((string) $artifact->content);
        }
    }

    private function conceptMapToText(?string $content): string
    {
        $graph = json_decode((string) $content, true);
        if (!is_array($graph)) {
            return '';
        }

        $labels = [];
        $lines = [];
        foreach ($graph['nodes'] ?? [] as $n) {
            $id = $n['id'] ?? null;
            $label = trim((string) ($n['label'] ?? ''));
            if ($id !== null) {
                $labels[$id] = $label;
            }
            $desc = trim((string) ($n['description'] ?? ''));
            if ($label !== '') {
                $lines[] = $desc !== '' ? "{$label}: {$desc}" : $label;
            }
        }
        foreach ($graph['edges'] ?? [] as $e) {
            $from = $labels[$e['from'] ?? ''] ?? null;
            $to = $labels[$e['to'] ?? ''] ?? null;
            $rel = trim((string) ($e['label'] ?? ''));
            if ($from && $to) {
                $lines[] = "{$from} {$rel} {$to}";
            }
        }

        return implode(". ", $lines);
    }

    private function quizToText(TeachingArtifact $artifact): string
    {
        $quiz = $artifact->quiz()->with('questions')->first();
        if (!$quiz) {
            return '';
        }

        $lines = [];
        foreach ($quiz->questions as $q) {
            $line = 'Domanda: ' . $q->question;
            if ($q->correct_answer) {
                $line .= ' Risposta corretta: ' . $q->correct_answer;
            }
            if ($q->explanation) {
                $line .= ' ' . $q->explanation;
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Sorgente per le citazioni con minutaggio: url youtube oppure nome file.
     */
    private function sourceRef($doc): array
    {
        if (!$doc) {
            return [];
        }
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
    private function persistChunks(TeachingArtifact $artifact, array $chunks, array $scopeCols, array $extraMeta): Collection
    {
        $rows = collect();
        foreach ($chunks as $i => $chunk) {
            $rows->push(DocumentRag::create(array_merge($scopeCols, [
                'title' => $artifact->title,
                'content' => $chunk['content'],
                'chunk_index' => $i,
                'is_instructor_only' => false,
                'metadata' => array_merge([
                    'artifact_id' => $artifact->id,
                    'type' => $artifact->type,
                ], $extraMeta, $chunk['metadata']),
            ])));
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
            Log::warning('[schola] embedding ingestion fallito, accodo recupero', [
                'count' => $rows->count(),
                'error' => $e->getMessage(),
            ]);
            EmbedDocumentChunksJob::dispatch($rows->pluck('id')->all());
        }
    }
}
