<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modalità di erogazione del corso, che governa COME si contano le ore nel
 * registro di frequenza:
 *   - async : corso FAD asincrono → nel totale contano solo le ore FAD (heartbeat)
 *   - sync  : corso in aula/webinar → contano solo le presenze alle sessioni
 *   - NULL  : non impostata → contano entrambi i canali (comportamento storico)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('modality')->nullable()->after('duration_hours');
        });

        DB::statement("ALTER TABLE courses ADD CONSTRAINT courses_modality_check CHECK (modality IN ('async', 'sync'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE courses DROP CONSTRAINT IF EXISTS courses_modality_check');
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('modality');
        });
    }
};
