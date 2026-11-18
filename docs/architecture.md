# Architecture: modules and seams

Date: 2026-11-18, phase 4. Readable without the code open. Vocabulary from `codebase-design`: a **module** is a unit with an interface; it is **deep** when a small interface hides a lot; a **seam** is a place where behaviour can be observed or replaced without editing the code around it; an **adapter** sits on a seam and translates to something outside our control. Decisions behind this map: ADR 0012 to 0035; the reasoning per choice: `specs/001-omc-studio-v1/research.md`.

## The shape in one picture

```
Browser                                                     Server (one container, ADR 0035)
┌──────────────────────────────────────────────┐            ┌───────────────────────────────────────────────┐
│ Vue pages (Inertia)                          │  HTTP      │ Routes + middleware                            │
│  Shell, ListView, Invoeren tree, Toewijzen   │◄──────────►│  auth, must-change-password, can:administer,   │
│  Bekijken renderer ◄── layout engine ◄─ TextMeasurer      │  security headers (CSP), security log           │
│  Tekenen page ─► drawing adapter ─► OpenPencil│  (DT)     │ Controllers (thin) ─► Services per module      │
│  messages: lang/nl.json via t()              │            │  Structure, Canvas, Finance, Allocation,       │
└──────────────────────────────────────────────┘            │  Layout, Snapshot, Exchange, Accounts, Undo    │
                                                            │ Calculation (pure, no I/O)                     │
                                                            │ Eloquent models + Audited observer             │
                                                            │ SQLite file on a volume (PostgreSQL in CI)     │
                                                            └───────────────────────────────────────────────┘
```

## Server modules

Laravel's own directories hold framework types (`app/Models`, `app/Http/Controllers`, `app/Http/Requests`, `app/Http/Middleware`, `app/Console/Commands`). Domain logic lives in services, one class per file, grouped per module under `app/Services/<Module>/`. Controllers validate input through form requests, call one service method, and render or redirect; they hold no rule.

| Module | Interface (what callers use) | Hides | Delivery |
|---|---|---|---|
| Accounts | `SignInService`, `PasswordService`, `SecondFactorService`, `UserAdministration` | throttling per e-mail hash, one-time password expiry, TOTP replay window, recovery-code hashing, ending sessions, last-Administrator lock | D1 |
| Canvas | `CanvasLifecycle` (create from Template, copy, save as Template, archive, delete) | which records go with a copy (FR-028), creating the Unknown allocation Domain on every route (FR-043a), deletion counts | D1 |
| Structure | `ElementTree` (create, update, move, reorder, archive, restore, delete), `QuickEntryParser` | every rule of FR-033 to FR-043a, name uniqueness under lock, structure version (DT), Layout removal on move, archive and delete (DT) | D1 |
| Statements | `DirectionStatements` | one mission, one vision, ordered goals | D1 |
| Audit | `Audited` trait and observer, `AuditContext`, `AuditTrail` for explicit entries, `SecurityLog` | old and new values, secret fields excluded, pseudonymisation at user deletion, removal with Canvas or Organisation | D1 |
| Undo | `UndoStore` (remember, restore) | `undo_records` rows with expiry, re-validation at restore, clean-up during requests | D1 |
| Finance | `CostRecords`, `YearlyFigures`, `FiscalYears`, `OrganisationLists` (Owners, Applications with "Vervangen door") | Finalised-year freeze across Canvases (FR-051), amount parsing (FR-014) | D2 |
| Allocation | `AllocationSets` (replace, clear), `Redistribution`, `CarryOver`, `Finalisation` | the 100% rule, Target rule, Finalise guard under lock, one-step writes | D2 |
| Calculation | `FigureCalculator::calculate()` | largest remainder, roll up, Unknown allocation, breakdowns, closing check | D2 |
| Notices | `SystemNotices::record()` | one notice per fact, counts, lockout window (SD-41) | D2 |
| Layout | `LayoutStore` (save whole Layout, reset), `LayoutValidator`, `AutomaticWidths` | version checks, containment against stored or automatic parent widths, style and contrast rules, merged audit | DT |
| Snapshot | `Snapshots` (take, read, rename, archive, delete) | content assembly, checksum, damage notice | D3 |
| Exchange | `CanvasExport`, `CanvasImport`, `FullExport`, `FullImport`, `CostRecordCsv`, `AllocationCsv`, `StructureCsv` | formats of `contracts/exchange-formats.md`; every import is `preview()` then `commit()` over the same validator and the same services as the screens | D2 (cost CSV import), D3 |
| Operations | `app:install`, `app:reset-admin-password`, `app:backup`, `app:restore` | idempotent first start, last-resort reset, consistent copy with `VACUUM INTO` | D1, D3 |

Cross-cutting rules every module follows:

- **Serialised writes** (ADR 0032): a guarded write runs in one transaction and locks rows in the order Organisation, Canvas, User. Lock set per path: Organisation for Yearly figures, Cost records, lists and Fiscal years; Canvas for sets, structure, Statements, Layout and Redistribution; Organisation then Canvas for Finalise, Reopen, Carry over and imports; every Administrator row by id for role change and deletion.
- **One validator per field**: form requests, the import validator and the layout validator use the same rule objects, so a rule cannot hold in the screen and fail in an import (FR-085).
- **Writable guard in every service** (R-27): each write method first asserts its aggregate is writable (not archived; a Template takes only structure and Statements; "Leeg canvas" takes nothing). Nested routes use scoped bindings.
- **No silent writes** (ADR 0034): on audited tables, services change and delete model by model, children first; no mass update or delete, enforced by an architecture test.

## Browser modules

| Module | Directory | Interface | Hides | Delivery |
|---|---|---|---|---|
| Shell | `resources/js/shell/` | `AppShell` layout with slots for left panel, main, detail panel | tabs per role, year selector, notice warning, session timer, skip link, landmarks | D1 |
| List | `resources/js/components/list/` | `ListView` with columns, filters, form slot, bulk actions | search, sort, chips, confirm with counts, undo countdown with pause, per-row bulk results (US4) | D1 |
| Messages and formats | `resources/js/i18n/`, `resources/js/format/` | `t(key, params)`, `formatAmount`, `formatWholeEuros`, `formatShare` | the shared `lang/nl.json`, Dutch number and date formats, rounding half up per amount (GATE-C52) | D1 |
| Layout engine | `resources/js/layout/` | `computeCanvasLayout(input, measurer)` | Automatic layout, wrapping, growth, Warnings, Manual layout (DT) | D1, DT |
| Canvas view | `resources/js/canvas/` | `CanvasView` drawing a `DrawnCanvas`, overlays as slots per card | absolute boxes, keyboard tree navigation (US16), zoom, "Rand A3" and hatch, hover breakdowns | D1, D2 |
| Colours and contrast | `resources/js/colour/` | `effectiveFill`, `contrastRatio`, `autoTextColour` over `resources/data/default-colours.json` | compositing with opacity; mirror of the PHP rule (S3) | D1, DT |
| Allocation helpers | `resources/js/allocation/` | `remaining`, `restToLine`, `splitEvenly`, `splitOverDomainActivities`, `lineAmounts` | basis-point arithmetic and the largest-remainder mirror (R-4) | D2 |
| Drawing adapter | `resources/js/edit-engine/` | `DrawingEngine` | everything about OpenPencil | DT |
| Routes | `resources/js/routes.ts` | one function per route | paths and the `year` parameter | D1 |

## Seams

The seams below are the ones phase 6 tests use and the ones a later change uses. A test that needs a different seam is a sign the plan was wrong; it goes back to this document first.

| Id | Seam | Tests through it | Changes through it later |
|---|---|---|---|
| S1 | HTTP routes (Laravel feature tests) | every refusal of the spec (FR-094), Reader 403 on every write route by router enumeration, audit entries, undo, imports writing nothing on error (FR-097) | new screens use the same routes |
| S2 | `FigureCalculator::calculate()` | FR-093 unit tests, FR-095 property test, injected discrepancy | new breakdowns |
| S3 | Functions that exist in PHP and TypeScript, each pair over one fixture: `LargestRemainder` (`largest-remainder.json`), `AutomaticWidths` (`automatic-widths.json`, DT), contrast with opacity (`contrast.json`, DT); Default colours in one JSON file read by both | both languages against one fixture | none expected |
| S4 | Services per module | unit tests where a rule is intricate: `QuickEntryParser`, CSV readers, `LayoutValidator`, `Finalisation` guards | importers and screens share them |
| S5 | `TextMeasurer` port of the layout engine | Vitest with a fixed-width fake; browser tests with the real measurer | another font, another measuring method |
| S6 | `DrawingEngine` adapter | round trip, restrictions, undo, picture comparison, upgrade script | a library upgrade or replacement touches this directory only (H14) |
| S7 | Clock (`now()` with Carbon's test time) | one-time password expiry, TOTP steps, lockouts, 60-second merge, lockout window | none |
| S8 | Browser (Playwright) with three guards: request outside the own origin fails the test, `securitypolicyviolation` fails the test, axe violations fail the test | end-to-end (FR-096), keyboard scenarios, SC-021, SC-022, SC-027, H11 picture comparison | none |
| S9 | Exchange `preview()` and `commit()` | dry-run reports per line, all-or-nothing | new file formats |
| S10 | `AuditContext` | action names per write path | new actions |
| S11 | Two processes on a file SQLite database in WAL mode, and two connections on PostgreSQL in CI | each guard of ADR 0032 ends with one success and one refusal (FR-010, set against Finalise, Yearly figures against Finalise, Layout version) | none |

## Where the system is expected to change

- **Drawing library**: young, breaking releases (addendum chapter 12). Only `resources/js/edit-engine/` changes; versions pinned exactly; the upgrade script runs S6 against the new version.
- **Database engine**: portable migrations and queries, proven by the PostgreSQL run in CI (ADR 0022).
- **Screen texts and labels**: `lang/nl.json` only.
- **Layout and export formats**: `format_version` with small upgrade functions on read.
- **The deeper layer per location** (GATE-U3): explicitly not prepared; no abstraction exists for it.

## What the deliverable type cuts

`tracer-bullet`: every delivery runs through data model, server rules, UI and tests; breadth is cut, depth never. Concretely: no table or column exists before the delivery that uses it (layouts and versions arrive in DT, finance tables in D2, snapshots in D3); D1's layout engine has no Manual layout input; no adapter code exists before WP-E0 ends in go; no public API, queue, scheduler, cache server or plugin mechanism exists at all.
