<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Artefatti auto-generati dallo studente a partire da un artefatto PUBBLICATO
// (mindmap o quiz di autoverifica). Tracciati: il docente vede tutto (§8.1).
// status/failure_reason per il feedback UX (generazione asincrona ~60-90s).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_generated_artifacts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('artifact_publication_id')->constrained('artifact_publications')->cascadeOnDelete();
            $table->string('type');                       // mindmap | quiz
            $table->longText('content')->nullable();      // markdown (mindmap); null per quiz (vive su quizzes)
            $table->foreignUuid('quiz_id')->nullable()->constrained('quizzes')->nullOnDelete();
            $table->string('status')->default('generating'); // generating | ready | failed
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'created_at']);
            $table->index('artifact_publication_id');
        });

        DB::statement("ALTER TABLE student_generated_artifacts ADD CONSTRAINT student_generated_artifacts_type_check
            CHECK (type IN ('mindmap', 'quiz'))");
        DB::statement("ALTER TABLE student_generated_artifacts ADD CONSTRAINT student_generated_artifacts_status_check
            CHECK (status IN ('generating', 'ready', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('student_generated_artifacts');
    }
};
