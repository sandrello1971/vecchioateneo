<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Caricamenti massivi (docenti/studenti) a due passi: preview/dry-run → commit.
// Lo schema vive da P11; i controller di import arrivano in P13/P14.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('students')->cascadeOnDelete();
            $table->string('type');                       // professors | students
            $table->string('status')->default('previewed'); // previewed | committed | discarded
            $table->string('source_filename')->nullable();
            $table->json('summary')->nullable();          // righe valide, duplicati, errori, mapping
            $table->json('rows')->nullable();             // righe normalizzate + esito per riga
            $table->timestamps();
        });

        DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_type_check
            CHECK (type IN ('professors','students'))");
        DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_status_check
            CHECK (status IN ('previewed','committed','discarded'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
