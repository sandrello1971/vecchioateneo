<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_concept_maps', function (Blueprint $table) {
            $table->uuid('module_id')->nullable()->after('course_id');
            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
            $table->index('module_id');
        });

        // Unique parziali (PostgreSQL):
        // - una sola mappa per (course, module) quando module è valorizzato
        // - una sola mappa "corso" (module_id NULL) per corso
        DB::statement('CREATE UNIQUE INDEX course_concept_maps_course_module_unique ON course_concept_maps (course_id, module_id) WHERE module_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX course_concept_maps_course_global_unique ON course_concept_maps (course_id) WHERE module_id IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS course_concept_maps_course_module_unique');
        DB::statement('DROP INDEX IF EXISTS course_concept_maps_course_global_unique');

        Schema::table('course_concept_maps', function (Blueprint $table) {
            $table->dropForeign(['module_id']);
            $table->dropIndex(['module_id']);
            $table->dropColumn('module_id');
        });
    }
};
