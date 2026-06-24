<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit del consenso per iscrizione (§8.2): OPZIONALE e non bloccante. Il
// consenso resta responsabilità della scuola (titolare); questo campo serve
// solo a tracciarlo se la segreteria lo fornisce.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_students', function (Blueprint $table) {
            $table->timestamp('consent_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('class_students', function (Blueprint $table) {
            $table->dropColumn('consent_at');
        });
    }
};
