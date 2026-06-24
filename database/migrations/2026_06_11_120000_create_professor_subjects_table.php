<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Competenze del docente: "cosa può insegnare" (materia), distinto dalla
// CATTEDRA (materia×classe, P15). Pivot teacher×subject×school.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professor_subjects', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['teacher_id', 'subject_id', 'school_id'], 'professor_subject_unique');
            $table->index(['school_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professor_subjects');
    }
};
