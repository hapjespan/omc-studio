---
model: claude-sonnet-5-5
effort: medium
---

# Phase 6 — Implementation

## Purpose

Build it, one task at a time, with the specification as the only source of truth.
This phase writes code; it does not decide anything. A decision that has to be made
here is proof that an earlier phase was incomplete.

## Entry criteria

- Phase 5 complete.
- Issues exist and `/speckit-analyze` came back clean.

## Actions

1. Run `/speckit-implement` to orchestrate the task list.
2. Per task, route first: `python3 .workflow/routing.py task <TASK>` names the model
   that builds it, from the complexity flag in `tasks.md` and nothing else.
   - Standard tasks are built in this session, which runs on this phase's model.
   - `[complexity: high]` tasks are dispatched to the `task-builder-high` subagent,
     whose model is pinned in `.claude/agents/task-builder-high.md`. Hand it the one
     task, its acceptance criteria and the relevant spec and plan excerpts.
   - If a task turns out to need escalation mid-phase, do not silently build it on
     the heavier model: edit its flag in `tasks.md` with the reason, commit that,
     and then dispatch. `routing.py record-build` refuses an escalation the flag
     does not back.
3. Per task, in this order, without exception:
   - `mattpocock-skills:tdd` first: a failing test that expresses the acceptance
     criteria, before any implementation code exists.
   - Implement until that test passes and no other test broke. Write it the
     ponytail way: the smallest change that fully solves the task, reusing what
     the codebase already has. While working on a task run only the tests that
     cover the files it touches; the full checks run at every merge and when
     the session stops (the Stop hook does it, and sends you back on red).
   - `mattpocock-skills:code-review` on the change, on both axes: correctness and
     design. Use the qualified name, because the official `code-review` plugin ships
     a skill with the same short name.
   - One atomic commit, referencing the issue, conventional commit format, on a
     feature branch cut from `release`. Draft the commit message through the
     `scribe` subagent, which exists so bulk mechanical text never burns the heavier
     models' limit. It lands on `release` when its review passes, and nothing here
     goes near `main`: that step belongs to phase 8.
   - Record the builder: `python3 .workflow/routing.py record-build <TASK> <model>`,
     with the model that actually built it. Phase 7 reads this ledger to guarantee
     that no task is reviewed by the model that built it, and `complete-phase.py`
     refuses to end this phase while any task lacks its record.
4. Debugging goes through `mattpocock-skills:diagnosing-bugs`. Changing lines until
   the symptom disappears is not debugging and leaves the cause in place.
5. When uncertainty appears, stop. Do not resolve it in code. Go back to phase 2 or
   3, grill it out, update the artifact, and resume. Front-loaded autonomy is the
   whole point: implementation-time uncertainty means the spec was insufficient.

## Parallel building inside a pillar

Parallel is the default wherever the plan allows it. At the start of every
pillar, run `python3 .workflow/parallel-plan.py`: it groups the pillar's open
tasks into waves from the file paths the tasks themselves name. Tasks sharing a
wave are `[P]`-flagged and provably disjoint on files; every wave is a barrier.

For a wave of one, build as always. For a wider wave, this session acts as the
integrator and dispatches one subagent per task, each in its own worktree, each
receiving exactly what a task build always receives: the one task, its
acceptance criteria, the relevant spec excerpts, and the TDD protocol. Then:

1. Merge the returned branches serially, running the tests after each merge, so
   a conflict or a broken interaction surfaces at the merge that caused it.
2. The per-task rules do not dilute: one atomic commit per task, `routing.py
   record-build` with the model that actually built it, review by a model that
   did not build it. The wave changes who waits, never what is recorded.
3. The wave's tasks are checked off only after the merged whole is green.

The plan is advisory about speed and binding about safety: never widen a wave
beyond what parallel-plan.py grouped, and when in doubt about an interaction the
file paths cannot see (a shared migration, a generated file), fall back to
serial and say so.

## Pillars

Phase 6 is executed one pillar at a time. A pillar is one checkpoint-delimited
section of `tasks.md` (Setup, Foundational, a single user story, Polish). The
orchestrator clears this session's context at every pillar boundary, so a
pillar is also the unit of context: work exactly one pillar per context window.

- The active pillar is the first section in `tasks.md` containing an unchecked
  task, unless the orchestrator's resume message names another one explicitly.
- Read only what the pillar needs: the `tasks.md` header blocks, the active
  pillar's section and the dependencies/execution-order section, plus, per
  task, the spec artifacts that task itself cites. Never read the whole task
  list or the whole spec into context.
- A pillar is done only when all five hold:
  1. Every task in the section is checked off, and the checkbox flips are
     committed.
  2. Every task has its atomic commit on the pillar's feature branch.
  3. Every task has its `routing.py record-build` entry.
  4. The full test suite, lint and build are green in this session, and the
     section's Independent Test demonstrably passes.
  5. The working tree is clean and the pillar branch is merged to `release`
     via PR.
- Then end the final turn with exactly this line, on its own, as the last line
  of the reply, so the orchestrator can verify and clear. Ponytail's closing
  lines about what was skipped go above it, never below:
  `PILLAR COMPLETE: <section name> (<Txxx-Tyyy>) merged <sha>, tests green`
- Do not start the next pillar in the same context. After the sentinel, stop
  and wait for the orchestrator.

## Deliverables

- Code and tests, one atomic commit per task.
- Closed issues, each closed by the commit that implemented it.
- Updated PII inventory if implementation touched personal data.

## Exit criteria

- Every task is committed atomically and its issue is closed.
- The test suite is green, and every task added at least one test that would fail
  without its change.
- No task was closed with a review skipped.
- Nothing was decided in this phase that is not reflected back in the spec or an
  ADR.
- `python3 .workflow/routing.py check 6` passes: every task has a recorded builder
  consistent with its complexity flag. `complete-phase.py` refuses the phase
  otherwise.

## Transition

On completion, run: `python3 .workflow/complete-phase.py 6`
