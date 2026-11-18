#!/usr/bin/env python3
"""PostToolUse hook: check the one file that was just edited, in seconds.

The full checks run when the session stops (verify-on-stop.py); this is the
fast half of the loop, so a lint or syntax error is fixed while the edit is
still in context instead of surfacing a pillar later. Only tools the project
already installed are used, and only on the edited file: whole-project checks
such as tsc stay in `verify`, they are too slow to run per edit.

Exit 2 feeds the output back to the session. Everything unexpected exits 0.
"""
import json
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent.parent
TAIL = 20
ESLINT = ROOT / "node_modules" / ".bin" / "eslint"
PINT = ROOT / "vendor" / "bin" / "pint"
SCRIPT = {".js", ".jsx", ".mjs", ".cjs", ".ts", ".tsx"}


def checks(path):
    if path.suffix in SCRIPT and ESLINT.is_file():
        yield [str(ESLINT), "--no-warn-ignored", str(path)]
    if path.suffix == ".php":
        yield ["php", "-l", str(path)]
        if PINT.is_file():
            yield [str(PINT), "--test", str(path)]


def main():
    payload = json.load(sys.stdin)
    path = pathlib.Path((payload.get("tool_input") or {}).get("file_path") or "").resolve()
    if not path.is_file() or ROOT not in path.parents:
        return 0
    for command in checks(path):
        try:
            result = subprocess.run(command, cwd=ROOT, capture_output=True, text=True, timeout=45)
        except (OSError, subprocess.TimeoutExpired):
            continue
        if result.returncode != 0:
            tail = "\n".join((result.stdout + result.stderr).strip().splitlines()[-TAIL:])
            print(f"{path.relative_to(ROOT)} fails `{pathlib.Path(command[0]).name}`; fix it now:\n{tail}",
                  file=sys.stderr)
            return 2
    return 0


if __name__ == "__main__":
    try:
        code = main()
    except Exception:
        code = 0
    sys.exit(code)
