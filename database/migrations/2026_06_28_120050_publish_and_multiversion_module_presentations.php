<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blocco B — bi-versione presentazioni MODULI (come le lezioni): rimuove l'unicità
// (1 sola per modulo) per permettere bozza + pubblicata, e aggiunge published_at
// (null=bozza, valorizzato=pubblicata). Additivo/safe: il drop allenta un vincolo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_presentations', function (Blueprint $table) {
            $table->dropUnique(['module_id']); // era: una sola presentazione per modulo
            $table->timestamp('published_at')->nullable()->after('source');
            $table->index(['module_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('module_presentations', function (Blueprint $table) {
            $table->dropIndex(['module_id', 'published_at']);
            $table->dropColumn('published_at');
            $table->unique('module_id');
        });
    }
};
