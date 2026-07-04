<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Materiale a livello SCUOLA:
//  - school_id: scuola del materiale (= scuola del proprietario alla creazione).
//    Usato per la visibilità 'all'=scuola, per lo scoping segreteria e per il RAG.
//  - is_school_material: materiale caricato dall'admin/segreteria per l'intera scuola
//    (finisce in Biblioteca, utilizzabile dai docenti via import).
// Sostituisce shared_school_id (un materiale appartiene a una sola scuola): la logica
// passa a school_id, la colonna precedente viene rimossa (feature non ancora in prod).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_documents', function (Blueprint $table) {
            $table->foreignUuid('school_id')->nullable()->after('subject_id')
                  ->constrained('schools')->nullOnDelete();
            $table->boolean('is_school_material')->default(false)->after('school_id');
            $table->index(['school_id', 'share_scope']);
            $table->index(['school_id', 'is_school_material']);
        });

        // Backfill: scuola del materiale = scuola del proprietario (docente/admin).
        DB::statement('UPDATE teaching_documents td
            SET school_id = s.school_id
            FROM students s
            WHERE s.id = td.teacher_id AND s.school_id IS NOT NULL');

        // Rimuovi il vecchio perimetro per-condivisione (superato da school_id).
        Schema::table('teaching_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shared_school_id');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_documents', function (Blueprint $table) {
            $table->foreignUuid('shared_school_id')->nullable()->after('share_scope')
                  ->constrained('schools')->nullOnDelete();
        });
        DB::statement('UPDATE teaching_documents SET shared_school_id = school_id WHERE share_scope IS NOT NULL');

        Schema::table('teaching_documents', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'is_school_material']);
            $table->dropIndex(['school_id', 'share_scope']);
            $table->dropColumn('is_school_material');
            $table->dropConstrainedForeignId('school_id');
        });
    }
};
