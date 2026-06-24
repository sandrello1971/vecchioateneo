<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P25.3c — Motivo del fallimento di applicazione (verbatim non trovato / non unico).
// Quando l'applicazione di una proposta approvata fallisce in modo pulito, NON viene
// applicata e il motivo è registrato qui (la proposta resta 'approved', non 'applied').
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('update_proposals', function (Blueprint $table) {
            $table->text('apply_error')->nullable()->after('applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('update_proposals', function (Blueprint $table) {
            $table->dropColumn('apply_error');
        });
    }
};
