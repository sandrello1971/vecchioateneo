<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Cattedre: professore × materia × classe × anno. Sostituiscono l'ownership
// fetta 1 (school_classes.teacher_id) come criterio di accesso/pubblicazione
// per i docenti di scuola.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('school_class_id')->constrained()->cascadeOnDelete();
            $table->string('school_year', 9);
            $table->timestamps();
            $table->unique(['teacher_id', 'subject_id', 'school_class_id', 'school_year'], 'cattedra_unique');
            $table->index(['school_class_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
    }
};
