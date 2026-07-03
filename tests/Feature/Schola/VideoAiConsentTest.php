<?php

namespace Tests\Feature\Schola;

use App\Jobs\ExtractTeachingDocumentJob;
use App\Models\School;
use App\Models\Student;
use App\Models\TeachingDocument;
use App\Services\Schola\TeachingDocumentExtractor;
use App\Support\VideoAiConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

/**
 * R5 — gate compliance: i materiali Schola che passano da sub-processori esterni
 * (audio/video→Whisper, foto→Vision) sono BLOCCATI senza consenso DPA della scuola.
 * I generati (embedding locali) e Officina business non sono toccati.
 */
class VideoAiConsentTest extends TestCase
{
    use RefreshDatabase;

    private function school(bool $dpa): School
    {
        return School::create([
            'name' => 'Liceo X', 'slug' => 'liceo-' . uniqid(), 'type' => 'liceo', 'status' => 'active',
            'video_ai_dpa_accepted_at' => $dpa ? now() : null,
        ]);
    }

    private function teacher(?School $school): Student
    {
        return Student::create(['name' => 'P', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false, 'school_id' => $school?->id]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    // ---- logica del gate ----

    public function test_blocked_schola_senza_dpa_su_tipo_esterno(): void
    {
        $t = $this->teacher($this->school(false));
        $this->assertTrue(VideoAiConsent::blocked($t, 'audio'));
        $this->assertTrue(VideoAiConsent::blocked($t, 'photos'));
        $this->assertTrue(VideoAiConsent::blocked($t, 'youtube'));
    }

    public function test_non_blocked_con_dpa_o_tipo_locale_o_officina(): void
    {
        $this->assertFalse(VideoAiConsent::blocked($this->teacher($this->school(true)), 'audio'), 'con DPA → ok');
        $this->assertFalse(VideoAiConsent::blocked($this->teacher($this->school(false)), 'pdf'), 'pdf locale → ok');
        $this->assertFalse(VideoAiConsent::blocked($this->teacher($this->school(false)), 'text'), 'testo locale → ok');
        $this->assertFalse(VideoAiConsent::blocked($this->teacher(null), 'audio'), 'Officina (no scuola) → ok');
    }

    // ---- backstop nel job (anche su dispatch diretto) ----

    public function test_job_backstop_non_chiama_estrattore_senza_dpa(): void
    {
        $t = $this->teacher($this->school(false));
        $doc = TeachingDocument::create(['teacher_id' => $t->id, 'title' => 'Audio', 'source_type' => 'audio', 'status' => 'pending']);

        $extractor = Mockery::mock(TeachingDocumentExtractor::class);
        $extractor->shouldNotReceive('extract'); // niente Whisper/Vision
        (new ExtractTeachingDocumentJob($doc->id))->handle($extractor);

        $doc->refresh();
        $this->assertSame('failed', $doc->status);
        $this->assertStringContainsString('DPA', $doc->failure_reason);
    }

    // ---- gate nel controller di upload (e2e) ----

    public function test_upload_audio_schola_senza_dpa_rifiutato(): void
    {
        Bus::fake();
        $t = $this->teacher($this->school(false));

        $this->asProf($t)->post(route('docente.materials.store'), [
            'title' => 'Lezione', 'source_type' => 'audio',
            'file' => UploadedFile::fake()->create('a.mp3', 100, 'audio/mpeg'),
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('teaching_documents', 0); // nessun doc creato
        Bus::assertNotDispatched(ExtractTeachingDocumentJob::class);
    }

    public function test_upload_pdf_schola_senza_dpa_consentito(): void
    {
        Bus::fake();
        $t = $this->teacher($this->school(false));

        $this->asProf($t)->post(route('docente.materials.store'), [
            'title' => 'Dispensa', 'source_type' => 'pdf',
            'file' => UploadedFile::fake()->create('d.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseCount('teaching_documents', 1); // locale → consentito
        Bus::assertDispatched(ExtractTeachingDocumentJob::class);
    }

    public function test_upload_audio_schola_con_dpa_consentito(): void
    {
        Bus::fake();
        $t = $this->teacher($this->school(true));

        $this->asProf($t)->post(route('docente.materials.store'), [
            'title' => 'Lezione', 'source_type' => 'audio',
            'file' => UploadedFile::fake()->create('a.mp3', 100, 'audio/mpeg'),
        ])->assertRedirect();

        $this->assertDatabaseCount('teaching_documents', 1);
        Bus::assertDispatched(ExtractTeachingDocumentJob::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
