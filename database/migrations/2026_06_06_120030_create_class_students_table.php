<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Iscrizione studente↔classe con stato (pivot con dati).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_students', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('status')->default('pending');   // pending | active | removed
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['school_class_id', 'student_id']);
        });
        DB::statement("ALTER TABLE class_students ADD CONSTRAINT class_students_status_check
            CHECK (status IN ('pending', 'active', 'removed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('class_students');
    }
};
