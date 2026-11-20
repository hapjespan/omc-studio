# Quickstart: validating OMC Studio version 1

How to prove each delivery works end to end. Commands run inside the development container unless stated; the delivered start runs on a machine with only Docker. Details of routes, data and formats: [contracts/](contracts/), [data-model.md](data-model.md).

## Prerequisites

- Development: the project container (`docker exec -it omc-studio-dev bash`), PHP 8.4, Node 24, SQLite.
- Delivered start: Docker with Compose, a copy of the repository, nothing else.

## Checks every delivery must pass

| Check | Command | Expected |
|---|---|---|
| PHP tests on SQLite | `composer test` | green |
| PHP tests on PostgreSQL | CI job `tests-postgresql` | green |
| Static PHP | `composer verify` (Pint, Larastan) | no findings |
| Front end | `npm run verify` (vue-tsc, ESLint, Vitest) | green |
| Browser | `npm run e2e` (Playwright on Chromium, Firefox, WebKit) | green, with zero outside requests, zero CSP violations, zero axe violations |
| Built assets | `npm run build && npm run check:assets` | no outside URL (FR-098) |
| Forbidden names | `npm run check:names` | pass, or a visible skip when the list file is absent (FR-099) |
| Audits | `composer audit`, `npm audit --audit-level=high` | no high or critical |

## Delivery 1

The acceptance checklist is written for the product owner in Dutch, with the steps, the expected result and the evidence per kickoff criterion: [d1-acceptance.md](d1-acceptance.md). The steps below are the technical short form; recorded results of 2026-11-20 are in that file.

1. Clean start (SC-001): on a machine with only Docker, `cp env.example .env` and `docker compose up -d` (the delivered stack at the root, ADR 0038; on the development machine the local `.env` points `COMPOSE_FILE` at `.devcontainer/`, and the same command starts the development container). Once the image is built, within 3 minutes `http://localhost:8080` shows "Inloggen"; `docker compose logs` shows the Administrator e-mail and one-time password once. Restart: no new password. Not run in the development container (no Docker there).
2. Sign in, replace the password, enable the second factor, sign out and in with a code (US1, US2); a second session ends when the factor is turned on; a session older than 12 hours ends (ADR 0037).
3. As Administrator: create a Reader, an Organisation with a logo, a Canvas from "Leeg canvas" (US5, US6, US8).
4. Invoeren with the keyboard only: a Domain, three Activities, a Cluster, move an Activity into it through "Hangt onder" (the built form of "dragging in", ADR 0036), reorder with the arrows (SC-003); quick entry of 3 Domains with 15 Activities within a minute (SC-005).
5. Bekijken: the eight Areas on the A3 Artboard, the Unknown allocation card last in Supporting activities, zoom and "Passend maken" (US16).
6. As Reader: only Bekijken; a direct POST to any write route returns 403 (US5.7).
7. Logboek shows the rename with old and new value (US7).
8. Performance dataset: `PerformanceDatasetFactory` (T279) generates 500 Elements and 1000 Cost records; `php artisan test --filter=CalculationPerformanceTest` proves the figures come within 1 second (P-1). The browser timing per screen follows with the end-to-end flow of delivery 2 (T316).
9. The end-to-end of the whole flow: `npx playwright test tests/e2e/d1` (Organisation, Canvas, Invoeren, Quick entry, Koers, Bekijken, with the network, CSP and axe guards; the CSP is checked before every full page load). The earlier reload step for `[GAP referrer back()]` is gone since `Referrer-Policy: same-origin` (ADR 0046); `tests/e2e/entry/referrer.spec.ts` proves that saving on an existing Canvas opened from its Overzicht card keeps you on Invoeren.
10. Refusals: `D1RefusalCoverageTest` fails when a refusal message of `lang/nl.json` has no server test; the list is [d1-refusals.md](d1-refusals.md).

## WP-E0 (after WP1)

Run the proof page, collect the report per point P1 to P9 with screenshot, test or log, and hand it to the product owner for go or no-go (spec, "Technical proof WP-E0").

## Delivery DT

1. Tekenen: drag and resize a Domain, reload, compare with Bekijken within 1 pixel (SC-025); the picture comparison suite passes for the three reference Canvases.
2. Add an Element in Invoeren; it appears in Tekenen with "nieuw" without handwork (SC-026).
3. Send the faulty payloads of US37.2 directly; each is refused and nothing is stored.
4. "Alles automatisch opmaken" equals a new Canvas with the same content (SC-028).
5. A full Tekenen session records no request outside the own server (SC-027).
6. Shift-drag an Activity into a Cluster of another activity Area, cancel the dialog (it returns), repeat and confirm (it stays where released, same size and style); do the same through "Verplaatsen naar" with the keyboard only; the Logboek shows the old and new "Hangt onder"; undo asks and moves it back; a name clash and a list Area target are refused; an Activity at Domain level dropped into a narrower Cluster is narrowed to its width with a message (FR-101a, assumption QS-8).

## Delivery 2

Acceptance run: perform each step, write the result (pass or fail, date, initials) in the last column, and attach the failing output to a failure.

| Step | Do | Expected | Result |
|---|---|---|---|
| 1 | Fiscal year 2026, Owners, a Cost record with Yearly figures, CSV import of 20 lines with a dry run (US17 to US19) | the dry run reports new, updated and unchanged lines; nothing is stored before "Importeren" | |
| 2 | SC-009: delete a Cost record in an open year, then try it in a Finalised year | the Allocation sets go and the closing check passes; the Finalised year refuses | |
| 3 | SC-010: delete an Owner and an Application that are in use | refused until "Vervangen door" is used | |
| 4 | SC-011: allocate a Budget of € 230,00 at 33,33 / 33,33 / 33,34 | € 76,66 / € 76,66 / € 76,68, adding up to exactly € 230,00 | |
| 5 | SC-012: send sets of 99,99% and 100,01% directly to the server | both refused, nothing stored | |
| 6 | SC-013: overlay "Financieel" | each Domain shows its Total including Clusters and Activities; the Canvas total equals the hand sum of all Budgets of the year | |
| 7 | SC-014: leave Cost records without a set | they appear in the Unknown allocation Domain of Supporting activities, as its own line in the side panel, and count in the Area and Canvas totals | |
| 8 | SC-015: Finalise a year with one Cost record in Unknown allocation | refused (GATE-U1) | |
| 9 | SC-016: delete an allocated Activity | Redistribution opens; afterwards every affected Cost record is at 0% or 100% | |
| 10 | SC-020: `php artisan test --filter=CalculationPerformanceTest` (dataset from `PerformanceDatasetFactory`, T279) | figures for 500 Elements and 1000 Cost records within 1 second (P-1); the browser p95 per screen is added when the end-to-end flow T316 exists | |
| 11 | SC-023: `php artisan test --filter=Property` (FR-095), 1000 cases | passes on every run; no open defect of the kind "figures do not close" or "rule can be bypassed" | |
| 12 | SC-024: acceptance session, steps below | done within 30 minutes without help | |

### SC-024 acceptance session

Done by the product owner alone; the observer only keeps time and does not answer questions.

1. Start with a clean Organisation, the Template "Leeg canvas" and 10 prepared Cost records with Yearly figures for fiscal year 2026.
2. Start the timer when the product owner opens "Nieuw canvas".
3. The product owner builds a Canvas with at least one Domain in each of the four activity Areas, then allocates all ten Cost records.
4. Stop the timer when the tenth Cost record has a stored set. Record the elapsed time, every moment the product owner stopped or asked for help, and the overlay totals against the hand sum.
5. Result: pass when the elapsed time is at most 30 minutes and no help was given; otherwise record the obstacles for the next iteration.

### K-6 performance run on the reference machine

CI runners are not the reference machine (K-6), so the product owner repeats the timing on the reference machine of constraint O-10 (4 cores, 8 GB memory), in the development container (the performance test is not part of the delivered stack).

1. Start the development container and close other programs.
2. Run `php artisan test --filter=CalculationPerformanceTest` once.
3. Write down the machine (`uname -sm`, `nproc`) and whether the test passed in the Result column of step 10. The test does not print the machine on success; on failure its message does.

## Delivery 3

1. Full export, delete every Organisation, full import: figures identical for every Canvas, year and filter; every Snapshot passes its checksum (SC-017).
2. A Snapshot shows the old structure and amounts after changes (SC-018).
3. PNG and print of a 120-Element Canvas with the overlay: every text at least 6 pt on A3 (SC-019).
4. `make backup`, change data, `make restore`: the backed-up state returns.
5. A fresh start shows "Voorbeeld BV"; "Voorbeelddata verwijderen" removes it for good (US32).
