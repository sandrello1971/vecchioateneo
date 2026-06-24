<?php

namespace Tests\Feature\P26;

use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CourseTopic;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P26.2 — pivot course_topics (pesata) + transizione retrocompat (topic singolo → primary).
 */
class CourseTopicSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Course
    {
        return Course::create(['name' => 'CIRCUITO', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_pivot_pesata_e_helper(): void
    {
        $course = $this->course();
        CourseTopic::create(['course_id' => $course->id, 'topic' => 'gestione-conoscenza', 'weight' => 'primary']);
        CourseTopic::create(['course_id' => $course->id, 'topic' => 'agenti-ai', 'weight' => 'secondary']);

        $this->assertSame('gestione-conoscenza', $course->primaryTopic());
        $this->assertEqualsCanonicalizing(['gestione-conoscenza', 'agenti-ai'], $course->topicSlugs());
    }

    public function test_unique_course_topic(): void
    {
        $course = $this->course();
        CourseTopic::create(['course_id' => $course->id, 'topic' => 'agenti-ai', 'weight' => 'secondary']);
        $this->expectException(QueryException::class);
        CourseTopic::create(['course_id' => $course->id, 'topic' => 'agenti-ai', 'weight' => 'primary']); // stesso (course,topic)
    }

    public function test_check_weight(): void
    {
        $this->expectException(QueryException::class);
        CourseTopic::create(['course_id' => $this->course()->id, 'topic' => 'x', 'weight' => 'tertiary']);
    }

    public function test_transizione_topic_singolo_diventa_primary(): void
    {
        $course = $this->course();
        CourseFreshnessConfig::create(['course_id' => $course->id, 'web_search_enabled' => true,
            'primary_sources' => [], 'audience' => 'adult', 'topic' => 'Agenti AI']); // legacy, non-slug

        $n = CourseTopic::backfillFromConfigs();

        $this->assertGreaterThanOrEqual(1, $n);
        $this->assertDatabaseHas('course_topics', ['course_id' => $course->id, 'topic' => 'agenti-ai', 'weight' => 'primary']);

        // idempotente: non duplica al secondo giro.
        CourseTopic::backfillFromConfigs();
        $this->assertSame(1, CourseTopic::where('course_id', $course->id)->count());
    }
}
