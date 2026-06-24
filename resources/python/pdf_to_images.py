#!/usr/bin/env python
"""S1 — anteprima slide: converte un PDF (reso da LibreOffice dal .pptx) in un
PNG per pagina. Usato da SlidePreviewService; gemello operativo di build_pptx.py.

Uso:  pdf_to_images.py <pdf_in> <out_dir> <dpi>
Scrive <out_dir>/slide_1.png, slide_2.png, ... e stampa su stdout il numero di
slide generate. Esce con codice != 0 (e messaggio su stderr) in caso di errore.
"""
import sys

from pdf2image import convert_from_path


def main() -> int:
    if len(sys.argv) != 4:
        sys.stderr.write("uso: pdf_to_images.py <pdf_in> <out_dir> <dpi>\n")
        return 2

    pdf_in, out_dir, dpi = sys.argv[1], sys.argv[2], int(sys.argv[3])

    images = convert_from_path(pdf_in, dpi=dpi)
    for i, img in enumerate(images, start=1):
        img.save(f"{out_dir}/slide_{i}.png", "PNG")

    print(len(images))
    return 0


if __name__ == "__main__":
    sys.exit(main())
