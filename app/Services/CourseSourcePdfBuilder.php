<?php

namespace App\Services;

use App\Support\Branding\ResolvedTheme;
use DOMDocument;
use DOMElement;
use DOMNode;
use TCPDF;

/**
 * P25.1 — Rigenera un PDF dal sorgente strutturato (blocchi tipizzati).
 *
 * Il PDF è un OUTPUT del sorgente, non il contrario. Obiettivo: FEDELTÀ DI
 * CONTENUTO E STRUTTURA, non estetica — il risultato è volutamente diverso dal
 * .docx originale. La gerarchia PART/H1/H2 deve essere visivamente distinguibile.
 *
 * Font: DejaVuSans (unicode, bundled in TCPDF) per rendere em-dash, apostrofi
 * tipografici e accenti senza glyph mancanti.
 */
class CourseSourcePdfBuilder
{
    // Palette di DEFAULT (P25 sorgente strutturato: nessun tema → teal admin).
    private const TEAL = [85, 177, 174];   // #55B1AE — PART band / H2 (default storico)
    private const INK = [26, 31, 31];      // testo (default storico)
    private const MUTED = [138, 150, 150]; // #8A9696 — sottotitolo header (P29)
    private const BOX_FILL = [244, 246, 246];
    private const BOX_BORDER = [200, 210, 210];

    // Palette ATTIVA del render corrente. Resta sui default (teal/ink) se non
    // arriva un ResolvedTheme; con un tema, banda/H2/accenti usano l'accent del
    // brand e il testo l'ink del brand. Il renderer NON sa quale brand sia
    // (GLITCH, scuola, …): è agnostico al tema.
    private array $accentRgb = self::TEAL;
    private array $inkRgb = self::INK;

    /** Diagnostica dell'ultima build (per i log/round-trip). */
    public int $lastRenderedBlocks = 0;
    public int $lastPageCount = 0;

    /**
     * Costruisce i bytes del PDF dai blocchi.
     *
     * @param  list<array>  $blocks  blocchi tipizzati (output di CourseSourceExtractor)
     * @param  array{title?: string}  $meta
     * @param  ResolvedTheme|null  $theme  tema brand (accent/ink); null → default teal (P25)
     */
    public function build(array $blocks, array $meta = [], ?ResolvedTheme $theme = null): string
    {
        // Tema arbitrario: il renderer usa qualunque brand gli arrivi. Null = retrocompat
        // P25 (teal). Reset esplicito a ogni build (l'istanza può essere riusata via DI).
        $this->accentRgb = $theme ? $this->hexToRgb($theme->accent, self::TEAL) : self::TEAL;
        $this->inkRgb = $theme ? $this->hexToRgb($theme->ink, self::INK) : self::INK;

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Atheneum');
        $pdf->SetTitle($meta['title'] ?? 'Corso — sorgente strutturato');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 18, 20);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();

        $rendered = 0;
        foreach ($blocks as $block) {
            $this->renderBlock($pdf, $block, $rendered);
            $rendered++;
        }

        $this->lastRenderedBlocks = $rendered;
        $this->lastPageCount = $pdf->getNumPages();

        return $pdf->Output('', 'S');
    }

    /**
     * P29 — costruisce il PDF dal CONTENT HTML semantico di un modulo
     * (h2/h3/h4/p/ul/ol/li/strong) convertendolo nei blocchi tipizzati che
     * build() già sa rendere. Nessun renderer nuovo: solo un adattatore HTML→blocchi.
     *
     * @param  array{title?: string, subtitle?: string}  $meta  title = header documento, subtitle = corso
     * @param  ResolvedTheme|null  $theme  tema brand passato dal service (GLITCH oggi, scuola domani)
     */
    public function buildFromHtml(string $html, array $meta = [], ?ResolvedTheme $theme = null): string
    {
        $blocks = [];

        $title = trim((string) ($meta['title'] ?? ''));
        if ($title !== '') {
            $blocks[] = ['type' => 'TITLE', 'text' => $title, 'subtitle' => trim((string) ($meta['subtitle'] ?? ''))];
        }

        // La banda TITLE rende già il titolo: se il content ripete lo stesso titolo
        // come primo heading, lo rimuoviamo (no titolo duplicato in testa).
        $content = $this->stripLeadingTitleEcho($this->htmlToBlocks($html), $title);
        foreach ($content as $block) {
            $blocks[] = $block;
        }

        return $this->build($blocks, $meta, $theme);
    }

    /**
     * P29 Fase 2 — costruisce il PDF dell'INTERO corso da più sezioni-modulo:
     * header del corso (TITLE) + per ogni modulo una banda PART col titolo (nuova
     * pagina) seguita dai blocchi del suo content HTML. Riusa htmlToBlocks/build —
     * nessun renderer nuovo. Tema arbitrario come buildFromHtml (agnostico).
     *
     * @param  list<array{title?: string, html?: string}>  $sections  moduli ordinati
     * @param  array{title?: string, subtitle?: string}  $meta  title = nome corso, subtitle = sottotitolo
     */
    public function buildFromSections(array $sections, array $meta = [], ?ResolvedTheme $theme = null): string
    {
        $blocks = [];

        $title = trim((string) ($meta['title'] ?? ''));
        if ($title !== '') {
            $blocks[] = ['type' => 'TITLE', 'text' => $title, 'subtitle' => trim((string) ($meta['subtitle'] ?? ''))];
        }

        foreach ($sections as $section) {
            $secTitle = trim((string) ($section['title'] ?? ''));
            if ($secTitle !== '') {
                // PART = banda piena su nuova pagina (un modulo per pagina).
                $blocks[] = ['type' => 'PART', 'text' => $secTitle];
            }
            // La banda PART rende già il titolo del modulo: deduplica l'eco in testa.
            $secBlocks = $this->stripLeadingTitleEcho($this->htmlToBlocks((string) ($section['html'] ?? '')), $secTitle);
            foreach ($secBlocks as $block) {
                $blocks[] = $block;
            }
        }

        return $this->build($blocks, $meta, $theme);
    }

    /**
     * P29 — se il PRIMO blocco è un heading che ripete il titolo già reso dalla
     * banda TITLE/PART del builder, lo rimuove (evita il titolo duplicato in testa
     * al documento/sezione). Solo il primo blocco e solo se heading: eventuali
     * ripetizioni più in basso restano. Difetto di rendering, non del content.
     *
     * @param  list<array>  $blocks
     * @return list<array>
     */
    private function stripLeadingTitleEcho(array $blocks, string $title): array
    {
        $title = $this->normalizeHeading($title);
        if ($title === '' || $blocks === []) {
            return $blocks;
        }

        $first = $blocks[0];
        $isHeading = in_array($first['type'] ?? '', ['H1', 'H2', 'H3'], true);
        if ($isHeading && $this->normalizeHeading((string) ($first['text'] ?? '')) === $title) {
            array_shift($blocks);
        }

        return array_values($blocks);
    }

    /** Normalizza un heading per il confronto: trim, spazi collassati, minuscole. */
    private function normalizeHeading(string $s): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
    }

    /**
     * Hex (con o senza '#', 3 o 6 cifre) → [r,g,b]. Input non valido → fallback.
     *
     * @param  array{0:int,1:int,2:int}  $fallback
     * @return array{0:int,1:int,2:int}
     */
    private function hexToRgb(string $hex, array $fallback): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return $fallback;
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * Converte l'HTML semantico del modulo nei blocchi tipizzati del builder.
     * Mappatura: h2→H1 (capitolo), h3→H2 (sezione teal), h4/h5/h6→H3 (sotto-sezione),
     * p→P, ul→BUL, ol→NUM, blockquote→BOX. I tag inline (strong/em/a/span) si
     * appiattiscono a testo: il documento punta alla pulizia strutturale, non allo
     * styling inline.
     *
     * @return list<array>
     */
    private function htmlToBlocks(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // Prefisso XML per forzare UTF-8 (em-dash/accenti) senza mb_convert_encoding (rimosso in PHP 8.x).
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return [];
        }

        $blocks = [];
        $this->collectBlocks($body, $blocks);

        return $blocks;
    }

    /** Visita ricorsiva: emette un blocco per ogni elemento di blocco noto, ricorre nei contenitori. */
    private function collectBlocks(DOMNode $node, array &$blocks): void
    {
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);
            $text = $this->nodeText($child);

            switch ($tag) {
                case 'h1':
                case 'h2':
                    if ($text !== '') {
                        $blocks[] = ['type' => 'H1', 'text' => $text];
                    }
                    break;

                case 'h3':
                    if ($text !== '') {
                        $blocks[] = ['type' => 'H2', 'text' => $text];
                    }
                    break;

                case 'h4':
                case 'h5':
                case 'h6':
                    if ($text !== '') {
                        $blocks[] = ['type' => 'H3', 'text' => $text];
                    }
                    break;

                case 'p':
                    if ($text !== '') {
                        $blocks[] = ['type' => 'P', 'text' => $text];
                    }
                    break;

                case 'ul':
                    $items = $this->listItems($child);
                    if ($items !== []) {
                        $blocks[] = ['type' => 'BUL', 'items' => $items];
                    }
                    break;

                case 'ol':
                    $items = $this->listItems($child);
                    if ($items !== []) {
                        $blocks[] = ['type' => 'NUM', 'items' => $items];
                    }
                    break;

                case 'blockquote':
                    if ($text !== '') {
                        $blocks[] = ['type' => 'BOX', 'text' => $text];
                    }
                    break;

                case 'div':
                case 'section':
                case 'article':
                    // Contenitore senza semantica propria → ricorri nei figli.
                    $this->collectBlocks($child, $blocks);
                    break;

                default:
                    // Elemento sconosciuto con testo → paragrafo (mai contenuto perso).
                    if ($text !== '') {
                        $blocks[] = ['type' => 'P', 'text' => $text];
                    }
                    break;
            }
        }
    }

    /** Voci di una lista: testo di ogni <li> diretto, vuote scartate. @return list<string> */
    private function listItems(DOMElement $list): array
    {
        $items = [];
        foreach ($list->childNodes as $li) {
            if ($li instanceof DOMElement && strtolower($li->nodeName) === 'li') {
                $text = $this->nodeText($li);
                if ($text !== '') {
                    $items[] = $text;
                }
            }
        }

        return $items;
    }

    /** Testo di un nodo, spazi normalizzati (inline tags appiattiti via textContent). */
    private function nodeText(DOMNode $node): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
    }

    private function renderBlock(TCPDF $pdf, array $block, int $index): void
    {
        $type = $block['type'] ?? 'P';
        $text = trim((string) ($block['text'] ?? ''));

        switch ($type) {
            case 'TITLE':
                // P29 — header documento: banda teal piena (brand GLITCH) + titolo
                // modulo (NON uppercase, distinto dal PART) + sottotitolo = corso.
                $pdf->SetFont('dejavusans', 'B', 18);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFillColorArray($this->accentRgb);
                // maxh=0 → la banda cresce e manda a capo i titoli lunghi (niente clip).
                $pdf->MultiCell(0, 11, $text, 0, 'L', true, 1, '', '', true, 0, false, true, 0, 'M');
                $pdf->SetTextColorArray($this->inkRgb);
                $subtitle = trim((string) ($block['subtitle'] ?? ''));
                if ($subtitle !== '') {
                    $pdf->Ln(2);
                    $pdf->SetFont('dejavusans', '', 11);
                    $pdf->SetTextColorArray(self::MUTED);
                    $pdf->MultiCell(0, 6, $subtitle, 0, 'L');
                    $pdf->SetTextColorArray($this->inkRgb);
                }
                $pdf->Ln(5);
                break;

            case 'PART':
                // Nuova pagina (tranne se è il primissimo blocco) + banda colorata piena.
                if ($index > 0) {
                    $pdf->AddPage();
                }
                $pdf->Ln(2);
                $pdf->SetFont('dejavusans', 'B', 19);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFillColorArray($this->accentRgb);
                $pdf->MultiCell(0, 12, mb_strtoupper($text), 0, 'L', true, 1, '', '', true, 0, false, true, 12, 'M');
                $pdf->SetTextColorArray($this->inkRgb);
                $pdf->Ln(4);
                break;

            case 'H1':
                // Capitolo: grande, grassetto, con riga inferiore (border 'B').
                $pdf->Ln(3);
                $pdf->SetFont('dejavusans', 'B', 15);
                $pdf->SetTextColorArray($this->inkRgb);
                $pdf->MultiCell(0, 8, $text, 'B', 'L');
                $pdf->Ln(2);
                break;

            case 'H2':
                // Sezione: medio, grassetto, colore teal — distinto da H1.
                $pdf->Ln(1.5);
                $pdf->SetFont('dejavusans', 'B', 12);
                $pdf->SetTextColorArray($this->accentRgb);
                $pdf->MultiCell(0, 6.5, $text, 0, 'L');
                $pdf->SetTextColorArray($this->inkRgb);
                $pdf->Ln(1);
                break;

            case 'H3':
                // P29 — sotto-sezione (module h4): grassetto ink, piccolo, niente
                // colore → distinto da H2 (teal) e dal corpo.
                $pdf->Ln(1);
                $pdf->SetFont('dejavusans', 'B', 10.5);
                $pdf->SetTextColorArray($this->inkRgb);
                $pdf->MultiCell(0, 5.5, $text, 0, 'L');
                $pdf->Ln(0.5);
                break;

            case 'BOX':
                $pdf->Ln(1);
                $pdf->SetFont('dejavusans', '', 10);
                $pdf->SetFillColorArray(self::BOX_FILL);
                $pdf->SetDrawColorArray(self::BOX_BORDER);
                $pdf->MultiCell(0, 5, $text, 1, 'L', true);
                $pdf->Ln(2);
                break;

            case 'EX':
            case 'ESE':
                $label = $type === 'EX' ? 'ESEMPIO' : 'ESERCIZIO';
                $pdf->Ln(1);
                $pdf->SetFont('dejavusans', 'B', 9);
                $pdf->SetTextColorArray($this->accentRgb);
                $pdf->MultiCell(0, 5, $label, 0, 'L');
                $pdf->SetTextColorArray($this->inkRgb);
                $pdf->SetFont('dejavusans', '', 10);
                $pdf->SetFillColorArray(self::BOX_FILL);
                $pdf->SetDrawColorArray(self::BOX_BORDER);
                $pdf->MultiCell(0, 5, $text, 1, 'L', true);
                $pdf->Ln(2);
                break;

            case 'NUM':
            case 'BUL':
                $pdf->SetFont('dejavusans', '', 10.5);
                $pdf->SetTextColorArray($this->inkRgb);
                $items = $block['items'] ?? [];
                foreach ($items as $i => $item) {
                    $marker = $type === 'NUM' ? (($i + 1) . '.') : '•';
                    $pdf->MultiCell(0, 5, $marker . '  ' . trim((string) $item), 0, 'L');
                }
                $pdf->Ln(1.5);
                break;

            case 'P':
            default:
                $pdf->SetFont('dejavusans', '', 10.5);
                $pdf->SetTextColorArray($this->inkRgb);
                $pdf->MultiCell(0, 5.2, $text, 0, 'J');
                $pdf->Ln(1.5);
                break;
        }
    }
}
