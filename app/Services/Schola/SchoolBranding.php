<?php

namespace App\Services\Schola;

use App\Models\School;

/**
 * Branding white-label risolto a livello di SCUOLA, SOPRA il default piattaforma.
 *
 * Riusa il branding settings-driven esistente (`atheneum_setting`) come
 * fallback: una chiave valorizzata in `schools.settings` vince sul default
 * piattaforma; altrimenti si eredita il default. Per i docenti/utenti "liberi"
 * (school NULL) si ottiene esattamente il branding piattaforma (nessuna
 * regressione).
 */
class SchoolBranding
{
    public function __construct(public ?School $school = null) {}

    public static function for(?School $school): self
    {
        return new self($school);
    }

    /** Valore di scuola se presente e non vuoto, altrimenti default piattaforma. */
    public function get(string $key, $default = null)
    {
        if ($this->school) {
            $value = $this->school->setting($key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return atheneum_setting($key, $default);
    }

    public function instanceName(): string
    {
        return (string) $this->get('instance_name', 'Atheneum');
    }

    public function assistantName(): string
    {
        return (string) $this->get('assistant_name', 'Minerva');
    }

    /** Etichetta proprietario: nome scuola se in contesto scuola, altrimenti owner piattaforma. */
    public function ownerLabel(): string
    {
        return $this->school?->name ?: (string) atheneum_setting('platform_owner', 'Noscite Srl');
    }

    /** URL del logo: route privata di scuola se caricato, altrimenti logo piattaforma. */
    public function logoUrl(): string
    {
        if ($this->school && $this->school->setting('logo_path')) {
            return route('scuola.logo', $this->school);
        }

        return '/images/logo.png';
    }

    public function hasSchool(): bool
    {
        return $this->school !== null;
    }
}
