<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// §8.1 credenziali duali: gli studenti di scuola SENZA email accedono con
// username interno. Quindi students.email diventa nullable (almeno uno tra
// email e username deve essere presente — vincolo applicativo). L'indice
// UNIQUE su email resta: in Postgres i NULL sono distinti, niente collisioni.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE students ALTER COLUMN email DROP NOT NULL');
    }

    public function down(): void
    {
        // No-op: re-imporre NOT NULL fallirebbe con account username-only.
    }
};
