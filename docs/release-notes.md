# Release notes, version 1

First delivered version of OMC Studio, a local web application for building an Operating Model Canvas, linking costs to it, drawing it and keeping Snapshots.

## What it does

- Organisations, Canvases with a Domain and element tree, Fiscal years finalised per Canvas, and Cost records per Organisation with allocations per Canvas.
- Three modes: Invoeren (data entry), Tekenen (drawing on a fixed A3 artboard, saved automatically) and Bekijken (viewing); PNG export of the drawing.
- Calculation as a pure server module in whole cents and basis points; a Fiscal year cannot be finalised while an amount sits in Unknown allocation.
- Snapshots with checksums, undo from a short-lived table, and an Audit log of every change.
- JSON, CSV and full export and import, checked per line before anything is written.
- Own authentication with optional TOTP, recovery codes, lockout, session control and Administrator roles.

## Decisions taken at the end of the review (2026-11-22)

- Deleting a user rewrites only the Audit entries about or by that user; each deleted user keeps a stable pseudonym "verwijderde gebruiker N" in the Audit log, "Definitief door" and "Gemaakt door". Users deleted before this change show the plain "verwijderde gebruiker".
- The session reference in the security log stays.
- The modality counts strip is reserved at the bottom of every Domain.
- Pull requests run Playwright on Chromium only; pushes to release and main run all three engines and the performance specs (ADR 0049).

## Delivery

Docker stack on the product owner's machine, no outgoing connections, no deployment platform (`deploy_target: none`). Install, back-up and restore are in README.md; operation and incidents are in docs/runbook.md. Decisions are indexed in docs/CONTEXT.md.

## Known limits

- Backups are manual (ADR 0026); there is no availability target.
- The database used during testing is not touched by delivery: no reset or seeding is part of the release steps.
