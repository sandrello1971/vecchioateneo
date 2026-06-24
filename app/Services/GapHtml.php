<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * P26 Fase D — Strumenti HTML per l'inserimento:
 *  - toBlocks(): converte la bozza formatore_html in BLOCCHI tipizzati per course_sources, con id
 *    fuori dallo schema posizionale (suffisso "-insN", niente collisione) e meta.origin='gap_insert'.
 *  - spliceAfter(): inserisce un frammento HTML DOPO l'ancora testuale, con parsing DOM robusto
 *    (non rompe il markup esistente). Usato per lo studente (modules.content) e il formatore live.
 */
class GapHtml
{
    /** @return list<array<string,mixed>> blocchi {id,type,text|items,meta} */
    public static function toBlocks(string $html, string $anchorBlockId): array
    {
        $root = self::wrap($html);
        if (!$root) {
            return [];
        }

        $blocks = [];
        $n = 0;
        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($node->nodeName);
            $text = self::norm($node->textContent);
            if ($text === '') {
                continue;
            }

            $type = match (true) {
                in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5'], true) => 'H2',
                $tag === 'ul' => 'BUL',
                $tag === 'ol' => 'NUM',
                in_array($tag, ['blockquote', 'div'], true) => 'BOX',
                default => 'P',
            };

            $n++;
            $block = ['id' => $anchorBlockId . '-ins' . $n, 'type' => $type, 'meta' => ['origin' => 'gap_insert']];
            if (in_array($type, ['BUL', 'NUM'], true)) {
                $items = [];
                foreach ($node->getElementsByTagName('li') as $li) {
                    $t = self::norm($li->textContent);
                    if ($t !== '') {
                        $items[] = $t;
                    }
                }
                $block['items'] = $items ?: [$text];
            } else {
                $block['text'] = $text;
            }
            $blocks[] = $block;
        }

        return $blocks;
    }

    /** Inserisce $fragment DOPO il blocco top-level che contiene $anchor. null se l'ancora non c'è. */
    public static function spliceAfter(string $html, string $anchor, string $fragment): ?string
    {
        $anchor = trim($anchor);
        if ($anchor === '') {
            return null;
        }
        $root = self::wrap($html);
        if (!$root) {
            return null;
        }
        $dom = $root->ownerDocument;

        $target = self::findContaining($root, $anchor);
        if (!$target) {
            return null; // ancora non trovata: NON inserire a caso
        }
        // Sali fino al blocco figlio diretto del contenitore.
        $block = $target;
        while ($block->parentNode && $block->parentNode !== $root) {
            $block = $block->parentNode;
        }

        $fragRoot = self::wrap($fragment);
        $ref = $block->nextSibling;
        if ($fragRoot) {
            foreach (iterator_to_array($fragRoot->childNodes) as $child) {
                $imported = $dom->importNode($child, true);
                $root->insertBefore($imported, $ref);
            }
        }

        return self::inner($root);
    }

    private static function wrap(string $html): ?DOMElement
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // <?xml encoding> forza UTF-8; NOIMPLIED/NODEFDTD evita html/body/doctype impliciti.
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        return $dom->documentElement instanceof DOMElement ? $dom->documentElement : null;
    }

    private static function findContaining(DOMNode $node, string $anchor): ?DOMElement
    {
        $found = null;
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && mb_strpos($child->textContent, $anchor) !== false) {
                $found = self::findContaining($child, $anchor) ?: $child;
            }
        }

        return $found;
    }

    private static function inner(DOMElement $root): string
    {
        $out = '';
        foreach ($root->childNodes as $c) {
            $out .= $root->ownerDocument->saveHTML($c);
        }

        return $out;
    }

    private static function norm(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
