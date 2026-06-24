<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 3 — artefatti a livello di lezione (es. presentazione/outline della
// lezione composta in P19). NULL = artefatto non legato a una lezione.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_artifacts', function (Blueprint $table) {
            $table->foreignUuid('lesson_id')->nullable()->after('teacher_id')
                  ->constrained('lessons')->nullOnDelete();
            $table->index('lesson_id');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_artifacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lesson_id');
        });
    }
};
