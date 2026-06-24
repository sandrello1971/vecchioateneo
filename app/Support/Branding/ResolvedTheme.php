<?php

namespace App\Support\Branding;

use App\Enums\BaseTheme;

/**
 * Tema slide EFFETTIVO dopo aver risolto base_theme + override + ereditarietà.
 * Value-object immutabile, pronto per il generatore (Fase 3). Colori in hex
 * SENZA '#'.
 */
final readonly class ResolvedTheme
{
    public function __construct(
        public BaseTheme $theme,
        public string $ink,
        public string $background,
        public string $accent,
        public FontPair $fonts,
        public ?string $logoPath,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'theme'     => $this->theme->value,
            'palette'   => ['ink' => $this->ink, 'background' => $this->background, 'accent' => $this->accent],
            'fonts'     => $this->fonts->toArray(),
            'logo_path' => $this->logoPath,
        ];
    }
}
