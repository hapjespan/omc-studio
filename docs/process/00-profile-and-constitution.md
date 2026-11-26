---
model: claude-sonnet-5-5
effort: low
---

# Phase 0 — Profile and constitution

## Purpose

Decide how this project will be run and what it will never trade away, before any
thinking about the problem starts. The profile fixes the operating mode, the
constitution fixes the non-negotiables. Both are cheap now and expensive later:
every phase after this one reads them.

## Entry criteria

- `/start-project` has completed and its dry-run of the Stop hook was silent.
- `specs/PROFILE.md` and `.specify/` exist.

## Actions

1. Fill every field in `specs/PROFILE.md` with the user. Ask, never assume, and
   write the scope note paragraph including what the project is explicitly not for.
   If `specs/kickoff.md` exists it is the base the user handed and confirmed at
   kickoff: propose the answers and the scope note from it and have the user
   confirm the set, instead of asking each question blind.
2. Sync the chosen mode into the state machine, which is what the hook reads:
   `python3 - <<'PY'` … as described in `/start-project` step 4, or edit
   `.workflow/state.json` by hand and keep the two in step.
   Model routing needs no decision here: the defaults ship in the phase files'
   frontmatter, per the routing policy in `docs/process/workflow.md`. A project
   that must deviate records a `model_phase_<N>:` override in `specs/PROFILE.md`
   with its reason, which is rare and never touches the hard rules.
3. Run `/speckit-constitution` and give it:
   - the five pillars of this project, derived from the scope note;
   - the engineering baseline: test-driven development per task, two-axis review
     (correctness and design), one atomic commit per task;
   - the four compliance articles from `docs/process/workflow.md`, quoted as
     articles rather than summarised.

## Deliverables

- `specs/PROFILE.md`, fully filled, including the scope note.
- `.specify/memory/constitution.md`.
- `.workflow/state.json` with `gate_mode` equal to the profile.

## Exit criteria

- No field in `specs/PROFILE.md` is still at its template default by accident: each
  one was discussed and confirmed.
- The scope note names at least one thing the project will not do.
- The constitution names all four compliance articles and the engineering baseline.
- `gate_mode` in `.workflow/state.json` matches `specs/PROFILE.md`.

## Transition

On completion, run: `python3 .workflow/complete-phase.py 0`

## risk_class

`specs/PROFILE.md` carries a `risk_class` field: `minimal`, `standard` or
`regulated`. It scales how much compliance evidence a story owes, not whether
the articles apply: a `minimal` hobby tool records one line per article
(applies / does not apply and why), `standard` keeps the per-story WCAG and
security criteria plus a PII inventory when personal data appears, `regulated`
adds the full evidence trail per requirement. Scaling the evidence rather than
the article keeps the compliance sections honest on small projects instead of
skipped.

## House conventions (binding for every project)

These are constitution articles, not preferences: the gate review blocks on
them, and a task that violates one is not done.

1. **OOP, English everywhere in code.** Every variable, reference, function,
   class, path and route name is English and object-oriented in structure.
   Dutch exists in exactly one place: user-facing labels in the i18n messages
   file (`messages/nl.ts` or the stack's equivalent). A Dutch identifier in
   code is a blocking finding.
2. **Every database table carries an index.** A table ships with at least the
   index its access pattern needs, chosen and named in the migration that
   creates the table, never deferred to "when it gets slow". The data model
   document records which index serves which query.
3. **No code comments.** Names carry the meaning: a function that needs a
   comment needs a better name or a smaller body. The only exceptions are
   annotations the toolchain itself requires (a directive, a pragma) and legal
   headers a dependency licence forces.
4. **Nothing traces back to the tool that built it.** No AI or assistant
   references in code, comments, commit messages, or pull requests; the
   project settings strip attribution trailers, and the review blocks on any
   that slip through. The code must read as deliberately engineered, not as
   generated.
   Exceptions to article 4, recorded by decision of the product owner
   (2026-11-21, option A):
   - The tracked workflow tooling is development tooling and exempt from this
     article: `.claude/` (agents, commands, settings), `CLAUDE.md`, and the
     development-container wiring (the agent install line in
     `.devcontainer/Dockerfile`, the `DEV_CLAUDE_HOME` and `DEV_CLAUDE_JSON`
     variables in `.devcontainer/README.md` and `docker-compose.yml`). By
     decision of 2026-11-22 (review P5, option A) the exemption also names
     `.specify/`, `.workflow/`, `scripts/vendored-urls.txt` and
     `proof/wp-e0/asset-report.mjs`: development tooling and strings from
     third-party libraries. The exemption covers only these tooling files. It never covers product code,
     tests, documents of the product, or commit messages going forward.
   - Two commits, `d66a78c` and `d0fc304`, carry an attribution trailer and a
     session link. They are already on `release` and on the remote, and history
     is not rewritten. Nothing else is excused.
   - From now on the tracked hook `.githooks/commit-msg` refuses a commit
     message that has a line starting with an attribution trailer (any case)
     or contains a session link. Only those two shapes are refused; the same
     words inside a sentence are accepted. Activate it once per clone with
     `make hooks` (`git config core.hooksPath .githooks`).
5. **One file per component, compact files.** Each model, service, controller,
   repository and UI component lives in its own file that does that one thing.
   A file approaching 300 lines is a split waiting to happen; a file over 400
   lines is a blocking finding unless the file is generated.

## The base slice

Every project's spec starts from the same foundation before any user story is
specified, so phase 2 writes these as the first stories of every product:

- **Base data model**: the core entities, each table with its index (article 2).
- **CRUD**: create, read, update, delete flows for the core entities.
- **Authentication with optional 2FA**: sessions plus a TOTP second factor the
  owner can enable per account; projects with a recorded no-auth exemption
  (SEC-001-style) document why instead.
- **UI shell**: top menu, sidebar, and a detail window, as the navigation frame
  every story renders into. The shell is a story of its own, built once.

