# Merges made while GitHub Actions was blocked

GitHub Actions refused every job from 2026-11-19 23:20 UTC ("recent account payments have failed or your spending limit needs to be increased"). Evidence for a merge is the full local check on the merged state. When Actions works again, the first thing to run is the CI on the tip of the branch, and a red run goes before everything else.

Local check per merge: `composer verify`, `composer test` (SQLite, PostgreSQL where a test supports it), `npm run verify`, `npm run check`, `npm run build`, and the Playwright suite on chromium, firefox and webkit with one worker and one engine at a time, the performance specs on their own.

| Branch | Tip | Merged | Local evidence |
|---|---|---|---|
| trial/d2-release (release PR 395 plus integration/d2) | 3f3caba | design foundation merged into the trial | PHPUnit 1414, vitest 487, phpstan 0 errors, pint, conventions clean; diff against dfdce17: no file deleted, two files not touched by release (the ADR renumber and one token fix) |
| trial/d2-release, D2.10 Kostenposten and Toewijzen | 29d7fb7 | T384, T385 | opus review, fixes, specs on chromium, firefox and webkit by the builders |
| trial/d2-release, D2.10 Bekijken | 9410d1b and later | T386 | opus review, fixes, chromium run |
| trial/d2-release, D2.10 delivery 3 | fe5feb2 | T387 (open: product owner has not seen the screens) | PHPUnit 1417, vitest 500, phpstan, pint, build, check; Playwright functional: chromium 313 passed 1 failed, firefox 312 passed 1 failed, webkit 312 passed 1 failed 1 skipped; the one failure per engine is `shell/reflow.spec.ts:30` (200% text), which passes alone on a fresh database and fails after a long run on the accumulated e2e data |
| integration/d2 | 7aac192 | fast-forward of the trial, pushed | PHPUnit 1417, vitest 498, static checks; chromium, firefox and webkit functional and the performance specs on all three engines passed before the last merge; PostgreSQL suite 1415 tests, one performance test over its sanity limit under load, 6.9 s alone |
