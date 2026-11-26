# Implementation Plan: OMC Studio version 1

**Branch**: `docs/phase-4-architecture` | **Date**: 2026-11-18 | **Spec**: [spec.md](spec.md)

**Input**: `spec.md`, `source-conflicts.md`, `design-contract.md`, `pii-inventory.md`, `docs/constraints.md` (phase 3), ADR 0001 to 0027. Revised the same day with the product owner's answers to Q-1 to Q-13 and QS-1 to QS-7: `[GATE-U1]`, ADR 0011 and `[SD-25]` to `[SD-41]` are confirmed, and the Shift move in Tekenen (`[GATE-C53]`, ADR 0036) was added with its scope.

## Summary

A locally running Laravel 13 application with an Inertia and Vue 3 front end, one SQLite file, one container. The server owns every rule and every figure: a pure PHP calculation module produces the figures and the closing check (ADR 0028), and services per module enforce the structure, allocation and Finalise rules inside serialised transactions (ADR 0032). The browser owns the picture: one pure TypeScript layout engine computes the Automatic layout and breaks text into lines, and both the HTML view and, in DT, the drawing adapter draw exactly those lines (ADR 0029), which is how H11 becomes reachable. In Tekenen one Cluster, Activity or Item can be moved to another parent of the same kind of Area with Shift, after a confirmation; that move is a separate structure request through the same structure service as Invoeren, never part of the Layout save (ADR 0036). Delivered in four slices, each through every layer: D1, then WP-E0 right after the project setup, then DT, D2, D3 (PROFILE "Deliveries").

## Technical Context

**Language/Version**: PHP 8.4; TypeScript 5 on Node 24 (build only).

**Primary Dependencies**: Laravel 13, Inertia 3, Vue 3.5 (at least 3.5.41), Tailwind 4, Vite 8, Reka UI, pragmarx/google2fa, bacon/bacon-qr-code; DT: `@open-pencil/*` pinned exactly after WP-E0; D3: html-to-image (pending Q-7). Research R-1, R-7, R-13, R-20.

**Storage**: SQLite file on a Docker volume, WAL, IMMEDIATE transactions (ADR 0032); PostgreSQL in CI only (ADR 0022). Data model: [data-model.md](data-model.md).

**Testing**: PHPUnit (unit, feature, property), Vitest, Playwright with axe on Chromium, Firefox and WebKit, Larastan level 6, vue-tsc, ESLint, Pint; static scripts for outside URLs, forbidden names and `@open-pencil` imports (R-22).

**Target Platform**: one Linux container (`php:8.4-apache`) on the product owner's laptop or development server, listening on `127.0.0.1:8080`; browsers of O-9.

**Project Type**: web application, server-rendered Inertia pages in one Laravel repository (no separate front-end project).

**Performance Goals**: P-1 p95 1000 ms per screen at 500 Elements and 1000 Cost records; P-2 Tekenen 3 s first use, 1 s per action; P-3 CSV 10 s for 5 MB; P-4 JSON 60 s for 50 MB.

**Constraints**: no outgoing connection (ADR 0027), CSP own origin (R-19), one process and one file (ADR 0023), integer money (ADR 0005), ASVS 5.0 level 2 with two exemptions (ADR 0025).

**Scale/Scope**: at most 5 users, 2 writing (L-1); up to 20 Organisations, 20 Canvases each, 500 Elements per Canvas, 1000 Cost records per Organisation (V-1 to V-4); 39 user stories, about 60 routes ([contracts/routes.md](contracts/routes.md)).

No item is left as NEEDS CLARIFICATION: every technical unknown is decided in [research.md](research.md); every product unknown is a question in "Open questions for the product owner" below.

## Constitution Check

*Pre-design and post-design, both passing. Constitution 1.0.1 (PATCH approved by the product owner) removed the three wording deviations recorded earlier under Complexity Tracking.*

| Principle or convention | How this plan meets it | Verdict |
|---|---|---|
| I Local only | No outgoing connection; fonts, icons, CanvasKit bundled; CSP own origin; FR-098 and FR-099 checks in CI (R-19, R-22, R-23) | Pass |
| II Exact money | Integers end to end (R-3); one calculation module with property test (ADR 0028); sets replaced whole under lock (ADR 0032) | Pass |
| III Structure integrity | Areas and Modalities are reference data with no write route; rules in the Structure service, not in screens; the Shift move in Tekenen calls the same move service as "Hangt onder" (ADR 0036) | Pass |
| IV Manageable through the screens | Every kind of record has its routes and list ([contracts/routes.md](contracts/routes.md)); labels only in the messages file | Pass |
| V Finished, tested slices | Tables arrive with their delivery; no stub; tracer bullet through every layer ([docs/architecture.md](../../docs/architecture.md), "What the deliverable type cuts") | Pass |
| House 1 English, Dutch only in the messages file | `lang/nl.json` is the only Dutch file (ADR 0031); English paths and identifiers | Pass |
| House 2 Index on every table | Each table in [data-model.md](data-model.md) names its index and the query it serves; unused skeleton tables removed | Pass |
| House 3 No code comments | Nothing in the plan needs one | Pass |
| House 4 No tool attribution | Process only; checked at review | Pass |
| House 5 One file per component, compact | One service, page or component per file; modules split by concern in [docs/architecture.md](../../docs/architecture.md) | Pass |
| Base slice | Data model, CRUD, optional TOTP, shell built once (US3, US4) | Pass |
| Compliance articles | AVG: PII inventory unchanged by this plan (no new personal field; throttle keys hashed, R-7). NIS2: logging per R-17, notices per FR-023. WCAG: Reka UI primitives plus axe and keyboard tests (R-13, S8). OWASP: ASVS mapping below | Pass |

## Project Structure

### Documentation (this feature)

```text
specs/001-omc-studio-v1/
├── spec.md, source-conflicts.md, design-contract.md, pii-inventory.md
├── plan.md                 this file
├── research.md             decisions R-1 to R-25
├── data-model.md           tables, indexes, state transitions
├── quickstart.md           validation guide per delivery
├── contracts/
│   ├── routes.md
│   ├── calculation.md
│   ├── layout-engine.md
│   ├── layout-payload.md   (DT)
│   ├── edit-engine-adapter.md (DT)
│   └── exchange-formats.md (D2, D3)
└── tasks.md                phase 5
docs/architecture.md        module and seam map
docs/adr/0028 to 0035       decisions of this phase
```

### Source Code (repository root)

```text
app/
├── Calculation/            pure figures module (D2)
├── Console/Commands/       app:install, app:reset-admin-password, app:backup, app:restore
├── Http/Controllers/       thin, one per screen or resource
├── Http/Middleware/        security headers, must-change-password, archived-is-read-only, refused-access logging
├── Http/Requests/          form requests using shared rule objects
├── Models/                 Eloquent models with the Audited trait
├── Rules/                  field rules shared by forms and imports
└── Services/<Module>/      Accounts, Canvas, Structure, Statements, Audit, Undo, Finance, Allocation, Notices, Layout, Snapshot, Exchange
database/migrations/        one migration per table, created in its delivery
lang/nl.json                the only Dutch file
public/fonts/               Inter, Space Grotesk
resources/js/
├── pages/                  Inertia pages
├── shell/, components/     shell and shared components (list, dialogs, fields)
├── canvas/                 HTML canvas view and overlays
├── layout/                 layout engine (pure)
├── allocation/             basis-point helpers (D2)
├── edit-engine/            drawing adapter (DT, only after a go)
├── i18n/, format/, routes.ts
tests/
├── Unit/                   calculation, parsers, validators
├── Feature/                routes, rules, refusals, audit, undo
├── fixtures/               largest-remainder.json, reference Canvases
├── js/                     Vitest
└── e2e/                    Playwright with network, CSP and axe guards
docker/                     delivered image: Dockerfile, entrypoint, Apache site
docker-compose.yml, env.example, Makefile    the delivered stack at the root (ADR 0038)
.devcontainer/              the development container's compose file and env template (ADR 0038)
```

**Structure Decision**: one Laravel repository with the Vue front end in `resources/js`, Laravel's own directories for framework types and `app/Services/<Module>/` for domain logic; the module boundaries and seams are in [docs/architecture.md](../../docs/architecture.md).

## Delivery sequence

| Order | Slice | Content | Gate |
|---|---|---|---|
| 1 | WP1 project setup | Vue 3, Inertia, Tailwind, test stack and CI (SQLite and PostgreSQL), delivered image and compose at the root with the development container moved to `.devcontainer/` and a local `.env` setting `COMPOSE_FILE` (ADR 0038): WP1 proves that this machine still starts the development container and a clean clone starts the delivered stack, and stops and reports if either needs a change to the host tooling; `app:install`, CSP, security log, messages file; verification point Secure cookie on `http://localhost` per browser (constraints section 6) | none |
| 2 | WP-E0 | Technical proof P1 to P10 on top of WP1; report with evidence | product owner go or no-go; a no-go stops DT only (FR-115) |
| 3 | D1 rest (WP2 to WP4) | Accounts and second factor, shell, list component, Users, Organisations, Audit log, overview, Canvas lifecycle, Templates, Structure, Invoeren, quick entry, Direction, Modality, layout engine, Bekijken | delivery 1 acceptance |
| 4 | DT (WP-E1 to WP-E4) | Layout table and versions, Manual layout in the engine, adapter, Tekenen, saving, reset, Shift move, Layout in copies | after a go only |
| 5 | D2 (WP5 to WP8) | Fiscal years, Cost records, lists, CSV import, calculation, Toewijzen, Redistribution, Carry over, Finalise, overlays, System notices | none (ADR 0011 and GATE-U1 confirmed) |
| 6 | D3 (WP9 to WP11) | Snapshots, JSON and CSV exchange, PNG and print, sample data, backup, README, final review | none |

DT and D2 are independent after D1 (addendum chapter 9 point 2); the order above follows the product owner's "Deliveries". DT clauses tagged "from D2" or "from D3" are built when that delivery exists.

## How the deliverable type shapes the plan

`deliverable_type: tracer-bullet` (PROFILE): depth is never cut, breadth is. Each delivery runs through data model, server rules, UI and tests, and the calculation, allocation and closing-check paths carry their full tests from the first D2 task. Deliberately cut from the architecture: no table or column before its delivery (layouts and versions in DT, finance in D2, snapshots and sample data in D3); no Manual layout input in D1's layout engine; no adapter code before WP-E0 ends in go; no public API, queue, scheduler, cache server, plugin mechanism or preparation for the deeper layer of GATE-U3. Detail: [docs/architecture.md](../../docs/architecture.md), "What the deliverable type cuts".

## Constraint mapping

Every constraint of `docs/constraints.md` maps to a decision or an accepted risk with an owner. "PO" is the product owner, who operates the machine (O-4).

| Constraint | Decision or accepted risk |
|---|---|
| L-1 5 users, 2 writing | IMMEDIATE SQLite transactions serialise writers (ADR 0032); one process (ADR 0035) |
| L-2 beyond L-1 | Accepted risk, not designed for. Owner PO |
| P-1 1000 ms p95 | Server: one calculation pass per request, indexes per query (data model), no N+1 (eager loading, checked by Laravel's lazy-loading prevention in tests). Browser: layout engine under 50 ms for 500 Elements (contract test). Measured by the end-to-end suite on the 500/1000 dataset |
| P-2 Tekenen 3 s, 1 s per action | Tekenen bundle loaded on demand (FR-110); save sends one JSON body; validated by WP-E0 P9 and DT browser tests |
| P-3 CSV 5 MB in 10 s | Streamed line reading; field rules checked before the write transaction, which stays short (R-9); the file is sent again at commit, not stored (R-16); test with a generated 5 MB file |
| P-4 JSON 50 MB in 60 s | One `json_decode`; `upload_max_filesize` 50M, `post_max_size` 55M, memory limit 1G for the import routes only (R-16); validation outside the write transaction (R-9); test with a generated 50 MB file. Risk K-5, accepted by the PO |
| P-5 sign-in within 3 minutes of `docker compose up -d` | Entrypoint only migrates and installs (R-18, R-25). The 3 minutes count once the image is built on that machine; the README states the one-time build time measured in WP1 (ADR 0035) |
| P-6 PNG and print speed | Accepted unknown, measured once in D3. Owner PO |
| P-7 change notice within 100 ms | Adapter `onChange` contract; WP-E0 P4 |
| V-1 to V-5 volumes | Indexes per access pattern; property and performance datasets at these sizes |
| V-6 database up to 1 GB | Accepted. Owner PO |
| V-7 Audit log unbounded | Accepted; indexed by Canvas, Organisation and time. Owner PO |
| V-8 upload limits | R-16, checked on content |
| V-9 real size unknown | Accepted; V-1 to V-5 stand. Owner PO |
| R-1 Audit log retention | Removed by the Canvas and Organisation deletion services, one deletion entry left (FR-021) |
| R-2 System notices | `canvas_id` cascade; lockout notices without Canvas kept. Accepted unbounded. Owner PO |
| R-3 Snapshots | Delete route; cascade with the Canvas |
| R-4 security log 90 days, R-5 error log 14 days | Daily channels with `days` set; rotation deletes (R-17) |
| R-6 sessions | Database sessions without IP address or user agent (R-28), lifetime 120, lottery clean-up during requests |
| R-7 throttle keys 60 s | File cache, keys an HMAC of the e-mail address with the application key; an expired file can remain until its key is reused, holding only that hash and a count. Residual K-7, accepted by the PO |
| R-8 deleted user | Hard delete; `actor_id` set null; values about the user rewritten (FR-011) |
| R-9, R-10 backups and exports | Outside the application. Owner PO |
| A-1 no availability target, A-4 no automatic backup | Accepted (ADR 0026). Owner PO |
| A-2, A-3 recovery | `make backup` copies a consistent database (`VACUUM INTO`), the uploads and the application key, without which restored second-factor secrets cannot be decrypted (R-18); `make restore` stops the application, removes the `-wal` and `-shm` files, copies back and starts; README (D3) says the backup folder holds the key |
| O-1 to O-4 | Process; no design consequence beyond O-4 operations commands |
| O-5 two commands | Entrypoint and compose at the repository root (R-18, ADR 0038) |
| O-6 one container, one file | ADR 0035, ADR 0023 |
| O-7 engines | SQLite everywhere, PostgreSQL second CI run; portable migrations (no partial or expression indexes, data model) |
| O-8 loopback, no outgoing | Compose `127.0.0.1:8080:80`; CSP; network guard in Playwright |
| O-9 browsers | Playwright on Chromium, Firefox, WebKit; CSS and APIs within those versions |
| O-10 reference machine | Performance tests state the machine; CI numbers are indicative only. Accepted risk: CI runners are not the reference machine. Owner PO |
| O-11 CI without client data | Forbidden-names check (FR-099); generated test data only |
| V1 encoding | Vue escapes by default; `v-html` forbidden by ESLint; Eloquent bindings; CSV formula prefix (FR-084) |
| V2 validation and business logic | Form requests plus services; shared rule objects for imports; serialised guards (ADR 0032) |
| V3 web front end | CSP and headers middleware, `data:` and `blob:` image sources for PNG export (ADR 0039), WASM relaxation on the Tekenen page only (R-19); cookies HttpOnly, SameSite=Lax, Secure by setting; no personal data or intermediate state in browser storage (ESLint rule against `localStorage`); Inertia history encrypted and cleared at sign-out and after one-time passwords or recovery codes, its random key in session storage (R-19) |
| V4 API | No public API; methods per [contracts/routes.md](contracts/routes.md); CSRF on all writes |
| V5 file handling | R-16; generated names only (FR-088) |
| V6 authentication | R-7; bundled 10,000-password list; exemption MFA optional (ADR 0010, ADR 0025) |
| V7 session management | FR-005 as amended (ADR 0037): 12 hours absolute lifetime, unlimited concurrent sessions documented, other sessions end on factor changes, "Sessies beëindigen" for Administrators, current password for one's own e-mail change; exemption 7.5.2 (ADR 0025). Sign-in time kept in the session and checked by middleware (R-31) |
| V8 authorisation | Gate `administer`, route-group middleware, router-enumeration test (R-12) |
| V9, V10, V17 | Out of scope (constraints section 6) |
| V11 cryptography | `random_bytes` and `Str::password` with CSPRNG for one-time passwords, codes, file names; TOTP secret encrypted with the application key; Snapshot SHA-256 |
| V12 transport | Exemption: loopback and SSH tunnel (ADR 0024) |
| V13 configuration | `APP_DEBUG=false` in the image; application key generated at first start into the volume and exported by the entrypoint (R-18); error pages without trace (FR-091); uploads and logs outside `public` |
| V14 data protection | `Cache-Control: no-store` on authenticated responses; no user data in exports (exchange contract) |
| V15 secure coding | Mass assignment through `$fillable` lists only; lockfiles; OpenPencil pinned with telemetry, AI, collaboration and cloud off |
| V16 logging | R-17 (no bindings, no client address in Apache's error log), R-11, System notices |
| Dependency policy | CI fails on `composer audit` and `npm audit --audit-level=high`; Dependabot alerts only |
| Secrets | `.env` out of git; first password once in the container log (R-25) |
| WP1 verification point | Task in WP1: Secure cookie over `http://localhost:8080` in Chromium (Firefox and WebKit dropped by the product owner, 2026-11-19); README names the setting where one refuses |
| Section 7 PII | No new personal data in this plan: sessions lose the IP address and user agent the framework would store (R-28); throttle keys are keyed hashes; undo records hold no user records (User deletion has no undo) and expire within 10 minutes |
| Section 8 logging and incidents | Security and error logs (R-17); System notices with top-bar warning (US34); notification by the PO through the ISO policy (GATE-U2) |
| Section 9 closed options | Respected: no database server, no Redis or second service, no TLS in the stack, no automatic backup, no outside service |

## Rejected alternatives

Each with the constraint that killed it; detail in research.md and the ADRs.

| Alternative | Killed by |
|---|---|
| Automatic layout computed on the server in PHP | H11 needs the same text lines as the browser and the drawing library; text measurement needs the font files in a shaping engine PHP does not have (ADR 0029) |
| Each renderer wrapping text itself | Browser and Skia break lines differently, so H11 (same text lines within 1 pixel) fails |
| Figures calculated in the browser | The server must refuse Finalise and Snapshots on a failed closing check and record a System notice (FR-068, FR-071, FR-079): a browser result cannot be trusted for that (ADR 0028) |
| Laravel Fortify | FR-006 requires recovery codes stored as hashes; Fortify stores them encrypted (ADR 0030) |
| Soft deletes for undo | FR-035 name uniqueness and every list query would have to skip deleted rows; FR-017 only needs a short-lived restore (ADR 0033) |
| Undo records in the session | Two concurrent requests of one user overwrite each other's session payload, and a bulk deletion would be read and written on every request (ADR 0033) |
| The WASM relaxation for every page | FR-090 allows it for the Tekenen bundle only; full page loads into Tekenen make the narrow policy possible (R-19) |
| A Manual Element's parent forced to Manual so the server knows its width | FR-107 resets a single Element while its children keep their Layout; automatic widths need no text, so the server computes them instead (R-5) |
| nginx and php-fpm under a supervisor | O-6 one process per container; nothing gained at L-1 (ADR 0035) |
| Laravel Dusk | O-9 needs Firefox and Safari engines and SC-022 needs request interception |
| New parents inside the whole-Layout save for the Shift move | H12 bypass check (FR-103, US37.4) could no longer tell a move from tampering, and structure rules would live in two services (ADR 0036) |
| Partial unique indexes for name uniqueness | O-7: not portable between SQLite and PostgreSQL over a nullable parent |

## Items confirmed after the plan

The product owner confirmed on 2026-11-18: ADR 0011 (now accepted), GATE-U1 (Finalise only with an empty Unknown allocation), SD-25 to SD-41, and the risks K-3, K-5, K-6 and K-7. Nothing in this plan rests on an unconfirmed item any more.

## Risks with an owner

| Id | Risk | Measure | Owner |
|---|---|---|---|
| K-1 | OpenPencil cannot meet P2, P3, P6 or P7 | WP-E0 right after WP1 decides go or no-go before any DT work (FR-115) | PO decides |
| K-2 | Breaking releases of the drawing library | Exact pins, adapter only, upgrade script (S6) | PO approves each upgrade |
| K-3 | Two browsers break a line differently (R-5) | H11 is per browser; stored sizes do not depend on it; picture tests per engine | PO, accepted 2026-11-18 |
| K-4 | The 500-Element Canvas overflows the A3 Artboard by design (GATE-C51) | Shown, warned and printed on following pages; speed tested regardless | PO accepted in decision 51 |
| K-5 | 50 MB JSON import memory, about ten times the file size | Raised memory limit for import routes; tested at 50 MB | PO, accepted 2026-11-18 |
| K-6 | CI runners are not the reference machine | Performance tests report the machine; acceptance run on the PO's laptop | PO, accepted 2026-11-18 |
| K-7 | Expired throttle files remain until their key is reused (R-7) | Content is a keyed hash and a count only | PO, accepted 2026-11-18 |
| K-8 | An undo token is valid for 10 minutes on the server, longer than the 10-second message (R-10) | Same user only; restore re-checks every rule; the screen ends it at 10 seconds or on leaving | Orchestrator; reported at the gate |

## Answers to the phase 4 questions

| Id | Answer | Who | Recorded in |
|---|---|---|---|
| Q-1 | Absolute session lifetime 12 hours, with a re-authentication dialog 10 minutes before (WCAG 2.2.1) | orchestrator choice | ADR 0037, FR-005, US1.9a |
| Q-2 | No limit on concurrent sessions, documented | orchestrator choice | ADR 0037, constraints V7 |
| Q-3 | Factor on or off and new recovery codes end the other sessions | orchestrator choice | ADR 0037, FR-005, US2.11 |
| Q-4 | "Sessies beëindigen" for Administrators; exemption for 7.5.2, a risk acceptance the PO gave by taking the advice | orchestrator choice, exemption accepted by the PO | ADR 0037, ADR 0025, US5.9 |
| Q-5 | Current password for one's own e-mail change | orchestrator choice | ADR 0037, FR-005, US5.10 |
| Q-6 | Delivered stack at the root, development container in `.devcontainer/`, local `.env` sets `COMPOSE_FILE` and `COMPOSE_PROJECT_NAME`; host tooling unchanged; proven in WP1 | orchestrator choice | ADR 0038 |
| Q-7 | `img-src 'self' data: blob:` | orchestrator choice | ADR 0039, FR-090 |
| Q-8 | (a) Shift-drag moves a Cluster or Activity to another Domain; H12 amended; scope settled by QS-1 to QS-7 below. (b) Automatic siblings stay automatic under the lowest Manual child and may jump | PO | ADR 0036, source points 53 and 54, FR-101a, FR-106a |
| Q-9 | GATE-U1 confirmed; ADR 0011 accepted; SD-25 to SD-41 confirmed | PO | spec, ADR 0008, ADR 0011 |
| Q-10 | Constitution PATCH 1.0.1, including the H12 change in principle III | PO | `.specify/memory/constitution.md` |
| Q-11 | Wording of FR-103 and FR-108 amended | orchestrator choice | spec |
| Q-12 | Local build; 3 minutes count from a built image; README states the build time | orchestrator choice | ADR 0035, SC-001, P-5 |
| Q-13 | K-3, K-5, K-6, K-7 accepted, owner PO | PO | risks below |

## Answers on the Shift move (QS-1 to QS-7)

Given by the product owner one by one on 2026-11-18; recorded in `source-conflicts.md` point 53, ADR 0036, FR-101a and US35 scenarios 16 to 25. The interim rule of the previous revision is gone.

| Id | Answer | Deviation |
|---|---|---|
| QS-1 | An Activity may be Shift-moved into and out of a Cluster, also within its own Domain | |
| QS-2 | To a parent in another Area of the same kind (activity Areas among themselves, list Areas among themselves), never across kinds | |
| QS-3 | Allowed in a Finalised year | |
| QS-4 | The block stays where it is released, with its size and style | from addendum 6.2 and SD-29 (which keep holding for "Hangt onder") |
| QS-5 | Items in the list Areas may be Shift-moved to another Group | |
| QS-6 | A Domain is only slid within its own Area; Shift moves one block (Cluster, Activity or Item); a whole Domain to another Area goes through Invoeren | |
| QS-7 | Always confirm: "Verplaatsen naar <doel>?" after release and before anything is stored, also through the keyboard route | from the advice |

Technical consequences settled by the orchestrator, one line each:

- A block released partly outside its target at a side is pushed inside the target's width, keeping its size; released below, the target grows (ADR 0020).
- A Cluster keeps its Activities' Layout, which is relative to the Cluster, so they move with it unchanged.
- Automatic siblings in the target stay automatic under the lowest Manual child and may jump (source point 54); overlaps become Warnings.
- The keyboard route places the block at the target's left edge under its lowest Manual child, or at the top when it has none, keeping size and style.
- Cancelling puts the block back and leaves nothing on the undo stack.
- A Shift-drag acts on the block under the pointer alone (the selection becomes that block); a Shift-drag of a Domain, over its current parent or over no valid target is a plain drag.
- `ElementTree::move` changes structure only; "Hangt onder" resets the subtree's Layout, the Shift move writes the block's row and keeps the subtree's rows.
- Undo of a move restores the former Layout, Automatic included (no row); the adapter's undo of a move emits a move intent instead of changing the scene.
- While the dialog is open or a move is being sent, Tekenen takes no other action; a move lost to a version conflict says so, and a re-sent move asks again.
- The Layout audit entry is never merged across a parent change.

## Assumptions awaiting the product owner

Two collisions came out of applying QS-1 to QS-7. The product owner was unreachable; on the instruction of 2026-11-18 the orchestrator's advice is recorded as an ASSUMPTION so that phase 5 has a complete plan. Both stay the product owner's choice and are to be confirmed; a reversal touches only the Shift move of DT.

| Id | Collision | Assumption (to be confirmed by the PO) | Cost of a reversal |
|---|---|---|---|
| QS-8 | QS-4 keeps the block's size, while QS-1 and QS-2 let it go into a narrower target (an Activity at Domain level is always wider than a Cluster in that Domain) | Decided by the product owner 2026-11-19: a block wider than its target is made exactly as wide as the target; height, style and place otherwise kept; Manual children pushed left, then narrowed; automatic children re-flow; shown while dragging ("wordt smaller") and announced after the move | Low: one branch in the adapter's drop handling and its tests; refusing instead means replacing the narrowing by the "Kan hier niet" label |
| QS-9 | QS-7 names releasing and the keyboard route; undo and redo are neither | Decided by the product owner 2026-11-19: undo and redo of a move ask "Verplaatsen naar <doel>?" too; "Annuleren" leaves the undo history unchanged | Low: the page skips the dialog for undo and redo |

## Open questions for the product owner

None besides confirming the two assumptions above.

## Grilling record, third pass (after QS-1 to QS-7)

14 findings: the shared move would have dropped a Cluster's Activity Layout (move now changes structure only); the adapter lacked commit, revert and preview commands and an undo hook for moves; undo could not restore an automatic block (`layout: null`); the keyboard landing ignored point 54 (now under the lowest Manual child); a Cluster-onto-Cluster refusal was unreachable; selection, in-flight moves, re-sent moves, audit merging, dialog focus, scenario 6's wording, the verbatim record in `source-conflicts.md` and stale citations were fixed. Two were product choices; they are recorded as assumptions QS-8 and QS-9, awaiting the product owner.

## Complexity Tracking

No open deviation: constitution 1.0.1 aligned the wording on the messages file, the four deliveries and the left panel (Q-10).

## Grilling record, second pass (after the phase 4 answers)

A second independent pass over the revision returned 11 findings: the server already allowed open Shift-move cases through FR-037 (then an interim core with refusals, since replaced by the answers to QS-1 to QS-7); QS-4 hid addendum 6.2 (QS-4 reframed); the drop target contradicted itself and some gestures had no rule (now US35.16 and 21); the `COMPOSE_FILE` route needed a project name, `..` paths and the DEV variables' place (ADR 0038); the 12-hour limit lacked a WCAG 2.2.1 extension (re-authentication dialog, ADR 0037); versions, undo and the move were worked through (now US35.23 and 24; `position` is a plain optional field); question ids, stale statuses, provenance of ADR 0037 to 0039 and 0025, the constraints legend, the constitution's impact note and the build's one-time internet need were corrected.

## Grilling record

Phase 4 step 3: an independent reviewer on the phase model grilled this plan against the spec and constraints and returned 21 findings. Outcome per finding:

| # | Finding | Outcome |
|---|---|---|
| 1 | Containment cannot be checked when the parent is automatic | Fixed: `AutomaticWidths` on both sides (R-5, S3, layout payload) |
| 2 | Cascades and mass updates bypass the audit observer | Fixed: model-by-model writes, `no action` keys, architecture test (R-11, ADR 0034) |
| 3 | Long imports hold the SQLite write lock | Fixed: validation before the write transaction, file cache, busy timeout 15 s (R-9) |
| 4 | Inertia history keeps pages and secrets after sign-out | Fixed: history encryption and clearing (R-19) |
| 5 | A path middleware cannot guard archived parents and Templates | Fixed: guard in every service, scoped bindings (R-27) |
| 6 | Sessions table stores IP address and user agent | Fixed: own session handler, columns removed (R-28) |
| 7 | Restore without the application key loses second factors | Fixed: backup includes the key (R-18) |
| 8 | Invoeren placement writes no Layout, against FR-103 and FR-108 wording; "nieuw" and "Opmaak aangepast" unowned | Owned (R-6); wording to the PO as Q-11 |
| 9 | Calculation contract unclear on archived Elements, Snapshot filters, Finalise filter | Fixed (R-2, calculation contract) |
| 10 | All contributions in page props break P-1 | Fixed: per Element on request (R-2) |
| 11 | Undo without server expiry, lost in concurrent session writes | Fixed: `undo_records` table with expiry (ADR 0033); residual K-8 |
| 12 | Locking never tested with two processes; lock sets unstated | Fixed: lock set per path (R-9), seam S11 (R-29) |
| 13 | WASM relaxation on every page wider than FR-090 allows | Fixed: Tekenen page only, full page loads (R-19) |
| 14 | Contrast and Default colours in two languages can drift | Fixed: shared JSON and fixture (R-26) |
| 15 | PHP upload limits; file between dry run and commit | Fixed (R-16) |
| 16 | Image build time and how the key reaches PHP | Key: fixed (R-18). Build time: Q-12 |
| 17 | Unknown allocation Domain in files and Templates | Fixed (exchange contract) |
| 18 | Throttle rows outlive 60 s; plain hash reversible | Fixed: keyed hash; residual K-7 to Q-13 |
| 19 | SQL bindings and client address in logs | Fixed (R-17) |
| 20 | Risks labelled accepted without the PO | Fixed: Q-13 |
| 21 | Data model details (actor type, index, Fiscal year cascade, sample flag, 422 in Inertia, restore with WAL files, Canvas updated_at, explicit Fiscal year check) | Fixed (data model, routes, A-2 mapping) |
