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
8. Performance dataset: `php artisan app:dev-dataset --elements=500 --cost-records=1000` (a development-only command), then the end-to-end timing suite reports p95 under 1000 ms per screen (P-1).
9. The end-to-end of the whole flow: `npx playwright test tests/e2e/d1` (Organisation, Canvas, Invoeren, Quick entry, Koers, Bekijken, with the network, CSP and axe guards; the CSP is checked before every full page load). It reloads the page once after the Canvas is created, in a function named `workaroundGapReferrerBack`, because `[GAP referrer back()]` is open: any form that answers with `back()` on a page reached through an in-app link (the Canvas card in Overzicht, the mode switch, the Beheer menu) returns to the last fully loaded page. Seen in Chromium: add a Domain or save the mission on an existing Canvas opened from its card, and the page ends on Overzicht; after a full reload it works. SC-003 reads "not passed without a reload" until the product owner decides (`Referrer-Policy: same-origin` or a return path per form).
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

1. Fiscal year 2026, Owners, a Cost record with Yearly figures, CSV import of 20 lines with a dry run (US17 to US19).
2. Allocate € 230,00 at 33,33 / 33,33 / 33,34: € 76,66 / € 76,66 / € 76,68 (SC-011); 99,99% sent directly is refused (SC-012).
3. Overlay "Financieel": the Canvas total equals the hand sum of all Budgets; Unknown allocation is its own card and line (SC-013, SC-014).
4. Finalise with one Cost record unallocated is refused (SC-015, GATE-U1 pending); Redistribution leaves every set at 0% or 100% (SC-016).
5. The property test of FR-095 passes with 1000 cases.

## Delivery 3

1. Full export, delete every Organisation, full import: figures identical for every Canvas, year and filter; every Snapshot passes its checksum (SC-017).
2. A Snapshot shows the old structure and amounts after changes (SC-018).
3. PNG and print of a 120-Element Canvas with the overlay: every text at least 6 pt on A3 (SC-019).
4. `make backup`, change data, `make restore`: the backed-up state returns.
5. A fresh start shows "Voorbeeld BV"; "Voorbeelddata verwijderen" removes it for good (US32).
