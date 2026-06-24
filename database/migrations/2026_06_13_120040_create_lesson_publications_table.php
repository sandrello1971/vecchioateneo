<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fase 3 — pubblicazione di una LEZIONE su una classe (gemella di
// artifact_publications, livello lezione). rag_status segue lo stesso ciclo
// dell'ingestion per il Feedback UX ("pubblicazione in corso" → polling → pronta).
// La generazione/pubblicazione vera è P20: qui solo lo schema.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_publications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignUuid('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->boolean('students_can_generate')->default(true);
            $table->string('rag_status')->default('pending');     // pending|indexing|ready|failed
            $table->text('rag_failure_reason')->nullable();
            $table->timestamp('published_at')->useCurrent();
            $table->timestamps();
            $table->unique(['lesson_id', 'school_class_id']);
            $table->index(['school_class_id', 'published_at']);
        });

        DB::statement("ALTER TABLE lesson_publications ADD CONSTRAINT lesson_publications_rag_status_check
            CHECK (rag_status IN ('pending', 'indexing', 'ready', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_publications');
    }
};
