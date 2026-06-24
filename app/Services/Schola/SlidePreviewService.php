<?php

namespace App\Services\Schola;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * S1 — anteprima slide in-app: renderizza il .pptx di una presentazione
 * (lezione o modulo) in un PNG per slide. Pipeline già verificata in diagnosi:
 * LibreOffice headless (.pptx → .pdf) + pdf2image (venv) (.pdf → .png).
 *
 * Agnostico alla sorgente: lavora sul path RELATIVO del .pptx sul disk 'local'
 * (storage privato), così serve sia lesson- sia module-presentations. I PNG
 * sono cache accanto al .pptx; si rigenerano solo se mancanti o se il .pptx è
 * più recente (mtime) → niente render inutili. Mai URL diretto: i PNG vivono in
 * storage privato e li serve un controller.
 */
class SlidePreviewService
{
    private const DPI = 130;
    private const DISK = 'local';

    /**
     * Path RELATIVI (disk 'local') dei PNG delle slide, ordinati per numero.
     * Render lazy con cache: rende tutte le slide al primo accesso.
     *
     * @return array<int, string>
     */
    public function imagesFor(string $pptxRelPath): array
    {
        $disk = Storage::disk(self::DISK);
        if (!$disk->exists($pptxRelPath)) {
            throw new RuntimeException('File della presentazione non trovato per l\'anteprima.');
        }

        $cacheDir = $this->cacheDir($pptxRelPath);
        $cached = $this->cachedImages($cacheDir);

        if ($cached !== [] && $this->cacheIsFresh($cached, $pptxRelPath)) {
            return $cached;
        }

        // Una pagina carica N <img> in lazy: senza serializzazione ogni richiesta
        // lancerebbe un render LibreOffice. Il lock fa renderizzare una sola volta;
        // le altre richieste attendono e leggono dalla cache appena pronta.
        $lock = Cache::lock('slide-preview:' . md5($pptxRelPath), 120);
        $lock->block(90);
        try {
            $cached = $this->cachedImages($cacheDir);
            if ($cached !== [] && $this->cacheIsFresh($cached, $pptxRelPath)) {
                return $cached;
            }
            $this->render($pptxRelPath, $cacheDir);

            return $this->cachedImages($cacheDir);
        } finally {
            $lock->release();
        }
    }

    /** Directory di cache dei PNG: il path del .pptx senza estensione. */
    private function cacheDir(string $pptxRelPath): string
    {
        return preg_replace('/\.pptx$/i', '', $pptxRelPath);
    }

    /**
     * PNG già in cache, ordinati per numero di slide (slide_1, slide_2, …).
     *
     * @return array<int, string>
     */
    private function cachedImages(string $cacheDir): array
    {
        $disk = Storage::disk(self::DISK);
        $files = array_values(array_filter(
            $disk->files($cacheDir),
            fn (string $p) => (bool) preg_match('#/slide_\d+\.png$#', $p),
        ));

        usort($files, fn ($a, $b) => $this->slideNumber($a) <=> $this->slideNumber($b));

        return $files;
    }

    private function slideNumber(string $path): int
    {
        preg_match('/slide_(\d+)\.png$/', $path, $m);

        return (int) ($m[1] ?? 0);
    }

    /** La cache è valida se il .pptx non è più recente del PNG meno recente. */
    private function cacheIsFresh(array $cached, string $pptxRelPath): bool
    {
        $disk = Storage::disk(self::DISK);
        $pptxMtime = $disk->lastModified($pptxRelPath);
        foreach ($cached as $png) {
            if ($disk->lastModified($png) < $pptxMtime) {
                return false;
            }
        }

        return true;
    }

    /** (Ri)render: .pptx → .pdf (LibreOffice) → PNG (pdf2image). Pulisce prima la cache. */
    private function render(string $pptxRelPath, string $cacheDir): void
    {
        $disk = Storage::disk(self::DISK);

        // Svuota la cache vecchia: se la nuova versione ha meno slide, niente PNG orfani.
        foreach ($disk->files($cacheDir) as $old) {
            if (preg_match('#/slide_\d+\.png$#', $old)) {
                $disk->delete($old);
            }
        }
        $disk->makeDirectory($cacheDir);

        $pptxAbs = $disk->path($pptxRelPath);
        $cacheAbs = $disk->path($cacheDir);

        // Profilo LibreOffice univoco per evitare lock tra render concorrenti.
        $profile = 'file://' . sys_get_temp_dir() . '/lo_profile_' . Str::uuid();
        $workDir = rtrim(sys_get_temp_dir(), '/') . '/slidepdf_' . Str::uuid();
        @mkdir($workDir, 0755, true);

        try {
            $soffice = new Process([
                'soffice', '--headless', '-env:UserInstallation=' . $profile,
                '--convert-to', 'pdf', '--outdir', $workDir, $pptxAbs,
            ]);
            $soffice->setTimeout(120);
            $soffice->run();
            if (!$soffice->isSuccessful()) {
                throw new RuntimeException('Conversione PDF fallita: ' . trim($soffice->getErrorOutput() ?: $soffice->getOutput()));
            }

            $pdf = $workDir . '/' . pathinfo($pptxAbs, PATHINFO_FILENAME) . '.pdf';
            if (!is_file($pdf)) {
                throw new RuntimeException('PDF intermedio non prodotto dall\'anteprima.');
            }

            $python = config('services.pptx.python', '/home/noscite/venv/bin/python');
            $script = base_path('resources/python/pdf_to_images.py');
            $toPng = new Process([$python, $script, $pdf, $cacheAbs, (string) self::DPI]);
            $toPng->setTimeout(120);
            $toPng->run();
            if (!$toPng->isSuccessful()) {
                throw new RuntimeException('Render PNG fallito: ' . trim($toPng->getErrorOutput() ?: $toPng->getOutput()));
            }
        } finally {
            $this->rmrf($workDir);
            $this->rmrf(str_replace('file://', '', $profile));
        }
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
