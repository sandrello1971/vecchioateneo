<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P25.3e — Flag che distingue un audience impostato MANUALMENTE dall'admin (override
// autorevole) dal default/euristica. Il backfill euristico NON sovrascrive gli override.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_freshness_configs', function (Blueprint $table) {
            $table->boolean('audience_overridden')->default(false)->after('audience');
        });
    }

    public function down(): void
    {
        Schema::table('course_freshness_configs', function (Blueprint $table) {
            $table->dropColumn('audience_overridden');
        });
    }
};
