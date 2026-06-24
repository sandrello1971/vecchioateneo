<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tracciamento viste artefatto (analytics minime fetta 1) — sorgente segnale agente.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_artifact_views', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('artifact_publication_id')->constrained('artifact_publications')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('first_viewed_at');
            $table->timestamp('last_viewed_at');
            $table->integer('view_count')->default(1);
            $table->unique(['artifact_publication_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_artifact_views');
    }
};
