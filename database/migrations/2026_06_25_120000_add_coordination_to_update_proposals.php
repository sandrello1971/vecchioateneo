<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.B-b.1 — Coordinamento formatore→discente. Una proposta discente "coordinata" nasce
// dall'approvazione di una proposta formatore (parent) e ne traccia il legame + i metadati
// del match semantico. Additiva su tabella P25: nessun ALTER su modules/students.
//
// Retrocompatibile: le proposte esistenti (formatore + studente autonome) restano valide —
// parent_proposal_id null + origin='autonomous' = comportamento attuale.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('update_proposals', function (Blueprint $table) {
            // Legame padre→figlia (self-referenziale). nullOnDelete: la figlia non sparisce
            // se il padre viene cancellato (l'orfananza è gestita esplicitamente sotto).
            $table->foreignUuid('parent_proposal_id')->nullable()->constrained('update_proposals')->nullOnDelete();

            // Distingue coordinate (figlie di un'approvazione formatore) da autonome (run su
            // student_proposals_enabled). Default 'autonomous' = pregresso invariato.
            $table->string('origin', 12)->default('autonomous'); // autonomous | coordinated

            // Metadati del match semantico (reperto probe: affidabile sui dati, debole sui
            // fatti-prodotto/policy → marcabili lower-trust per eyeball obbligatorio).
            $table->decimal('match_confidence', 4, 3)->nullable(); // 0.000–1.000
            $table->string('match_trust', 8)->nullable();          // high | low

            // Orfananza (D1 reattivo): flag SEPARATO dallo status, così una figlia GIÀ
            // APPLICATA (live sul materiale studente) resta 'applied' ma viene SEGNALATA,
            // mentre una figlia ancora pending verrà auto-scartata (status='rejected') —
            // entrambi i casi tracciati da orphaned_at + orphan_reason.
            $table->timestamp('orphaned_at')->nullable();
            $table->text('orphan_reason')->nullable();
        });

        DB::statement("ALTER TABLE update_proposals ADD CONSTRAINT update_proposals_origin_check
            CHECK (origin IN ('autonomous', 'coordinated'))");
        DB::statement("ALTER TABLE update_proposals ADD CONSTRAINT update_proposals_match_trust_check
            CHECK (match_trust IS NULL OR match_trust IN ('high', 'low'))");
        DB::statement("ALTER TABLE update_proposals ADD CONSTRAINT update_proposals_match_confidence_check
            CHECK (match_confidence IS NULL OR (match_confidence >= 0 AND match_confidence <= 1))");
    }

    public function down(): void
    {
        foreach (['origin_check', 'match_trust_check', 'match_confidence_check'] as $c) {
            DB::statement("ALTER TABLE update_proposals DROP CONSTRAINT IF EXISTS update_proposals_{$c}");
        }
        Schema::table('update_proposals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_proposal_id');
            $table->dropColumn(['origin', 'match_confidence', 'match_trust', 'orphaned_at', 'orphan_reason']);
        });
    }
};
