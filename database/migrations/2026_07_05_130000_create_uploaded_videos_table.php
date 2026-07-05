<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Video CARICATI dal docente (non generati): inviati a noscite-videoai per l'analisi
// COMPLETA (trascrizione + frame + Vision) → riproducibili + ricercabili al loro interno
// + testo nel RAG (Minerva). Contesto lezione (lesson_id) o materiale generico (subject_id).
// Una lezione può avere PIÙ video (nessun vincolo unique su lesson_id).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploaded_videos', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->foreignUuid('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignUuid('school_id')->nullable()->constrained('schools')->nullOnDelete();
            // Trascrizione/analisi indicizzata come artefatto per la Minerva (RAG).
            $table->foreignUuid('artifact_id')->nullable()->constrained('teaching_artifacts')->nullOnDelete();

            $table->string('title');
            $table->string('source_filename')->nullable();
            $table->string('file_path')->nullable();               // mp4 locale (player)
            $table->string('status')->default('pending');          // pending|processing|ready|failed
            $table->text('failure_reason')->nullable();
            $table->string('video_ai_id')->nullable();             // id collection videoai (ricerca)
            $table->timestamp('indexed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamp('published_at')->nullable();         // NULL = bozza
            $table->json('meta')->nullable();                      // progress, ecc.
            $table->timestamps();
            $table->softDeletes();

            $table->index('lesson_id');
            $table->index('teacher_id');
            $table->index(['subject_id', 'school_id']);
        });

        DB::statement("ALTER TABLE uploaded_videos ADD CONSTRAINT uploaded_videos_status_check
            CHECK (status IN ('pending','processing','ready','failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_videos');
    }
};
