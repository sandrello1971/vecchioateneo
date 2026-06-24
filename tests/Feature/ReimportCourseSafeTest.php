<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use App\Models\Module;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * course:reimport-safe — orchestratore sicuro. Dry-run di default; con --confirm
 * backup verificato (abort se troncato) + write. Riusa course:reimport-from-markdown.
 * Il backup è iniettato (DatabaseBackupService) → mockato nei test (niente pg_dump).
 */
class ReimportCourseSafeTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourseWithCanvas(string $slug): array
    {
        $course = Course::create(['name' => 'Corso ' . $slug, 'slug' => $slug, 'is_active' => true, 'sort_order' => 1]);
        $mod = Module::create(['course_id' => $course->id, 'title' => 'Vecchio', 'content' => '<p>old</p>', 'sort_order' => 0, 'is_active' => true]);
        $canvas = Material::create([
            'module_id' => $mod->id, 'title' => 'Canvas', 'file_type' => 'canvas',
            'file_path' => 'materials/c.html', 'is_downloadable' => false, 'is_instructor_only' => false, 'sort_order' => 0,
        ]);

        return [$course, $mod, $canvas];
    }

    private function writeMd(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'safe_') . '.md';
        file_put_contents($path, $content);

        return $path;
    }

    /** Backup fake: scrive un file finto valido/troncato senza pg_dump. */
    private function fakeBackup(bool $valid): void
    {
        $fake = new class($valid) extends DatabaseBackupService {
            public function __construct(private bool $valid) {}

            public function dump(string $label, string $timestamp): string
            {
                $path = tempnam(sys_get_temp_dir(), 'bk_') . '.sql';
                file_put_contents($path, 'FAKE DUMP');

                return $path;
            }

            public function isValid(string $path): bool
            {
                return $this->valid;
            }
        };
        $this->app->instance(DatabaseBackupService::class, $fake);
    }

    // ============================================================
    // Dry-run (default): nessuna scrittura, nessun backup
    // ============================================================

    public function test_dry_run_non_scrive_e_indica_confirm(): void
    {
        [$course, $mod] = $this->makeCourseWithCanvas('safe-a');
        $md = $this->writeMd("# Cap 1\n\nProsa uno lunga abbastanza da non essere un'intestazione corta di modulo.\n\n# Cap 2\n\nProsa due.");

        $this->artisan('course:reimport-safe', ['course_slug' => 'safe-a', 'md_path' => $md])
            ->expectsOutputToContain('Per eseguire: rilancia con --confirm')
            ->assertSuccessful();

        // Niente scrittura: il modulo vecchio resta.
        $this->assertNotNull(Module::find($mod->id));
        $this->assertSame(1, $course->modules()->count());
    }

    // ============================================================
    // --confirm con backup valido: write eseguito, canvas preservato
    // ============================================================

    public function test_confirm_con_backup_valido_scrive_e_preserva_canvas(): void
    {
        $this->fakeBackup(valid: true);
        [$course, $mod, $canvas] = $this->makeCourseWithCanvas('safe-b');
        $md = $this->writeMd("# PARTE PRIMA — INTRO\n\n# Capitolo 1 — Uno\n\nProsa uno lunga e reale del capitolo, con contenuto sufficiente.\n\n# Capitolo 2 — Due\n\nProsa due reale del capitolo.");

        $this->artisan('course:reimport-safe', ['course_slug' => 'safe-b', 'md_path' => $md, '--confirm' => true])
            ->expectsOutputToContain('Backup OK')
            ->expectsOutputToContain('REPORT POST-WRITE')
            ->expectsOutputToContain('PROSSIMO PASSO')
            ->assertSuccessful();

        $this->assertNull(Module::find($mod->id), 'Modulo vecchio cancellato.');
        $this->assertSame(3, $course->modules()->count(), '1 divisore + 2 capitoli.');

        $canvas->refresh();
        $this->assertNull($canvas->module_id, 'Canvas staccato.');
        $this->assertSame($course->id, $canvas->course_id, 'Canvas re-legato al corso (preservato).');
    }

    // ============================================================
    // --confirm con backup troncato: ABORT prima del write
    // ============================================================

    public function test_confirm_con_backup_troncato_aborta_prima_del_write(): void
    {
        $this->fakeBackup(valid: false);
        [$course, $mod] = $this->makeCourseWithCanvas('safe-c');
        $md = $this->writeMd("# Cap 1\n\nProsa reale uno.\n\n# Cap 2\n\nProsa reale due.");

        $this->artisan('course:reimport-safe', ['course_slug' => 'safe-c', 'md_path' => $md, '--confirm' => true])
            ->expectsOutputToContain('ABORT')
            ->assertFailed();

        // Nessuna scrittura: il modulo vecchio resta intatto.
        $this->assertNotNull(Module::find($mod->id));
        $this->assertSame(1, $course->modules()->count());
    }

    // ============================================================
    // Avvisi: modulo-intestazione corto + split-level 2
    // ============================================================

    public function test_avviso_modulo_intestazione_corto(): void
    {
        $this->makeCourseWithCanvas('safe-d');
        // Un # con corpo cortissimo (< 150 char) → contenuto, ma sospetto intestazione.
        $md = $this->writeMd("# MANUALE DISCENTE\n\nbreve.\n\n# Capitolo 1\n\nProsa reale e sufficientemente lunga del primo capitolo del manuale.");

        $this->artisan('course:reimport-safe', ['course_slug' => 'safe-d', 'md_path' => $md])
            ->expectsOutputToContain('DA VERIFICARE')
            ->expectsOutputToContain('possibile intestazione')
            ->assertSuccessful();
    }

    public function test_avviso_split_level_2(): void
    {
        $this->makeCourseWithCanvas('safe-e');
        $md = $this->writeMd("# Titolo manuale\n\nintro.\n\n## Cap A\n\nprosa reale lunga del capitolo A del manuale didattico.\n\n## Cap B\n\nprosa reale lunga del capitolo B.");

        $this->artisan('course:reimport-safe', ['course_slug' => 'safe-e', 'md_path' => $md])
            ->expectsOutputToContain('split-level 2')
            ->assertSuccessful();
    }

    // ============================================================
    // Validazione: corso inesistente, file mancante
    // ============================================================

    public function test_corso_inesistente_messaggio_chiaro(): void
    {
        $md = $this->writeMd("# Cap 1\n\nprosa.");
        $this->artisan('course:reimport-safe', ['course_slug' => 'non-esiste', 'md_path' => $md])
            ->expectsOutputToContain('Corso non trovato')
            ->assertFailed();
    }

    public function test_file_mancante_messaggio_chiaro(): void
    {
        $this->makeCourseWithCanvas('safe-f');
        $this->artisan('course:reimport-safe', ['course_slug' => 'safe-f', 'md_path' => '/tmp/non-esiste-xyz.md'])
            ->expectsOutputToContain('File .md non trovato')
            ->assertFailed();
    }
}
