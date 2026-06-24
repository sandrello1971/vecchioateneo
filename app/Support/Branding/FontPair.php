<?php

namespace App\Support\Branding;

use InvalidArgumentException;

/**
 * Coppia font (titoli + corpo) di un tema slide, con fallback "sicuri".
 *
 * Il `primary` è il font desiderato (può non essere installato sulla macchina
 * che apre il .pptx); il `fallback` è un font quasi-ovunque presente
 * (PowerPoint/OS), così la slide resta leggibile anche senza il font primario.
 *
 * Catalogo NON editabile: le coppie selezionabili (override `font_choice`)
 * sono quelle dichiarate qui.
 */
final readonly class FontPair
{
    public function __construct(
        public string $titlePrimary,
        public string $titleFallback,
        public string $bodyPrimary,
        public string $bodyFallback,
    ) {}

    /**
     * Catalogo curato delle coppie font selezionabili.
     *
     * @return array<string, self>
     */
    public static function catalog(): array
    {
        return [
            // GLITCH: editoriale d'impatto. Dichiara Playfair + JetBrains Mono,
            // fallback sicuri Bookman + Courier.
            'editoriale'     => new self('Playfair Display', 'Bookman Old Style', 'JetBrains Mono', 'Courier New'),
            // Classico: serif sobrio, fallback già di sistema.
            'serif_classico' => new self('Georgia', 'Times New Roman', 'Georgia', 'Times New Roman'),
            // Moderno: grotesque pulito, fallback Arial/Calibri.
            'sans_moderno'   => new self('Montserrat', 'Arial', 'Open Sans', 'Calibri'),
            // Caldo: serif umanista + sans morbido, fallback Cambria/Calibri.
            'umanista_caldo' => new self('Merriweather', 'Cambria', 'Source Sans Pro', 'Calibri'),
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    public static function named(string $key): self
    {
        return self::catalog()[$key]
            ?? throw new InvalidArgumentException("Coppia font sconosciuta: {$key}");
    }

    /** Variante tollerante: chiave nulla o sconosciuta → null (per ereditarietà). */
    public static function tryNamed(?string $key): ?self
    {
        return $key !== null ? (self::catalog()[$key] ?? null) : null;
    }

    /** @return array{title: array{primary:string,fallback:string}, body: array{primary:string,fallback:string}} */
    public function toArray(): array
    {
        return [
            'title' => ['primary' => $this->titlePrimary, 'fallback' => $this->titleFallback],
            'body'  => ['primary' => $this->bodyPrimary,  'fallback' => $this->bodyFallback],
        ];
    }
}
