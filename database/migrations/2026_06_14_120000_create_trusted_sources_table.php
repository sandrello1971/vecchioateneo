<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P26 Fase 0 — Registro delle FONTI ATTENDIBILI per dominio tematico, fondamento dello
// Scout (fase successiva). Le fonti sono condivise tra corsi dello stesso tema (chiave
// = `topic` stringa, non per-corso). Due modalità: `search` (dominio da cercare via
// site:, es. arxiv.org) e `fetch` (pagina specifica da rileggere, es. la pagina dell'AI
// Act). Stato HITL: una fonte diventa `approved` SOLO per azione admin; l'agente può
// solo PROPORRE (`suggested`, `proposed_by='agent'`). Tabella nuova: non tocca P25/B/F.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_sources', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->string('label');          // nome leggibile (es. "arXiv — cs.AI")
            $table->string('url_or_domain');   // dominio (mode=search) o URL pieno (mode=fetch)
            $table->string('mode', 12);        // search | fetch
            $table->string('topic');           // dominio tematico, stringa (es. "agenti-ai")

            // Stato HITL: suggested = proposta (da rivedere); approved = lo Scout la userà.
            $table->string('status', 12)->default('suggested'); // suggested | approved | rejected
            $table->string('proposed_by', 12)->default('admin'); // agent | admin (origine)

            $table->text('notes')->nullable();

            // Audit dell'azione admin (chi ha approvato/rifiutato). Nullable: una suggested
            // non rivista non li ha. nullOnDelete: la fonte sopravvive alla rimozione dell'admin.
            $table->foreignUuid('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index('topic');
            $table->index(['topic', 'status']);
            // Una fonte è unica per (tema, target, modalità): evita doppioni dai suggerimenti.
            $table->unique(['topic', 'url_or_domain', 'mode']);
        });

        DB::statement("ALTER TABLE trusted_sources ADD CONSTRAINT trusted_sources_mode_check
            CHECK (mode IN ('search', 'fetch'))");
        DB::statement("ALTER TABLE trusted_sources ADD CONSTRAINT trusted_sources_status_check
            CHECK (status IN ('suggested', 'approved', 'rejected'))");
        DB::statement("ALTER TABLE trusted_sources ADD CONSTRAINT trusted_sources_proposed_by_check
            CHECK (proposed_by IN ('agent', 'admin'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_sources');
    }
};
