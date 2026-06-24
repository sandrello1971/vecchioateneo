<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            // Secret TOTP encrypted (Crypt::encryptString via Admin mutator)
            $table->text('two_factor_secret')->nullable()->after('password');
            // Recovery codes JSON encrypted (8 codici monouso)
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            // Timestamp di conferma attivazione (NULL = setup avviato ma non confermato)
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
