<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fondazione per l'agente proattivo (non ancora usata in fetta 1).
// recipient_id = students.id (docente o studente, distinti da recipient_type).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('recipient_type');                 // teacher | student
            $table->foreignUuid('recipient_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('school_class_id')->nullable()
                  ->constrained('school_classes')->nullOnDelete();
            $table->string('type');     // review_quiz|class_gap|missing_material|reentry_summary|...
            $table->string('title');
            $table->text('body');
            $table->json('payload')->nullable();
            $table->string('status')->default('proposed');    // proposed|approved|sent|dismissed|expired
            $table->string('source')->nullable();             // regola/job che l'ha generata
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_type', 'recipient_id', 'status']);
        });

        DB::statement("ALTER TABLE agent_suggestions ADD CONSTRAINT agent_suggestions_recipient_type_check
            CHECK (recipient_type IN ('teacher', 'student'))");
        DB::statement("ALTER TABLE agent_suggestions ADD CONSTRAINT agent_suggestions_status_check
            CHECK (status IN ('proposed', 'approved', 'sent', 'dismissed', 'expired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_suggestions');
    }
};
