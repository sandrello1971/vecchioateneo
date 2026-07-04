<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Condivisione del materiale grezzo con altri docenti, con AMBITO:
//   share_scope = null  → privato (default)
//   share_scope = 'subject' → visibile ai docenti della STESSA materia nella STESSA
//     scuola (shared_school_id = scuola dell'owner al momento della condivisione)
//   share_scope = 'all' → visibile a tutti i docenti
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_documents', function (Blueprint $table) {
            $table->string('share_scope')->nullable()->after('subject_id');
            $table->foreignUuid('shared_school_id')->nullable()->after('share_scope')
                  ->constrained('schools')->nullOnDelete();
            $table->index(['share_scope', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('teaching_documents', function (Blueprint $table) {
            $table->dropIndex(['share_scope', 'subject_id']);
            $table->dropConstrainedForeignId('shared_school_id');
            $table->dropColumn('share_scope');
        });
    }
};
