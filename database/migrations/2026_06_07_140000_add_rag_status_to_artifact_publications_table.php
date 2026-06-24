<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Stato dell'ingestion RAG di una pubblicazione, per il feedback UX
// ("pubblicazione in corso" → polling → completata) e la diagnostica.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artifact_publications', function (Blueprint $table) {
            $table->string('rag_status')->default('pending')->after('downloadable');
            $table->text('rag_failure_reason')->nullable()->after('rag_status');
        });

        DB::statement("ALTER TABLE artifact_publications ADD CONSTRAINT artifact_publications_rag_status_check
            CHECK (rag_status IN ('pending', 'indexing', 'ready', 'failed'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE artifact_publications DROP CONSTRAINT IF EXISTS artifact_publications_rag_status_check');
        Schema::table('artifact_publications', function (Blueprint $table) {
            $table->dropColumn(['rag_status', 'rag_failure_reason']);
        });
    }
};
