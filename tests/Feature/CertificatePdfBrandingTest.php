<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Student;
use App\Services\CertificatePdfBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Riscritto il 06/06: il certificato non è più renderizzato da una Blade
 * (pdf/certificate.blade.php + dompdf), ma generato da CertificatePdfBuilder
 * via FPDI/TCPDF (overlay su template PDF). La vecchia suite testava la Blade
 * ormai rimossa (commit 38e82d1) e falliva per view inesistente.
 *
 * Qui si verifica ciò che resta logica nostra e non rendering di terze parti:
 *  - il builder produce un PDF valido a partire da un Certificate;
 *  - il branding (intestatario) è preso da settings, non hardcoded, e propaga
 *    nei metadati del documento (con fallback al default cablato).
 */
class CertificatePdfBrandingTest extends TestCase
{
    use RefreshDatabase;

    private static bool $fontsRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();

        // CertificatePdfBuilder usa font TCPDF custom (Cormorant Garamond, Inter)
        // generati dai .ttf in storage/fonts/. In un checkout pulito / in CI non
        // sono ancora registrati: senza, SetFont lancerebbe. La registrazione è
        // idempotente, la eseguiamo una volta per processo.
        if (!self::$fontsRegistered) {
            Artisan::call('pdf:register-tcpdf-fonts');
            self::$fontsRegistered = true;
        }
    }

    private function makeCertificate(): Certificate
    {
        $student = Student::create([
            'name' => 'Mario Rossi',
            'email' => 'mario+' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pw'),
            'is_active' => true,
        ]);
        $course = Course::create([
            'name' => 'Corso Test',
            'slug' => 'corso-' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return Certificate::create([
            'student_id' => $student->id,
            'course_id'  => $course->id,
            'code'       => 'TEST-' . strtoupper(uniqid()),
            'score'      => 90,
            'issued_at'  => now(),
            'certification_name' => 'Certificato Test',
        ]);
    }

    public function test_builder_produces_a_valid_pdf(): void
    {
        $pdf = (new CertificatePdfBuilder())->build($this->makeCertificate());

        // Header e trailer di un PDF ben formato + dimensione non banale
        // (il template viene importato, quindi sono decine di KB).
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertGreaterThan(2000, strlen($pdf), 'PDF troppo piccolo: build incompleta');
    }

    public function test_owner_branding_comes_from_settings(): void
    {
        // Default cablato: senza platform_owner il documento usa "Noscite SRLS".
        $pdfDefault = (new CertificatePdfBuilder())->build($this->makeCertificate());
        $this->assertStringContainsString('Noscite SRLS', $pdfDefault,
            'Senza setting, il PDF deve riportare l\'intestatario di default');

        // Con platform_owner valorizzato (ASCII, così resta literal nei metadati
        // PDF e l\'asserzione non dipende dalla compressione degli stream di
        // contenuto), il nuovo intestatario deve propagare nel documento e il
        // default Noscite non deve più comparire.
        atheneum_setting_put('platform_owner', 'ACME Formazione SRL');

        $pdfBranded = (new CertificatePdfBuilder())->build($this->makeCertificate());
        $this->assertStringContainsString('ACME Formazione SRL', $pdfBranded,
            'Il PDF deve riportare l\'intestatario impostato via settings');
        $this->assertStringNotContainsString('Noscite SRLS', $pdfBranded,
            'L\'intestatario di default non deve sopravvivere quando platform_owner è settato');
    }
}
