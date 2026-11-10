#!/usr/bin/env python3
"""Guess category / type / person / year / numbers from filename + OCR text.
stdin JSON: {"filename":..., "text":..., "types":[{"name":..., "category":...}]}  ->  stdout JSON"""
import json
import os
import re
import sys

CATEGORY_WORDS = {
    "Criminal": ["criminal", "charge sheet", "accused", "republic v", "penal code", "bail", "prosecution"],
    "Civil": ["civil", "plaintiff", "defendant", "plaint", "written statement of defence", "affidavit"],
    "Administrative": ["memorandum", "circular", "administrative"],
    "Correspondence": ["dear sir", "dear madam", "yours faithfully", "yours sincerely", "our ref", "your ref"],
}
ROLE_RE = re.compile(r"([A-Z][A-Z'\- ]{4,50}?)\s*[.,:\-\s]{0,6}\s*(?:APPELLANT|APPLICANT|ACCUSED|PLAINTIFF|RESPONDENT|PETITIONER)\b")
YEAR_RE = re.compile(r"\b(19[5-9]\d|20[0-4]\d)\b")
DOCNO_RE = re.compile(r"(?i)\bno\.?\s*[:.]?\s*(\d{1,6}\s*(?:/|of)\s*(?:19|20)\d{2})")
REF_RE = re.compile(r"(?i)\b(?:our ref|your ref|ref(?:erence)?)\.?(?:\s*no\.?)?\s*[:.]?\s*([A-Z0-9][A-Z0-9/\-.]{3,30})")


def nice(name):
    name = re.sub(r"\s+", " ", name).strip(" -.,")
    return " ".join(w.capitalize() for w in name.split()) if name.isupper() or name.islower() else name


def main():
    d = json.load(sys.stdin)
    fn = d.get("filename") or ""
    text = (d.get("text") or "")[:6000]
    types = [t for t in d.get("types", []) if t["name"].lower() != "other"]
    stem = os.path.splitext(fn)[0].replace("_", " ")
    lstem, ltext = stem.lower(), text[:2500].lower()
    hay = lstem + " " + ltext

    scores = {c: sum(hay.count(w) for w in ws) for c, ws in CATEGORY_WORDS.items()}
    guess = max(scores, key=scores.get) if any(scores.values()) else None

    types.sort(key=lambda t: (t["category"] != guess, -len(t["name"])))
    dtype = None
    for source in (lstem, ltext):
        for t in types:
            if re.search(r"\b" + re.escape(t["name"].lower()) + r"\b", source):
                dtype = t
                break
        if dtype:
            break

    category = dtype["category"] if dtype else (guess or "Other")

    person = None
    for p in [x.strip() for x in re.split(r"\s+[-\u2013\u2014]\s+", stem) if x.strip()]:
        if re.fullmatch(r"[\d\s/.\-]+", p) or (dtype and dtype["name"].lower() in p.lower()):
            continue
        person = nice(p)
        break
    if not person:
        m = ROLE_RE.search(text)
        if m:
            person = nice(m.group(1))

    m = YEAR_RE.search(stem) or YEAR_RE.search(text[:2500])
    m1, m2 = DOCNO_RE.search(text), REF_RE.search(text)

    print(json.dumps({
        "category": category,
        "type": dtype["name"] if dtype else None,
        "person": person,
        "year": int(m.group(1)) if m else None,
        "document_number": re.sub(r"\s+", " ", m1.group(1)) if m1 else None,
        "reference_number": m2.group(1) if m2 else None,
    }))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(str(e), file=sys.stderr)
        sys.exit(1)
