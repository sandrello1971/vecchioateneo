<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Nuovo scope RAG 'teacher_shared': chunk dei materiali condivisi tra docenti.
// I filtri d'ambito (share_scope / subject_id / school_id) viaggiano nel metadata
// JSON del chunk, coerente con l'uso esistente di metadata->artifact_id / publication_id.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE documents_rag DROP CONSTRAINT IF EXISTS documents_rag_scope_check');
        DB::statement("ALTER TABLE documents_rag ADD CONSTRAINT documents_rag_scope_check
            CHECK (scope IN ('platform', 'instructor_only', 'teacher_private', 'class', 'teacher_shared'))");
    }

    public function down(): void
    {
        // Rimuove i chunk del nuovo scope prima di ripristinare il CHECK originale.
        DB::statement("DELETE FROM documents_rag WHERE scope = 'teacher_shared'");
        DB::statement('ALTER TABLE documents_rag DROP CONSTRAINT IF EXISTS documents_rag_scope_check');
        DB::statement("ALTER TABLE documents_rag ADD CONSTRAINT documents_rag_scope_check
            CHECK (scope IN ('platform', 'instructor_only', 'teacher_private', 'class'))");
    }
};
