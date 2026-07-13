<?php

namespace App\Services;

use App\Models\Course;
use App\Support\Pdf\CopyrightTcpdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Costruisce il PDF del registro di frequenza di un corso: intestazione con i
 * dati del corso, tabella discenti con ore sincrono / asincrono / totale, e le
 * sessioni sincrone svolte. Font unicode 'dejavusans' (bundled) per accenti.
 */
class AttendanceRegisterPdfBuilder
{
    /**
     * @param  Collection<int, array>  $rows   output di AttendanceService::courseRegister()
     * @param  Collection<int, \App\Models\CourseSession>  $sessions
     */
    public function buildCourseRegister(Course $course, Collection $rows, Collection $sessions): string
    {
        $owner = atheneum_setting('platform_owner', 'Stefano Andrello');

        $pdf = new CopyrightTcpdf('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Officina');
        $pdf->SetAuthor($owner);
        $pdf->SetTitle('Registro di frequenza — ' . $course->name);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        // Intestazione.
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->SetTextColor(26, 31, 31);
        $pdf->Cell(0, 9, 'Registro di frequenza', 0, 1, 'L');

        $pdf->SetFont('dejavusans', '', 11);
        $pdf->SetTextColor(85, 177, 174);
        $pdf->Cell(0, 7, $course->name, 0, 1, 'L');

        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetTextColor(90, 90, 90);
        $totHours = $course->duration_hours ? $course->duration_hours . 'h previste' : '';
        $meta = trim(implode('  ·  ', array_filter([
            $totHours,
            'Iscritti: ' . $rows->count(),
            'Emesso il ' . Carbon::now()->locale('it')->isoFormat('D MMMM YYYY'),
        ])));
        $pdf->Cell(0, 6, $meta, 0, 1, 'L');
        $pdf->Ln(3);

        // Tabella discenti.
        $this->tableHeader($pdf, [
            ['Discente', 70], ['Sincrono', 28], ['FAD', 28], ['Totale', 28], ['Ultima attività', 26],
        ]);

        $pdf->SetFont('dejavusans', '', 9);
        $fill = false;
        foreach ($rows as $r) {
            $pdf->SetFillColor(247, 249, 249);
            $pdf->SetTextColor(40, 40, 40);
            $last = $r['last_activity'] ? Carbon::parse($r['last_activity'])->format('d/m/Y') : '—';
            $this->row($pdf, [
                [$r['student']->name, 70, 'L'],
                [$this->h($r['sync_hours']), 28, 'C'],
                [$this->h($r['async_hours']), 28, 'C'],
                [$this->h($r['total_hours']), 28, 'C'],
                [$last, 26, 'C'],
            ], $fill);
            $fill = ! $fill;
        }

        if ($rows->isEmpty()) {
            $pdf->SetFont('dejavusans', 'I', 9);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->Cell(0, 8, 'Nessun discente iscritto.', 0, 1, 'L');
        }

        // Sessioni sincrone.
        if ($sessions->isNotEmpty()) {
            $pdf->Ln(6);
            $pdf->SetFont('dejavusans', 'B', 11);
            $pdf->SetTextColor(26, 31, 31);
            $pdf->Cell(0, 7, 'Sessioni sincrone', 0, 1, 'L');

            $this->tableHeader($pdf, [
                ['Sessione', 80], ['Data', 34], ['Durata', 24], ['Modalità', 42],
            ]);
            $pdf->SetFont('dejavusans', '', 9);
            $fill = false;
            foreach ($sessions as $s) {
                $modality = $s->modality === 'in_person' ? 'In aula' : 'Live online';
                $this->row($pdf, [
                    [$s->title, 80, 'L'],
                    [$s->scheduled_at?->format('d/m/Y H:i') ?? '—', 34, 'C'],
                    [($s->duration_minutes ?? 0) . ' min', 24, 'C'],
                    [$modality, 42, 'C'],
                ], $fill);
                $fill = ! $fill;
            }
        }

        return $pdf->Output('registro.pdf', 'S');
    }

    /** @param array<int, array{0:string,1:int}> $cols */
    private function tableHeader($pdf, array $cols): void
    {
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->SetFillColor(85, 177, 174);
        $pdf->SetTextColor(255, 255, 255);
        foreach ($cols as $i => [$label, $w]) {
            $align = $i === 0 ? 'L' : 'C';
            $pdf->Cell($w, 8, $label, 0, $i === count($cols) - 1 ? 1 : 0, $align, true);
        }
    }

    /** @param array<int, array{0:string,1:int,2:string}> $cells */
    private function row($pdf, array $cells, bool $fill): void
    {
        foreach ($cells as $i => [$text, $w, $align]) {
            $pdf->Cell($w, 7, $text, 0, $i === count($cells) - 1 ? 1 : 0, $align, $fill);
        }
    }

    private function h(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, ',', ''), '0'), ',') . 'h';
    }
}
