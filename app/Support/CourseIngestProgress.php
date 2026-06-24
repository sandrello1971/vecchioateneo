<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class CourseIngestProgress
{
    private const TTL_SECONDS = 3600;
    private const KEY_PREFIX = 'course_ingest:';

    public static function init(string $jobId, int $totalStages = 5): void
    {
        Cache::put(self::KEY_PREFIX . $jobId, [
            'stage' => 0,
            'total_stages' => $totalStages,
            'message' => 'In coda...',
            'done' => false,
            'error' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], self::TTL_SECONDS);
    }

    public static function setStage(string $jobId, int $stage, string $message): void
    {
        $data = self::get($jobId) ?? [];
        $data['stage'] = $stage;
        $data['message'] = $message;
        $data['updated_at'] = now()->toIso8601String();
        Cache::put(self::KEY_PREFIX . $jobId, $data, self::TTL_SECONDS);
    }

    public static function setResult(string $jobId, array $result): void
    {
        $data = self::get($jobId) ?? [];
        $data['done'] = true;
        $data['result'] = $result;
        $data['updated_at'] = now()->toIso8601String();
        Cache::put(self::KEY_PREFIX . $jobId, $data, self::TTL_SECONDS);
    }

    public static function setError(string $jobId, string $message, int $failedAtStage = 0): void
    {
        $data = self::get($jobId) ?? [];
        $data['error'] = $message;
        $data['failed_at_stage'] = $failedAtStage;
        $data['done'] = true;
        $data['updated_at'] = now()->toIso8601String();
        Cache::put(self::KEY_PREFIX . $jobId, $data, self::TTL_SECONDS);
    }

    public static function get(string $jobId): ?array
    {
        return Cache::get(self::KEY_PREFIX . $jobId);
    }

    public static function forget(string $jobId): void
    {
        Cache::forget(self::KEY_PREFIX . $jobId);
    }
}
