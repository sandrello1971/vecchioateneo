<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P26 Fase A — Gap di copertura candidati prodotti dallo Scout: "argomenti emergenti non
// coperti dal corso", ciascuno con la fonte (approvata) da cui emerge e una confidenza. Sono
// SUGGERIMENTI rumorosi: l'admin scarta/accetta (HITL). Nessuna generazione/inserimento qui.
// Tabella nuova, additiva. Scrive SOLO qui (legge i course_sources, mai corsi/moduli/studenti).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverage_gaps', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('topic');        // snapshot del dominio analizzato

            $table->string('title');        // titolo breve dell'argomento mancante
            $table->text('rationale');      // perché è rilevante
            $table->string('source_url')->nullable();   // citazione/URL da cui emerge
            $table->string('source_label')->nullable(); // quale fonte approvata (dominio/label)
            $table->decimal('confidence', 4, 3)->nullable(); // rilevanza soggettiva: bassa è normale

            // HITL: suggested (proposto) → accepted (lo terrò per le fasi B/C/D) | dismissed (scartato).
            $table->string('status', 12)->default('suggested');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['course_id', 'status']);
        });

        DB::statement("ALTER TABLE coverage_gaps ADD CONSTRAINT coverage_gaps_status_check
            CHECK (status IN ('suggested', 'accepted', 'dismissed'))");
        DB::statement("ALTER TABLE coverage_gaps ADD CONSTRAINT coverage_gaps_confidence_check
            CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_gaps');
    }
};
