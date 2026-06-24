<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.2 — Affermazioni databili estratte (Fase 1) e verificate (Fase 2).
//
// È la FONDAZIONE di P25.3: i claim con verdict 'obsoleto' diventeranno le proposte
// HITL. Qui NON c'è il diff/after né l'approvazione: solo estrazione + verifica.
// La posizione (course_id, block_id, sentence_ref) è l'ancora per il diff chirurgico
// futuro: sentence_ref è calcolato deterministicamente, non dedotto dall'LLM.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freshness_claims', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('run_id')->constrained('freshness_runs')->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();

            // Fase 1 — posizione + contenuto databile (il futuro "before").
            $table->string('block_id', 64);
            $table->integer('sentence_ref')->nullable();
            $table->text('claim_text');
            $table->string('category', 20); // model | norma | data | prezzo | prodotto

            // Fase 2 — esito verifica (nullable finché non verificato).
            $table->string('verdict', 12)->nullable(); // attuale | obsoleto | incerto
            $table->text('source_url')->nullable();
            $table->string('source_type', 12)->nullable(); // primary | web
            $table->date('source_date')->nullable();
            $table->decimal('confidence', 4, 3)->nullable(); // 0.000–1.000
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->index('run_id');
            $table->index(['course_id', 'verdict']);
        });

        DB::statement("ALTER TABLE freshness_claims ADD CONSTRAINT freshness_claims_category_check
            CHECK (category IN ('model', 'norma', 'data', 'prezzo', 'prodotto'))");
        DB::statement("ALTER TABLE freshness_claims ADD CONSTRAINT freshness_claims_verdict_check
            CHECK (verdict IS NULL OR verdict IN ('attuale', 'obsoleto', 'incerto'))");
        DB::statement("ALTER TABLE freshness_claims ADD CONSTRAINT freshness_claims_source_type_check
            CHECK (source_type IS NULL OR source_type IN ('primary', 'web'))");
        DB::statement("ALTER TABLE freshness_claims ADD CONSTRAINT freshness_claims_confidence_check
            CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('freshness_claims');
    }
};
