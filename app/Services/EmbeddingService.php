<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client HTTP verso l'endpoint embeddings di noscite-videoai (/api/embeddings).
 * Stesso pattern di VideoAIService. Le dimensioni attese arrivano da config e
 * DEVONO combaciare con la colonna vector(D) di documents_rag.
 */
class EmbeddingService
{
    private string $baseUrl;
    private int $dimensions;
    private int $batch;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.embeddings.url'), '/');
        $this->dimensions = (int) config('services.embeddings.dimensions', 768);
        $this->batch = max(1, (int) config('services.embeddings.batch', 128));
        $this->timeout = (int) config('services.embeddings.timeout', 60);
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * Embedda una lista di testi. Suddivide automaticamente in batch secondo
     * services.embeddings.batch. Ritorna una lista di vettori (float[]) nello
     * stesso ordine dei testi in input. Lancia RuntimeException su errore o
     * dimensione inattesa (mai vettori parziali silenziosi).
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $vectors = [];
        foreach (array_chunk($texts, $this->batch) as $chunk) {
            foreach ($this->embedBatch(array_values($chunk)) as $v) {
                $vectors[] = $v;
            }
        }

        return $vectors;
    }

    /**
     * Embedda un singolo testo. Comodo per la query di retrieval.
     *
     * @return list<float>
     */
    public function embedOne(string $text): array
    {
        $out = $this->embed([$text]);

        return $out[0] ?? throw new RuntimeException('Embedding non restituito dal servizio.');
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    private function embedBatch(array $texts): array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders(['X-Internal-Token' => (string) config('services.videoai.token')])
            ->acceptJson()
            ->post("{$this->baseUrl}/api/embeddings", ['texts' => $texts]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Servizio embeddings non disponibile (HTTP {$response->status()}): " . $response->body()
            );
        }

        $embeddings = $response->json('embeddings');
        if (!is_array($embeddings) || count($embeddings) !== count($texts)) {
            throw new RuntimeException('Risposta embeddings malformata: conteggio vettori inatteso.');
        }

        foreach ($embeddings as $v) {
            if (!is_array($v) || count($v) !== $this->dimensions) {
                throw new RuntimeException(
                    'Dimensione embedding inattesa: atteso ' . $this->dimensions . '.'
                );
            }
        }

        return $embeddings;
    }
}
