<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Path del PDF non firmato, generato automaticamente alla creazione
            // del certificato. NULL solo per record legacy creati prima di
            // questo refactor — gestiti in fallback dal controller.
            $table->string('unsigned_pdf_path', 500)->nullable()->after('issued_at');

            // Path del PDF firmato, riempito quando l'admin carica la versione
            // firmata digitalmente via admin UI. NULL = certificato in attesa
            // di firma del legale rappresentante.
            $table->string('signed_pdf_path', 500)->nullable()->after('unsigned_pdf_path');

            // Timestamp del momento in cui la firma è stata caricata.
            // Utile per audit trail e per mostrare data firma sulla pagina
            // pubblica di verifica.
            $table->timestamp('signed_at')->nullable()->after('signed_pdf_path');

            // Snapshot del nome admin che ha caricato la firma. Snapshot
            // perché in futuro l'admin potrebbe cambiare nome/lasciare
            // l'azienda, ma il certificato deve rimanere immutabile.
            $table->string('signed_by', 255)->nullable()->after('signed_at');

            // Indice per query "lista certificati pending di firma"
            $table->index('signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['signed_at']);
            $table->dropColumn(['unsigned_pdf_path', 'signed_pdf_path', 'signed_at', 'signed_by']);
        });
    }
};
