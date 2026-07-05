<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class VideoAIService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.videoai.url');
    }

    /**
     * Client HTTP pre-configurato con l'header di autenticazione interna. OGNI
     * chiamata a videoai passa da qui, così il token è inviato sempre (anche se
     * videoai per ora lo ignora: rollout sicuro in due fasi).
     */
    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'X-Internal-Token' => (string) config('services.videoai.token'),
        ]);
    }

    public function ingestVideo(string $filePath, string $filename): array
    {
        $response = $this->client()->timeout(300)
            ->attach('file', file_get_contents($filePath), $filename)
            ->post("{$this->baseUrl}/api/videos/ingest");

        if ($response->failed()) {
            throw new \Exception('VideoAI ingest failed: ' . $response->body());
        }

        return $response->json();
    }

    public function getStatus(string $videoId): array
    {
        $response = $this->client()->timeout(10)
            ->get("{$this->baseUrl}/api/videos/{$videoId}/status");

        if ($response->failed()) {
            return ['status' => 'error', 'progress' => 0, 'can_chat' => false];
        }

        return $response->json();
    }

    public function chat(string $videoId, string $question, array $history = []): array
    {
        $response = $this->client()->timeout(60)
            ->post("{$this->baseUrl}/api/videos/{$videoId}/chat", [
                'question' => $question,
                'history' => $history,
            ]);

        if ($response->failed()) {
            throw new \Exception('VideoAI chat failed: ' . $response->body());
        }

        return $response->json();
    }

    public function getTranscript(string $videoId): array
    {
        $response = $this->client()->timeout(30)
            ->get("{$this->baseUrl}/api/videos/{$videoId}/transcript");

        if ($response->failed()) return ['segments' => []];
        return $response->json();
    }

    /**
     * Schola — chunk testuali (parlato + descrizioni visive Vision) di un video
     * caricato, per alimentare il RAG della Minerva. Ritorna la lista di chunk
     * {text,type,start,timestamp_str}. Errore → lista vuota (best-effort).
     * @return array<int, array{text: string, type: string, start: float, timestamp_str: string}>
     */
    public function getChunksText(string $videoId): array
    {
        $response = $this->client()->timeout(30)
            ->get("{$this->baseUrl}/api/videos/{$videoId}/chunks_text");

        if ($response->failed()) return [];

        return (array) ($response->json('chunks') ?? []);
    }

    public function getThumbnailUrl(string $videoId): string
    {
        return "{$this->baseUrl}/api/videos/{$videoId}/thumbnail";
    }

    public function getStreamUrl(string $videoId): string
    {
        return "/learn/video/{$videoId}/stream";
    }

    public function deleteVideo(string $videoId): bool
    {
        $response = $this->client()->timeout(30)
            ->delete("{$this->baseUrl}/api/videos/{$videoId}");
        return $response->successful();
    }

    public function search(string $query, array $videoIds): array
    {
        if (empty($videoIds)) return [];

        $response = $this->client()->timeout(15)
            ->post("{$this->baseUrl}/api/search", [
                'question' => $query,
                'video_ids' => array_values(array_unique($videoIds)),
            ]);

        if ($response->failed()) return [];
        return $response->json() ?? [];
    }

    // ===== Schola: trascrizione audio puro e YouTube (pacchetto 4a/4b) =====
    // Endpoint Python aggiunti nel pacchetto 4b. Qui il client: POST → {job_id},
    // poi polling fino a status completed/failed. Ritorna l'array risultato.

    public function transcribeAudio(string $filePath, string $filename): array
    {
        $response = $this->client()->timeout(120)
            ->attach('file', file_get_contents($filePath), $filename)
            ->post("{$this->baseUrl}/api/audio/transcribe");

        if ($response->failed()) {
            throw new \RuntimeException('VideoAI audio transcribe failed: ' . $response->status());
        }

        $jobId = $response->json('job_id');
        if (!$jobId) {
            throw new \RuntimeException('VideoAI audio: job_id mancante nella risposta');
        }

        return $this->pollTranscription("/api/audio/{$jobId}");
    }

    public function transcribeYouTube(string $url): array
    {
        $response = $this->client()->timeout(60)
            ->post("{$this->baseUrl}/api/youtube/transcribe", ['url' => $url]);

        if ($response->failed()) {
            throw new \RuntimeException('VideoAI youtube transcribe failed: ' . $response->status());
        }

        $jobId = $response->json('job_id');
        if (!$jobId) {
            throw new \RuntimeException('VideoAI youtube: job_id mancante nella risposta');
        }

        return $this->pollTranscription("/api/youtube/{$jobId}");
    }

    /** Polling generico di un job di trascrizione finché completed/failed. */
    private function pollTranscription(string $statusPath): array
    {
        $interval = (int) config('services.videoai.poll_interval', 3);
        $maxAttempts = (int) config('services.videoai.poll_max_attempts', 200);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $res = $this->client()->timeout(15)->get("{$this->baseUrl}{$statusPath}");
            if ($res->failed()) {
                throw new \RuntimeException('VideoAI status check failed: ' . $res->status());
            }

            $data = $res->json();
            $status = $data['status'] ?? 'processing';

            if (in_array($status, ['completed', 'ready', 'done'], true)) {
                return $data;
            }
            if (in_array($status, ['failed', 'error'], true)) {
                throw new \RuntimeException('VideoAI trascrizione fallita: ' . ($data['reason'] ?? 'motivo non specificato'));
            }

            sleep($interval);
        }

        throw new \RuntimeException('VideoAI trascrizione: timeout di polling superato');
    }
}
