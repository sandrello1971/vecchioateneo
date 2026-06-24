<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// P25.B-b.2 — Una proposta discente COORDINATA "candidate" nasce dal matching su
// un'approvazione formatore: ha la porzione discente (before verbatim) ma NON ancora la
// riscrittura (after arriva in B-b.3). Quindi:
//  - `after` diventa NULLABLE (stato candidate prima della riscrittura).
//  - nuovo status `matched` = "porzione trovata, in attesa di conferma umana + riscrittura".
// Additiva su tabella P25. Le proposte esistenti (after valorizzato, status nei valori
// esistenti) restano valide.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE update_proposals ALTER COLUMN "after" DROP NOT NULL');

        DB::statement('ALTER TABLE update_proposals DROP CONSTRAINT IF EXISTS update_proposals_status_check');
        DB::statement("ALTER TABLE update_proposals ADD CONSTRAINT update_proposals_status_check
            CHECK (status IN ('matched', 'pending', 'approved', 'rejected', 'applied'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE update_proposals DROP CONSTRAINT IF EXISTS update_proposals_status_check');
        DB::statement("ALTER TABLE update_proposals ADD CONSTRAINT update_proposals_status_check
            CHECK (status IN ('pending', 'approved', 'rejected', 'applied'))");
        DB::statement('ALTER TABLE update_proposals ALTER COLUMN "after" SET NOT NULL');
    }
};
