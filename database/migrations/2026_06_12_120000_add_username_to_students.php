<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Username interno per il login degli studenti SENZA email (§8.1). UNIQUE
// globale; in Postgres i NULL sono distinti, quindi gli account email-only
// (username NULL) non collidono.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
