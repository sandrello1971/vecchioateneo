<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.3f — Disaccoppiamento sorgente↔manuale formatore in fase di applicazione.
//
// Il `before` di una proposta nasce dal sorgente strutturato (course_sources), che è una
// RISTRUTTURAZIONE LLM del manuale: spesso NON è verbatim nel manuale formatore live
// (instructor_manual_sections). Finché l'apply era atomico-verbatim su entrambi, ogni
// proposta su una frase riformulata si piantava (0 applicate, proposta bloccata su 'approved').
//
// Ora l'apply applica i due target in modo indipendente, e per il manuale traccia l'esito qui:
//   - verbatim   → il before combaciava esatto nel manuale, sostituito.
//   - queued     → verbatim fallito; riscrittura semantica del manuale accodata (job async).
//   - rewritten  → riscrittura semantica applicata con successo (manual_before/after).
//   - unmatched  → il fatto non è stato ritrovato/ancorato nel manuale: da rivedere a mano.
//   - failed     → la riscrittura/applicazione manuale è andata in errore: da rivedere a mano.
// NULL = non pertinente (proposte studente, o formatore mai applicate).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('update_proposals', function (Blueprint $table) {
            $table->string('manual_status', 16)->nullable()->after('apply_error');
            $table->text('manual_before')->nullable()->after('manual_status'); // ancora verbatim trovata nel manuale
            $table->text('manual_after')->nullable()->after('manual_before');   // riscrittura applicata al manuale
        });

        DB::statement("ALTER TABLE update_proposals ADD CONSTRAINT update_proposals_manual_status_check
            CHECK (manual_status IS NULL OR manual_status IN ('verbatim', 'queued', 'rewritten', 'unmatched', 'failed'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE update_proposals DROP CONSTRAINT IF EXISTS update_proposals_manual_status_check');
        Schema::table('update_proposals', function (Blueprint $table) {
            $table->dropColumn(['manual_status', 'manual_before', 'manual_after']);
        });
    }
};
