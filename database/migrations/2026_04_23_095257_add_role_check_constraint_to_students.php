<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE students
            ADD CONSTRAINT students_role_check
            CHECK (role IS NULL OR role IN ('student', 'instructor', 'admin'))
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE students DROP CONSTRAINT IF EXISTS students_role_check");
    }
};
