<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class RebuildCertificatePdfs extends Command
{
    protected $signature = 'certificates:rebuild-pdfs
                            {--code= : Codice certificato specifico (es. ATH-AC3T-8GRM-ZZKG)}
                            {--since= : Data ISO da cui filtrare per created_at >= (es. 2026-05-01)}
                            {--include-signed : Include anche i PDF firmati (richiede --force)}
                            {--force : Conferma esplicita necessaria per --include-signed}
                            {--dry-run : Mostra cosa farebbe senza cancellare nulla}';

    protected $description = 'Invalida i PDF certificati cachati su disco. Il PDF sarà rigenerato dal CertificatePdfBuilder al prossimo download.';

    public function handle(): int
    {
        $code = $this->option('code');
        $since = $this->option('since');
        $includeSigned = (bool) $this->option('include-signed');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        if ($includeSigned && !$force && !$dryRun) {
            $this->error('--include-signed cancella anche i PDF firmati e si perdono le firme eIDAS.');
            $this->line('Richiede --force per conferma esplicita.');
            $this->line('Suggerimento: lancia prima con --dry-run --include-signed per vedere quanti.');
            return self::FAILURE;
        }

        $query = Certificate::query();
        if ($code) {
            $query->where('code', $code);
        }
        if ($since) {
            try {
                $sinceDate = Carbon::parse($since);
            } catch (\Exception $e) {
                $this->error("Data --since non valida: '{$since}'. Usa formato ISO (es. 2026-05-01).");
                return self::FAILURE;
            }
            $query->where('created_at', '>=', $sinceDate);
        }

        $total = $query->count();
        if ($total === 0) {
            $this->warn('Nessun certificato matchato dai filtri. Niente da fare.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Trovati %d certificati matchanti.', $total));
        if ($dryRun) {
            $this->warn('DRY RUN — nessun file sarà cancellato.');
        }
        $this->newLine();

        $disk = Storage::disk('local');
        $deletedUnsigned = 0;
        $deletedSigned = 0;
        $skippedNoUnsigned = 0;
        $skippedFileMissing = 0;
        $skippedNoSigned = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk(100, function ($certs) use (
            &$deletedUnsigned, &$deletedSigned,
            &$skippedNoUnsigned, &$skippedFileMissing, &$skippedNoSigned,
            $includeSigned, $dryRun, $disk, $bar
        ) {
            foreach ($certs as $cert) {
                if ($cert->unsigned_pdf_path) {
                    if ($disk->exists($cert->unsigned_pdf_path)) {
                        if (!$dryRun) {
                            $disk->delete($cert->unsigned_pdf_path);
                        }
                        $deletedUnsigned++;
                    } else {
                        $skippedFileMissing++;
                    }
                } else {
                    $skippedNoUnsigned++;
                }

                if ($includeSigned) {
                    if ($cert->signed_pdf_path) {
                        if ($disk->exists($cert->signed_pdf_path)) {
                            if (!$dryRun) {
                                $disk->delete($cert->signed_pdf_path);
                            }
                            $deletedSigned++;
                        }
                    } else {
                        $skippedNoSigned++;
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('=== Report ===');
        $this->line(sprintf('  Certificati esaminati: %d', $total));
        $this->line(sprintf('  Unsigned %s: %d',
            $dryRun ? 'da cancellare' : 'cancellati',
            $deletedUnsigned
        ));
        if ($skippedNoUnsigned > 0) {
            $this->line(sprintf('  Senza unsigned_pdf_path (mai generati): %d', $skippedNoUnsigned));
        }
        if ($skippedFileMissing > 0) {
            $this->line(sprintf('  Path popolato ma file mancante (gia pulito): %d', $skippedFileMissing));
        }
        if ($includeSigned) {
            $this->line(sprintf('  Signed %s: %d',
                $dryRun ? 'da cancellare' : 'cancellati',
                $deletedSigned
            ));
            if ($skippedNoSigned > 0) {
                $this->line(sprintf('  Senza signed_pdf_path: %d', $skippedNoSigned));
            }
        }

        if (!$dryRun && ($deletedUnsigned + $deletedSigned > 0)) {
            $this->newLine();
            $this->info('I PDF saranno rigenerati automaticamente al prossimo download dello studente.');
        }

        return self::SUCCESS;
    }
}
