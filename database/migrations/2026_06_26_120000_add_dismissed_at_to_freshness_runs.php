<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Storico analisi: rende i run "archiviabili" dalla UI senza distruggere dati (claim/proposte
// restano: freshness_claims è cascade su freshness_runs, quindi NON cancelliamo le righe).
// Un run archiviato sparisce dal pannello. Additiva.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freshness_runs', function (Blueprint $table) {
            $table->timestamp('dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('freshness_runs', function (Blueprint $table) {
            $table->dropColumn('dismissed_at');
        });
    }
};
