# Project profile

Filled in during phase 0, before anything else. The gate mode is synced from here
into `.workflow/state.json`, which is what the Stop hook actually reads. Change the
mode later by editing both, or by re-running the sync step in `/start-project`.

```
gate_mode: standard
deliverable_type: tracer-bullet
project_type: greenfield
risk_class: standard
deploy_target: none
tracker: github
```

## Fields

**`gate_mode`** — `autonomous`, `standard` or `strict`.
Where a human reviews before the next phase starts. `autonomous` gates after phases
4 and 7, `standard` after 2, 4 and 7, `strict` after every phase. Unknown values are
treated as `strict`, which stops more often rather than less.

**`deliverable_type`** — `proof-of-value` or `tracer-bullet`.
`proof-of-value` demonstrates that the idea is worth building and may cut depth to
get there. `tracer-bullet` runs one thin slice end to end through every layer that
production will use, and cuts breadth instead. This decides what "done" means in
phases 5 and 7, so it is not a label: write down what it excludes.

**`project_type`** — `greenfield` or `brownfield`.
`brownfield` enters phase 1 through `/speckit-converge` to establish what the code
already does before anything is specified about what it should do.

**`risk_class`** — `minimal`, `standard` or `regulated`.
How deep the four compliance articles are worked in phases 2, 3 and 7. The articles
themselves are never dropped; what changes is the evidence they demand.

- `minimal`: nothing user-facing beyond yourself, no personal data, no production
  exposure. Each article is answered in one recorded line, including why it does
  not apply. A weekend proof-of-value lives here.
- `standard`: real users or real data, self-hosted. Full PII inventory, full
  logging and incident plan, WCAG criteria per user-facing story, ten OWASP
  verdicts in phase 7.
- `regulated`: personal data at scale, a client, or a legal obligation. Everything
  `standard` demands, plus a named owner per article and evidence a third party
  could audit.

An unknown value is treated as `regulated`, the same way an unknown `gate_mode` is
treated as `strict`. Raising the class mid-project is normal; lowering it is a
decision that gets written down with its reason.

**`deploy_target`** — `coolify`, `none` or a named platform.
Where phase 8 delivers to. `coolify` is the platform running on the development
host, reachable on the host itself; `none` means the project is explicitly not
deployed, which phase 8 records rather than silently skips. A target other than
these two means writing the deploy step for it in phase 8 before you get there.

**`tracker`** — `github`.
Issues are created with `/speckit-taskstoissues`. Another tracker means a different
issue step, and `/to-tickets` stops being disabled.

**`model_phase_<N>`** — optional, absent by default.
Model routing is configuration: each phase file in `docs/process/` pins its model
in frontmatter, per the routing policy in `docs/process/workflow.md`. A project
that must deviate adds a line to the block above, for example
`model_phase_6: claude-opus-5-5`, and records the reason in prose here. An
override changes one phase's model and nothing else. The hard rules survive any
override, because they are enforced elsewhere: gate reviews run on the
`gate-review` agent's model in every mode, escalation happens only through the
`[complexity: high]` flag in `tasks.md`, and `routing.py` refuses a phase 7
reviewer equal to a task's recorded builder. `python3 .workflow/routing.py doctor`
shows what actually resolves.

**Decisions (2026-11-18)**

`deliverable_type: tracer-bullet`. The kickoff requires figures to be correct to the cent (H5, H7) and nothing is delivered that is not finished and tested, so depth is never cut. Breadth is cut instead: the work ships in four deliveries (kickoff chapter 14, addendum A1 chapter 9, see "Deliveries" below). Delivery 1 is WP0 to WP4 (Invoeren and Bekijken on automatic layout), delivery DT is Tekenen (WP-E0 to WP-E4), delivery 2 is WP5 to WP8 (financial layer visible), delivery 3 is WP9 to WP11 (export, import, snapshots, sample data, final review). Each delivery runs through every production layer (data model, server rules, UI, tests). It excludes: features of a later delivery, any stub or half-implemented rule, any amount or percentage path without full tests, and everything in the kickoff "Not in version 1" list.

`risk_class: standard`. Real financial data of a client and multiple users with login, so the full PII inventory, logging and incident plan, WCAG criteria per user-facing story and ten OWASP verdicts in phase 7 apply.

## Scope note

OMC Studio is a locally running web application with which the product owner builds an Operating Model Canvas and assigns cost items fully (100%) to domain, cluster and/or activity, with a financial overlay. It is explicitly not a hosted or external service, not a free drawing tool (Tekenen changes the layout of existing Elements only, never structure, and draws no free shapes, texts or images) and not a link to source systems.

**Visual design (2026-11-18, phase 2)**: answer A to `[GATE-D1]`: the product owner supplied a design, `specs/design/omc-studio-design.html` (five boards, screenshots next to it). The earlier "sober own style" lapses. The design contract derived from it is `specs/001-omc-studio-v1/design-contract.md`; its navigation is mapped onto the product in ADR 0019. The design is a visual reference only: its React code is not taken over, and the file never enters the build. Differences between the design, kickoff v1.2, addendum A1 and the spec are in `specs/001-omc-studio-v1/source-conflicts.md`; the open ones wait for the phase 2 gate.

**Deliveries (2026-11-18, product owner)**: delivery 1 is Invoeren and Bekijken on automatic layout; the technical proof WP-E0 for Tekenen runs right after the project setup, with go or no-go; Tekenen is its own delivery after delivery 1 (DT); then delivery 2 (financial layer) and delivery 3 (export, import, Snapshots, sample data, final review). A no-go stops DT only.
