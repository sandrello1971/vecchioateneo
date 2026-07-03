<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// R2/R3 — collega il video generato alla sua collection videoai (video_ai_id) e
// traccia l'indicizzazione (indexed_at). Additivo/nullable. Il gate "pubblicabile=
// interrogabile" (R3) userà indexed_at.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['lesson_videos', 'module_videos'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('video_ai_id')->nullable()->after('file_path');
                $table->timestamp('indexed_at')->nullable()->after('published_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['lesson_videos', 'module_videos'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropColumn(['video_ai_id', 'indexed_at']);
            });
        }
    }
};
