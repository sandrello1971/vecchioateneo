<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fase 3 (P20b) — appunti PERSONALI dello studente per paragrafo di una lezione.
// Tabella DEDICATA (separata da student_notes del mondo corsi: nessun impatto su
// quella). anchor = id del paragrafo (p-001…) iniettato da NoteAnchorInjector;
// anchor NULL = nota generale di lezione.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_lesson_notes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->string('anchor', 50)->nullable();
            $table->text('content');
            $table->timestamps();
            $table->index(['student_id', 'lesson_id']);
        });

        // Una sola nota generale per (studente, lezione) quando anchor IS NULL;
        // una sola nota per (studente, lezione, anchor) quando anchor IS NOT NULL.
        DB::statement('CREATE UNIQUE INDEX student_lesson_notes_general_uniq ON student_lesson_notes (student_id, lesson_id) WHERE anchor IS NULL');
        DB::statement('CREATE UNIQUE INDEX student_lesson_notes_anchored_uniq ON student_lesson_notes (student_id, lesson_id, anchor) WHERE anchor IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('student_lesson_notes');
    }
};
