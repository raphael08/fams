#!/usr/bin/env python3
"""Diagnose the OCR environment:   python check_setup.py"""
import sys

print("Python        :", sys.version.split()[0], sys.executable)
for mod in ("pymupdf", "PIL", "numpy", "rapidocr_onnxruntime"):
    try:
        m = __import__(mod)
        print("%-20s: OK %s" % (mod, getattr(m, "__version__", "")))
    except Exception as e:
        print("%-20s: MISSING (%s)  -> pip install -r requirements.txt" % (mod, e))
        sys.exit(1)

from PIL import Image, ImageDraw, ImageFont
from common import lines_to_text, ocr_lines

try:
    im = Image.new("RGB", (900, 140), "white")
    try:
        font = ImageFont.load_default(size=36)
    except TypeError:
        font = ImageFont.load_default()
    ImageDraw.Draw(im).text((20, 40), "IN THE HIGH COURT OF TANZANIA", fill="black", font=font)
    print("Test OCR      :", repr(lines_to_text(ocr_lines(im))))
    print("\nALL GOOD")
except Exception as e:
    print("OCR engine    : PROBLEM ->", e)
    sys.exit(1)
