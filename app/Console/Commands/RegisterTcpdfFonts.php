<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use TCPDF_FONTS;

class RegisterTcpdfFonts extends Command
{
    protected $signature = 'pdf:register-tcpdf-fonts';
    protected $description = 'Registra font custom (Cormorant Garamond, Inter) in TCPDF per il certificato.';

    /**
     * I .ttf in storage/fonts/ sono variable fonts scaricati nella
     * migrazione PNG precedente: niente statici weight-specifici.
     * TCPDF accetta variable font come TTF normali — l'asse interno
     * viene ignorato, ma la famiglia è registrata correttamente e i
     * caratteri Unicode (macron, ecc.) sono coperti.
     *
     * Mappiamo:
     *  - CormorantGaramond-Variable.ttf        → roman regular/bold
     *  - CormorantGaramond-Italic-Variable.ttf → italic regular/bold
     *  - Inter-Variable.ttf                    → sans regular/bold
     *
     * I nomi TCPDF assegnati (stampati a video) DEVONO matchare quelli
     * usati in CertificatePdfBuilder::COORDS['font'].
     */
    public function handle(): int
    {
        $fonts = [
            'CormorantGaramond-Regular'    => storage_path('fonts/cormorant-garamond/CormorantGaramond-Variable.ttf'),
            'CormorantGaramond-Italic'     => storage_path('fonts/cormorant-garamond/CormorantGaramond-Italic-Variable.ttf'),
            'CormorantGaramond-Bold'       => storage_path('fonts/cormorant-garamond/CormorantGaramond-Variable.ttf'),
            'CormorantGaramond-BoldItalic' => storage_path('fonts/cormorant-garamond/CormorantGaramond-Italic-Variable.ttf'),
            'Inter-Regular'                => storage_path('fonts/inter/Inter-Variable.ttf'),
            'Inter-Medium'                 => storage_path('fonts/inter/Inter-Variable.ttf'),
        ];

        $registered = [];
        $failed = 0;

        foreach ($fonts as $label => $path) {
            if (!file_exists($path)) {
                $this->error("  MISSING {$label}: {$path}");
                $failed++;
                continue;
            }
            $fontname = TCPDF_FONTS::addTTFfont($path, 'TrueTypeUnicode', '', 32);
            if ($fontname === false) {
                $this->error("  ✗ {$label} — addTTFfont failed");
                $failed++;
            } else {
                $this->info("  ✓ {$label} → TCPDF nome: {$fontname}");
                $registered[$label] = $fontname;
            }
        }

        $this->newLine();
        $this->info('Registrazione completata. Nomi TCPDF da usare in CertificatePdfBuilder::COORDS[\'font\']:');
        foreach ($registered as $label => $fontname) {
            $this->line("  '{$label}' → '{$fontname}'");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
