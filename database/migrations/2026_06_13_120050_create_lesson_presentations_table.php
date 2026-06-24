<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fase 3 — presentazione della lezione (file generato in P19). Storage privato,
// servito solo da controller. status segue il ciclo di generazione per il
// Feedback UX. Qui solo lo schema: la generazione è P19.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_presentations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->string('file_path')->nullable();              // storage/app/private/...
            $table->string('status')->default('pending');         // pending|generating|ready|failed
            $table->json('generation_meta')->nullable();
            $table->timestamps();
            $table->index('lesson_id');
        });

        DB::statement("ALTER TABLE lesson_presentations ADD CONSTRAINT lesson_presentations_status_check
            CHECK (status IN ('pending', 'generating', 'ready', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_presentations');
    }
};
