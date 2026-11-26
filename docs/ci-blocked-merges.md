# Pull requests merged while GitHub Actions was blocked

From 2026-11-19 the GitHub account refused to start Actions jobs ("recent account payments have failed or your spending limit needs to be increased"). By decision of the build team a pull request was merged on complete local evidence instead (composer verify, composer test, npm run verify, npm run check, npm run build, the full Playwright suite on chromium, firefox and webkit with two workers, the performance specs alone with one worker), recorded in its description under "Local evidence (CI blocked)".

When Actions works again, the first CI run on the tip of `release` comes before any other work; a red run goes before everything else.

## Status: GitHub Actions works again (2026-11-20)

The repository is public and Actions runs again. From now on a pull request is merged into `release` only when its CI on GitHub is green (`gh pr checks --watch`); a local run alone no longer counts.

The first CI run on the tip of `release` after the block, run 44715702490 on commit `2338580` (pull request 405, which contains every pull request in the table below), finished with conclusion success: Dependency audits, Delivered image build, Scope of the change, PHP suite on PostgreSQL, PHP suite on SQLite, Vitest types lint and static checks, Playwright performance on three engines (on the 2 vCPU runner) and Playwright on three engines, all green. Every pull request in the table is therefore covered by a green CI run through that tip, including the PostgreSQL half of the PHP suite and the performance job that earlier runs could only approximate locally.

| Pull request | Merged | PostgreSQL half of the PHP suite | Notes |
|---|---|---|---|
| 395 design foundation (T379) | 2026-11-19 | run afterwards on the tip of release on 2026-11-20: 725 passed, 2 errors that were a date bug in two tests (fixed in the pull request that adds this file) | Firefox opening Invoeren is close to the 1000 ms limit on this 11-core machine (p95 996 ms): check the performance job first on the 2 vCPU runner |
| 396 Invoeren to board 1 (T380) with the DB_SCHEMA change and the date fix of two tests | 2026-11-20 | run on the tip of release on 2026-11-20 (725 passed before the date fix, the two errors were the fixed date bug) | the first functional run was void (a leftover test server held the port); 8 load timeouts passed when repeated alone |
| 397 Bekijken, sign-in, Overview and admin lists (T381, T382) and the end-to-end harness port | 2026-11-20 | run on the combined state: 726 passed, 1 test of its own fixed afterwards | webkit Invoeren opening was slow under load of other sessions in the first performance run (p95 1063 ms) and fast when repeated alone |
| 398 Bekijken breadcrumb and detail panel, design comparison with screenshots (T381 follow-up, T383) | 2026-11-20 | run on this tip: 727 passed, 2 skipped (the DB_SCHEMA tests skip under a throwaway schema) | machine idle during the run: 164 functional specs per engine passed on chromium, firefox and webkit, one worker; performance 12 of 12; secure cookie 1 of 1 |
| 400 User list sorts on e-mail again, archived toggle on the shared 24 pixel target, and the decisions on the design questions | 2026-11-20 | not rerun (front end and tests only); the run of 398 stands | full functional suite engine by engine with one worker: 164, 165 and 165 passed; the new sort test was rewritten after its first version was not isolated, and one view spec failed once on a seeding error and passed alone |
| 402 D1.19 closing: the end-to-end test, the refusal coverage and the acceptance document (T170 to T172) | 2026-11-20 | not run on this branch; the run on release (727 passed, 2 skipped) stands | full Playwright suite engine by engine with one worker: 167, 167 and 167 passed; performance 12 of 12; secure cookie 1 of 1; open defect [GAP referrer back()] documented, not fixed |
| 403 Shared design foundation completed and the reflow spec hardened (T384, T385) | 2026-11-20 | not run on this branch (no PHP code changed); the run on release stands | full Playwright suite engine by engine with one worker: 182, 182 and 182 passed; performance first run 11 of 12 (webkit Bekijken p95 1112 ms after a long run on the shared machine), both scenarios passed when repeated alone (p95 298 to 409 ms); secure cookie 1 of 1 |

The tightest figure on the 11-core machine is Invoeren opening on Firefox and WebKit; the 2 vCPU runner is slower, so the performance job is the first thing to read on the first CI run after the block.

## Running the PostgreSQL half locally

The central PostgreSQL on host `postgres` serves the project database. The PHP suite uses `RefreshDatabase`, so it must never run against the `public` schema, which holds the development data. `config/database.php` reads `DB_SCHEMA` for the PostgreSQL search path (default `public`), so a throwaway schema keeps the suite apart:

1. Create the schema: `CREATE SCHEMA ci_scratch` in the project database (the project role owns it).
2. Export the connection of the CI job and the schema: `DB_CONNECTION=pgsql DB_SCHEMA=ci_scratch DB_HOST=postgres DB_PORT=5432 DB_DATABASE=omc-studio DB_USERNAME=omc-studio DB_PASSWORD=<from .env> DB_URL=`.
3. Check the path before running anything: `php artisan tinker --execute="echo DB::select('show search_path')[0]->search_path;"` must print `ci_scratch`.
4. `php artisan migrate --force`, then `php artisan test` through `.scratch/heavy-run`.
5. Drop the schema: `DROP SCHEMA ci_scratch CASCADE`, and check that the `public` schema still holds its tables.
