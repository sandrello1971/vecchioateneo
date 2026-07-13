<?php

namespace App\Support\Pdf;

use TCPDF;

/**
 * TCPDF con footer di tutela del diritto d'autore stampato in piccolo, in
 * grigio, centrato in fondo a OGNI pagina di ogni documento/PDF generato.
 *
 * La dicitura arriva da config('atheneum.copyright') (fonte unica). Se vuota,
 * il footer non viene stampato. Il font 'dejavusans' è bundled in TCPDF ed è
 * unicode: rende il simbolo © e le lettere accentate senza registrazioni.
 */
class CopyrightTcpdf extends TCPDF
{
    // TCPDF invoca Footer() automaticamente a ogni pagina quando
    // setPrintFooter(true). Nome PascalCase imposto dalla classe base.
    public function Footer(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        $notice = trim((string) config('atheneum.copyright', ''));
        if ($notice === '') {
            return;
        }

        $this->SetY(-12);
        $this->SetFont('dejavusans', '', 7);
        $this->SetTextColor(130, 130, 130);
        $this->Cell(0, 6, $notice, 0, 0, 'C', false, '', 0, false, 'T', 'M');
    }
}
