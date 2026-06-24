<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Ruolo school_admin (segreteria) + appartenenza scuola per TUTTI gli attori
// scuola (professor/student/school_admin). school_id NULL = attore "libero"
// (comportamento fetta 1).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_role_check');
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_role_check
            CHECK (role IS NULL OR role IN ('student','instructor','admin','professor','school_admin'))");

        Schema::table('students', function (Blueprint $table) {
            $table->foreignUuid('school_id')->nullable()->after('role')
                  ->constrained('schools')->nullOnDelete();
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
        });
        DB::statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_role_check');
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_role_check
            CHECK (role IS NULL OR role IN ('student','instructor','admin','professor'))");
    }
};
