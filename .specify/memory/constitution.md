<!--
Sync Impact Report
Version change: 1.0.0 -> 1.0.1 (PATCH, approved by the product owner, 2026-11-18)
Modified: Principle III (structure changes from every mode, including the Shift
  move in Drawing mode, go through the same service rules; H12 amended, ADR 0036),
  Principle IV and House Convention 1 (messages file is lang/nl.json, ADR 0031),
  Principle V (four deliveries, with DT), Base Slice (left panel per screen, ADR 0019)
Effect on specifications: principle III records the behaviour change already decided in ADR 0036 (the Shift move); the rest is wording; spec.md already follows these decisions
Deferred items: none

Earlier: template (unversioned) -> 1.0.0
Modified principles: none (initial ratification)
Added sections: Core Principles I-V, House Conventions, Compliance Articles,
  Engineering Baseline, Base Slice, Governance
Removed sections: none
Deferred items: none
-->

# OMC Studio Constitution

## Core Principles

### I. Local-Only, No External Dependencies

The application and all its data MUST stay on the machine where it runs (H1, H8).
No CDN, no external fonts, no telemetry, no external database, no outbound calls.
Neither the reference application, the external platform nor the consultancy behind
the method is named in screens, exports, file names, code or documentation.
Rationale: the client's financial data never leaves the machine, and the product
owner can start the application on a clean machine with Docker in at most two
commands, with working sample data (H9).

### II. Exact Money and Percentages

Amounts MUST be whole cents and percentages MUST be whole basis points (10000 = 100%).
A cost item per year is either unassigned (0 lines) or fully assigned (sum exactly
100.00%); an intermediate state MUST NOT be storable (H5). Totals MUST close to the
cent: sum of cost items equals sum assigned plus sum unassigned (H7). The server
enforces these rules; the screen mirrors them and never replaces them.
Rationale: no rounding differences, ever.

### III. Structure Integrity

The areas are fixed in code (seed) and MUST NOT be added, removed or renamed by users
(H2). Inside an area the structure is always domain > activity; an activity hangs
directly under a domain or under a cluster within that domain, and never without a
domain (H3). A cluster is optional, belongs to exactly one domain, contains one or
more activities and cannot be nested (H4). Structure changes from every mode, including moving a
Cluster or Activity to another Domain with Shift in Drawing mode (ADR 0036), go
through the same rules. Rules live in the model and service layer,
not in the screens.
Rationale: every figure on the canvas traces to one unambiguous place.

### IV. Everything Manageable Through the Screens

Everything a user enters MUST be creatable, viewable, editable and deletable through
the screens, per the CRUD matrix of the kickoff, without a database tool, seed or
import (H10). Screen texts are plain Dutch and live only in the i18n messages file
`lang/nl.json` (ADR 0031).
Rationale: the product owner is not a developer.

### V. Delivered Slices Are Finished and Tested

The deliverable type is tracer-bullet: breadth is cut, depth never is. Work ships in
four deliveries (D1: WP0 to WP4; DT, Drawing mode: WP-E0 to WP-E4; D2: WP5 to WP8;
D3: WP9 to WP11), each running through every
production layer: data model, server rules, UI and tests. A delivery MUST NOT contain
stubs, half-implemented rules, or any amount or percentage path without full tests.
Features of a later delivery and everything in the kickoff "Not in version 1" list
are excluded.
Rationale: figures that are correct to the cent cannot be added afterwards.

## House Conventions

Binding for every project. The gate review blocks on them, and a task that violates
one is not done.

1. **OOP, English everywhere in code.** Every variable, reference, function,
   class, path and route name is English and object-oriented in structure.
   Dutch exists in exactly one place: user-facing labels in the i18n messages
   file (`lang/nl.json` in this project, ADR 0031). A Dutch identifier in
   code is a blocking finding.
2. **Every database table carries an index.** A table ships with at least the
   index its access pattern needs, chosen and named in the migration that
   creates the table, never deferred to "when it gets slow". The data model
   document records which index serves which query.
3. **No code comments.** Names carry the meaning: a function that needs a
   comment needs a better name or a smaller body. The only exceptions are
   annotations the toolchain itself requires (a directive, a pragma) and legal
   headers a dependency licence forces.
4. **Nothing traces back to the tool that built it.** No reference to the tool
   that built the code appears in code, comments, commit messages or pull
   requests; the project settings strip attribution trailers, and the review
   blocks on any that slip through. The code must read as deliberately
   engineered.
5. **One file per component, compact files.** Each model, service, controller,
   repository and UI component lives in its own file that does that one thing.
   A file approaching 300 lines is a split waiting to happen; a file over 400
   lines is a blocking finding unless the file is generated.

## Compliance Articles

Always active. The risk class of this project is `standard`: each article demands
full artifacts, namely a PII inventory, an incident plan, WCAG criteria per
user-facing story and ten OWASP verdicts in phase 7. The articles themselves:

**AVG/GDPR.** Privacy by design and by default. Data minimisation: a field that is
not needed is not collected. Every personal data element is recorded in a PII
inventory with its lawful basis, retention period and processors. A change that adds
personal data updates that inventory in the same commit.

**NIS2.** Risk-based measures proportional to the system. Logging and monitoring
adequate to detect and reconstruct an incident, without logging the personal data
just minimised. Incident readiness: who is notified, in what window, through which
channel, written down before it is needed.

**WCAG 2.2 AA.** Every user-facing story carries accessibility acceptance criteria:
keyboard operability, focus visibility, contrast, target size, form errors that name
the field and the fix. A story without them is not ready for phase 5. Projects with
no user interface record that fact rather than silently skipping the article.

**OWASP.** Requirements are written ASVS-aligned. The Top 10 is a checklist in both
review and validation, not a slogan: authentication, access control, injection,
insecure design, misconfiguration, vulnerable dependencies, integrity, logging,
and server-side request forgery each get an explicit verdict in phase 7.

## Engineering Baseline and Base Slice

- Test-driven development per task: a failing test comes first, then the code.
- Two-axis review of every change: correctness and design.
- One atomic commit per task, with a conventional commit message.
- The specification starts from the base slice: data model (each table with its
  index), CRUD for the core entities, authentication with optional TOTP 2FA, and the
  UI shell (top menu, left panel per screen, detail window), built once as a story of its own.

## Governance

This constitution supersedes other practices. An amendment is a documented change to
this file that states the reason and its effect on existing specifications, approved
by the product owner, with the version bumped by semantic versioning: MAJOR for a
removed or redefined principle, MINOR for an added principle or materially expanded
guidance, PATCH for clarifications. Every gate review and pull request MUST verify
compliance with the principles, house conventions and compliance articles above, and
any deviation MUST be justified in writing in an ADR under `docs/adr/`. Lowering the
risk class is a decision that is written down with its reason.

**Version**: 1.0.1 | **Ratified**: 2026-11-18 | **Last Amended**: 2026-11-18
