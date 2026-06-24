<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P25.B-b.1 — Tracciabilità del coordinamento nel changelog: una modifica discente
// coordinata "nasce dall'applicazione della proposta formatore X". `proposal_id` resta la
// proposta applicata (la figlia discente); `parent_proposal_id` è la proposta formatore
// che l'ha originata. Additiva, nullable (le righe esistenti restano valide).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_changelog', function (Blueprint $table) {
            $table->foreignUuid('parent_proposal_id')->nullable()->constrained('update_proposals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_changelog', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_proposal_id');
        });
    }
};
