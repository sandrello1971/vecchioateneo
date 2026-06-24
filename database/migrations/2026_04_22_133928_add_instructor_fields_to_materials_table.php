<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->uuid('course_id')->nullable()->after('module_id');
            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();

            $table->uuid('module_id')->nullable()->change();

            $table->boolean('is_instructor_only')->default(false)->after('is_downloadable');

            $table->text('content_html')->nullable()->after('is_instructor_only');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn(['course_id', 'is_instructor_only', 'content_html']);
        });
    }
};
