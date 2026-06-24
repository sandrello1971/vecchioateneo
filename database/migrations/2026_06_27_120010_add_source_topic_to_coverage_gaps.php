<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P26.2 — provenienza di un gap: da quale TOPIC del corso è emerso e con quale PESO (primary/
// secondary). Permette di etichettare, filtrare e ordinare i gap (i primary contano di più). Additiva.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coverage_gaps', function (Blueprint $table) {
            $table->string('source_topic')->nullable();
            $table->string('source_weight', 12)->nullable(); // primary | secondary
        });
    }

    public function down(): void
    {
        Schema::table('coverage_gaps', function (Blueprint $table) {
            $table->dropColumn(['source_topic', 'source_weight']);
        });
    }
};
