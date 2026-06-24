<?php

namespace App\Support\Branding;

use App\Enums\BaseTheme;

/**
 * Guard di contrasto WCAG sull'accento. Calcolo PURO (niente UI, niente DB):
 * la validazione della segreteria (Fase 2) lo userà per respingere/segnalare un
 * accento illeggibile, ma il calcolo del contrasto vive qui.
 *
 * Verifica l'accento contro lo SFONDO del tema base (l'accento colora i titoli
 * sopra lo sfondo). Ratio WCAG da 1.0 (nessun contrasto) a 21.0 (nero su bianco).
 */
class ContrastGuard
{
    /** WCAG AA testo normale. Default prudente: meglio segnalare i borderline. */
    public const MIN_NORMAL_TEXT = 4.5;

    /** WCAG AA testo grande (titoli): soglia più permissiva. */
    public const MIN_LARGE_TEXT = 3.0;

    /** Ratio di contrasto WCAG tra due colori hex (con o senza '#'). */
    public function ratio(string $hexA, string $hexB): float
    {
        $l1 = $this->relativeLuminance($hexA);
        $l2 = $this->relativeLuminance($hexB);
        [$hi, $lo] = $l1 >= $l2 ? [$l1, $l2] : [$l2, $l1];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    /** L'accento è leggibile sullo sfondo del tema base? */
    public function isAccentReadable(string $accentHex, BaseTheme $theme, float $min = self::MIN_NORMAL_TEXT): bool
    {
        return $this->ratio($accentHex, $theme->palette()['background']) >= $min;
    }

    /**
     * Report dettagliato (per la UI di Fase 2).
     *
     * @return array{accent:string, background:string, ratio:float, min:float, readable:bool}
     */
    public function inspect(string $accentHex, BaseTheme $theme, float $min = self::MIN_NORMAL_TEXT): array
    {
        $background = $theme->palette()['background'];
        $ratio = $this->ratio($accentHex, $background);

        return [
            'accent'     => ltrim($accentHex, '#'),
            'background' => $background,
            'ratio'      => round($ratio, 2),
            'min'        => $min,
            'readable'   => $ratio >= $min,
        ];
    }

    /** Luminanza relativa WCAG (0..1). */
    private function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = $this->channels($hex);
        $linear = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);
    }

    /**
     * Canali RGB normalizzati 0..1.
     *
     * @return array{0:float,1:float,2:float}
     */
    private function channels(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }
}
