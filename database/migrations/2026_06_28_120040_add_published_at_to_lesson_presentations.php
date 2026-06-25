<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pubblicazione + bi-versione presentazioni LEZIONI: una lezione può avere più
// record (1 pubblicata visibile agli studenti + 1 bozza in lavorazione).
// published_at: null = bozza, valorizzato = pubblicata (e QUANDO). Additivo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_presentations', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('source');
            $table->index(['lesson_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('lesson_presentations', function (Blueprint $table) {
            $table->dropIndex(['lesson_id', 'published_at']);
            $table->dropColumn('published_at');
        });
    }
};
