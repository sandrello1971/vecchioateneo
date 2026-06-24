<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.3 — Coda HITL delle proposte di aggiornamento. Una proposta nasce da un
// freshness_claim con verdict='obsoleto' (Fase 3). È il CUORE del sistema: nulla
// raggiunge un corso senza che una proposta sia stata APPROVATA da un umano.
//
// `before` = il claim_text VERBATIM del claim (tipografia reale) → è l'ancora per
// l'applicazione (find-and-replace verbatim su sorgente E contenuto live). `after` =
// testo proposto. Aggancio per id interno (FK). Auto-applicazione strutturalmente
// impossibile: l'applicazione (P25.3c) consuma solo status='approved'.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            // Provenienza (audit). La proposta conserva il proprio snapshot anche se
            // run/claim vengono rigenerati: nullOnDelete, non cascade.
            $table->foreignUuid('run_id')->nullable()->constrained('freshness_runs')->nullOnDelete();
            $table->foreignUuid('freshness_claim_id')->nullable()->constrained('freshness_claims')->nullOnDelete();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();

            // Diff
            $table->string('block_id', 64);
            $table->integer('sentence_ref')->nullable();
            $table->text('before'); // = claim_text verbatim (ancora autorevole)
            $table->text('after');  // testo proposto
            $table->text('reason')->nullable();

            // Fonte (ereditata dalla verifica Fase 2)
            $table->text('source')->nullable();
            $table->string('source_type', 12)->nullable(); // primary | web
            $table->decimal('confidence', 4, 3)->nullable();

            // Audience (snapshot al momento della proposta) → gate Schola/minori (P25.3e)
            $table->string('audience', 12)->default('adult'); // adult | minor

            // Stato HITL
            $table->string('status', 12)->default('pending'); // pending | approved | rejected | applied
            $table->boolean('after_edited_by_human')->default(false); // "Modifica" vs approvazione as-is
            $table->foreignUuid('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable(); // valorizzato in P25.3c

            $table->timestamps();

            $table->index(['course_id', 'status']);
            $table->index('run_id');
            $table->index('freshness_claim_id');
        });

        DB::statement("ALTER TABLE update_proposals ADD CONSTRAINT update_proposals_status_check
            CHECK (status IN ('pending', 'approved', 'rejected', 'applied'))");
        DB::statement("ALTER TABLE update_proposals ADD CONSTRAINT update_proposals_audience_check
            CHECK (audience IN ('adult', 'minor'))");
        DB::statement("ALTER TABLE update_proposals ADD CONSTRAINT update_proposals_source_type_check
            CHECK (source_type IS NULL OR source_type IN ('primary', 'web'))");
        DB::statement("ALTER TABLE update_proposals ADD CONSTRAINT update_proposals_confidence_check
            CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('update_proposals');
    }
};
