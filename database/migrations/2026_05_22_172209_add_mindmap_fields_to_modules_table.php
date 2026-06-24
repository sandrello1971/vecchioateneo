<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            // Mappa mentale generata da Claude API (markdown gerarchico per markmap.js).
            // Editabile dall'instructor dopo la generazione.
            $table->text('mindmap_markdown')->nullable()->after('content');

            // Hash MD5 di Module.content al momento della generazione.
            // Confrontato con md5(strip_tags(content_corrente)) per detectare
            // se la mindmap è "obsoleta" (content modificato dopo generazione).
            $table->string('mindmap_content_hash', 32)->nullable()->after('mindmap_markdown');

            // Timestamp generazione (per UI: "generata il...")
            $table->timestamp('mindmap_generated_at')->nullable()->after('mindmap_content_hash');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['mindmap_markdown', 'mindmap_content_hash', 'mindmap_generated_at']);
        });
    }
};
