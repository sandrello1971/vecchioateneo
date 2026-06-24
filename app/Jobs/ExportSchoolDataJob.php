<?php

namespace App\Jobs;

use App\Models\School;
use App\Services\Schola\SchoolDataExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

// Genera l'export dati di UNA scuola in storage privato (scaricabile dalla sua
// segreteria). Asincrono: feedback UX via presenza del file in /scuola/privacy.
class ExportSchoolDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $schoolId) {}

    public static function path(string $schoolId): string
    {
        return 'school-exports/' . $schoolId . '/export.json';
    }

    public function handle(SchoolDataExportService $service): void
    {
        $school = School::find($this->schoolId);
        if (!$school) {
            return;
        }

        $data = $service->generate($school);

        Storage::disk('local')->put(
            self::path($school->id),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
