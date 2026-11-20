# Research: OMC Studio version 1

Date: 2026-11-18, phase 4. Every entry: decision, rationale, alternatives considered. Entries marked with an ADR number are recorded there as well; the rest are implementation-level choices inside an accepted ADR. Nothing here decides a product question: those are listed in `plan.md`, section "Open questions for the product owner", and stay open.

Knowledge base consulted (`bin/find.py` on laravel, inertia, vue, sqlite, playwright, docker-compose, csp, 2fa, export, vitest, phpstan): no lesson covers this stack's architecture choices; the CI lessons (`a-red-pipeline-is-an-ignored-pipeline`, `bound-network-installs-in-ci-with-retry-timeout`, `vitest-hooktimeout-default-is-10s`) shape the CI step in WP1, and `set-container-env-in-compose-not-a-profile` shapes the delivered compose file.

## R-1 Runtime versions

- **Decision**: PHP 8.4, Laravel 13 (already installed), Inertia 3 (`inertiajs/inertia-laravel` and `@inertiajs/vue3`), Vue 3.5 (at least 3.5.41, the floor the drawing library states), TypeScript, Tailwind 4, Vite 8, Node 24 for the build only. npm is the package manager (WP-E0 P1).
- **Rationale**: the skeleton already pins Laravel 13 and Vite 8; Vue 3.5.41 is the drawing library's floor, so choosing it in D1 avoids an upgrade inside DT.
- **Alternatives**: pnpm (no gain for one package.json, one more tool in the image).

## R-2 Where the figures are calculated (ADR 0028)

- **Decision**: one pure PHP module, `app/Calculation/`, with no database or other I/O (FR-059). Controllers load the input with plain queries (no model hydration), call it, and pass the result to the page. The closing check runs inside it. Finalise and Snapshots use the unfiltered calculation. A Snapshot stores its inputs and its unfiltered output; a filtered view of a Snapshot recalculates from the stored inputs. The per-Cost-record breakdown of one Element is sent only when asked for (hover, focus or selection), through a partial reload, so Bekijken never carries all 500 times 1000 contributions.
- **Rationale**: the closing check must record a System notice, block Finalise and Snapshot, and feed CSV and JSON exports; all of these are server-side, so one server implementation is the only source of truth. 1000 Cost records with sets of up to 20 lines is about 20,000 integer operations per request, far inside P-1.
- **Alternatives**: calculating in the browser (rejected: the server could no longer refuse Finalise or a Snapshot on a failed check without a second implementation); a database view in SQL (rejected: largest-remainder rounding with ties in set order is awkward and engine-specific in SQL, against ADR 0022 portability).

## R-3 Integer arithmetic

- **Decision**: PHP native 64-bit integers for cents, basis points and FTE hundredths. Largest product: 99,999,999,999 cents times 10,000 basis points is about 1e15, below 9.2e18. FTE is carried as hundredths times basis points and divided once at display.
- **Rationale**: ADR 0005; no bcmath or decimal library needed.
- **Alternatives**: `brick/money` or bcmath strings (rejected: a dependency for what integers already do exactly).

## R-4 Largest remainder in two languages

- **Decision**: the PHP calculation module owns largest-remainder splitting. Toewijzen shows line amounts of an unsaved concept set, so the browser needs the same split; a small TypeScript function mirrors it. Both are tested against one fixture file, `tests/fixtures/largest-remainder.json`, so they cannot drift.
- **Rationale**: a round trip to the server per keystroke in Toewijzen is slower and more code than one 20-line function.
- **Alternatives**: server-only (rejected: per-keystroke requests); browser-only for display (rejected: two unchecked implementations).

## R-5 Where the canvas is laid out (ADR 0029)

- **Decision**: one pure TypeScript layout engine, `resources/js/layout/`, computes the Automatic layout, applies stored Manual layout (DT), breaks names into lines, grows blocks, and derives the layout Warnings (beyond the Artboard, overlap, Direction text cut off). Text width comes through a `TextMeasurer` port: in the browser a canvas 2D measurer over the bundled font files; in unit tests a fixed-width fake. The engine returns absolute boxes with their text already broken into lines; the HTML view and, in DT, the drawing adapter both draw those lines as given, so neither renderer breaks text on its own.
- **Rationale**: FR-107 computes the layout "from structure, order and the font files alone"; text measurement needs the fonts, which live in the browser. Handing both renderers the same broken lines is what makes H11 (same text lines within 1 pixel) achievable across two different text engines (browser and Skia). The server never measures text: a stored height is a minimum (ADR 0020), so its checks in FR-103 use stored values only.
- **Automatic widths on both sides**: widths in Automatic layout never depend on text (Area widths follow the fixed arrangement, Domain cards share their Area's width per row, Clusters and Activities take their parent's inner width). A small `AutomaticWidths` function exists in PHP and in TypeScript, tested against one fixture `tests/fixtures/automatic-widths.json`, so the server can check "x + width within the parent's width" (FR-103) also when the parent has no Layout row.
- **Alternatives**: computing layout on the server in PHP (rejected: needs a font-shaping stack in PHP to match the browser, and H11 would then compare three text engines); letting each renderer wrap text itself (rejected: browser and Skia break lines differently, which breaks H11).
- **Accepted risk**: two browsers may measure a word a fraction of a pixel differently and break a line in another place. H11 compares modes within one browser, and stored sizes do not depend on it. Owner: product owner, see `plan.md` risk K-3.

## R-6 Placement of Elements added in Invoeren (DT)

- **Decision**: an Element without a Manual layout is placed by the layout engine at draw time: in a parent whose children are all automatic it flows; in a parent with a child in Manual layout it goes under the lowest child at the automatic size of its kind (FR-108). Invoeren writes no Layout row for it. The Layout version rises only when Layout rows are written or removed (Tekenen saves; reparenting, archiving and deleting remove rows, and those write paths raise it).
- **Rationale**: the placement depends on drawn heights, which only the engine knows; deriving it keeps the server free of text measurement and makes "placed without handwork" (SC-026) hold by construction. FR-103 asks the Layout version to rise "with every Layout write, including placements caused by Invoeren"; under this design an Invoeren placement writes nothing, while Invoeren's structure changes raise the structure version, which Tekenen checks first, so the concurrency protection FR-103 aims at is kept. FR-103 and FR-108 were reworded accordingly (Q-11).
- **"Opmaak aangepast"**: after a create in Invoeren the page runs the layout engine; when the parent's drawn height now exceeds its stored height, the message shows. **"nieuw" in "Lagen"** (SD-26): an Element without a Layout row created after the Canvas's last Layout save (`canvases.layout_saved_at`) carries the pill until it is selected in the open Tekenen session or saved with a Layout.
- **Alternatives**: the server writing a Layout row for every new Element (rejected: needs text measurement on the server).
- **Siblings** (product owner, source point 54): when one child gets a Manual layout, its automatic siblings stay automatic and go, in their order, under the lowest Manual child; they may jump.

## R-7 Authentication (ADR 0030)

- **Decision**: own controllers on Laravel's session guard, `Hash` (bcrypt, cost 12), `RateLimiter` and the database session store; `pragmarx/google2fa` for TOTP and `bacon/bacon-qr-code` for an inline SVG QR code. Recovery codes are stored as bcrypt hashes in their own table. Throttle keys are an HMAC-SHA256 of the lower-cased e-mail address with the application key, kept in the file cache, so no address is stored and the key cannot be reversed by guessing addresses without the application key. An expired throttle file can stay on disk until its key is used again; it holds only that keyed hash and a count (residual K-7, accepted by the product owner).
- **Rationale**: FR-006 requires hashed recovery codes and replay protection per time step; the spec adds one-time passwords with a 24-hour expiry, a forced change, and an Administrator reset. Each of these would be an override of a ready-made package.
- **Alternatives**: Laravel Fortify (rejected: stores recovery codes encrypted, not hashed, and its flows would be overridden almost everywhere); the Vue starter kit (rejected: brings Fortify and a UI the design replaces).

## R-8 One messages file (ADR 0031)

- **Decision**: `lang/nl.json` is the only file holding Dutch. PHP reads it through `__()`; the Vue app imports the same JSON at build time and reads it through one small `t(key, params)` function. Keys are English dotted names. Area and level labels, Modality names, the Template name "Leeg canvas", the Unknown allocation name and the CSV column headers are keys in it; the seed writes no Dutch of its own.
- **Rationale**: house convention 1 allows Dutch in exactly one place; server refusals ("Er moet minstens één beheerder blijven") and client labels then share one file.
- **Alternatives**: `messages/nl.ts` for the client plus Laravel's `lang/nl/*.php` for the server (rejected: two files); server returning keys only and the client translating everything (rejected: the console command and the container log also need Dutch text).

## R-9 Serialising concurrent writes (ADR 0032)

- **Decision**: SQLite in WAL mode with `busy_timeout` 15000 ms and `transaction_mode` IMMEDIATE (supported by Laravel's SQLite connection), so every write transaction takes the write lock when it begins. Every guarded write also locks rows with `lockForUpdate()` (no-op on SQLite, real on PostgreSQL in CI), always in the order Organisation, Canvas, then User rows by id. Lock set per write path: Yearly figures, Cost records, Owners and Applications, Fiscal years: Organisation. Allocation set, structure, Statements, Layout, Redistribution: Canvas. Finalise, Reopen, Carry over, imports: Organisation then Canvas. Role change or deletion of an Administrator: every Administrator row, by id. So a set write and a Finalise meet on the Canvas row, Yearly figures and a Finalise on the Organisation row (FR-051, edge cases).
- **Long writes**: imports parse and check every field rule before the write transaction opens; inside it they re-check only what another write can change (codes, names, Finalised years, references) and insert. The write phase stays a few seconds, under the busy timeout, even for a 50 MB file (P-4). The cache store is the file store, so throttling does not write to the database on every attempt.
- **Rationale**: SQLite's default deferred transactions let two writers read the same state and fail late; IMMEDIATE turns the race into a short wait. At 2 writing Administrators (L-1) the wait is negligible.
- **Alternatives**: optimistic version columns on every guarded row (rejected: more code for the same effect at this load); application mutexes in the cache (rejected: a second mechanism beside the database).

## R-10 Undo (ADR 0033)

- **Decision**: a deletion that offers undo writes one `undo_records` row: a random token, the user, the kind, the deleted rows with their identifiers and dependents, and an expiry 10 minutes ahead. "Ongedaan maken" posts the token; the server accepts it only from the same user before expiry, re-runs the rule checks the create path uses, inserts the rows with their original identifiers in one transaction, and deletes the record. Expired records are deleted whenever a new one is written (clean-up during requests, ADR 0023) and all of a user's records at sign-out. The 10-second countdown with its pause, and the end when the page is left, are the screen's (FR-017); the server bound only limits how long a token lives.
- **Rationale**: FR-017 asks an exact restore with the same identifiers and refusal when a rule fails now. A table is safe against two concurrent requests of one user, which overwrite each other's session payload, and holds a bulk deletion of 1000 Cost records without loading it on every request.
- **Alternatives**: soft deletes (rejected: every query and uniqueness check would have to skip deleted rows); the session (rejected: concurrent requests lose records, and a large payload is read and written on every request).

## R-11 Audit log (ADR 0034)

- **Decision**: an `Audited` model trait whose observer writes one entry per created, updated or deleted record with old and new values, tagged with the action from a request-scoped `AuditContext` (default: create, change, delete; set by services to archive, restore, undo, finalise, reopen, redistribution, carry over, import). Allocation sets and Layout write their entries explicitly, because a set is one entry over many line rows (US20 scenario 11) and Layout merges per Element and user over 60 seconds (FR-113). Secrets are never in the changed-field list: password, hashes and second-factor columns are excluded on the model and recorded as an event name (FR-020).
- **No silent writes**: Eloquent events do not fire for database cascades or query-builder mass updates and deletes. On audited tables services therefore change and delete model by model, children first (a subtree, a Cost record's Yearly figures and sets, "Vervangen door", subtree archive, Layout removal), and no audited foreign key cascades except under the hard deletion of a Canvas or Organisation, whose entries FR-021 removes anyway. An architecture test fails on `->update(`, `->delete()` on a query builder, or `DB::table(` against an audited table outside an allowlist.
- **Rationale**: an observer cannot be forgotten by a new write path, which FR-019's "every create, change, delete" needs; the two explicit cases are the only places where one entry spans several rows.
- **Alternatives**: explicit calls from every service (rejected: a missed call is a silent gap); database triggers (rejected: no actor, and not portable between SQLite and PostgreSQL).

## R-12 Authorisation

- **Decision**: one Gate `administer`; every state-changing route sits in a route group with `can:administer`, except the Reader's own routes (sign-out, own password, own second factor, session keep-alive). A test enumerates the router and asserts a Reader gets 403 on every POST, PUT, PATCH and DELETE outside that allowlist (FR-094).
- **Rationale**: two global roles (FR-009); per-record policies would add files without adding a rule.
- **Alternatives**: a policy per model (rejected: every policy would say the same thing).

## R-13 Front-end primitives

- **Decision**: Reka UI (headless, Vue) for dialog, popover, tooltip, select, radio group, menu and toast; own components on top following `design-contract.md`. Element icons for list Areas from `@lucide/vue`, bundled, a fixed subset each with a Dutch name in the messages file.
- **Rationale**: WB8 (focus into dialogs and back), WB12 (hover content on focus, Escape) and correct ARIA are hard to get right by hand; Reka UI is the drawing library's own UI dependency, so DT adds no second primitive set.
- **Alternatives**: hand-built primitives (rejected: accessibility risk on every dialog); a full component library such as PrimeVue (rejected: its own visual language fights the design contract).

## R-14 Routes and addresses

- **Decision**: English paths (house convention 1), for example `/overview`, `/canvases/{canvas}/entry`, `/canvases/{canvas}/view`, `/canvases/{canvas}/drawing`. The Fiscal year of the top bar is the query parameter `year`, carried by the shell's links; absent, the default of FR-074 applies. Front-end code builds addresses through one `routes.ts` module of plain functions.
- **Rationale**: the year in the address survives reload and back, like the mode (ADR 0014), and needs no write for a Reader.
- **Alternatives**: the year in the session (rejected: a reload in a second tab changes the first); Ziggy or Wayfinder (rejected: a generator for some forty routes is more machinery than one typed module).

## R-15 Quick entry and CSV parsing

- **Decision**: parsing lives on the server only. Quick entry posts its text to a dry-run route as the user types (debounced 300 ms) and shows the returned preview; saving posts the same text and the server parses it again. CSV and JSON imports follow the same dry-run then commit shape.
- **Rationale**: one parser, one set of line messages (FR-041, FR-085).
- **Alternatives**: a TypeScript parser for the preview (rejected: two parsers that must agree line by line).

## R-16 Upload handling

- **Decision**: logos are checked with `getimagesizefromstring` (type and dimensions from content), re-encoded with GD to PNG or JPEG (which drops metadata), stored as `storage/app/private/logos/<random>.<ext>` and served by a controller with a fixed content type. CSV and JSON are checked on content (UTF-8 text with the exact header; `json_decode` with depth and size limits) before any row is read.
- **Between dry run and commit** the file is not stored: the browser keeps the chosen file and sends it again with "Importeren", and the server runs the same checks again (FR-085).
- **PHP limits** in the image: `upload_max_filesize` 50M, `post_max_size` 55M; the import routes raise `memory_limit` to 1G for their request only.
- **Rationale**: FR-024, FR-086, V5; GD ships with the PHP image.
- **Alternatives**: Intervention Image (rejected: a dependency for two calls).

## R-17 Logs

- **Decision**: Laravel log channels: `security` (daily files, 90 days, JSON lines: time, event, user identifier) and `daily` for errors (14 days). Rotation by the daily handler removes old files without a scheduler. The web server writes no access log, and Apache's error log format leaves out the client address. The exception context carries user identifier and route name only; a database exception is logged with its SQL and placeholders, never its bindings.
- **Rationale**: FR-022, R-4, R-5, ADR 0023.
- **Alternatives**: a `security_events` table (rejected: FR-022 asks a file not shown in any screen).

## R-18 Delivered container (ADR 0035)

- **Decision**: one image built in two stages: Node 24 builds the assets; the runtime is the official `php:8.4-apache` image with `pdo_sqlite` and `gd`, assets copied in, access log off. One named volume holds `storage/` (database file, uploads, logs, generated application key). The entrypoint migrates, installs reference data and the first Administrator once (FR-089), then starts Apache. Compose publishes `127.0.0.1:8080:80`.
- **Application key**: generated at first start into `storage/app.key` on the volume; the entrypoint exports it as `APP_KEY` before starting Apache, and `make backup` copies it with the database and uploads, because without it a restored database cannot decrypt the second-factor secrets.
- **Build**: compose builds the image locally from the repository; a registry would be an outside service. The 3 minutes of SC-001 count once the image is built; the README states the one-time build time measured in WP1 (Q-12).
- **Build needs the internet once**: base images, Composer and npm packages; ADR 0027 governs the running application, not the build. The README says so.
- **Rationale**: one process, one container, one file (ADR 0023, O-6); Apache with mod_php is the most boring single-process way to serve PHP.
- **Alternatives**: nginx plus php-fpm under a supervisor (rejected: two processes in one container); FrankenPHP (rejected: newer, nothing gained at 5 users); `php artisan serve` (rejected: a development server).
- **Repository layout** (ADR 0038): the root holds the delivered `docker-compose.yml` and `env.example`; Laravel's `.env.example` is removed. The development container's compose file and Dockerfile move to `.devcontainer/`, with `..` as build context and mount source. The local, uncommitted root `.env` on this machine adds `COMPOSE_FILE=.devcontainer/docker-compose.yml`, `COMPOSE_PROJECT_NAME=omc-studio` (so the running container keeps its project) and the `DEV_*` variables; `.devcontainer/README.md` lists these lines. WP1 proves with a live run how Compose resolves `.env`, the project directory and the name, and that both routes work; it stops if either needs a host-tooling change.

## R-19 Content security policy

- **Decision**: one middleware sets `default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'`, plus nosniff, `Referrer-Policy: same-origin` (ADR 0046; it was no-referrer until 2026-11-20) and `Cache-Control: no-store` on authenticated responses. From DT the Tekenen page alone adds `'wasm-unsafe-eval'` to `script-src`; every link into and out of Tekenen is a full page load, not an Inertia visit, so each document carries its own policy. Inertia's progress indicator must not inject a style element: it is configured without its own CSS and styled from the stylesheet, which the CSP guard of the end-to-end suite proves. In local development only, the Vite dev server origin is added. The end-to-end suite fails on any `securitypolicyviolation` event.
- **Rationale**: FR-090, V3.
- **Browser history**: Inertia keeps page props in the history state, so Back after sign-out could rebuild a page, and one-time passwords and recovery codes would stay in history. History encryption is on for every page and the history is cleared at sign-out, on a forced password change and after showing one-time passwords or recovery codes. Its key lives in session storage; it is a random key, neither personal data nor intermediate state (V3).
- **Alternatives**: nonces (rejected: nothing inline needs them); the WASM relaxation for every page (rejected: FR-090 allows it for the Tekenen bundle only, and full page loads into Tekenen make the narrow policy possible).
- **Images**: `img-src 'self' data: blob:` for the PNG export (ADR 0039).

## R-20 PNG export and print (D3)

- **Decision**: print through a print stylesheet on the Bekijken view (`@page` A3 landscape, the Artboard scaled to the page, shell hidden, overflow on following pages). PNG through a DOM-to-image library run on the Bekijken view at scale 2 (`html-to-image`, pinned), fonts inlined from the own server. The CSP allows the image sources this needs (ADR 0039); the library version is pinned in D3.
- **Rationale**: FR-087 renders from the HTML view; no server-side browser is available or wanted (ADR 0023).
- **Alternatives**: drawing the PNG with our own canvas 2D renderer from the layout engine's boxes (rejected: a third renderer of the canvas and overlays).

## R-21 Automatic layout of miniatures

- **Decision**: the overview's miniature is drawn by the same layout engine at Area and Domain level only (Domain cards at their automatic or stored place, without children, heights not grown), from a light payload of Areas, Domains and their Layout rows.
- **Rationale**: up to 400 cards (V-1 times V-2) cannot each run a full 500-Element layout; the miniature is decorative (US8 accessibility).
- **Alternatives**: a stored thumbnail image (rejected: one more artefact to keep in sync).

## R-22 Testing stack

- **Decision**: PHPUnit (already installed) for unit and feature tests, on SQLite locally and on SQLite and PostgreSQL in CI (ADR 0022); a seeded random generator inside PHPUnit for the property test of FR-095 (1000 cases, seed printed on failure); Vitest for the layout engine, formatting and allocation helpers; Playwright with `@axe-core/playwright` for end-to-end, keyboard, accessibility, network and CSP checks, on Chromium, Firefox and WebKit; Larastan at level 6 and `vue-tsc` with ESLint as static checks. Static scripts: outside URLs in built assets (FR-098), forbidden names (FR-099), `@open-pencil/*` imports outside the adapter (FR-110, ESLint `no-restricted-imports`).
- **Rationale**: Playwright covers the three browser engines of O-9, request interception and axe in one tool.
- **Alternatives**: Pest (rejected: PHPUnit is installed and nothing in the spec needs Pest); Laravel Dusk (rejected: Chrome only, no request interception); a property-testing library (rejected: a loop with a seeded generator is enough).

## R-23 Fonts

- **Decision**: Inter and Space Grotesk as WOFF2 files in `public/fonts/`, weights 400, 500, 600 and 700 plus italics for canvas text, loaded with `@font-face` and awaited through `document.fonts.load` before the layout engine measures. The font list is a PHP enum and a TypeScript constant, not a table: no route can change it, which is what "seeded read-only" in FR-112 asks.
- **Rationale**: ADR 0017; a table for two fixed rows adds a migration and a model for nothing.
- **Alternatives**: a `fonts` table (kickoff addendum 6.1; rejected for the reason above).

## R-24 Identifiers

- **Decision**: UUIDs (Laravel `HasUuids`, ordered) for Elements and Cost records, which travel in exports and must survive moves (spec Key Entities); auto-increment integers elsewhere.
- **Rationale**: kickoff chapter 9; integers are simpler wherever an identifier never leaves the database.
- **Alternatives**: UUIDs everywhere (rejected: no need).

## R-25 Delivery of the first start

- **Decision**: an idempotent console command `app:install` run by the entrypoint: migrate, then insert the eight Areas, four Modalities and the Template "Leeg canvas" when absent, then, only when no User exists, create the Administrator and print e-mail and one-time password once. Sample data (D3) is a separate idempotent step recorded by a flag so that removal survives restarts (US32 scenario 2).
- **Rationale**: FR-089, US1 scenario 2.
- **Alternatives**: Laravel seeders on every start (rejected: not idempotent by default).

## R-26 Default colours and contrast in two languages (DT)

- **Decision**: the Default colours of `design-contract.md` live in one JSON file, `resources/data/default-colours.json`, read by PHP and TypeScript. Contrast with opacity compositing (FR-105) exists in both languages, tested against one fixture `tests/fixtures/contrast.json`, like R-4.
- **Rationale**: the panel shows the ratio while typing and the server refuses a save; the two must agree to the same rounding.
- **Alternatives**: server-only (rejected: a request per colour change in the panel).

## R-27 Guards for archived parents and Templates

- **Decision**: every service write method first asserts that its aggregate is writable: the Organisation and Canvas are not archived, a Template accepts only structure and Statement writes, and "Leeg canvas" accepts none. Nested routes use scoped bindings, so an Element, goal or set of another Canvas in the path is a 404. Refusals are validation errors on a `rule` key, so Inertia shows them on the page.
- **Rationale**: a middleware keyed on the path cannot see the parent of `/cost-records/{id}` or an import, while every write path goes through a service.
- **Alternatives**: one middleware (rejected for the reason above).

## R-28 Sessions without network data

- **Decision**: database sessions with a small handler that does not store the IP address or user agent; the `sessions` table has neither column.
- **Rationale**: Laravel's default handler stores both on every request, against SD-20 and constraints section 8.
- **Alternatives**: file sessions (rejected: ending all sessions of one user needs the `user_id` index).

## R-29 Concurrency tests

- **Decision**: a test group runs each guard of R-9 with two PHP processes against a file SQLite database in WAL mode (the shipped configuration) and in CI with two connections against PostgreSQL, asserting one success and one refusal: last Administrator, set against Finalise, Yearly figures against Finalise, Layout version.
- **Rationale**: an in-memory database in one process never exercises the locking that ships.
- **Alternatives**: trusting the lock design without a test (rejected: verification must gate).

## R-30 The Shift move in Tekenen (ADR 0036)

- **Decision**: the adapter recognises a drag with Shift held (a Shift-click without movement keeps its meaning: add to the selection, US35.7; with more than one block selected a Shift-drag is a plain drag). The move is decided at release: Shift held and the pointer over a valid target, the innermost one under the pointer (for a Cluster, a Cluster under the pointer counts as its Domain); anything else, including its current parent, is a plain drag. While dragging the adapter pre-checks the target on the loaded structure (kind of Area, Unknown allocation, name; a wider block is narrowed, assumption QS-8) and shows "Naar <doel>" or "Kan hier niet: <reden>"; the server decides. It suppresses the drawing library's own reparenting and Shift behaviour (WP-E0 P10). On release over a valid target it pushes the block inside the target's width, keeping its size, and emits a move intent with the target and the block's Layout relative to the target; the page opens the dialog "Verplaatsen naar <doel>?" (Reka UI dialog, WB8). "Annuleren" or Escape tells the adapter to put the block back (no request, nothing on the undo stack). "Verplaatsen" sends `POST /canvases/{canvas}/drawing/moves` in the same one-at-a-time queue as the Layout saves: Element, target, structure and Layout versions, the block's `layout`, and for undo the former `position`. The server locks the Canvas, checks the versions, calls the same `ElementTree::move` as "Hangt onder" (FR-037 same-kind rule, FR-043a, name uniqueness, Cluster only under a Domain, end of order or the given `position`), validates the block's Layout with `LayoutValidator` against the new parent (inside its width, stored or automatic, minimum sizes, style), writes it (the subtree's rows stay, being relative to the block), raises both versions, and returns them. Audit: the observer's entry with old and new parent, plus the Layout entry. Undo and redo send the former or repeated parent, position and Layout through the same request (asking the same dialog, assumption QS-9); a refused undo clears the undo history and reloads. The panel field "Verplaatsen naar" (SD-42) lists the valid targets, places the block at the target's left edge under its lowest Manual child (or at the top when it has none), and opens the same dialog. A Shift-drag acts on the block under the pointer alone. Undo of a move back to a block that had no Manual layout sends `layout: null` and the row is removed. The Layout audit entry is never merged across a parent change. A block wider than its target is narrowed to the target's width, its Manual children pushed left and narrowed, and the user told; undo and redo ask the same confirmation (assumptions QS-8 and QS-9 in `plan.md`, to be confirmed by the product owner). A Finalised year is not checked: structure stays editable (FR-072).
- **Rationale**: one move service for both modes; the Layout save stays a pure Layout check, so its H12 bypass check keeps refusing any parent change it carries.
- **Alternatives**: new parents inside the whole-Layout save (rejected: mixes structure and Layout rules in one payload and blinds the bypass check); applying the move in the browser until the next save (rejected: an unchecked structure path).

## R-31 Session lifetime and termination (ADR 0037)

- **Decision**: the sign-in time is stored in the session; 10 minutes before 12 hours the shell shows a re-authentication dialog (password, and code when the second factor is on) that starts a new period on the same page (WCAG 2.2.1 extension); a middleware ends a session older than 12 hours and sends the user to sign-in with the reason, then back to the page they were on. Ending sessions deletes the user's rows in the `sessions` table (all of them for "Sessies beëindigen", all but the current one after a password or factor change). The own-row form on the Users list asks "Huidig wachtwoord" only when the e-mail address changes.
- **Rationale**: ASVS 7.3.2, 7.4.3, 7.4.5, 7.5.1 with the least machinery; database sessions already index `user_id` (R-28).
- **Alternatives**: a re-authentication step for every change on one's own row (rejected: only the e-mail address is a sign-in attribute there).
