<?php

namespace App\Models;

use App\Enums\BaseTheme;
use App\Support\Branding\FontPair;
use App\Support\Branding\ResolvedTheme;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * P27 — profilo di branding (tema slide) di un owner.
 *
 * Owner polimorfico NULLABLE: oggi School, domani Tenant; owner NULL = profilo
 * di PIATTAFORMA (default GLITCH). Copre solo il tema slide; instance_name/
 * assistant_name restano in schools.settings (convivenza).
 *
 * Risoluzione del tema effettivo: si parte dal base_theme curato e si applicano
 * gli override non-null (accento, font, logo); dove l'override è vuoto si eredita
 * dal tema base — e per il logo da schools.settings['logo_path'].
 */
class BrandProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'owner_type', 'owner_id', 'base_theme', 'accent_color', 'font_choice', 'logo_path',
    ];

    protected $casts = [
        'base_theme' => BaseTheme::class,
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** Tema effettivo dopo override + ereditarietà. */
    public function resolvedTheme(): ResolvedTheme
    {
        $base = $this->base_theme ?? BaseTheme::Glitch;
        $palette = $base->palette();

        $accent = $this->accent_color ?: $palette['accent'];
        $fonts  = FontPair::tryNamed($this->font_choice) ?? $base->fonts();
        $logo   = $this->logo_path ?: $this->inheritedLogoPath();

        return new ResolvedTheme(
            theme: $base,
            ink: $palette['ink'],
            background: $palette['background'],
            accent: $accent,
            fonts: $fonts,
            logoPath: $logo,
        );
    }

    /** Logo ereditato da schools.settings['logo_path'] quando l'owner è una Scuola. */
    private function inheritedLogoPath(): ?string
    {
        $owner = $this->owner; // relazione morphTo (può essere già caricata via setRelation)

        return $owner instanceof School ? $owner->setting('logo_path') : null;
    }

    /** Profilo della scuola, o il default di piattaforma (GLITCH) se non esiste. */
    public static function forSchool(School $school): self
    {
        $profile = static::query()
            ->where('owner_type', School::class)
            ->where('owner_id', $school->id)
            ->first();

        if ($profile) {
            // Evita una query extra quando resolvedTheme eredita il logo dai settings.
            $profile->setRelation('owner', $school);

            return $profile;
        }

        return static::forPlatform();
    }

    /** Profilo di piattaforma (owner NULL), o un default GLITCH non persistito. */
    public static function forPlatform(): self
    {
        $profile = static::query()
            ->whereNull('owner_type')
            ->whereNull('owner_id')
            ->first();

        return $profile ?? static::defaultGlitch();
    }

    /** Default di piattaforma GLITCH: istanza NON persistita, nessun override. */
    public static function defaultGlitch(): self
    {
        return new static(['base_theme' => BaseTheme::Glitch->value]);
    }
}
