<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Firmatari dei certificati configurabili da UID/DB invece che via singola
 * env LEGAL_REPRESENTATIVE_EMAIL: più amministratori possono essere abilitati
 * alla firma dalla pagina /admin/admins.
 *
 * Backfill: il legale rappresentante storicamente configurato
 * (config atheneum.legal_representative_email) viene marcato firmatario, così
 * il deploy non lascia la piattaforma senza nessuno autorizzato a firmare.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->boolean('can_sign_certificates')->default(false)->after('is_active');
        });

        $legalRep = config('atheneum.legal_representative_email');
        if (!empty($legalRep)) {
            DB::table('admins')
                ->whereRaw('lower(email) = ?', [strtolower($legalRep)])
                ->update(['can_sign_certificates' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('can_sign_certificates');
        });
    }
};
