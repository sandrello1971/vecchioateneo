<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// I quiz Schola vivono senza modulo corso (module_id NULL). La colonna risulta
// già nullable nella create originale: DROP NOT NULL è idempotente su Postgres
// (no-op se già nullable). Niente ->change()/dbal, niente rischio sulla FK.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE quizzes ALTER COLUMN module_id DROP NOT NULL');
    }

    public function down(): void
    {
        // No-op: la colonna era già nullable prima di questa migrazione;
        // re-imporre NOT NULL fallirebbe in presenza di quiz Schola.
    }
};
