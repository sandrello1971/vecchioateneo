<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Process;

/**
 * P25.1 — Estrattore .docx → sorgente strutturato (blocchi tipizzati).
 *
 * Pipeline: .docx --[pandoc docx+styles --to=json]--> AST pandoc --[mapAst]--> blocchi.
 *
 * `mapAst()` è il CUORE testabile: opera sull'AST già decodificato, quindi i test
 * unitari girano su fixture JSON SENZA invocare pandoc. `extractFromDocx()` aggiunge
 * solo il guard su pandoc e la conversione.
 *
 * Mappatura ancorata alla logica già in produzione (CourseDocumentParser):
 *   PARTE/MODULO/LEZIONE/UNITÀ/SEZIONE/ARGOMENTO + ordinale  → PART
 *   Capitolo N                                               → H1
 *   X.Y Titolo                                               → H2
 *   X.Y.Z (o più profondo)                                   → H2 (degradato) + warning
 *   paragrafo                                                → P
 *   lista puntata / numerata                                 → BUL / NUM
 *   blockquote                                               → BOX
 *   paragrafo che apre con "Esempio"/"Esercizio"             → EX / ESE  (euristica FRAGILE)
 *
 * Tutto ciò che precede il PRIMO heading di contenuto (titolo manuale, sottotitolo,
 * Table of Contents) è FRONTMATTER ed è escluso dai blocchi. Gli ID deterministici
 * contano solo i blocchi emessi → nessun buco dovuto al frontmatter escluso.
 */
class CourseSourceExtractor
{
    /** Heading di livello-parte: "PARTE PRIMA", "MODULO 1", "LEZIONE II"... */
    private const RE_PART = '/^(?:PARTE|MODULO|LEZIONE|UNIT[ÀA]|SEZIONE|ARGOMENTO)\s+(?:PRIMA|SECONDA|TERZA|QUARTA|QUINTA|SESTA|SETTIMA|OTTAVA|NONA|DECIMA|[IVX]+|\d+)\b/iu';

    /** Heading di capitolo: "Capitolo 1 — ...". */
    private const RE_CHAPTER = '/^Capitolo\s+\d+/iu';

    /** Heading di sezione numerata: cattura il token "X.Y" o "X.Y.Z...". */
    private const RE_SECTION = '/^(\d+(?:\.\d+)+)\s+\S/u';

    /** @var list<string> warning raccolti durante l'ultima estrazione */
    private array $warnings = [];

    /**
     * Estrae i blocchi da un file .docx.
     *
     * @return array{blocks: list<array>, frontmatter: list<string>, warnings: list<string>}
     */
    public function extractFromDocx(string $docxPath): array
    {
        if (!is_file($docxPath)) {
            throw new RuntimeException("File .docx non trovato: {$docxPath}");
        }

        $this->assertPandocAvailable();
        $ast = $this->docxToAst($docxPath);

        return $this->mapAst($ast);
    }

    /**
     * Estrae i blocchi da un file Markdown (.md/.markdown). Gemello di
     * extractFromDocx: cambia SOLO il convertitore davanti (gfm→json invece di
     * docx→json); mapAst() è riusato as-is (format-agnostic sull'AST pandoc),
     * quindi block_id/claim hanno la STESSA forma del docx → la Freshness
     * (FreshnessAgent, StudentMatchFinder, StudentRewriter) funziona identica.
     *
     * @return array{blocks: list<array>, frontmatter: list<string>, warnings: list<string>}
     */
    public function extractFromMarkdown(string $mdPath): array
    {
        if (!is_file($mdPath)) {
            throw new RuntimeException("File .md non trovato: {$mdPath}");
        }

        $this->assertPandocAvailable();
        $ast = $this->markdownToAst($mdPath);

        return $this->mapAst($ast);
    }

    /**
     * Verifica esplicita che pandoc sia installato e invocabile. Se manca, errore
     * CHIARO (non un crash oscuro più avanti nella pipeline).
     */
    public function assertPandocAvailable(): void
    {
        try {
            $process = new Process(['pandoc', '--version']);
            $process->setTimeout(15);
            $process->run();
        } catch (ProcessException $e) {
            throw new RuntimeException(
                'pandoc non è installato o non è invocabile sul server: ' . $e->getMessage()
                . '. Installa pandoc per usare l\'estrattore dei corsi.'
            );
        }

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                'pandoc non risponde correttamente (exit ' . $process->getExitCode() . '): '
                . trim($process->getErrorOutput())
            );
        }
    }

    /**
     * Converte il .docx in AST pandoc (JSON) e lo decodifica.
     *
     * Usiamo `--from=docx` SENZA `+styles`: con `+styles` pandoc avvolge OGNI
     * paragrafo in un Div con lo stile Word (BodyText/Compact/...), che andrebbe
     * appiattito comunque. I documenti attuali (es. INTERFERENZA) non usano stili
     * semantici (Box/Esempio), quindi `+styles` aggiungerebbe solo rumore. La
     * rilevazione di BOX da stile Word nominato è un hook per un package futuro,
     * quando esisterà un corso che li usa davvero.
     *
     * @return array AST pandoc decodificato (chiave 'blocks')
     */
    private function docxToAst(string $docxPath): array
    {
        $process = new Process([
            'pandoc', $docxPath,
            '--from=docx',
            '--to=json',
        ]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('pandoc ha fallito la conversione .docx→json: ' . $process->getErrorOutput());
        }

        $ast = json_decode($process->getOutput(), true);
        if (!is_array($ast) || !isset($ast['blocks']) || !is_array($ast['blocks'])) {
            throw new RuntimeException('Output pandoc non valido: AST senza chiave "blocks".');
        }

        return $ast;
    }

    /**
     * Converte il .md in AST pandoc (JSON) con `--from=gfm`. Gemello di docxToAst:
     * stesso `--to=json`, stesso AST a valle → mapAst() lo tratta identico.
     *
     * @return array AST pandoc decodificato (chiave 'blocks')
     */
    private function markdownToAst(string $mdPath): array
    {
        $process = new Process([
            'pandoc', $mdPath,
            '--from=gfm',
            '--to=json',
        ]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('pandoc ha fallito la conversione .md→json: ' . $process->getErrorOutput());
        }

        $ast = json_decode($process->getOutput(), true);
        if (!is_array($ast) || !isset($ast['blocks']) || !is_array($ast['blocks'])) {
            throw new RuntimeException('Output pandoc non valido: AST senza chiave "blocks".');
        }

        return $ast;
    }

    /**
     * CUORE testabile: AST pandoc decodificato → blocchi tipizzati.
     *
     * @param  array  $ast  AST pandoc (json_decode associativo), con $ast['blocks']
     * @return array{blocks: list<array>, frontmatter: list<string>, warnings: list<string>}
     */
    public function mapAst(array $ast): array
    {
        $this->warnings = [];
        // Appiattisce i Div (contenitori/stili Word) nei loro blocchi figli: un Para
        // dentro un Div deve restare un P, non diventare un BOX. Difensivo verso
        // entrambe le modalità pandoc (docx e docx+styles).
        $astBlocks = $this->flattenBlocks($ast['blocks'] ?? []);
        // Promuove i paragrafi-titolo in GRASSETTO (es. "**PARTE PRIMA — …**") a heading:
        // alcuni manuali non usano gli stili Word ma il grassetto come titolo.
        $astBlocks = $this->promoteBoldHeadings($astBlocks);

        // Contatori gerarchici: incrementano SOLO sui blocchi emessi → niente buchi.
        $part = 0;
        $chapter = 0;
        $section = 0;
        $leafCounters = []; // contatori PER TIPO di leaf, resettati ad ogni heading
        $started = false; // true dopo il primo heading di contenuto: prima è frontmatter

        $blocks = [];
        $frontmatter = [];

        foreach ($astBlocks as $node) {
            $type = $node['t'] ?? null;

            // --- Heading ---
            if ($type === 'Header') {
                $text = $this->inlineText($node['c'][2] ?? []);
                $level = $node['c'][0] ?? 1;
                $kind = $this->classifyHeading($text, $level);

                if (!$started && $kind === null) {
                    // Frontmatter: titolo manuale, sottotitolo, "Table of Contents"...
                    $frontmatter[] = $text;
                    continue;
                }
                // Un heading non riconosciuto DOPO l'inizio del contenuto: fallback per livello.
                if ($kind === null) {
                    $kind = $level <= 2 ? 'H1' : 'H2';
                    $this->warn("Heading non riconosciuto, degradato a {$kind} per livello {$level}: \"{$text}\"");
                }

                $started = true;
                $leafCounters = []; // i leaf ripartono da 1 ad ogni nuovo heading

                if ($kind === 'PART') {
                    $part++;
                    $chapter = 0;
                    $section = 0;
                    $blocks[] = ['id' => $this->id($part, 0, 0, null, 0), 'type' => 'PART', 'text' => $text];
                } elseif ($kind === 'H1') {
                    $chapter++;
                    $section = 0;
                    $blocks[] = ['id' => $this->id($part, $chapter, 0, null, 0), 'type' => 'H1', 'text' => $text];
                } else { // H2
                    $section++;
                    $blocks[] = ['id' => $this->id($part, $chapter, $section, null, 0), 'type' => 'H2', 'text' => $text];
                }
                continue;
            }

            // Prima di qualsiasi heading di contenuto, ignoriamo i blocchi non-heading
            // (sono frontmatter: sottotitolo, nota di riservatezza, TOC generata, ecc.).
            if (!$started) {
                $t = $this->nodeText($node);
                if ($t !== '') {
                    $frontmatter[] = $t;
                }
                continue;
            }

            // --- Blocchi foglia ---
            $leaf = $this->mapLeaf($node);
            if ($leaf === null) {
                continue; // nodo non testuale/non supportato: ignorato (loggato in mapLeaf)
            }

            $seq = $leafCounters[$leaf['type']] = ($leafCounters[$leaf['type']] ?? 0) + 1;
            $leaf['id'] = $this->id($part, $chapter, $section, $leaf['type'], $seq);
            $blocks[] = $leaf;
        }

        return [
            'blocks' => $blocks,
            'frontmatter' => $frontmatter,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Classifica un heading per TESTO (logica di CourseDocumentParser), con fallback
     * per livello gestito dal chiamante. Ritorna 'PART'|'H1'|'H2' oppure null se non
     * riconosciuto come heading di contenuto.
     */
    private function classifyHeading(string $text, int $level): ?string
    {
        if (preg_match(self::RE_PART, $text)) {
            return 'PART';
        }
        if (preg_match(self::RE_CHAPTER, $text)) {
            return 'H1';
        }
        if (preg_match(self::RE_SECTION, $text, $m)) {
            // X.Y → H2; X.Y.Z (o più profondo) → degradato a H2 + warning (robustezza).
            $depth = substr_count($m[1], '.');
            if ($depth >= 2) {
                $this->warn("Sezione profonda \"{$m[1]}\" (X.Y.Z) degradata a H2: \"{$text}\"");
            }
            return 'H2';
        }

        // Titolo numerato "flat" (es. "1. Introduzione", "4. Modulo 1 — ...") — NON è X.Y
        // (già gestito sopra). Manuali a numerazione progressiva: trattati uniformemente
        // come H1 (sezione di primo livello).
        if (preg_match('/^\d+[.)]\s+\S/u', $text)) {
            return 'H1';
        }

        return null;
    }

    /**
     * Mappa un nodo-blocco pandoc NON-heading in un blocco foglia tipizzato.
     * Ritorna ['type'=>..., 'text'|'items'=>...] senza 'id' (assegnato dal chiamante),
     * oppure null se il nodo va ignorato.
     */
    private function mapLeaf(array $node): ?array
    {
        $type = $node['t'] ?? null;

        switch ($type) {
            case 'Para':
            case 'Plain':
                $text = $this->inlineText($node['c'] ?? []);
                if (trim($text) === '') {
                    return null;
                }
                // EURISTICA FRAGILE: in assenza di stili semantici, riconosciamo
                // EX/ESE solo dall'apertura testuale. INTERFERENZA non la esercita;
                // primo banco di prova reale = un altro corso. NON irrobustire qui.
                if (preg_match('/^Esempio\b/iu', $text)) {
                    return ['type' => 'EX', 'text' => $text];
                }
                if (preg_match('/^Esercizio\b/iu', $text)) {
                    return ['type' => 'ESE', 'text' => $text];
                }
                return ['type' => 'P', 'text' => $text];

            case 'BulletList':
                return ['type' => 'BUL', 'items' => $this->listItems($node['c'] ?? [])];

            case 'OrderedList':
                // OrderedList c = [listAttributes, [items...]]
                return ['type' => 'NUM', 'items' => $this->listItems($node['c'][1] ?? [])];

            case 'BlockQuote':
                return ['type' => 'BOX', 'text' => $this->blocksText($node['c'] ?? [])];

            // Div: già appiattito in mapAst()/flattenBlocks() prima di arrivare qui.
            // Se per qualche motivo ne resta uno, lo ignoriamo (loggato dal default).

            case 'CodeBlock':
                $code = $node['c'][1] ?? '';
                return trim($code) === '' ? null : ['type' => 'BOX', 'text' => $code, 'meta' => ['variant' => 'code']];

            case 'Table':
                $this->warn('Tabella incontrata: mappata a BOX testuale (P25.1 non rende tabelle strutturate).');
                return ['type' => 'BOX', 'text' => $this->nodeText($node), 'meta' => ['variant' => 'table']];

            case 'HorizontalRule':
                return null;

            default:
                $this->warn("Nodo pandoc non gestito ignorato: {$type}");
                return null;
        }
    }

    /**
     * Costruisce un ID deterministico gerarchico. Componenti a zero omessi.
     * Esempi: PART → "p1"; H1 → "p1-cap2"; H2 → "p1-cap2-sec3";
     *         paragrafo sotto quella sezione → "p1-cap2-sec3-p1".
     */
    private function id(int $part, int $chapter, int $section, ?string $leafType, int $leafSeq): string
    {
        $parts = [];
        if ($part > 0) {
            $parts[] = 'p' . $part;
        }
        if ($chapter > 0) {
            $parts[] = 'cap' . $chapter;
        }
        if ($section > 0) {
            $parts[] = 'sec' . $section;
        }
        if ($leafType !== null) {
            $parts[] = strtolower($leafType) . $leafSeq;
        }

        return implode('-', $parts);
    }

    /**
     * Appiattisce ricorsivamente i Div nei loro blocchi figli, preservando l'ordine.
     * Un Div è un contenitore (stile Word, sezione): i suoi figli vanno trattati come
     * blocchi di primo livello. Tutti gli altri nodi restano invariati.
     *
     * @param  array  $astBlocks  blocchi pandoc
     * @return list<array>
     */
    private function flattenBlocks(array $astBlocks): array
    {
        $out = [];
        foreach ($astBlocks as $node) {
            if (($node['t'] ?? null) === 'Div') {
                $children = $this->flattenBlocks($node['c'][1] ?? []); // Div c = [attr, [blocks]]

                // Hook stile semantico (se un domani si usa `+styles`): un Div con
                // custom-style "box-like" (Box/Block Text/Quote/Callout/Richiamo)
                // diventa un BOX invece di essere appiattito. Lo riscriviamo come
                // BlockQuote per riusare l'unico percorso BOX di mapLeaf().
                if ($this->isBoxLikeStyle($this->customStyle($node))) {
                    $out[] = ['t' => 'BlockQuote', 'c' => $children];
                    continue;
                }

                foreach ($children as $child) {
                    $out[] = $child;
                }
                continue;
            }
            $out[] = $node;
        }

        return $out;
    }

    /**
     * Promuove i paragrafi interamente in GRASSETTO che hanno l'aspetto di un titolo
     * (PARTE/MODULO/Capitolo/X.Y o numerato) in nodi Header. Alcuni manuali non usano gli
     * stili Word ma il grassetto come titolo: senza questo, pandoc li emette come Para e
     * l'estrattore non riconoscerebbe alcuna struttura. Solo i Para bold-only che matchano
     * un pattern di titolo vengono promossi (il grassetto in mezzo alla prosa resta P).
     *
     * @param  list<array>  $astBlocks
     * @return list<array>
     */
    private function promoteBoldHeadings(array $astBlocks): array
    {
        $out = [];
        foreach ($astBlocks as $node) {
            if (($node['t'] ?? null) === 'Para') {
                $boldText = $this->boldOnlyText($node['c'] ?? []);
                if ($boldText !== null && $this->looksLikeHeading($boldText)) {
                    // Header c = [level, attr, inlines]. Il livello è indicativo: la
                    // classificazione PART/H1/H2 avviene per TESTO in classifyHeading.
                    $out[] = ['t' => 'Header', 'c' => [1, ['', [], []], $node['c']]];
                    continue;
                }
            }
            $out[] = $node;
        }

        return $out;
    }

    /**
     * Se il paragrafo è composto SOLO da testo in grassetto (Strong, a parte spazi/break),
     * ritorna quel testo; altrimenti null (c'è contenuto non-bold → non è un titolo).
     */
    private function boldOnlyText(array $inlines): ?string
    {
        $parts = [];
        foreach ($inlines as $inl) {
            $t = $inl['t'] ?? null;
            if (in_array($t, ['Space', 'SoftBreak', 'LineBreak'], true)) {
                continue;
            }
            if ($t === 'Strong') {
                $parts[] = $this->inlineText($inl['c'] ?? []);
                continue;
            }
            return null; // contenuto non in grassetto → non è un titolo-paragrafo
        }

        if (empty($parts)) {
            return null;
        }
        return trim(preg_replace('/[ \t]+/u', ' ', implode(' ', $parts)));
    }

    /** Il testo ha l'aspetto di un titolo (uno dei pattern riconosciuti)? */
    private function looksLikeHeading(string $text): bool
    {
        return (bool) (preg_match(self::RE_PART, $text)
            || preg_match(self::RE_CHAPTER, $text)
            || preg_match(self::RE_SECTION, $text)
            || preg_match('/^\d+[.)]\s+\S/u', $text));
    }

    /** Estrae il valore di custom-style da un nodo Div pandoc, se presente. */
    private function customStyle(array $node): ?string
    {
        $kvs = $node['c'][0][2] ?? []; // attr = [id, classes, [[k,v],...]]
        foreach ($kvs as $kv) {
            if (($kv[0] ?? null) === 'custom-style') {
                return $kv[1] ?? null;
            }
        }

        return null;
    }

    /** Stili Word che rappresentano un riquadro/citazione → BOX. */
    private function isBoxLikeStyle(?string $style): bool
    {
        if ($style === null) {
            return false;
        }
        $s = mb_strtolower($style);
        foreach (['box', 'block text', 'blocktext', 'quote', 'callout', 'richiamo'] as $needle) {
            if (str_contains($s, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** Testo piatto da una lista di inline pandoc. */
    private function inlineText(array $inlines): string
    {
        $out = '';
        foreach ($inlines as $inline) {
            $t = $inline['t'] ?? null;
            switch ($t) {
                case 'Str':
                    $out .= $inline['c'] ?? '';
                    break;
                case 'Space':
                case 'SoftBreak':
                case 'LineBreak':
                    $out .= ' ';
                    break;
                case 'Emph':
                case 'Strong':
                case 'Underline':
                case 'Strikeout':
                case 'SmallCaps':
                case 'Superscript':
                case 'Subscript':
                    $out .= $this->inlineText($inline['c'] ?? []);
                    break;
                case 'Quoted':
                    $out .= '"' . $this->inlineText($inline['c'][1] ?? []) . '"';
                    break;
                case 'Code':
                    $out .= $inline['c'][1] ?? '';
                    break;
                case 'Math':
                    $out .= $inline['c'][1] ?? '';
                    break;
                case 'Link':
                case 'Image':
                    $out .= $this->inlineText($inline['c'][1] ?? []);
                    break;
                // Note, RawInline, ecc.: ignorati.
            }
        }

        return trim(preg_replace('/[ \t]+/u', ' ', $out));
    }

    /** Testo concatenato dai blocchi figli (per blockquote/div). */
    private function blocksText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $b) {
            $t = $this->nodeText($b);
            if ($t !== '') {
                $parts[] = $t;
            }
        }

        return implode("\n", $parts);
    }

    /** Estrae il testo da un nodo-blocco generico (best effort). */
    private function nodeText(array $node): string
    {
        $type = $node['t'] ?? null;
        return match ($type) {
            'Para', 'Plain', 'Header' => $this->inlineText(
                $type === 'Header' ? ($node['c'][2] ?? []) : ($node['c'] ?? [])
            ),
            'BlockQuote', 'Div' => $this->blocksText($type === 'Div' ? ($node['c'][1] ?? []) : ($node['c'] ?? [])),
            'BulletList' => implode("\n", $this->listItems($node['c'] ?? [])),
            'OrderedList' => implode("\n", $this->listItems($node['c'][1] ?? [])),
            'CodeBlock' => $node['c'][1] ?? '',
            default => '',
        };
    }

    /**
     * Item di lista pandoc → array di stringhe (ogni item è una lista di blocchi).
     *
     * @return list<string>
     */
    private function listItems(array $items): array
    {
        $out = [];
        foreach ($items as $itemBlocks) {
            $text = $this->blocksText(is_array($itemBlocks) ? $itemBlocks : []);
            if (trim($text) !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    private function warn(string $message): void
    {
        $this->warnings[] = $message;
        Log::warning('[CourseSourceExtractor] ' . $message);
    }
}
