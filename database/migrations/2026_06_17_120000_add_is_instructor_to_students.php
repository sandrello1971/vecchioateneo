<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Identità multi-contesto (gemella di is_secretary): il FORMATORE corsi diventa
// una CAPACITÀ (flag is_instructor), non più solo un valore di `role`. Così un
// account può essere contemporaneamente docente Schola (role='professor') E
// formatore corsi (flag), oltre che corsista/segreteria. NON tocca il CHECK su
// `role` (additivo, prod-safe). isInstructor() = role==='instructor' OR flag.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('is_instructor')->default(false)->after('is_secretary');
        });

        // Backfill: gli instructor esistenti (role='instructor') ottengono il flag,
        // così le liste/query che filtrano per capacità sono coerenti.
        DB::table('students')->where('role', 'instructor')
            ->update(['is_instructor' => true]);
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('is_instructor');
        });
    }
};
