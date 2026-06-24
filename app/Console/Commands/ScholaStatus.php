<?php

namespace App\Console\Commands;

use App\Models\ArtifactPublication;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\StudentGeneratedArtifact;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use App\Models\UnansweredQuestion;
use App\Support\PgVector;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Colpo d'occhio operativo del modulo Schola: monitoraggio e demo.
class ScholaStatus extends Command
{
    protected $signature = 'schola:status';
    protected $description = 'Conteggi operativi del modulo Schola (classi, studenti, documenti, pubblicazioni, embedding, domande, generazioni).';

    public function handle(): int
    {
        $this->info('=== Schola — stato operativo ===');

        $this->line(sprintf(
            'Classi: %d attive / %d archiviate · Professor: %d',
            SchoolClass::where('is_archived', false)->count(),
            SchoolClass::where('is_archived', true)->count(),
            \App\Models\Student::where('role', 'professor')->count(),
        ));

        $this->row('Studenti per stato', ClassStudent::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')->all());
        $this->row('Documenti per stato', TeachingDocument::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')->all());
        $this->row('Artefatti per tipo', TeachingArtifact::selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type')->all());

        $this->line('Pubblicazioni: ' . ArtifactPublication::count()
            . ' (rag ' . ArtifactPublication::selectRaw("rag_status, count(*) c")->groupBy('rag_status')->pluck('c', 'rag_status')->map(fn ($c, $s) => "$s=$c")->implode(', ') . ')');

        // Embedding pending (solo se la colonna esiste).
        if (PgVector::available() && Schema::hasColumn('documents_rag', 'embedding')) {
            $pending = (int) DB::table('documents_rag')->whereNull('embedding')->count();
            $total = (int) DB::table('documents_rag')->count();
            $this->line("Embedding: {$pending} pending su {$total} chunk" . ($pending ? '  ⚠ esegui schola:backfill-embeddings' : '  ✓'));
        } else {
            $this->line('Embedding: pgvector non disponibile su questo DB (retrieval ILIKE).');
        }

        $this->line('Domande scoperte aperte: ' . UnansweredQuestion::where('status', 'open')->count());

        $since = Carbon::now()->subDay();
        $genArt = TeachingArtifact::where('created_at', '>=', $since)->count();
        $genStu = StudentGeneratedArtifact::where('created_at', '>=', $since)->count();
        $this->line("Generazioni ultime 24h: {$genArt} artefatti docente + {$genStu} auto-generazioni studente");

        return self::SUCCESS;
    }

    private function row(string $label, array $counts): void
    {
        if (empty($counts)) {
            $this->line("$label: —");
            return;
        }
        $parts = [];
        foreach ($counts as $k => $v) {
            $parts[] = "$k=$v";
        }
        $this->line("$label: " . implode(', ', $parts));
    }
}
