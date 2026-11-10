"""Shared helpers: open a PDF or image file and read it one page at a time."""
import os

from PIL import Image, ImageOps

try:
    import pymupdf as fitz
except ImportError:  # older PyMuPDF
    import fitz

IMAGE_EXT = {".jpg", ".jpeg", ".png", ".tif", ".tiff"}


class Source:
    """Uniform page access for PDFs (PyMuPDF) and images (Pillow, multi-frame TIFF ok)."""

    def __init__(self, path):
        self.path = path
        self.is_image = os.path.splitext(path)[1].lower() in IMAGE_EXT
        if self.is_image:
            self.img = Image.open(path)
            self.doc = None
            self.count = getattr(self.img, "n_frames", 1)
        else:
            self.doc = fitz.open(path)
            if self.doc.needs_pass:
                raise ValueError("PDF is password protected")
            self.count = self.doc.page_count

    def text(self, i):
        """Embedded text layer of page i (0-based). Images have none."""
        if self.is_image:
            return ""
        return self.doc.load_page(i).get_text()

    def image(self, i, dpi=200, gray=False):
        """Render page i (0-based) to a PIL image. Only this one page is held in memory."""
        if self.is_image:
            self.img.seek(i)
            im = ImageOps.exif_transpose(self.img.copy())
            return im.convert("L" if gray else "RGB")
        page = self.doc.load_page(i)
        cs = fitz.csGRAY if gray else fitz.csRGB
        pix = page.get_pixmap(dpi=dpi, colorspace=cs, alpha=False)
        im = Image.frombytes("L" if gray else "RGB", (pix.width, pix.height), pix.samples)
        del pix, page
        return im

    def image_dpi(self):
        try:
            d = self.img.info.get("dpi", (200, 200))[0]
            return d if 50 <= d <= 1200 else 200
        except Exception:
            return 200

    def close(self):
        if self.doc is not None:
            self.doc.close()
        elif getattr(self, "img", None) is not None:
            self.img.close()


def parse_pages(spec, count):
    """'1,3,5-7' -> [1,3,5,6,7]; whole document when spec is empty."""
    if not spec:
        return list(range(1, count + 1))
    pages = set()
    for part in spec.split(","):
        part = part.strip()
        if not part:
            continue
        if "-" in part:
            a, b = part.split("-", 1)
            pages.update(range(int(a), int(b) + 1))
        else:
            pages.add(int(part))
    return sorted(pages)


# --- OCR engine (RapidOCR / ONNX) ---------------------------------------------
# Pure pip install: the models ship inside the wheel. No tesseract.exe, no OS packages.
_engine = None


def engine():
    global _engine
    if _engine is None:
        from rapidocr_onnxruntime import RapidOCR
        _engine = RapidOCR()
    return _engine


def ocr_lines(img):
    """OCR a PIL image -> list of dicts {x0,y0,x1,y1,text,conf(0-100)} in reading order."""
    import numpy as np
    if img.mode != "RGB":
        img = img.convert("RGB")
    res, _ = engine()(np.array(img))
    lines = []
    for box, text, score in (res or []):
        text = (text or "").strip()
        if not text:
            continue
        xs = [p[0] for p in box]
        ys = [p[1] for p in box]
        lines.append({"x0": min(xs), "y0": min(ys), "x1": max(xs), "y1": max(ys),
                      "text": text, "conf": float(score) * 100.0})
    lines.sort(key=lambda l: (round((l["y0"] + l["y1"]) / 2 / max(1.0, (l["y1"] - l["y0"]))), l["x0"]))
    return lines


def lines_to_text(lines):
    """Same row -> space, next row -> newline, big vertical gap -> blank line."""
    out, prev = [], None
    for l in lines:
        if prev is not None:
            h = max(1.0, prev["y1"] - prev["y0"])
            same_row = abs((l["y0"] + l["y1"]) / 2 - (prev["y0"] + prev["y1"]) / 2) < h * 0.6
            if same_row:
                out.append(" ")
            elif l["y0"] - prev["y1"] > h * 1.2:
                out.append("\n\n")
            else:
                out.append("\n")
        out.append(l["text"])
        prev = l
    return "".join(out)


def mean_conf(lines):
    total = sum(len(l["text"]) for l in lines)
    if not total:
        return None
    return round(sum(l["conf"] * len(l["text"]) for l in lines) / total, 2)
