<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('video_ai_id')->nullable()->after('video_url');
            $table->string('video_filename')->nullable()->after('video_ai_id');
            $table->string('video_status')->default('none')->after('video_filename');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['video_ai_id', 'video_filename', 'video_status']);
        });
    }
};
