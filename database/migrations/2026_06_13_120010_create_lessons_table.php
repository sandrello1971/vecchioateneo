<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fase 3 — Lezioni. Unità che lo studente fruisce (UX Officina: appunti per
// paragrafo, ricerca video, Minerva). Una lezione è composta da N materiali
// (teaching_documents) dai quali, in P19, il sistema genererà `content`.
// generation_status descrive lo stato di quella generazione (qui sempre 'draft').
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
            $table->string('title');
            $table->integer('position')->default(0);
            $table->longText('content')->nullable();        // corpo composto (P19)
            $table->string('generation_status')->default('draft'); // draft|generating|ready|failed
            $table->json('generation_meta')->nullable();    // modello, token, prompt version (P19)
            $table->timestamps();
            $table->softDeletes();
            $table->index(['topic_id', 'position']);
        });

        DB::statement("ALTER TABLE lessons ADD CONSTRAINT lessons_generation_status_check
            CHECK (generation_status IN ('draft', 'generating', 'ready', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
