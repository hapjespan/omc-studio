#!/usr/bin/env python3
"""Refuse the house-convention violations a script can prove.

The constitution's articles are review criteria, but two of them are mechanical
enough to gate on before a push ever happens: nothing in the repository may
trace back to the tool that built it, and no hand-written file grows past the
split threshold. The rest (English identifiers, comment discipline, an index
per table) needs judgement and stays with the gate review.

    python3 .workflow/conventions-check.py [paths...]   # default: src app tests

Exit 1 on any violation, so the pre-push hook can chain it before the verify
script. Generated files are exempted by path segment, never by content.
"""

import pathlib
import re
import sys

TRACEABLE = re.compile(
    r"claude|anthropic|copilot|chatgpt|written by ai|generated with|co-authored-by",
    re.IGNORECASE)
MAX_LINES = 400
CODE_SUFFIXES = {".ts", ".tsx", ".js", ".jsx", ".py", ".php", ".sql", ".css", ".vue"}
GENERATED_SEGMENTS = {"generated", "node_modules", "vendor", "migrations-meta", "dist", ".next"}


def is_generated(path):
    return any(segment in GENERATED_SEGMENTS for segment in path.parts)


def main(argv):
    roots = [pathlib.Path(a) for a in argv] or [pathlib.Path(d) for d in ("src", "app", "tests")]
    violations = []
    for root in roots:
        if not root.exists():
            continue
        for path in sorted(root.rglob("*")):
            if path.suffix not in CODE_SUFFIXES or not path.is_file() or is_generated(path):
                continue
            text = path.read_text(errors="replace")
            for number, line in enumerate(text.splitlines(), 1):
                if TRACEABLE.search(line):
                    violations.append(f"{path}:{number}: traceable tool reference: {line.strip()[:80]}")
            count = text.count("\n") + 1
            if count > MAX_LINES:
                violations.append(f"{path}: {count} lines (limit {MAX_LINES}); split it per component")
    for violation in violations:
        print(violation)
    if violations:
        print(f"\n{len(violations)} convention violation(s); the constitution's "
              "House conventions section says why these block")
        return 1
    print("conventions: clean")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
