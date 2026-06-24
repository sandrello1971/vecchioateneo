<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Classe scolastica creata dal docente (teacher_id = students.id con role professor).
// school_id nullable: predisposizione fase 2 (ente scuola), per ora sempre NULL.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('school_id')->nullable();
            $table->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
            $table->string('name');                                   // "3ªB"
            $table->foreignUuid('subject_id')->constrained('subjects'); // §8: normalizzato
            $table->string('school_year', 9);                         // "2026/2027"
            $table->string('invite_code', 8)->unique();
            $table->boolean('invite_enabled')->default(true);
            $table->boolean('requires_approval')->default(true);      // default prudente (minori)
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['teacher_id', 'school_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
