<?php

namespace App\Enums;

use App\Support\Branding\FontPair;

/**
 * Catalogo curato dei temi slide (NON editabile). Ogni tema dichiara una
 * palette (ink/sfondo/accento-default) e una coppia font (con fallback sicuri).
 * I layout-safe sono impliciti: li applicherà il generatore (Fase 3).
 *
 * GLITCH (nero/cremisi/avorio) è il default di piattaforma.
 *
 * Nota: i valori string combaciano col CHECK constraint su brand_profiles.base_theme.
 */
enum BaseTheme: string
{
    case Glitch = 'glitch';
    case Classico = 'classico';
    case Moderno = 'moderno';
    case Caldo = 'caldo';

    public function label(): string
    {
        return match ($this) {
            self::Glitch => 'GLITCH',
            self::Classico => 'Classico',
            self::Moderno => 'Moderno',
            self::Caldo => 'Caldo',
        };
    }

    /**
     * Palette del tema in esadecimale SENZA '#'.
     * ink = colore testo, background = sfondo slide, accent = accento di default.
     *
     * @return array{ink:string, background:string, accent:string}
     */
    public function palette(): array
    {
        return match ($this) {
            // nero / avorio / cremisi
            self::Glitch   => ['ink' => '0A0A0A', 'background' => 'F4F1EA', 'accent' => 'A6192E'],
            // navy / avorio caldo / bordeaux
            self::Classico => ['ink' => '1E2A3A', 'background' => 'FBF9F4', 'accent' => '8A2434'],
            // quasi-nero / bianco / blu
            self::Moderno  => ['ink' => '111827', 'background' => 'FFFFFF', 'accent' => '1D4ED8'],
            // testa di moro / crema / terracotta
            self::Caldo    => ['ink' => '3A2A1E', 'background' => 'FAF3E8', 'accent' => '9E3F12'],
        };
    }

    /** Accento di default del tema (hex senza '#'). */
    public function defaultAccent(): string
    {
        return $this->palette()['accent'];
    }

    /** Chiave del catalogo FontPair usata di default dal tema. */
    public function fontKey(): string
    {
        return match ($this) {
            self::Glitch => 'editoriale',
            self::Classico => 'serif_classico',
            self::Moderno => 'sans_moderno',
            self::Caldo => 'umanista_caldo',
        };
    }

    /** Coppia font di default del tema. */
    public function fonts(): FontPair
    {
        return FontPair::named($this->fontKey());
    }

    /** @return list<string> valori per CHECK / validazione */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
