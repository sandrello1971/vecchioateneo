<?php

namespace App\Services\Freshness;

/**
 * P25.3c — Localizzazione e sostituzione VERBATIM, con la stessa normalizzazione
 * length-preserving (1:1 in caratteri) di Fase 1 (FreshnessClaimExtractor): apostrofi/
 * virgolette tipografiche, trattini, NBSP. Gli offset in caratteri restano allineati,
 * quindi la sostituzione avviene sul testo ORIGINALE (tipografia preservata).
 *
 * Regola "verbatim o niente": la sostituzione riesce SOLO se il `before` si trova
 * ESATTAMENTE UNA volta. Zero occorrenze o più d'una → fallimento pulito (nessuna
 * modifica), mai un replace approssimativo o multiplo.
 */
class VerbatimReplacer
{
    /** Conta le occorrenze (normalizzate) di $needle in $haystack. */
    public static function countOccurrences(string $haystack, string $needle): int
    {
        $normNeedle = trim(self::normalize($needle));
        if ($normNeedle === '') {
            return 0;
        }
        return mb_substr_count(self::normalize($haystack), $normNeedle);
    }

    /**
     * Sostituisce $needle con $replacement SOLO se unico. Ritorna l'esito e il testo.
     *
     * @return array{ok: bool, result: ?string, count: int, reason: ?string}
     */
    public static function replaceUnique(string $haystack, string $needle, string $replacement): array
    {
        $normHaystack = self::normalize($haystack);
        $normNeedle = trim(self::normalize($needle));

        if ($normNeedle === '') {
            return ['ok' => false, 'result' => null, 'count' => 0, 'reason' => 'testo di ricerca vuoto'];
        }

        $count = mb_substr_count($normHaystack, $normNeedle);
        if ($count === 0) {
            return ['ok' => false, 'result' => null, 'count' => 0, 'reason' => 'before non trovato (nessuna occorrenza verbatim)'];
        }
        if ($count > 1) {
            return ['ok' => false, 'result' => null, 'count' => $count, 'reason' => "before non univoco ({$count} occorrenze): non si sceglie a caso"];
        }

        // Una sola occorrenza: estrai la sottostringa ORIGINALE alla posizione trovata
        // (normalizzazione 1:1 → offset coincidenti) e sostituiscila una sola volta.
        $pos = mb_strpos($normHaystack, $normNeedle);
        $original = mb_substr($haystack, $pos, mb_strlen($normNeedle));

        $result = self::replaceFirst($haystack, $original, $replacement);

        return ['ok' => true, 'result' => $result, 'count' => 1, 'reason' => null];
    }

    /** Sostituzione della PRIMA occorrenza (la sola, già verificata univoca). */
    private static function replaceFirst(string $haystack, string $search, string $replacement): string
    {
        $pos = mb_strpos($haystack, $search);
        if ($pos === false) {
            return $haystack;
        }
        return mb_substr($haystack, 0, $pos) . $replacement . mb_substr($haystack, $pos + mb_strlen($search));
    }

    /** Normalizzazione 1:1 (length-preserving in caratteri). */
    public static function normalize(string $s): string
    {
        return strtr($s, [
            '’' => "'", '‘' => "'", '‛' => "'", '‚' => "'",
            '“' => '"', '”' => '"', '„' => '"', '‟' => '"',
            '—' => '-', '–' => '-', '‒' => '-', '―' => '-',
            "\u{00A0}" => ' ',
        ]);
    }
}
