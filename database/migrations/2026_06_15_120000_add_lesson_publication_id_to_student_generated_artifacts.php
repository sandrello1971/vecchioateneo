<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fase 3 (P20c) — auto-generazione studente ANCHE da una lezione pubblicata, non
// solo da un artefatto. Binding doppio: uno fra artifact_publication_id e
// lesson_publication_id è valorizzato (CHECK). Additiva: le righe esistenti
// (tutte con artifact_publication_id) restano valide.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_generated_artifacts', function (Blueprint $table) {
            $table->foreignUuid('lesson_publication_id')->nullable()->after('artifact_publication_id')
                  ->constrained('lesson_publications')->cascadeOnDelete();
            $table->index('lesson_publication_id');
        });

        // L'artefatto non è più obbligatorio (può essere lesson-bound).
        DB::statement('ALTER TABLE student_generated_artifacts ALTER COLUMN artifact_publication_id DROP NOT NULL');

        // Esattamente UNA sorgente valorizzata.
        DB::statement('ALTER TABLE student_generated_artifacts ADD CONSTRAINT student_generated_artifacts_one_source_check
            CHECK ((artifact_publication_id IS NOT NULL)::int + (lesson_publication_id IS NOT NULL)::int = 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE student_generated_artifacts DROP CONSTRAINT IF EXISTS student_generated_artifacts_one_source_check');
        Schema::table('student_generated_artifacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lesson_publication_id');
        });
        // NB: non ripristiniamo il NOT NULL su artifact_publication_id (down best-effort).
    }
};
