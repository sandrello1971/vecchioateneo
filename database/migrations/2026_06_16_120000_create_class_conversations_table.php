<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fase 3 (P22) — messaggistica didattica di CLASSE. Rispecchia `conversations`
// del mondo corsi (1:1 studente↔formatore) ma legata a una CLASSE e al docente
// (cattedra/proprietà), in tabella DEDICATA: i corsi restano invariati.
// Un solo thread per coppia (studente, docente) nella stessa classe.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
            $table->string('subject', 200);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_class_id', 'student_id', 'teacher_id'], 'class_conversation_pair_unique');
            $table->index(['student_id', 'deleted_at', 'last_message_at']);
            $table->index(['teacher_id', 'deleted_at', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_conversations');
    }
};
