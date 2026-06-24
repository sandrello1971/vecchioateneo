<?php

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

class CertificatePdfBuilder
{
    /**
     * Path relativo al base_path() del template PDF vettoriale.
     * Il template è caricato come "pagina importata" da FPDI e gli
     * elementi dinamici sono scritti sopra con TCPDF a coordinate in mm.
     */
    private const TEMPLATE_PATH = 'resources/pdf/templates/certificate-default.pdf';

    /**
     * Coordinate Y (in mm) dei campi dinamici sul template A4 landscape
     * (297×210mm). Aggiustabili senza toccare altro codice.
     * Font names devono matchare quelli stampati da
     * `php artisan pdf:register-tcpdf-fonts`:
     *   cormorantgaramondvariable   = roman (regular/bold equivalenti, variable font)
     *   cormorantgaramondivariable  = italic (regular/bold equivalenti)
     *   intervariable               = sans (regular/bold equivalenti)
     */
    private const COORDS = [
        'student_name'   => ['y' => 72.0,  'font' => 'cormorantgaramondivariable', 'size' => 36, 'color' => [26, 31, 31]],
        'course_name'    => ['y' => 96.0,  'font' => 'cormorantgaramondvariable',  'size' => 22, 'color' => [85, 177, 174], 'uppercase' => true, 'spacing' => 0.8],
        'cert_subtitle'  => ['y' => 110.0, 'font' => 'cormorantgaramondivariable', 'size' => 13, 'color' => [226, 138, 83]],
        'score'          => ['y' => 124.0, 'font' => 'cormorantgaramondivariable', 'size' => 13, 'color' => [85, 177, 174]],
        'date_value'     => ['y' => 150.0, 'font' => 'cormorantgaramondvariable',  'size' => 12, 'color' => [26, 31, 31]],
        'code_value'     => ['y' => 166.0, 'font' => 'intervariable',              'size' => 11, 'color' => [26, 31, 31], 'spacing' => 0.3],
        'owner_value'    => ['y' => 183.0, 'font' => 'cormorantgaramondvariable',  'size' => 12, 'color' => [26, 31, 31]],
    ];

    /**
     * Genera i bytes del PDF certificato.
     */
    public function build(Certificate $cert): string
    {
        $student = $cert->student;
        $course = $cert->course; // null safe: lo snapshot del Certificate ha tutti i dati
        $verifyUrl = route('certificate.verify', ['code' => $cert->code]);
        $date = Carbon::parse($cert->issued_at)->locale('it')->isoFormat('D MMMM YYYY');

        $templateAbsPath = base_path(self::TEMPLATE_PATH);
        if (!file_exists($templateAbsPath)) {
            throw new \RuntimeException("Template PDF mancante: {$templateAbsPath}");
        }

        $pdf = new Fpdi();
        $pdf->setSourceFile($templateAbsPath);
        $tplId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tplId);

        $pdf->SetCreator('Atheneum');
        $pdf->SetAuthor(atheneum_setting('platform_owner', 'Noscite SRLS'));
        $pdf->SetTitle("Certificato {$cert->code}");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0);

        // Per AddPage: TCPDF si aspetta format in ordine PORTRAIT [smaller,
        // larger]; per orientation 'L' swappa. Passare già un format in
        // ordine landscape porta a una pagina con dimensioni invertite.
        $isLandscape = $size['width'] >= $size['height'];
        $portraitW = min($size['width'], $size['height']);
        $portraitH = max($size['width'], $size['height']);
        $orientation = $isLandscape ? 'L' : 'P';
        $pdf->AddPage($orientation, [$portraitW, $portraitH]);
        $pdf->useTemplate($tplId);

        $pageW = $isLandscape ? $portraitH : $portraitW;

        // Helper inline per scrivere testo centrato orizzontalmente a y dato
        $writeCentered = function (string $text, array $cfg) use ($pdf, $pageW): void {
            $renderText = !empty($cfg['uppercase']) ? mb_strtoupper($text) : $text;
            $pdf->SetFont($cfg['font'], '', $cfg['size']);
            [$r, $g, $b] = $cfg['color'];
            $pdf->SetTextColor($r, $g, $b);
            $pdf->setFontSpacing($cfg['spacing'] ?? 0);
            $pdf->SetXY(0, $cfg['y']);
            $pdf->Cell($pageW, 8, $renderText, 0, 0, 'C');
            $pdf->setFontSpacing(0); // reset
        };

        // === Campi dinamici ===
        $writeCentered($student->name, self::COORDS['student_name']);

        $courseName = $course?->name ?? $cert->certification_name;
        $writeCentered($courseName, self::COORDS['course_name']);

        if ($cert->certification_name && $cert->certification_name !== $courseName) {
            $writeCentered($cert->certification_name, self::COORDS['cert_subtitle']);
        }

        // Score: il template ha un oval grande con placeholder "Punteggio".
        // Strategia in 3 step:
        //   1. eraser bianco rettangolare per coprire l'oval del template
        //      (e cancellare il watermark in quella zona, accettabile);
        //   2. nuovo pill più piccolo disegnato via codice (stroke teal,
        //      fill bianco) — più compatto per scelta UI dell'utente;
        //   3. testo "Punteggio: NN%" centrato nel nuovo pill, stesso font.
        if ($cert->score) {
            // 1. Eraser sopra l'oval del template
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect(95.0, 120.0, 110.0, 18.0, 'F');

            // 2. Nuovo pill compatto, centrato sulla pagina
            $pillW = 55.0;
            $pillH = 10.0;
            $pillX = (297.0 - $pillW) / 2.0;
            $pillY = 124.0;
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(85, 177, 174);
            $pdf->SetLineWidth(0.4);
            $pdf->RoundedRect($pillX, $pillY, $pillW, $pillH, $pillH / 2.0, '1111', 'DF');

            // 3. Testo
            $cfg = self::COORDS['score'];
            $pdf->SetFont($cfg['font'], '', $cfg['size']);
            [$r, $g, $b] = $cfg['color'];
            $pdf->SetTextColor($r, $g, $b);
            $pdf->SetXY($pillX, $pillY);
            $pdf->Cell($pillW, $pillH, "Punteggio: {$cert->score}%", 0, 0, 'C');
        }

        $writeCentered($date, self::COORDS['date_value']);
        $writeCentered($cert->code, self::COORDS['code_value']);
        $writeCentered(atheneum_setting('platform_owner', 'Noscite SRLS'), self::COORDS['owner_value']);

        // === QR + verify URL (basso a sinistra) ===
        // CSS spec: verify-block left:25mm, top:162mm, width:45mm;
        // QR interno 22mm × 22mm centrato → x=36.5, y=162.
        $qrX = 36.5;
        $qrY = 162.0;
        $qrSize = 22.0;
        $pdf->write2DBarcode($verifyUrl, 'QRCODE,M', $qrX, $qrY, $qrSize, $qrSize, [
            'border'  => 0,
            'padding' => 0,
            'fgcolor' => [26, 31, 31],
            'bgcolor' => false,
        ], 'N');

        // Label "VERIFICA ONLINE" sotto QR (centrata nel verify-block 45mm)
        $blockX = 25.0;
        $blockW = 45.0;
        $pdf->SetFont('intervariable', '', 6);
        $pdf->SetTextColor(138, 150, 150);
        $pdf->setFontSpacing(0.5);
        $pdf->SetXY($blockX, $qrY + $qrSize + 1.5);
        $pdf->Cell($blockW, 3, mb_strtoupper('Verifica online'), 0, 0, 'C');

        // URL sotto label (wrappato, font più piccolo)
        $pdf->SetFont('intervariable', '', 5.5);
        $pdf->SetTextColor(74, 82, 82);
        $pdf->setFontSpacing(0);
        $pdf->SetXY($blockX, $qrY + $qrSize + 5.0);
        $pdf->MultiCell($blockW, 2.5, $verifyUrl, 0, 'C');

        // === Output bytes ===
        // 'S' = ritorna come stringa (i bytes del PDF)
        return $pdf->Output('', 'S');
    }

    /**
     * Genera il PDF e lo salva sul disco 'local' nella cartella unsigned.
     * Ritorna il path relativo (utilizzabile con Storage::disk('local')).
     *
     * Idempotente: se il file esiste già viene sovrascritto. Utile per
     * eventuali rigenerazioni controllate (es. correzione di un dato
     * anagrafico nello snapshot del Certificate).
     */
    public function saveUnsigned(Certificate $cert): string
    {
        $path = $this->unsignedPathFor($cert);
        Storage::disk('local')->put($path, $this->build($cert));
        return $path;
    }

    /**
     * Convenzione path per PDF non firmato.
     * Il code del Certificate è univoco e validato a creazione,
     * quindi safe come componente del filename (no path traversal).
     */
    public function unsignedPathFor(Certificate $cert): string
    {
        return "certificates/unsigned/{$cert->code}.pdf";
    }

    /**
     * Convenzione path per PDF firmato. Usata dall'admin UI
     * quando il legale rappresentante carica la versione firmata.
     */
    public function signedPathFor(Certificate $cert): string
    {
        return "certificates/signed/{$cert->code}.pdf";
    }
}
