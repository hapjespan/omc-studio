#!/usr/bin/env python3
"""The project's own checks, and whether they are green.

Shared by complete-phase.py (phases 6 and 7 end only on green) and the
verify-on-stop hook (a session does not stop on red). "Tests green" in a
session's reply is a claim; the exit status of the suite is the evidence.
"""
import json
import subprocess

TAIL = 30


def commands(root):
    """The checks this project defines for itself, in the order they run."""
    def scripts(manifest):
        try:
            return json.loads((root / manifest).read_text()).get("scripts", {})
        except (OSError, ValueError):
            return {}

    found = []
    node = scripts("package.json")
    if "verify" in node:
        found.append(["npm", "run", "verify"])
    if "test" in node:
        found.append(["npm", "test"])
    php = scripts("composer.json")
    if "verify" in php:
        found.append(["composer", "verify"])
    if "test" in php:
        found.append(["composer", "test"])
    return found


def failure(root):
    """Run the checks; return a message for the first red one, None when green."""
    for command in commands(root):
        try:
            result = subprocess.run(command, cwd=root, capture_output=True, text=True)
        except OSError as exc:
            return f"`{' '.join(command)}` could not run: {exc}"
        if result.returncode != 0:
            tail = "\n".join((result.stdout + result.stderr).strip().splitlines()[-TAIL:])
            return f"`{' '.join(command)}` exited {result.returncode}:\n{tail}"
    return None
