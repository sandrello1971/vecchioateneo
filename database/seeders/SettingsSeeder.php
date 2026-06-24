<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Valori di brand di default per l'istanza. Idempotente e NON distruttivo:
 * imposta il default SOLO se la chiave è assente o vuota, così su un DB fresco
 * il brand nasce già corretto ('Atheneum') ma un valore personalizzato
 * dall'admin via UNN settings non viene mai sovrascritto da un re-seed.
 *
 * Semantica "vuoto = default" coerente con Setting::resolve: una riga con
 * value='' è trattata come assente, quindi viene (re)impostata.
 */
class SettingsSeeder extends Seeder
{
    /**
     * Default cablati del brand di piattaforma.
     *
     * @var array<string,string>
     */
    private const DEFAULTS = [
        'instance_name' => 'Atheneum',
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            $current = Setting::query()->find($key);

            if ($current === null || $current->value === null || $current->value === '') {
                Setting::put($key, $value);
            }
        }
    }
}
