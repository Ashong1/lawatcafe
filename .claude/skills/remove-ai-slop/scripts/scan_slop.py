#!/usr/bin/env python3
"""Find AI-slop candidates in a Laravel codebase.

Prints candidates grouped by file, with line numbers and a category. It only
REPORTS — every hit is a candidate for a human-quality judgement call, never an
automatic fix (see SKILL.md for how to decide).

Usage:
    python3 scan_slop.py [ROOT] [--only copy|comments|code] [--max N] [--summary]

Categories:
    copy/buzzword    marketing filler in visible Blade text
    copy/codename    snake_case / camelCase identifiers in visible Blade text
    copy/emoji       decorative emoji in visible text or user-facing PHP strings
    comments/diary   history instead of reasons: dates, versions, "used to", quotes of reports
    comments/long    comment blocks long enough to deserve a second look
    code/unused-use  PHP `use` imports never referenced in the file
"""
import argparse
import os
import re
import sys
from collections import defaultdict

BUZZWORDS = [
    r"enterprise(?:[- ]grade)?", r"intelligen(?:t|ce)\b(?! report)", r"seamless(?:ly)?", r"leverag(?:e|es|ing)",
    r"cutting[- ]edge", r"state[- ]of[- ]the[- ]art", r"next[- ]gen(?:eration)?", r"world[- ]class",
    r"robust", r"synerg\w*", r"revolutioni[sz]\w*", r"empower\w*", r"unlock\w*", r"elevate\w*",
    r"premium", r"monetiz\w*", r"digital concierge", r"smart analysis", r"system online",
    r"blazing(?:ly)? fast", r"supercharg\w*", r"game[- ]chang\w*", r"effortless(?:ly)?",
    r"harness(?:es|ing)? the power", r"in today's", r"delve", r"tapestry", r"streamlin\w*",
    r"holistic", r"best[- ]in[- ]class", r"mission[- ]critical", r"live monitoring",
]
BUZZ_RE = re.compile(r"\b(?:" + "|".join(BUZZWORDS) + r")", re.I)
EMOJI_RE = re.compile("[\U0001F300-\U0001FAFF☀-➿⭐⬆↔-↪]")
SNAKE_RE = re.compile(r"\b[a-z]+(?:_[a-z0-9]+)+\b")
CAMEL_RE = re.compile(r"\b[a-z]+(?:[A-Z][a-z0-9]+){1,}\b")
DIARY_RE = re.compile(
    r"\b20\d\d-\d\d-\d\d\b|\bv\d+\.\d+\.\d+(?:\.\d+)?\b|\bused to\b|\bpreviously\b|\bthis replaced\b"
    r"|\b(?:owner|user|guest)(?: feedback)?:\s*[\"']|\bwas reported\b|\breported (?:live|by)\b|\blive(?: check| report)?:",
    re.I,
)
SKIP_DIRS = {"vendor", "node_modules", "storage", "bootstrap", "public", ".git", ".claude"}


def iter_files(root, exts):
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for name in filenames:
            if name.endswith(exts):
                yield os.path.join(dirpath, name)


def _blank(m):
    """Replace a match with the same number of newlines, so line numbers survive."""
    return "\n" * m.group(0).count("\n")


# A tag, respecting quoted attribute values that contain '>' (Alpine: "x => x > 1").
TAG_RE = re.compile(r"""<[A-Za-z/!][^"'>]*(?:(?:"[^"]*"|'[^']*')[^"'>]*)*>""", re.S)


def visible_text(src):
    """What a person can read on the rendered page, one entry per source line."""
    for pattern in (r"\{\{--.*?--\}\}", r"@php\b.*?@endphp", r"<script\b.*?</script>", r"<style\b.*?</style>",
                    r"\{\{.*?\}\}", r"\{!!.*?!!\}"):
        src = re.sub(pattern, _blank, src, flags=re.S | re.I)
    src = TAG_RE.sub(_blank, src)
    src = re.sub(r"@\w+(?:\s*\((?:[^()]|\([^()]*\))*\))?", " ", src)  # Blade directives
    return src.split("\n")


def scan_blade(path, hits):
    raw_lines = open(path, encoding="utf-8", errors="replace").read().split("\n")
    for no, text in enumerate(visible_text("\n".join(raw_lines)), 1):
        text = text.strip()
        if not text:
            continue
        ctx = raw_lines[no - 1].strip()
        for m in BUZZ_RE.finditer(text):
            hits[path].append((no, "copy/buzzword", m.group(0), ctx))
        for m in SNAKE_RE.finditer(text):
            hits[path].append((no, "copy/codename", m.group(0), ctx))
        for m in CAMEL_RE.finditer(text):
            if m.group(0) not in {"iPhone", "iPad", "iOS", "macOS", "eWallet", "gCash"}:
                hits[path].append((no, "copy/codename", m.group(0), ctx))
        e = EMOJI_RE.search(text)
        if e:
            hits[path].append((no, "copy/emoji", e.group(0), ctx))


def comment_blocks(lines):
    """Yield (start_line, [comment lines]) for // runs, /* */ blocks and {{-- --}} blocks."""
    i = 0
    while i < len(lines):
        s = lines[i].strip()
        if s.startswith("/*") or s.startswith("{{--"):
            end = "*/" if s.startswith("/*") else "--}}"
            start, block = i, [lines[i]]
            while end not in lines[i] and i + 1 < len(lines):
                i += 1
                block.append(lines[i])
            yield start + 1, block
        elif s.startswith("//"):
            start, block = i, []
            while i < len(lines) and lines[i].strip().startswith("//"):
                block.append(lines[i])
                i += 1
            yield start + 1, block
            continue
        i += 1


def scan_comments(path, hits, long_at):
    lines = open(path, encoding="utf-8", errors="replace").read().split("\n")
    for start, block in comment_blocks(lines):
        text = " ".join(l.strip() for l in block)
        m = DIARY_RE.search(text)
        if m:
            hits[path].append((start, "comments/diary", m.group(0), block[0].strip()[:100]))
        if len(block) >= long_at:
            hits[path].append((start, "comments/long", f"{len(block)} lines", block[0].strip()[:100]))


def scan_php_strings(path, hits):
    for no, raw in enumerate(open(path, encoding="utf-8", errors="replace"), 1):
        s = raw.strip()
        if s.startswith(("//", "*", "/*")):
            continue
        for q in re.findall(r"'([^'\n]{8,})'|\"([^\"\n]{8,})\"", raw):
            text = q[0] or q[1]
            if EMOJI_RE.search(text):
                hits[path].append((no, "copy/emoji", EMOJI_RE.search(text).group(0), s[:120]))


def scan_unused_imports(path, hits):
    src = open(path, encoding="utf-8", errors="replace").read()
    body = re.sub(r"^use [^;]+;\s*$", "", src, flags=re.M)
    for m in re.finditer(r"^use ([\w\\]+)(?: as (\w+))?;", src, re.M):
        short = m.group(2) or m.group(1).split("\\")[-1]
        if not re.search(r"\b" + re.escape(short) + r"\b", body):
            no = src[: m.start()].count("\n") + 1
            hits[path].append((no, "code/unused-use", short, m.group(0)))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("root", nargs="?", default=".")
    ap.add_argument("--only", choices=["copy", "comments", "code"])
    ap.add_argument("--max", type=int, default=0, help="show at most N hits per file (0 = all)")
    ap.add_argument("--long-at", type=int, default=12, help="flag comment blocks with at least N lines")
    ap.add_argument("--summary", action="store_true", help="counts per category and top files only")
    a = ap.parse_args()

    hits = defaultdict(list)
    for p in iter_files(os.path.join(a.root, "resources/views"), (".blade.php",)):
        if a.only in (None, "copy"):
            scan_blade(p, hits)
        if a.only in (None, "comments"):
            scan_comments(p, hits, a.long_at)
    for sub in ("app", "routes", "config", "database"):
        for p in iter_files(os.path.join(a.root, sub), (".php",)):
            if a.only in (None, "copy"):
                scan_php_strings(p, hits)
            if a.only in (None, "comments"):
                scan_comments(p, hits, a.long_at)
            if a.only in (None, "code") and p.endswith(".php"):
                scan_unused_imports(p, hits)

    counts = defaultdict(int)
    for items in hits.values():
        for _, cat, _, _ in items:
            counts[cat] += 1

    if a.summary:
        for cat in sorted(counts):
            print(f"{counts[cat]:6d}  {cat}")
        print()
        ranked = sorted(hits.items(), key=lambda kv: -len(kv[1]))[:25]
        for path, items in ranked:
            print(f"{len(items):5d}  {os.path.relpath(path, a.root)}")
        return

    for path in sorted(hits):
        items = sorted(hits[path])
        print(f"\n== {os.path.relpath(path, a.root)} ({len(items)})")
        for no, cat, match, ctx in items[: a.max or None]:
            print(f"  {no:5d}  {cat:18s} {match!r:28s} {ctx[:110]}")
    print("\nTotals: " + ", ".join(f"{c}={n}" for c, n in sorted(counts.items())), file=sys.stderr)


if __name__ == "__main__":
    main()
