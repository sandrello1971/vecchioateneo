<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Artefatti lavorati a partire da un teaching_document.
// origin_artifact_id: lineage del fork (attribuzione) — volutamente uuid SENZA
// FK, così la cancellazione/modifica dell'originale non tocca i fork (SPEC §2.2/§6).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_artifacts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('teaching_document_id')->nullable()
                  ->constrained('teaching_documents')->cascadeOnDelete(); // nullable: i fork vivono senza doc grezzo (pacchetto 9)
            $table->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
            $table->string('type');            // transcript|summary|mindmap|conceptmap|quiz|outline
            $table->string('title');
            $table->longText('content')->nullable();
            $table->foreignUuid('quiz_id')->nullable()->constrained('quizzes')->nullOnDelete();
            $table->string('status')->default('ready');     // generating|ready|failed
            $table->json('generation_meta')->nullable();    // modello, token, prompt version
            $table->boolean('shared_with_teachers')->default(false);
            $table->uuid('origin_artifact_id')->nullable(); // lineage fork (no FK, attribuzione)
            $table->foreignUuid('subject_id')->nullable()
                  ->constrained('subjects')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['teacher_id', 'type']);
            $table->index(['shared_with_teachers', 'subject_id']);
            $table->index('origin_artifact_id');
        });

        DB::statement("ALTER TABLE teaching_artifacts ADD CONSTRAINT teaching_artifacts_type_check
            CHECK (type IN ('transcript', 'summary', 'mindmap', 'conceptmap', 'quiz', 'outline'))");
        DB::statement("ALTER TABLE teaching_artifacts ADD CONSTRAINT teaching_artifacts_status_check
            CHECK (status IN ('generating', 'ready', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_artifacts');
    }
};
