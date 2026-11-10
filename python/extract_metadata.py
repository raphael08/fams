#!/usr/bin/env python3
"""Reads embedded PDF metadata (title/author/creation date) as JSON.   python extract_metadata.py --file F"""
import argparse
import json
import re
import sys

from common import Source


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--file", required=True)
    a = ap.parse_args()
    src = Source(a.file)
    try:
        meta = {} if src.is_image else (src.doc.metadata or {})
    finally:
        src.close()
    year = None
    m = re.search(r"(19|20)\d{2}", meta.get("creationDate", "") or "")
    if m:
        year = int(m.group(0))
    print(json.dumps({"title": meta.get("title") or None, "author": meta.get("author") or None, "created_year": year}))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(str(e), file=sys.stderr)
        sys.exit(1)
