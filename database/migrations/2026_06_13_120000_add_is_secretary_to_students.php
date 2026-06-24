<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Identità multi-contesto: la SEGRETERIA diventa una CAPACITÀ (flag is_secretary
// + school_id), non più un valore di `role`. Così un account può essere
// contemporaneamente professore (role) E segreteria (flag), oltre che corsista
// (iscrizioni). Migra gli account esistenti role='school_admin' al flag e
// restringe il CHECK su role (rimuove 'school_admin').
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('is_secretary')->default(false)->after('school_id');
        });

        // Migrazione dati: ex school_admin → flag (role azzerato, school_id tenuto).
        DB::table('students')->where('role', 'school_admin')
            ->update(['is_secretary' => true, 'role' => null]);

        DB::statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_role_check');
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_role_check
            CHECK (role IS NULL OR role IN ('student','instructor','admin','professor'))");
    }

    public function down(): void
    {
        // Ripristina 'school_admin' nel CHECK e riconverte i flag in role.
        DB::statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_role_check');
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_role_check
            CHECK (role IS NULL OR role IN ('student','instructor','admin','professor','school_admin'))");

        DB::table('students')->where('is_secretary', true)->whereNull('role')
            ->update(['role' => 'school_admin']);

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('is_secretary');
        });
    }
};
