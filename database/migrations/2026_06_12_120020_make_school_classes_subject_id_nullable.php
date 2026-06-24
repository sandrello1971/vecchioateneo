<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Nel modello scuola la classe è un GRUPPO (es. "3A"): le materie arrivano via
// cattedre (teaching_assignments), non sono un attributo della classe. Quindi
// school_classes.subject_id diventa nullable (le classi minimali create
// dall'import studenti non hanno una materia). I docenti liberi continuano a
// valorizzarlo. DROP NOT NULL idempotente; la FK resta invariata.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE school_classes ALTER COLUMN subject_id DROP NOT NULL');
    }

    public function down(): void
    {
        // No-op: re-imporre NOT NULL fallirebbe con classi-gruppo senza materia.
    }
};
