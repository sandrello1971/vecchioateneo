<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_course', function (Blueprint $table) {
            $table->uuid('instructor_id')->nullable()->after('course_id');
            $table->foreign('instructor_id')->references('id')->on('students')->nullOnDelete();
            $table->index('instructor_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_course', function (Blueprint $table) {
            $table->dropForeign(['instructor_id']);
            $table->dropIndex(['instructor_id']);
            $table->dropColumn('instructor_id');
        });
    }
};
