<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// S0 — gemella di add_spec_to_lesson_presentations: persiste la SPEC JSON delle
// slide del MODULO. Additivo e nullable.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_presentations', function (Blueprint $table) {
            $table->json('spec')->nullable()->after('generation_meta');
        });
    }

    public function down(): void
    {
        Schema::table('module_presentations', function (Blueprint $table) {
            $table->dropColumn('spec');
        });
    }
};
