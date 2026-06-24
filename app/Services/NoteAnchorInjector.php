<?php

namespace App\Services;

class NoteAnchorInjector
{
    /**
     * Aggiunge id="p-001", "p-002"... ai blocchi annotabili dell'HTML modulo.
     * Tag target: p, h1-h6, li, blockquote.
     * Skippa elementi che già hanno un id.
     *
     * Gli id sono stabili rispetto all'ordine dei blocchi: lo stesso content
     * produce sempre la stessa sequenza di anchor.
     */
    public function inject(string $html): string
    {
        if (empty($html)) return $html;

        $counter = 0;

        return preg_replace_callback(
            '/<(p|h2|h3|h4)(\s[^>]*)?>/i',
            function ($m) use (&$counter) {
                $tag = strtolower($m[1]);
                $attrs = $m[2] ?? '';

                // Se il tag ha già un id, non lo tocco
                if (preg_match('/\bid\s*=/i', $attrs)) {
                    return $m[0];
                }

                $counter++;
                $anchor = sprintf('p-%03d', $counter);
                return '<' . $tag . $attrs . ' id="' . $anchor . '" data-note-anchor="' . $anchor . '">';
            },
            $html
        );
    }
}
