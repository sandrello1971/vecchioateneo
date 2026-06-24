<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// S0 — persiste la SPEC JSON delle slide, oggi scartata dopo il render.
// Prerequisito della correzione via prompt (S2). Additivo e nullable: i record
// vecchi (1 in prod) restano senza spec, va bene.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_presentations', function (Blueprint $table) {
            $table->json('spec')->nullable()->after('generation_meta');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_presentations', function (Blueprint $table) {
            $table->dropColumn('spec');
        });
    }
};
