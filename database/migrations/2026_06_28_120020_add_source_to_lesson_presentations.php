<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// S3 — origine della presentazione: 'generated' (dal sistema, correggibile via
// prompt) | 'uploaded' (file caricato dall'utente, usato as-is, niente prompt).
// I record esistenti diventano 'generated' (default).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_presentations', function (Blueprint $table) {
            $table->string('source')->default('generated')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_presentations', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
