<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fase 3 (P20b) — note del DOCENTE per paragrafo di una lezione. A differenza
// degli appunti personali dello studente (student_lesson_notes, PRIVATI), queste
// sono didattiche e VISIBILI a tutti gli studenti delle classi dove la lezione è
// pubblicata. anchor = id del paragrafo (p-001…) da NoteAnchorInjector.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_teacher_notes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
            $table->string('anchor', 50);
            $table->text('content');
            $table->timestamps();
            $table->unique(['lesson_id', 'anchor']); // una nota docente per paragrafo
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_teacher_notes');
    }
};
