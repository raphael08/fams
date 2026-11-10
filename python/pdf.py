#!/usr/bin/env python3
"""  python pdf.py info   --file F
     python pdf.py render --file F --page N --out out.jpg [--dpi 150]"""
import argparse
import json
import os
import sys

from common import Source


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("cmd", choices=["info", "render"])
    ap.add_argument("--file", required=True)
    ap.add_argument("--page", type=int, default=1)
    ap.add_argument("--out")
    ap.add_argument("--dpi", type=int, default=150)
    a = ap.parse_args()

    src = Source(a.file)
    try:
        if a.cmd == "info":
            print(json.dumps({"page_count": src.count}))
            return
        if a.page < 1 or a.page > src.count:
            raise ValueError("page out of range")
        im = src.image(a.page - 1, a.dpi)
        if src.is_image:
            im.thumbnail((1800, 100000))
        tmp = a.out + ".part"
        im.save(tmp, "JPEG", quality=82)
        os.replace(tmp, a.out)
    finally:
        src.close()


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(str(e), file=sys.stderr)
        sys.exit(1)
