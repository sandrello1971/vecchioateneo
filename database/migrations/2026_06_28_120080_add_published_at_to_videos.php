<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// V4 — pubblicazione del video (mirror delle presentations): published_at null=bozza,
// valorizzato=pubblicato (visibile ai discenti). Additivo/nullable su entrambe le tabelle.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('script_status');
            $table->index(['lesson_id', 'published_at']);
        });
        Schema::table('module_videos', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('script_status');
            $table->index(['module_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table) {
            $table->dropIndex(['lesson_id', 'published_at']);
            $table->dropColumn('published_at');
        });
        Schema::table('module_videos', function (Blueprint $table) {
            $table->dropIndex(['module_id', 'published_at']);
            $table->dropColumn('published_at');
        });
    }
};
