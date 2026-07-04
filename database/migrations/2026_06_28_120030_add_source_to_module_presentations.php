<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// S3 — gemella: origine della presentazione del MODULO. 'generated' | 'uploaded'.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_presentations', function (Blueprint $table) {
            $table->string('source')->default('generated')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('module_presentations', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
