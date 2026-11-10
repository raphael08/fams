#!/usr/bin/env python3
"""Page-by-page OCR. Prints one JSON line per page to stdout (Laravel saves them).

  python ocr.py --file /path/doc.pdf [--pages 1,5-7] [--lang eng+swa] [--dpi 200] [--enhance]
"""
import argparse
import gc
import json
import sys

from PIL import ImageFilter, ImageOps

from common import Source, lines_to_text, mean_conf, ocr_lines, parse_pages


def emit(**kw):
    if isinstance(kw.get("text"), str):
        kw["text"] = kw["text"].replace("\x00", "")
    print(json.dumps(kw), flush=True)


def enhance(img, strong):
    """Basic enhancement always; noise reduction + thresholding for difficult pages."""
    img = ImageOps.autocontrast(img, cutoff=1)
    if strong:
        img = img.filter(ImageFilter.MedianFilter(3)).filter(ImageFilter.SHARPEN)
        hist = img.histogram()
        mean = sum(i * c for i, c in enumerate(hist)) / max(1, img.width * img.height)
        img = img.point(lambda p, t=mean * 0.9: 255 if p > t else 0)
    return img


def ocr_image(img):
    lines = ocr_lines(img)
    return lines_to_text(lines), mean_conf(lines)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--file", required=True)
    ap.add_argument("--pages", default="")
    ap.add_argument("--lang", default="", help="ignored (compatibility)")
    ap.add_argument("--dpi", type=int, default=200)
    ap.add_argument("--low", type=float, default=70.0)
    ap.add_argument("--enhance", action="store_true", help="manual retry: force OCR + strong enhancement")
    a = ap.parse_args()

    src = Source(a.file)
    try:
        for n in parse_pages(a.pages, src.count):
            img = None
            try:
                if n < 1 or n > src.count:
                    raise ValueError("page out of range")
                layer = "" if a.enhance else src.text(n - 1).strip()
                if len(layer) >= 40:  # born-digital page: no OCR needed
                    emit(page=n, text=layer, confidence=100.0)
                    continue
                img = src.image(n - 1, a.dpi, gray=True)
                text, conf = ocr_image(enhance(img, False))
                if a.enhance or (conf is not None and conf < a.low):
                    t2, c2 = ocr_image(enhance(img, True))
                    if c2 is not None and (conf is None or c2 > conf):
                        text, conf = t2, c2
                emit(page=n, text=text, confidence=conf)
            except Exception as e:  # one bad page must not stop the document
                emit(page=n, error=str(e)[:500])
            finally:
                img = None
                gc.collect()
    finally:
        src.close()


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(str(e), file=sys.stderr)
        sys.exit(1)
