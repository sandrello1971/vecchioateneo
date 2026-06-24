<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class CourseDocumentParser
{
    public function __construct(private CourseIngestionService $llm)
    {
    }

    public function convertDocxToHtml(string $docxPath): string
    {
        $process = new Process([
            'pandoc',
            $docxPath,
            '--from=docx',
            '--to=html5',
            '--wrap=none',
        ]);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('pandoc fallito: ' . $process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * Converte un manuale Markdown (GitHub-Flavored) in HTML, nella STESSA forma
     * di convertDocxToHtml (h1/h2/p/em/ul…), così lo split moduli a valle è
     * identico. Il .md è strutturalmente esplicito (#=heading certo) → più
     * affidabile del docx. pandoc gestisce l'UTF-8 (accenti) leggendo il file.
     */
    public function convertMarkdownToHtml(string $mdPath): string
    {
        $process = new Process([
            'pandoc',
            $mdPath,
            '--from=gfm',
            '--to=html5',
            '--wrap=none',
        ]);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('pandoc (markdown) fallito: ' . $process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * Dispatcher per estensione: instrada il manuale al convertitore giusto.
     * .md/.markdown → Markdown (gfm); tutto il resto (.docx/.doc) → ramo docx
     * INVARIATO. Punto unico di diramazione per tipo file dell'ingestion.
     */
    public function convertManualToHtml(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'md', 'markdown' => $this->convertMarkdownToHtml($path),
            default => $this->convertDocxToHtml($path),
        };
    }

    // P29 ingestion fix — marcatori front-matter del manuale (allowlist): rimossi
    // anche se POST-heading (extractFrontmatter taglia solo prima del primo <h1>).
    // Conservativo: si rimuove solo un <p> che È INTERAMENTE il marcatore.
    private const FRONTMATTER_MARKERS = [
        '/^MANUALE\s+(?:DISCENTE|DOCENTE|STUDENTE|FORMATORE|PARTECIPANTE)$/iu',
        '/^(?:GUIDA|DISPENSA)\s+(?:DISCENTE|DOCENTE|STUDENTE|FORMATORE)$/iu',
        '/nova\s+virt[uū]s/iu',            // payoff latino (tagline brand)
        '/^il\s+rumore\s+che\s+serve$/iu',  // payoff nuovo (tagline brand)
    ];

    // Prompt-segnaposto: invitano a scrivere in un campo che NON esiste nell'HTML
    // → testo penzolante. Allowlist conservativa; si rimuove solo il <p> segnaposto.
    private const PLACEHOLDER_PROMPTS = [
        '/^usa\s+questo\s+spazio\b/iu',
        '/^scrivi\s+(?:qui|le\s+tue|i\s+tuoi)\b/iu',
        '/^spazio\s+(?:per\s+(?:gli\s+)?appunti|libero)\b/iu',
        '/^annota\s+qui\b/iu',
    ];

    public function normalizeHeadings(string $html): string
    {
        // Pipeline conservativa (ordine): pulizia boilerplate → promozione heading.
        // Promozione bold/numerati: serve al DOCX (Word codifica gli heading come
        // paragrafi in grassetto). NON va applicata al Markdown (heading espliciti).
        $html = $this->stripParagraphsMatching($html, self::FRONTMATTER_MARKERS);   // difetto (a)
        $html = $this->stripParagraphsMatching($html, self::PLACEHOLDER_PROMPTS);   // difetto (d)
        $html = $this->promoteBoldHeadings($html);                                  // esistente (docx)
        $html = $this->promoteNumberedHeadings($html);                              // difetto (c) (docx)

        return $html;
    }

    /**
     * Normalizzazione per il MARKDOWN: SOLO pulizia boilerplate, NESSUNA promozione
     * heading. In Markdown gli heading sono già espliciti (pandoc #→<h1>, ##→<h2>),
     * mentre un <p><strong>Sezione 1 — …</strong></p> è ENFASI inline, non un heading:
     * promuoverlo a <h1> spezzerebbe i moduli (bug "strong come confine"). I confini
     * di modulo restano quindi SOLO i veri <h1>/<h2> prodotti da pandoc.
     */
    public function normalizeMarkdownHtml(string $html): string
    {
        $html = $this->stripParagraphsMatching($html, self::FRONTMATTER_MARKERS);
        $html = $this->stripParagraphsMatching($html, self::PLACEHOLDER_PROMPTS);

        return $html;
    }

    /**
     * Promozione heading dal grassetto a inizio paragrafo (logica storica).
     * La cattura NON deve attraversare il confine </p>: con il flag /s un
     * <strong> che è solo prefisso del paragrafo (es. "<strong>Sezione 1</strong>. testo")
     * farebbe scavalcare al match i paragrafi e gli heading successivi fino al primo
     * </strong></p>, inglobando interi capitoli in un unico falso heading.
     */
    private function promoteBoldHeadings(string $html): string
    {
        return preg_replace_callback(
            '/<p[^>]*>\s*<strong[^>]*>((?:(?!<\/p>).)*?)<\/strong>\s*<\/p>/is',
            function ($m) {
                $text = trim(strip_tags($m[1]));

                // Livello 1: PARTE/MODULO/LEZIONE/UNITÀ/SEZIONE/ARGOMENTO seguito da ordinale o numero
                if (preg_match('/^(?:PARTE|MODULO|LEZIONE|UNIT[ÀA]|SEZIONE|ARGOMENTO)\s+(?:PRIMA|SECONDA|TERZA|QUARTA|QUINTA|SESTA|SETTIMA|OTTAVA|NONA|DECIMA|[IVX]+|\d+)\b/iu', $text)) {
                    return '<h1>' . $m[1] . '</h1>';
                }

                // Livello 2: Capitolo N
                if (preg_match('/^Capitolo\s+\d+/iu', $text)) {
                    return '<h2>' . $m[1] . '</h2>';
                }

                // Livello 3: X.Y Titolo
                if (preg_match('/^\d+\.\d+\s+\S/u', $text)) {
                    return '<h3>' . $m[1] . '</h3>';
                }

                return $m[0];
            },
            $html
        );
    }

    /**
     * Difetto (a)/(d): rimuove i <p> che SONO interamente un marcatore noto
     * (front-matter manuale o prompt-segnaposto). Conservativo: match sull'intero
     * testo del paragrafo + guardia di lunghezza (i marcatori sono righe brevi),
     * così non si tocca mai un paragrafo di contenuto reale. La cattura non
     * attraversa </p> (come promoteBoldHeadings).
     *
     * @param  list<string>  $patterns
     */
    private function stripParagraphsMatching(string $html, array $patterns): string
    {
        return preg_replace_callback(
            '/<p[^>]*>((?:(?!<\/p>).)*?)<\/p>\s*/is',
            function ($m) use ($patterns) {
                $text = trim(strip_tags($m[1]));
                // Solo righe brevi: i marcatori/prompt sono corti; il contenuto reale no.
                if ($text === '' || mb_strlen($text) > 120) {
                    return $m[0];
                }
                foreach ($patterns as $re) {
                    if (preg_match($re, $text)) {
                        return ''; // rimuove l'intero <p> marcatore
                    }
                }

                return $m[0];
            },
            $html
        );
    }

    /**
     * Difetto (c): promuove a heading i <p> "N. TITOLO" (numero + titolo di
     * sezione), tipici di manuali con stili Word incoerenti. MOLTO conservativo:
     * solo se il testo dopo "N. " è un titolo plausibile — prevalentemente
     * maiuscolo, breve, senza punteggiatura di frase — così "1. compra il latte"
     * (voce di lista/frase) NON viene promosso. Livello h3 (sotto il top-level):
     * non interferisce mai con lo split dei moduli (h1/h2).
     */
    private function promoteNumberedHeadings(string $html): string
    {
        return preg_replace_callback(
            '/<p[^>]*>((?:(?!<\/p>).)*?)<\/p>/is',
            function ($m) {
                $text = trim(strip_tags($m[1]));
                if (!preg_match('/^\d+\.\s+(\S.*)$/u', $text, $mm)) {
                    return $m[0];
                }
                if (!$this->looksLikeSectionTitle(trim($mm[1]))) {
                    return $m[0];
                }

                return '<h3>' . $m[1] . '</h3>';
            },
            $html
        );
    }

    /**
     * Euristica conservativa "è un titolo di sezione?": breve, poche parole,
     * niente punteggiatura di frase finale, e lettere PREVALENTEMENTE MAIUSCOLE
     * (≥60%). Separa "UTILIZZO DEGLI STRUMENTI AI" (titolo) da "compra il latte"
     * o "Compra il latte" (lista/frase, che NON vanno promossi).
     */
    private function looksLikeSectionTitle(string $rest): bool
    {
        if ($rest === '' || mb_strlen($rest) > 80) {
            return false;
        }
        if (preg_match('/[.;:]\s*$/u', $rest)) {
            return false; // termina come una frase/voce → non è un titolo
        }
        if (str_word_count(preg_replace('/[^\p{L}\s]/u', ' ', $rest)) > 10) {
            return false; // troppo lungo per un titolo
        }

        $letters = preg_replace('/[^\p{L}]/u', '', $rest);
        if ($letters === '') {
            return false;
        }
        $upper = preg_replace('/[^\p{Lu}]/u', '', $rest);

        return mb_strlen($upper) / max(1, mb_strlen($letters)) >= 0.6;
    }

    // Pattern-titolo di SEZIONE (allowlist estendibile): conferma per il divisore.
    // NON è il criterio decisivo — quello è l'assenza di corpo (vedi isDividerModule).
    private const DIVIDER_TITLE_PATTERN =
        '/^(?:PARTE|SEZIONE|UNIT[ÀA]|MODULO|BLOCCO)\s+(?:PRIMA|SECONDA|TERZA|QUARTA|QUINTA|SESTA|SETTIMA|OTTAVA|NONA|DECIMA|[IVXLC]+|\d+)\b/iu';

    /**
     * @param  int|null  $level  livello di split (1=h1, 2=h2). null → auto (chooseTopLevel).
     *                           Backward-compatible: i caller esistenti non passano il livello.
     */
    public function splitIntoModules(string $normalizedHtml, ?int $level = null): array
    {
        $level = $level ?? $this->chooseTopLevel($normalizedHtml);
        $modules = $this->extractTopLevelSections($normalizedHtml, $level);

        // Fallback: nessun titolo riconosciuto come heading → modulo unico con tutto il contenuto.
        // Evita il blocco "0 moduli"; l'utente può poi suddividere a mano nell'admin.
        if (empty($modules) && trim(strip_tags($normalizedHtml)) !== '') {
            $modules[] = [
                'title' => 'Contenuto del corso',
                'short_description' => null,
                'content_html' => $normalizedHtml,
                'sort_order' => 0,
            ];
        }

        // Classifica ogni modulo: divisore (solo titolo, es. "PARTE PRIMA") vs
        // contenuto. Vale per entrambi i rami (md e docx) a valle dello split:
        // nel docx i moduli-PARTE hanno corpo (i Capitoli h2) → restano contenuto,
        // quindi il comportamento docx non cambia.
        foreach ($modules as &$mod) {
            $mod['is_divider'] = $this->isDividerModule($mod['title'], $mod['content_html']);
        }
        unset($mod);

        return $modules;
    }

    /**
     * Un top-level heading è un DIVISORE (es. "PARTE PRIMA — …") quando non ha
     * corpo: solo il titolo, nessun contenuto fino al modulo successivo.
     *
     * Criterio PRIMARIO e robusto: ASSENZA DI CORPO (un capitolo vero ha sempre
     * prosa; un divisore no) → indipendente dalla parola-chiave. Il pattern-titolo
     * (PARTE/SEZIONE/UNITÀ/MODULO + ordinale) è una CONFERMA secondaria, non
     * decisiva: un capitolo con titolo ambiguo ma con corpo NON è un divisore.
     */
    public function isDividerModule(string $title, string $contentHtml): bool
    {
        if ($this->hasBodyAfterHeading($contentHtml)) {
            return false; // ha corpo → modulo-contenuto (il no-corpo è il criterio primario)
        }

        // Nessun corpo → divisore. Il pattern-titolo conferma (telemetria/robustezza),
        // ma anche un heading vuoto senza pattern resta un divisore (no contenuto da rendere).
        return true;
    }

    /** True se il titolo combacia col pattern di sezione (conferma del divisore). */
    public function looksLikeDividerTitle(string $title): bool
    {
        return (bool) preg_match(self::DIVIDER_TITLE_PATTERN, trim($title));
    }

    /** True se, tolto il primo heading, resta del testo reale nel blocco. */
    private function hasBodyAfterHeading(string $contentHtml): bool
    {
        $body = preg_replace('/^\s*<(h[1-6])\b[^>]*>.*?<\/\1>/is', '', $contentHtml, 1);

        return trim(strip_tags((string) $body)) !== '';
    }

    public function normalizeAndSplitIntoModules(string $html): array
    {
        return $this->splitIntoModules($this->normalizeHeadings($html));
    }

    public function extractFrontmatter(string $normalizedHtml): string
    {
        $pos = stripos($normalizedHtml, '<h1');
        if ($pos === false) {
            return $normalizedHtml;
        }
        return substr($normalizedHtml, 0, $pos);
    }

    public function separateExamPrep(array $modules): array
    {
        if (empty($modules)) {
            return ['modules' => $modules, 'exam_prep_html' => null];
        }

        $lastIndex = count($modules) - 1;
        $lastContent = $modules[$lastIndex]['content_html'];

        if (preg_match(
            '/<h2[^>]*>(?:[^<]*?)(?:preparazione\s+all[\'\x{2019}]?esame|preparazione\s+esame|ripasso|recap)(?:[^<]*?)<\/h2>/iu',
            $lastContent,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            $cutPos = $matches[0][1];
            $beforeCut = substr($lastContent, 0, $cutPos);
            $examPrep = substr($lastContent, $cutPos);

            $modules[$lastIndex]['content_html'] = rtrim($beforeCut);

            return ['modules' => $modules, 'exam_prep_html' => $examPrep];
        }

        return ['modules' => $modules, 'exam_prep_html' => null];
    }

    public function extractCourseMetadata(string $frontmatterHtml): array
    {
        $text = strip_tags($frontmatterHtml);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if (mb_strlen($text) < 50) {
            return [
                'name' => 'Corso senza titolo',
                'short_description' => null,
                'description' => null,
            ];
        }

        $brand = atheneum_setting('instance_name', 'di formazione');
        $systemPrompt = <<<SYS
Ricevi il testo introduttivo di un manuale didattico {$brand}. Devi estrarre tre campi:
1. name: il nome del corso (cerca pattern tipo "SEGNALE — Fondamenta AI Operativa", o titoli simili)
2. short_description: una frase di 1-2 righe che riassume di cosa tratta il corso
3. description: una descrizione più estesa (2-4 frasi) che riprende i temi principali

Rispondi SOLO con JSON valido, nessun testo extra, nessun markdown.

Formato richiesto:
{"name": "...", "short_description": "...", "description": "..."}

Regole:
- Mantieni il tono del testo originale
- Non inventare informazioni non presenti
- Se non riesci a estrarre un campo, mettilo a null (non stringa vuota)
SYS;

        $userPrompt = "Frontmatter del manuale:\n\n" . mb_substr($text, 0, 8000);

        return $this->llm->callClaudeJsonPublic($systemPrompt, $userPrompt);
    }

    public function extractTextForExam(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'txt') {
            return file_get_contents($path) ?: '';
        }

        if (in_array($ext, ['docx', 'doc'], true)) {
            $process = new Process([
                'pandoc',
                $path,
                '--from=' . ($ext === 'docx' ? 'docx' : 'doc'),
                '--to=plain',
                '--wrap=none',
            ]);
            $process->setTimeout(60);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('pandoc plain fallito: ' . $process->getErrorOutput());
            }

            return $process->getOutput();
        }

        if ($ext === 'pdf') {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($path);
                return $pdf->getText();
            } catch (\Exception $e) {
                Log::warning('PDF extract error: ' . $e->getMessage());
                return '';
            }
        }

        return '';
    }

    private function chooseTopLevel(string $html): int
    {
        $h1Count = preg_match_all('/<h1[^>]*>/i', $html);
        $h2Count = preg_match_all('/<h2[^>]*>/i', $html);

        if ($h1Count > 0) return 1;
        if ($h2Count > 0) return 2;
        return 1;
    }

    /**
     * Suggerisce il livello di split robusto alla granularità del .md:
     *  - un SOLO h1 (titolo-manuale) + ≥2 h2 → livello 2 (i moduli sono i ##);
     *  - ≥2 h1 → livello 1 (i moduli sono i #);
     *  - altrimenti → 1 (default).
     * I conteggi h1/h2 vanno passati sull'HTML GIÀ normalizzato.
     */
    public function suggestSplitLevel(string $normalizedHtml): int
    {
        $h1 = preg_match_all('/<h1[^>]*>/i', $normalizedHtml);
        $h2 = preg_match_all('/<h2[^>]*>/i', $normalizedHtml);

        if ($h1 <= 1 && $h2 >= 2) {
            return 2;
        }
        if ($h1 >= 2) {
            return 1;
        }

        return 1;
    }

    private function extractTopLevelSections(string $html, int $level): array
    {
        $tag = $level === 1 ? 'h1' : 'h2';

        $parts = preg_split(
            "/(<{$tag}[^>]*>.*?<\\/{$tag}>)/is",
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        $modules = [];
        $current = null;
        $sortOrder = 0;

        foreach ($parts as $part) {
            if (preg_match("/^<{$tag}[^>]*>(.*?)<\\/{$tag}>$/is", $part, $m)) {
                if ($current !== null) {
                    $modules[] = $current;
                }
                $title = trim(strip_tags($m[1]));
                if ($title === '') {
                    $current = null;
                    continue;
                }
                $current = [
                    'title' => $title,
                    'short_description' => null,
                    'content_html' => $part,
                    'sort_order' => $sortOrder++,
                ];
            } else {
                if ($current !== null) {
                    $current['content_html'] .= $part;
                }
            }
        }
        if ($current !== null) {
            $modules[] = $current;
        }

        if ($level === 2) {
            foreach ($modules as &$mod) {
                $mod['content_html'] = preg_replace('/<(\/?)h2/', '<$1h1', $mod['content_html'], 1);
            }
            unset($mod);
        }

        return $modules;
    }
}
