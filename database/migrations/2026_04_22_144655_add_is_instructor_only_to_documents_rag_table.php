<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents_rag', function (Blueprint $table) {
            $table->boolean('is_instructor_only')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('documents_rag', function (Blueprint $table) {
            $table->dropIndex(['is_instructor_only']);
            $table->dropColumn('is_instructor_only');
        });
    }
};
