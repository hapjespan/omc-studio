#!/usr/bin/env python3
"""Compute which tasks of a pillar may be built in parallel, from the files they name.

Spec Kit already marks parallelizable tasks `[P]` and every task names its exact
file paths, but a session building a pillar serially leaves that on the table:
six independent `[P]` tasks in a row is six subagent dispatches' worth of
wall-clock spent waiting. Whether tasks can safely run side by side is a
mechanical question (are their file footprints disjoint?), so a script answers
it and the session follows the plan instead of judging.

    python3 .workflow/parallel-plan.py                # plan the active pillar
    python3 .workflow/parallel-plan.py --section "User Story 5"
    python3 .workflow/parallel-plan.py --json

The plan is a list of waves. Tasks in one wave are pairwise disjoint on files
and all `[P]`; every wave is a barrier (merge and verify before the next
starts). A task without the `[P]` flag, or overlapping an earlier task in the
wave, closes the wave: order within the section is preserved, so an author's
implicit befores stay befores. A task naming no files at all is treated as
overlapping everything, because an unknown footprint is not a licence to guess.

Wave size is capped (default 4): the integrator has to review and merge every
branch that comes back, and a wider fan-out than that just moves the queue.
"""

import argparse
import json
import pathlib
import re
import sys

# Spec Kit numbers tasks T105; hand-written plans use lettered groups (A3, B12).
# Both are task ids, and a planner that recognises only one silently reports
# "every task is done" on the other, which reads as success.
TASK_LINE = re.compile(r"^- \[(?P<done>[ xX])\] (?P<id>[A-Z]{1,3}\d+)\s*(?P<flags>(?:\[[^\]]+\]\s*)*)")
SECTION = re.compile(r"^## .*$", re.MULTILINE)
# Paths appear in backticks or bare. Backticked anything-with-a-slash is a path
# (the author quoted it as one); bare tokens only count under the well-known
# prefixes, because prose contains slashes too. The first version knew only the
# web prefixes and read a Swift project's [P] tasks as having no files at all,
# which silently planned everything serial: the failure mode of this tool is
# never a crash, always a quietly narrower plan.
BACKTICKED_PATH = re.compile(r"`([\w@.\[\]-]+(?:/[\w@.\[\]-]+)+/?)`")
BARE_PATH = re.compile(r"\b((?:src|app|tests|engine|web|docs|specs|components|lib|pages|clicky\w*|scripts)/[\w@/.\[\]-]+)")


def extract_paths(line):
    return set(BACKTICKED_PATH.findall(line)) | set(BARE_PATH.findall(line))
MAX_WAVE = 4


def find_tasks_file(root):
    matches = sorted(root.glob("specs/*/tasks.md"))
    if not matches:
        sys.exit("no specs/*/tasks.md found; run from the project root")
    return matches[0]


def parse_sections(text):
    """[(section title, [task dict, ...]), ...] in file order."""
    sections, current_title, current_tasks, current_task = [], None, [], None
    for line in text.splitlines():
        if line.startswith("## "):
            if current_title is not None:
                sections.append((current_title, current_tasks))
            current_title, current_tasks, current_task = line[3:].strip(), [], None
            continue
        match = TASK_LINE.match(line)
        if match:
            current_task = {
                "id": match.group("id"),
                "done": match.group("done") != " ",
                "parallel": "[P]" in match.group("flags"),
                "files": extract_paths(line),
            }
            current_tasks.append(current_task)
        elif current_task is not None and line[:1] in (" ", "\t"):
            # Anything indented under the open task belongs to it: Spec Kit
            # wraps long lines with deep indentation, hand-written plans hang
            # the metadata (Files:, Accept:) as two-space sub-bullets. Only a
            # column-zero line closes the task.
            current_task["files"] |= extract_paths(line)
        else:
            current_task = None
    if current_title is not None:
        sections.append((current_title, current_tasks))
    return sections


def pick_section(sections, wanted):
    if wanted:
        for title, tasks in sections:
            if wanted.lower() in title.lower():
                return title, tasks
        sys.exit(f"no section matches {wanted!r}")
    for title, tasks in sections:
        if any(not task["done"] for task in tasks):
            return title, tasks
    sys.exit("every task is done; nothing to plan")


def plan_waves(tasks, max_wave=MAX_WAVE):
    """Greedy, order-preserving: a task joins the open wave only when that is
    provably safe, otherwise it closes the wave and starts the next."""
    waves, wave, wave_files = [], [], set()
    for task in tasks:
        if task["done"]:
            continue
        joinable = (task["parallel"] and task["files"]
                    and not (task["files"] & wave_files)
                    and len(wave) < max_wave
                    and all(t["parallel"] for t in wave))
        if wave and not joinable:
            waves.append(wave)
            wave, wave_files = [], set()
        wave.append(task)
        wave_files |= task["files"] or {"<unknown>"}
        if not task["parallel"] or not task["files"]:
            waves.append(wave)
            wave, wave_files = [], set()
    if wave:
        waves.append(wave)
    return waves


def main(argv):
    parser = argparse.ArgumentParser()
    parser.add_argument("--section", help="section title substring; default: first with open tasks")
    parser.add_argument("--json", action="store_true")
    parser.add_argument("--max-wave", type=int, default=MAX_WAVE)
    args = parser.parse_args(argv)

    root = pathlib.Path.cwd()
    tasks_file = find_tasks_file(root)
    title, tasks = pick_section(parse_sections(tasks_file.read_text()), args.section)
    waves = plan_waves(tasks, args.max_wave)

    if args.json:
        print(json.dumps({
            "section": title,
            "waves": [[{"id": t["id"], "parallel": t["parallel"],
                        "files": sorted(t["files"])} for t in wave] for wave in waves],
        }, indent=2))
        return 0

    print(f"pillar: {title}")
    parallel_count = sum(1 for wave in waves if len(wave) > 1 for _ in wave)
    print(f"{sum(len(w) for w in waves)} open tasks in {len(waves)} waves "
          f"({parallel_count} run in parallel)")
    for number, wave in enumerate(waves, 1):
        kind = "parallel" if len(wave) > 1 else "serial"
        ids = ", ".join(task["id"] for task in wave)
        print(f"  wave {number} ({kind}): {ids}")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
