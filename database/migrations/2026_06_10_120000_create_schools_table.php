<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tenant radice della fase 2: la Scuola. Possiede anagrafiche, classi, cattedre.
// Branding white-label per scuola in `settings` (sopra il default piattaforma).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type');                 // liceo | istituto_tecnico | altro
            $table->string('city')->nullable();
            $table->json('settings')->nullable();   // branding: assistant_name, instance_name, logo_path, ...
            $table->boolean('allow_professor_create_classes')->default(false); // modello puro
            $table->string('status')->default('active'); // active | suspended
            $table->timestamp('dpa_signed_at')->nullable(); // accordo titolare/responsabile (art.28)
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE schools ADD CONSTRAINT schools_type_check
            CHECK (type IN ('liceo','istituto_tecnico','altro'))");
        DB::statement("ALTER TABLE schools ADD CONSTRAINT schools_status_check
            CHECK (status IN ('active','suspended'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
