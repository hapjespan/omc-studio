#!/usr/bin/env python3
"""Stop hook: a session in phase 6 or 7 does not stop on red checks.

complete-phase.py already refuses to end those phases on red, but that is once
per phase. This hook closes the loop per turn: when the working tree changed
since the last green run, it runs the project's checks (verify.py) and blocks
the stop with the failing output, so the session keeps fixing until green.

Order of decisions, all of which end in a silent exit 0:

  1. No phase 6 or 7 in progress, or not a git repository.
  2. The working tree is identical to the last one that was green.
  3. The checks are green: remember this tree.
  4. MAX_RED blocks in a row on red: let the stop through and say so, because
     a session that cannot fix it must reach a human instead of spinning.

stop_hook_active is deliberately not a reason to yield: the turn after a block
is exactly the one that has to be checked again. MAX_RED is the loop guard.

The state lives in the git directory, so it is never tracked and never makes
the tree it describes dirty. Next to it, verify-on-stop.log gets one line per
run: the state only knows the latest outcome, and the dashboard shows how often
the checks were red and that a loop gave up. Any unexpected failure exits 0 and silent: a
broken guard must never wedge a session.
"""
import json
import os
import pathlib
import subprocess
import sys
from datetime import datetime, timezone

ROOT = pathlib.Path(__file__).resolve().parent.parent.parent
CHECKED_PHASES = {6, 7}
MAX_RED = 5

sys.path.insert(0, str(ROOT / ".workflow"))


def git(*args, env=None):
    result = subprocess.run(["git", *args], cwd=ROOT, capture_output=True, text=True, env=env)
    if result.returncode != 0:
        raise RuntimeError(result.stderr)
    return result.stdout.strip()


def tree_fingerprint(git_dir):
    """The tree hash of the working directory as it stands, untracked files included."""
    env = dict(os.environ, GIT_INDEX_FILE=str(git_dir / "verify-on-stop.index"))
    git("add", "-A", env=env)
    return git("write-tree", env=env)


def record(git_dir, result, failure=""):
    # shortcut: the log is never trimmed (one short line per run), rotate it if a project ever runs for months
    line = {"at": datetime.now(timezone.utc).isoformat(timespec="seconds"), "result": result, "tail": failure}
    with (git_dir / "verify-on-stop.log").open("a") as fh:
        fh.write(json.dumps(line) + "\n")


def main():
    import routing
    import verify

    if routing.phase_in_progress() not in CHECKED_PHASES:
        return
    git_dir = (ROOT / git("rev-parse", "--git-dir")).resolve()
    state_path = git_dir / "verify-on-stop.json"
    try:
        state = json.loads(state_path.read_text())
    except (OSError, ValueError):
        state = {}

    fingerprint = tree_fingerprint(git_dir)
    if state.get("green") == fingerprint:
        return

    failure = verify.failure(ROOT)
    if failure is None:
        state_path.write_text(json.dumps({"green": fingerprint}))
        record(git_dir, "green")
        return

    red = state.get("red", 0) + 1
    if red > MAX_RED:
        state_path.write_text(json.dumps({"green": state.get("green")}))
        record(git_dir, "gave_up", failure)
        json.dump({"systemMessage": f"Checks still red after {MAX_RED} rounds; stopping for a human.\n{failure}"},
                  sys.stdout)
        return
    state_path.write_text(json.dumps({"green": state.get("green"), "red": red}))
    record(git_dir, "red", failure)
    reason = (
        f"The project's checks are red (round {red} of {MAX_RED}), so this turn is not done. "
        f"Find the cause, fix it and stop again; do not report green and do not weaken the check.\n"
        f"{failure}"
    )
    json.dump({"decision": "block", "reason": reason}, sys.stdout)


if __name__ == "__main__":
    try:
        main()
    except Exception:
        pass
    sys.exit(0)
