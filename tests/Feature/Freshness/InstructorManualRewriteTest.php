<?php

namespace Tests\Feature\Freshness;

use App\Jobs\RewriteInstructorManualJob;
use App\Models\Course;
use App\Models\CourseSource;
use App\Models\FormatoreSnapshot;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\UpdateProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P25.3f (Livello 2) — Riscrittura semantica del manuale formatore quando il `before` del
 * sorgente non è verbatim nel manuale. Riusa StudentMatchFinder (match) + StudentRewriter
 * (riscrittura) + ProposalApplicator::applyManualPatch (sostituzione verbatim + snapshot).
 */
class InstructorManualRewriteTest extends TestCase
{
    use RefreshDatabase;

    // Il manuale dice "(DPR 249/1998)"; il sorgente (before) dice "modificato dal DPR 235/2007".
    private const MANUAL_SENTENCE = 'Lo Statuto delle studentesse e degli studenti (DPR 249/1998) tutela i diritti.';
    private const SOURCE_BEFORE = 'Lo Statuto delle studentesse e degli studenti (DPR 249/1998 modificato dal DPR 235/2007).';
    private const SOURCE_AFTER = 'Lo Statuto delle studentesse e degli studenti (DPR 249/1998 modificato dal DPR 235/2007, aggiornato nel 2025).';
    private const MANUAL_REWRITTEN = 'Lo Statuto delle studentesse e degli studenti (DPR 249/1998, aggiornato nel 2025) tutela i diritti.';

    private function course(): Course
    {
        return Course::create(['name' => 'BUSSOLA', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function section(Course $course, string $html): InstructorManualSection
    {
        $material = Material::create(['course_id' => $course->id, 'title' => 'Manuale', 'is_instructor_only' => true]);
        return InstructorManualSection::create([
            'material_id' => $material->id, 'course_id' => $course->id,
            'title' => 'Sez', 'anchor' => 'sez-' . uniqid(), 'heading_level' => 2, 'sort_order' => 0,
            'content_html' => $html,
        ]);
    }

    private function queuedProposal(Course $course): UpdateProposal
    {
        return UpdateProposal::create([
            'course_id' => $course->id, 'content_source' => 'instructor', 'block_id' => 'b1', 'sentence_ref' => 0,
            'before' => self::SOURCE_BEFORE, 'after' => self::SOURCE_AFTER, 'reason' => 'aggiornamento',
            'audience' => 'adult', 'status' => 'applied', 'reviewed_at' => now(), 'applied_at' => now(),
            'manual_status' => 'queued',
        ]);
    }

    public function test_job_riscrive_il_manuale_quando_il_before_non_e_verbatim(): void
    {
        $course = $this->course();
        CourseSource::create(['course_id' => $course->id, 'version' => '2.1', 'blocks' => [['id' => 'b1', 'type' => 'P', 'text' => self::SOURCE_AFTER]]]);
        $section = $this->section($course, '<p>' . self::MANUAL_SENTENCE . '</p>');
        $p = $this->queuedProposal($course);

        // Il finder deve restituire il module_id REALE della sezione.
        Http::fake(['api.anthropic.com/*' => function ($request) use ($section) {
            $system = $request->data()['system'] ?? '';
            if (str_contains($system, 'analista')) {
                return Http::response(['content' => [['type' => 'text', 'text' => json_encode([
                    'matches' => [['module_id' => $section->id, 'sentence' => self::MANUAL_SENTENCE, 'confidence' => 0.93]], 'none' => false,
                ])]]], 200);
            }
            return Http::response(['content' => [['type' => 'text', 'text' => json_encode([
                'after' => self::MANUAL_REWRITTEN, 'reason' => 'aggiunto riferimento 2025',
            ])]]], 200);
        }]);

        app(RewriteInstructorManualJob::class, ['proposalId' => $p->id])->handle(
            app(\App\Services\Freshness\StudentMatchFinder::class),
            app(\App\Services\Freshness\StudentRewriter::class),
            app(\App\Services\Freshness\ProposalApplicator::class),
        );

        // Manuale aggiornato col dato 2025, frase originale sostituita.
        $section->refresh();
        $this->assertStringContainsString('aggiornato nel 2025', $section->content_html);
        $this->assertStringNotContainsString(self::MANUAL_SENTENCE, $section->content_html);

        // Proposta marcata 'rewritten' con audit del before/after manuale.
        $p->refresh();
        $this->assertSame('rewritten', $p->manual_status);
        $this->assertSame(self::MANUAL_SENTENCE, $p->manual_before);
        $this->assertSame(self::MANUAL_REWRITTEN, $p->manual_after);

        // Snapshot pre-patch creato (rollback possibile).
        $this->assertTrue(FormatoreSnapshot::where('course_id', $course->id)
            ->where('instructor_manual_section_id', $section->id)->exists());
    }

    public function test_job_segna_unmatched_se_il_fatto_non_e_nel_manuale(): void
    {
        $course = $this->course();
        CourseSource::create(['course_id' => $course->id, 'version' => '2.1', 'blocks' => [['id' => 'b1', 'type' => 'P', 'text' => self::SOURCE_AFTER]]]);
        $section = $this->section($course, '<p>Un argomento completamente diverso, senza riferimenti normativi.</p>');
        $p = $this->queuedProposal($course);

        // Finder: nessun match.
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text',
            'text' => json_encode(['matches' => [], 'none' => true])]]], 200)]);

        app(RewriteInstructorManualJob::class, ['proposalId' => $p->id])->handle(
            app(\App\Services\Freshness\StudentMatchFinder::class),
            app(\App\Services\Freshness\StudentRewriter::class),
            app(\App\Services\Freshness\ProposalApplicator::class),
        );

        $this->assertSame('unmatched', $p->refresh()->manual_status);
        $this->assertStringContainsString('argomento completamente diverso', $section->refresh()->content_html); // intatto
    }
}
