<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 3 — classificazione materiali: un materiale grezzo può essere assegnato a
// una lezione (un materiale → una lezione). NULL = ancora nel pool "da organizzare".
// La cancellazione di una lezione non distrugge i materiali: tornano nel pool.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_documents', function (Blueprint $table) {
            $table->foreignUuid('lesson_id')->nullable()->after('teacher_id')
                  ->constrained('lessons')->nullOnDelete();
            $table->index('lesson_id');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lesson_id');
        });
    }
};
