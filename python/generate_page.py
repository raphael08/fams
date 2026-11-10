#!/usr/bin/env python3
"""Build a new PDF from selected pages, or a searchable PDF. The original is never touched.

  python generate_page.py --file F --pages 3,4,6,27 --out out.pdf
  python generate_page.py --file F --searchable --out out.pdf [--lang eng+swa] [--dpi 200]"""
import argparse
import io
import sys

from common import Source, fitz, ocr_lines, parse_pages


def selected_pages(src, pages, out):
    doc = fitz.open()
    for n in pages:
        if n < 1 or n > src.count:
            raise ValueError("page %d does not exist" % n)
        if not src.is_image:
            doc.insert_pdf(src.doc, from_page=n - 1, to_page=n - 1)
        else:
            im = src.image(n - 1)
            buf = io.BytesIO()
            im.save(buf, "JPEG", quality=90)
            dpi = src.image_dpi()
            page = doc.new_page(width=im.width * 72 / dpi, height=im.height * 72 / dpi)
            page.insert_image(page.rect, stream=buf.getvalue())
    doc.save(out, garbage=3, deflate=True)
    doc.close()


def overlay_text(page, lines, img_w, img_h):
    """Invisible (render_mode=3) text laid over the scan so the PDF is selectable/searchable."""
    sx = page.rect.width / img_w
    sy = page.rect.height / img_h
    for l in lines:
        h = (l["y1"] - l["y0"]) * sy
        w = (l["x1"] - l["x0"]) * sx
        if h < 1 or w < 1:
            continue
        size = max(4.0, min(h * 0.9, 72.0))
        txt = l["text"].encode("latin-1", "replace").decode("latin-1")
        natural = fitz.get_text_length(txt, fontname="helv", fontsize=size)
        if natural > 0 and w / natural < 0.98:
            size = max(3.0, size * w / natural)
        page.insert_text((l["x0"] * sx, l["y1"] * sy - h * 0.2), txt,
                         fontname="helv", fontsize=size, render_mode=3)


def searchable(src, out, dpi):
    doc = fitz.open()
    for i in range(src.count):  # one page at a time
        im = src.image(i, dpi)
        if src.is_image:
            buf = io.BytesIO()
            im.save(buf, "JPEG", quality=90)
            page = doc.new_page(width=im.width * 72 / dpi, height=im.height * 72 / dpi)
            page.insert_image(page.rect, stream=buf.getvalue())
            overlay_text(page, ocr_lines(im), im.width, im.height)
        else:
            doc.insert_pdf(src.doc, from_page=i, to_page=i)   # original page untouched
            if len(src.text(i).strip()) < 40:                  # scanned page only
                overlay_text(doc[-1], ocr_lines(im), im.width, im.height)
        del im
        print(i + 1, flush=True)
    doc.save(out, garbage=3, deflate=True)
    doc.close()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--file", required=True)
    ap.add_argument("--pages", default="")
    ap.add_argument("--out", required=True)
    ap.add_argument("--searchable", action="store_true")
    ap.add_argument("--lang", default="", help="ignored (compatibility)")
    ap.add_argument("--dpi", type=int, default=200)
    a = ap.parse_args()
    src = Source(a.file)
    try:
        if a.searchable:
            searchable(src, a.out, a.dpi)
        else:
            selected_pages(src, parse_pages(a.pages, src.count), a.out)
    finally:
        src.close()


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(str(e), file=sys.stderr)
        sys.exit(1)
