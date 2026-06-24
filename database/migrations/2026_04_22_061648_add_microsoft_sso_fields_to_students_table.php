<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('microsoft_id')->nullable()->unique();
            $table->boolean('auto_enroll_all_courses')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['microsoft_id']);
            $table->dropColumn(['microsoft_id', 'auto_enroll_all_courses']);
        });
    }
};
