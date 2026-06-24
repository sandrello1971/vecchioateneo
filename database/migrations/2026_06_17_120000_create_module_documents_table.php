<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P29 Fase 1 — documento PDF generato per un MODULO di corso Officina.
 *
 * Tabella PARALLELA e additiva, gemella di module_presentations (P28) ma con
 * STALE-DETECTION: content_hash = hash del module.content al momento della
 * generazione (come mindmap_content_hash sui moduli). isStale() lo confronta
 * con l'hash corrente per marcare il documento obsoleto SENZA rigenerarlo.
 *
 * Renderer = CourseSourcePdfBuilder (TCPDF, brand GLITCH di piattaforma).
 * Sorgente = SOLO module.content (i materials caricati restano fuori).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('file_path')->nullable();        // storage/app/private/module-documents/...
            $table->string('status')->default('pending');   // pending|generating|ready|failed
            $table->string('content_hash')->nullable();      // hash del content alla generazione (stale-detection)
            $table->json('generation_meta')->nullable();
            $table->timestamps();

            $table->unique('module_id'); // un documento per modulo (firstOrCreate)
        });

        DB::statement("ALTER TABLE module_documents ADD CONSTRAINT module_documents_status_check
            CHECK (status IN ('pending','generating','ready','failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('module_documents');
    }
};
