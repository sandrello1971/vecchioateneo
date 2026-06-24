<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P26.2 — Pivot pesato corso↔topic (molti-a-molti). weight: primary | secondary. La pivot è la
// fonte di verità dei topic del corso; course_freshness_configs.topic resta per retrocompat.
// Transizione: i topic singoli già impostati vengono copiati come 'primary' (non si perde nulla).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_topics', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('topic'); // slug del dominio
            $table->string('weight', 12)->default('secondary'); // primary | secondary
            $table->timestamps();

            $table->unique(['course_id', 'topic']);
            $table->index('topic');
        });

        DB::statement("ALTER TABLE course_topics ADD CONSTRAINT course_topics_weight_check
            CHECK (weight IN ('primary', 'secondary'))");

        // Transizione retrocompat: topic singolo esistente → primary nella pivot.
        \App\Models\CourseTopic::backfillFromConfigs();
    }

    public function down(): void
    {
        Schema::dropIfExists('course_topics');
    }
};
