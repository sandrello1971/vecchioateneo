<?php

namespace App\Services\Schola;

/**
 * Chunking RAG condiviso (Schola). Estratto da ArtifactRagIngestor così che la
 * stessa logica serva artefatti (P6a) e lezioni (P20) senza duplicazioni.
 *
 * Target ~420 caratteri (cap 480), allineato alla finestra di 128 token del
 * modello di embedding. Per le trascrizioni con segments, i chunk seguono i
 * segment portando il minutaggio (start/end) nel metadata per le citazioni.
 */
class RagChunker
{
    public const TARGET_CHARS = 420;
    public const MAX_CHARS = 480;
    public const OVERLAP = 60;

    /**
     * Chunking per caratteri (~TARGET, overlap), cap a MAX_CHARS.
     *
     * @return list<string>
     */
    public function chunkChars(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $length = mb_strlen($text);
        if ($length <= self::MAX_CHARS) {
            return [$text];
        }

        $chunks = [];
        $start = 0;
        while ($start < $length) {
            $chunks[] = trim(mb_substr($text, $start, self::TARGET_CHARS));
            $start += self::TARGET_CHARS - self::OVERLAP;
        }

        return array_values(array_filter($chunks, fn ($c) => $c !== ''));
    }

    /**
     * Chunking di una trascrizione a partire dai segments: accumula segment
     * consecutivi fino a ~TARGET caratteri, portando start/end nel metadata.
     * Un segment troppo lungo viene spezzato mantenendo gli stessi timing.
     *
     * @param  array  $source  riferimento sorgente (es. ['source_url'=>...] o ['source_file'=>...])
     * @return list<array{content: string, metadata: array}>
     */
    public function chunkSegments(array $segments, array $source = []): array
    {
        $chunks = [];
        $buf = '';
        $bufStart = null;
        $bufEnd = null;

        $flush = function () use (&$chunks, &$buf, &$bufStart, &$bufEnd, $source) {
            $buf = trim($buf);
            if ($buf !== '') {
                $chunks[] = [
                    'content' => $buf,
                    'metadata' => array_merge([
                        'start_seconds' => $bufStart,
                        'end_seconds' => $bufEnd,
                    ], $source),
                ];
            }
            $buf = '';
            $bufStart = null;
            $bufEnd = null;
        };

        foreach ($segments as $seg) {
            $t = trim((string) ($seg['text'] ?? ''));
            if ($t === '') {
                continue;
            }
            $start = $seg['start_seconds'] ?? null;
            $end = $seg['end_seconds'] ?? $start;

            // Segment singolo troppo lungo: spezzalo, stessi timing.
            if (mb_strlen($t) > self::MAX_CHARS) {
                $flush();
                foreach ($this->chunkChars($t) as $piece) {
                    $chunks[] = [
                        'content' => $piece,
                        'metadata' => array_merge([
                            'start_seconds' => $start,
                            'end_seconds' => $end,
                        ], $source),
                    ];
                }
                continue;
            }

            if ($buf !== '' && mb_strlen($buf) + 1 + mb_strlen($t) > self::TARGET_CHARS) {
                $flush();
            }
            if ($buf === '') {
                $bufStart = $start;
            }
            $buf = $buf === '' ? $t : $buf . ' ' . $t;
            $bufEnd = $end;
        }
        $flush();

        return $chunks;
    }
}
