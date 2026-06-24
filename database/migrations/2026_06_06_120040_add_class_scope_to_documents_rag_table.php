<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Scope di classe sui chunk RAG. NON tocca embedding/pgvector (prerequisito
// del pacchetto 6, vedi SPEC §0). is_instructor_only resta per retro-compat ma
// viene rimappato su scope='instructor_only' (backfill).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents_rag', function (Blueprint $table) {
            $table->foreignUuid('school_class_id')->nullable()
                  ->constrained('school_classes')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->nullable()
                  ->constrained('students')->cascadeOnDelete();
            $table->string('scope')->default('platform');
            $table->index(['school_class_id', 'scope']);
        });

        DB::statement("ALTER TABLE documents_rag ADD CONSTRAINT documents_rag_scope_check
            CHECK (scope IN ('platform', 'instructor_only', 'teacher_private', 'class'))");

        // Backfill: i chunk instructor-only diventano scope='instructor_only'.
        DB::statement("UPDATE documents_rag SET scope = 'instructor_only' WHERE is_instructor_only = true");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE documents_rag DROP CONSTRAINT IF EXISTS documents_rag_scope_check');
        Schema::table('documents_rag', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_class_id');
            $table->dropConstrainedForeignId('teacher_id');
            $table->dropColumn('scope');
        });
    }
};
