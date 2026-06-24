<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Nel modello scuola la classe appartiene alla SCUOLA (school_id), non al
// docente. school_classes.teacher_id diventa nullable e assume il significato
// di "coordinatore" (opzionale). I docenti vi sono legati via cattedre.
// DROP NOT NULL idempotente (no-op se già nullable); la FK resta invariata.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE school_classes ALTER COLUMN teacher_id DROP NOT NULL');
    }

    public function down(): void
    {
        // No-op: re-imporre NOT NULL fallirebbe in presenza di classi-scuola
        // senza coordinatore.
    }
};
