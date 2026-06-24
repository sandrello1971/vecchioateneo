<?php

namespace App\Console\Commands;

use App\Models\InstructorNote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeDeletedNotes extends Command
{
    protected $signature = 'atheneum:purge-deleted-notes
        {--days=30 : Età in giorni oltre cui purgare}
        {--dry-run : Solo report, no delete}';

    protected $description = 'Cancella permanentemente note nel cestino oltre N giorni';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $notes = InstructorNote::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->with('images')
            ->get();

        $this->info("Trovate {$notes->count()} note da purgare (>$days giorni)");

        if ($this->option('dry-run')) {
            $this->warn('Dry run, nessuna modifica');
            return self::SUCCESS;
        }

        $imageCount = 0;
        foreach ($notes as $note) {
            foreach ($note->images as $img) {
                if (Storage::disk('public')->exists($img->file_path)) {
                    Storage::disk('public')->delete($img->file_path);
                    $imageCount++;
                }
            }
            $note->forceDelete();
        }

        $this->info("Purgate {$notes->count()} note e $imageCount immagini.");
        return self::SUCCESS;
    }
}
