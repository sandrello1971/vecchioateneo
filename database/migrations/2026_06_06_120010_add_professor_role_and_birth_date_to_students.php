<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Ruolo 'professor' (docente Schola) sul CHECK di students.role + birth_date.
// birth_date nullable a schema; obbligatorio alla registrazione via codice classe
// (gestito a livello applicativo, non DB) — SPEC §8.3.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_role_check');
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_role_check
            CHECK (role IS NULL OR role IN ('student', 'instructor', 'admin', 'professor'))");

        Schema::table('students', function (Blueprint $table) {
            $table->date('birth_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('birth_date');
        });
        DB::statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_role_check');
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_role_check
            CHECK (role IS NULL OR role IN ('student', 'instructor', 'admin'))");
    }
};
