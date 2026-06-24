<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Presa di responsabilità sui diritti del contenuto alla PRIMA condivisione in
// Biblioteca docenti (§6). Persistita: una sola volta per docente.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('library_rights_ack_at')->nullable()->after('birth_date');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('library_rights_ack_at');
        });
    }
};
