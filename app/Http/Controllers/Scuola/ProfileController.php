<?php

namespace App\Http\Controllers\Scuola;

use App\Enums\BaseTheme;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use App\Models\BrandProfile;
use App\Models\School;
use App\Support\Branding\ContrastGuard;
use App\Support\Branding\FontPair;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// Anagrafica scuola (nome/tipo/città) + branding white-label (settings json:
// assistant_name, instance_name, logo) + tema slide (brand_profiles, P27).
// Sempre scoped sulla PROPRIA scuola.
class ProfileController extends Controller
{
    use ResolvesSchoolAccess;

    public function edit(): View
    {
        $school = $this->currentSchool();

        return view('scuola.anagrafica', [
            'school' => $school,
            // Profilo brand della scuola (nullable: NULL = non ancora configurato).
            'brand' => $this->ownBrandProfile($school),
            // Cataloghi curati per swatch + anteprima (niente colori hardcoded in view).
            'themes' => $this->themeCatalog(),
            'fonts' => $this->fontCatalog(),
        ]);
    }

    public function update(Request $request)
    {
        $school = $this->currentSchool();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:liceo,istituto_tecnico,altro',
            'city' => 'nullable|string|max:255',
            'assistant_name' => 'nullable|string|max:60',
            'instance_name' => 'nullable|string|max:120',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'remove_logo' => 'sometimes|boolean',
            // Tema slide (P27). base_theme è nullable lato server (additivo: la form
            // anagrafica preesistente non lo invia): si ricade su un default sensato.
            'base_theme' => ['nullable', Rule::in(BaseTheme::values())],
            'accent_color' => ['nullable', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'font_choice' => ['nullable', Rule::in(FontPair::keys())],
        ], [
            'accent_color.regex' => 'L\'accento deve essere un colore esadecimale a 6 cifre (es. A6192E).',
            'base_theme.in' => 'Tema non valido.',
            'font_choice.in' => 'Coppia font non valida.',
        ]);

        $existing = $this->ownBrandProfile($school);

        // Tema base: scelta esplicita → profilo esistente → default GLITCH.
        $baseTheme = !empty($data['base_theme'])
            ? BaseTheme::from($data['base_theme'])
            : ($existing?->base_theme ?? BaseTheme::Glitch);
        $accent = !empty($data['accent_color']) ? strtoupper(ltrim($data['accent_color'], '#')) : null;

        // Contrast-guard: un accento override deve essere leggibile sullo sfondo
        // del tema scelto, altrimenti non si salva (slide illeggibili vietate).
        if ($accent !== null) {
            $guard = new ContrastGuard();
            if (!$guard->isAccentReadable($accent, $baseTheme)) {
                $report = $guard->inspect($accent, $baseTheme);
                throw ValidationException::withMessages([
                    'accent_color' => "Questo accento (#{$accent}) non ha contrasto sufficiente sul tema {$baseTheme->label()} "
                        . "(rapporto {$report['ratio']}, minimo {$report['min']}). Scegline uno più scuro o saturo.",
                ]);
            }
        }

        // Anagrafica
        $school->name = $data['name'];
        $school->type = $data['type'];
        $school->city = $data['city'] ?? null;

        // Branding (settings json): valore vuoto = eredita il default piattaforma.
        $settings = $school->settings ?? [];
        $settings['assistant_name'] = ($data['assistant_name'] ?? '') ?: null;
        $settings['instance_name'] = ($data['instance_name'] ?? '') ?: null;

        // Logo in storage PRIVATO (servito solo via controller).
        if ($request->hasFile('logo')) {
            if (!empty($settings['logo_path'])) {
                Storage::disk('local')->delete($settings['logo_path']);
            }
            $ext = $request->file('logo')->getClientOriginalExtension() ?: 'png';
            $settings['logo_path'] = $request->file('logo')->storeAs(
                'school-logos/' . $school->id, 'logo.' . $ext, 'local'
            );
        } elseif ($request->boolean('remove_logo') && !empty($settings['logo_path'])) {
            Storage::disk('local')->delete($settings['logo_path']);
            $settings['logo_path'] = null;
        }

        $school->settings = array_filter($settings, fn ($v) => $v !== null);
        $school->save();

        // Tema slide: crea/aggiorna il BrandProfile della scuola. Il logo NON è un
        // campo a parte: resta NULL sul profilo e viene ereditato da settings['logo_path'].
        BrandProfile::updateOrCreate(
            ['owner_type' => School::class, 'owner_id' => $school->id],
            [
                'base_theme' => $baseTheme->value,
                'accent_color' => $accent,
                'font_choice' => $data['font_choice'] ?? null,
            ],
        );

        return redirect()->route('scuola.anagrafica.edit')
            ->with('success', 'Anagrafica, branding e tema presentazioni aggiornati.');
    }

    /** Profilo brand della scuola, o NULL se non ancora configurato. */
    private function ownBrandProfile(School $school): ?BrandProfile
    {
        return BrandProfile::query()
            ->where('owner_type', School::class)
            ->where('owner_id', $school->id)
            ->first();
    }

    /** @return array<int, array<string, mixed>> catalogo temi per swatch/anteprima */
    private function themeCatalog(): array
    {
        return array_map(fn (BaseTheme $t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'palette' => $t->palette(),
            'font_key' => $t->fontKey(),
            'fonts' => $t->fonts()->toArray(),
        ], BaseTheme::cases());
    }

    /** @return array<string, array<string, mixed>> catalogo coppie font per la select/anteprima */
    private function fontCatalog(): array
    {
        $out = [];
        foreach (FontPair::keys() as $key) {
            $out[$key] = FontPair::named($key)->toArray();
        }

        return $out;
    }
}
