<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Domande fuori KB (retrieval sotto soglia) → segnale per il docente / agente.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unanswered_questions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignUuid('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->text('question');
            $table->float('best_similarity')->nullable();   // miglior score sotto soglia (diagnostica)
            $table->string('status')->default('open');      // open|addressed|dismissed
            $table->timestamps();
            $table->index(['school_class_id', 'status']);
        });
        DB::statement("ALTER TABLE unanswered_questions ADD CONSTRAINT unanswered_questions_status_check
            CHECK (status IN ('open', 'addressed', 'dismissed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('unanswered_questions');
    }
};
