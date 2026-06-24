<?php

namespace Tests\Feature\Freshness;

use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CourseSource;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\UpdateProposal;
use App\Services\Freshness\ProposalApplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P25.3e — Gate Schola/minori rafforzato. Doppio gate per audience=minor: approvazione
 * (gate 1) + conferma esplicita di applicazione (gate 2). Adult = gate singolo (3c).
 * + backfill euristico idempotente che rispetta gli override manuali.
 */
class MinorGateTest extends TestCase
{
    use RefreshDatabase;

    private const BEFORE = 'Il dato 2025 è questo';
    private const AFTER = 'Il dato 2026 è questo';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function course(string $name = 'Corso'): Course
    {
        return Course::create(['name' => $name, 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function setupApplicable(Course $course): InstructorManualSection
    {
        CourseSource::create(['course_id' => $course->id, 'version' => '2.0', 'blocks' => [
            ['id' => 'b1', 'type' => 'P', 'text' => self::BEFORE],
        ]]);
        $material = Material::create(['course_id' => $course->id, 'title' => 'Manuale', 'is_instructor_only' => true]);
        $section = InstructorManualSection::create([
            'material_id' => $material->id, 'course_id' => $course->id, 'title' => 'Sez',
            'anchor' => 'a-' . uniqid(), 'heading_level' => 2, 'sort_order' => 0,
            'content_html' => '<p>' . self::BEFORE . '.</p>',
        ]);
        UpdateProposal::create([
            'course_id' => $course->id, 'block_id' => 'b1', 'before' => self::BEFORE, 'after' => self::AFTER,
            'audience' => 'minor', 'status' => 'approved', 'reviewed_at' => now(),
        ]);
        return $section;
    }

    private function configAudience(Course $course, string $audience, bool $overridden = false): void
    {
        CourseFreshnessConfig::create([
            'course_id' => $course->id, 'web_search_enabled' => true, 'primary_sources' => [],
            'audience' => $audience, 'audience_overridden' => $overridden, 'proposals_enabled' => true,
        ]);
    }

    // ---- Doppio gate sull'applicazione (service) ----

    public function test_minor_senza_conferma_e_bloccato(): void
    {
        $course = $this->course();
        $this->configAudience($course, 'minor');
        $section = $this->setupApplicable($course);

        $res = app(ProposalApplicator::class)->apply($course); // minorConfirmed=false (default)

        $this->assertSame(0, $res['applied']);
        $this->assertSame('minor_confirmation_required', $res['blocked']);
        $this->assertNull($res['version_to']);
        // Nessuna modifica: proposta resta approved, live intatto, niente nuova versione.
        $this->assertSame('approved', UpdateProposal::where('course_id', $course->id)->first()->status);
        $this->assertStringContainsString(self::BEFORE, $section->refresh()->content_html);
        $this->assertSame(1, CourseSource::where('course_id', $course->id)->count());
    }

    public function test_minor_con_conferma_e_consentito(): void
    {
        $course = $this->course();
        $this->configAudience($course, 'minor');
        $section = $this->setupApplicable($course);

        $res = app(ProposalApplicator::class)->apply($course, minorConfirmed: true);

        $this->assertSame(1, $res['applied']);
        $this->assertNull($res['blocked']);
        $this->assertSame('2.1', $res['version_to']);
        $this->assertStringContainsString(self::AFTER, $section->refresh()->content_html);
    }

    public function test_adult_gate_singolo_nessuna_conferma_extra(): void
    {
        $course = $this->course();
        $this->configAudience($course, 'adult');
        $section = $this->setupApplicable($course);

        $res = app(ProposalApplicator::class)->apply($course); // nessuna conferma extra

        $this->assertSame(1, $res['applied']);
        $this->assertNull($res['blocked']);
        $this->assertStringContainsString(self::AFTER, $section->refresh()->content_html);
    }

    public function test_controller_minor_senza_checkbox_blocca(): void
    {
        $course = $this->course();
        $this->configAudience($course, 'minor');
        $section = $this->setupApplicable($course);

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@ente.it'])
            ->post(route('admin.freshness.proposals.apply', $course)) // niente confirm_minor
            ->assertRedirect();

        // Bloccato: proposta resta approved, live intatto.
        $this->assertSame('approved', UpdateProposal::where('course_id', $course->id)->first()->status);
        $this->assertStringContainsString(self::BEFORE, $section->refresh()->content_html);
    }

    public function test_controller_minor_con_checkbox_applica(): void
    {
        $course = $this->course();
        $this->configAudience($course, 'minor');
        $section = $this->setupApplicable($course);

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@ente.it'])
            ->post(route('admin.freshness.proposals.apply', $course), ['confirm_minor' => '1'])
            ->assertRedirect();

        $this->assertSame('applied', UpdateProposal::where('course_id', $course->id)->first()->status);
        $this->assertStringContainsString(self::AFTER, $section->refresh()->content_html);
    }

    // ---- Backfill euristico ----

    public function test_backfill_marca_minori_e_non_falsa_schola(): void
    {
        $licei = $this->course('LICEI · Umanesimo Digitale e AI');
        $istituti = $this->course('Istituti tecnici · Umanesimo Digitale e AI');
        $scholaAdulti = $this->course('SCHOLA — AI per dirigenti scolastici e docenti');
        $altro = $this->course('CONSILIUM — Strategia AI per PMI');

        $this->artisan('freshness:backfill-audience')->assertExitCode(0);

        $this->assertSame('minor', $licei->fresh()->freshnessConfig->audience);
        $this->assertSame('minor', $istituti->fresh()->freshnessConfig->audience);
        // "scolastici" NON matcha 'scuol' → SCHOLA dirigenti NON marcato (nessuna config minor).
        $this->assertNotSame('minor', optional($scholaAdulti->fresh()->freshnessConfig)->audience);
        $this->assertNotSame('minor', optional($altro->fresh()->freshnessConfig)->audience);
    }

    public function test_backfill_idempotente(): void
    {
        $licei = $this->course('LICEI · Umanesimo Digitale e AI');

        $this->artisan('freshness:backfill-audience');
        $this->artisan('freshness:backfill-audience'); // seconda volta, nessun effetto avverso

        $this->assertSame(1, CourseFreshnessConfig::where('course_id', $licei->id)->count());
        $this->assertSame('minor', $licei->fresh()->freshnessConfig->audience);
    }

    public function test_backfill_rispetta_override_manuale(): void
    {
        // Corso name-minor MA marcato adult manualmente (override) → il backfill NON lo tocca.
        $licei = $this->course('LICEI · Umanesimo Digitale e AI');
        $this->configAudience($licei, 'adult', overridden: true);

        $this->artisan('freshness:backfill-audience')->assertExitCode(0);

        $this->assertSame('adult', $licei->fresh()->freshnessConfig->audience);
    }
}
