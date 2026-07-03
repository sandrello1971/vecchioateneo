<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// R5 — consenso DPA SPECIFICO per i sub-processori esterni del video-AI
// (Whisper/Groq, Vision/Anthropic), distinto dal dpa_signed_at generale della
// scuola. Senza questo flag, i materiali Schola che passano da sub-processori
// esterni (audio/video/foto) NON vengono elaborati. Nullable/additivo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->timestamp('video_ai_dpa_accepted_at')->nullable()->after('dpa_signed_at');
            $table->foreignUuid('video_ai_dpa_accepted_by')->nullable()->after('video_ai_dpa_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['video_ai_dpa_accepted_at', 'video_ai_dpa_accepted_by']);
        });
    }
};
