<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P26 Fase A — Il topic (dominio tematico) di un corso vive sulla sua config freshness
// esistente: è la via più semplice (nessuna entità nuova, una stringa per-corso). Lo Scout
// userà le trusted_sources `approved` di questo `topic`. Additiva, nullable: i corsi senza
// topic semplicemente non sono analizzabili dallo Scout finché non lo si imposta.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_freshness_configs', function (Blueprint $table) {
            $table->string('topic')->nullable();
            $table->index('topic');
        });
    }

    public function down(): void
    {
        Schema::table('course_freshness_configs', function (Blueprint $table) {
            $table->dropIndex(['topic']);
            $table->dropColumn('topic');
        });
    }
};
