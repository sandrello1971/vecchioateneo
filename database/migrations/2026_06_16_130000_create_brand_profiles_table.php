<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P27 Fase 1 — brand_profiles: tema di branding per le SLIDE (colori/font/logo),
 * additivo e compatibile multi-tenant.
 *
 * - owner polimorfico NULLABLE: oggi una School, domani un Tenant; owner NULL =
 *   profilo di PIATTAFORMA (default GLITCH). Pronto ad agganciarsi a un futuro
 *   tenant senza rifare la tabella.
 * - Copre SOLO il tema slide. instance_name/assistant_name restano in
 *   schools.settings (convivenza, nessuna migrazione di dati).
 * - I temi curati (base_theme) sono un catalogo NON editabile: vedi App\Enums\BaseTheme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            // Morph nullable definito a mano: l'indice di lookup è il vincolo
            // UNIQUE qui sotto (evita un indice morph ridondante).
            $table->string('owner_type')->nullable();
            $table->uuid('owner_id')->nullable();

            // Tema curato di base (catalogo immutabile).
            $table->string('base_theme');

            // Override controllati: NULL = eredita dal tema base.
            $table->string('accent_color', 6)->nullable();  // hex SENZA '#'
            $table->string('font_choice')->nullable();       // chiave catalogo font (FontPair)

            // Logo slide: NULL = eredita da schools.settings['logo_path']
            // (il logo caricato una volta serve sia chat sia slide).
            $table->string('logo_path')->nullable();

            $table->timestamps();
        });

        // base_theme ∈ catalogo curato.
        DB::statement("ALTER TABLE brand_profiles ADD CONSTRAINT brand_profiles_base_theme_check
            CHECK (base_theme IN ('glitch','classico','moderno','caldo'))");

        // accent_color, se valorizzato, deve essere esadecimale a 6 cifre (senza '#').
        DB::statement("ALTER TABLE brand_profiles ADD CONSTRAINT brand_profiles_accent_hex_check
            CHECK (accent_color IS NULL OR accent_color ~ '^[0-9A-Fa-f]{6}$')");

        // Un solo profilo per owner. NULLS NOT DISTINCT (PG15+) → un solo profilo
        // di piattaforma (owner_type NULL, owner_id NULL). Crea anche l'indice di lookup.
        DB::statement("ALTER TABLE brand_profiles ADD CONSTRAINT brand_profiles_owner_unique
            UNIQUE NULLS NOT DISTINCT (owner_type, owner_id)");
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_profiles');
    }
};
