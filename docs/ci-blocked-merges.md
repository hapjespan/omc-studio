# Pull requests merged while GitHub Actions was blocked

From 2026-11-19 the GitHub account refused to start Actions jobs ("recent account payments have failed or your spending limit needs to be increased"). By decision of the orchestrator a pull request was merged on complete local evidence instead (composer verify, composer test, npm run verify, npm run check, npm run build, the full Playwright suite on chromium, firefox and webkit with two workers, the performance specs alone with one worker), recorded in its description under "Local evidence (CI blocked)".

When Actions works again, the first CI run on the tip of `release` comes before any other work; a red run goes before everything else.

| Pull request | Merged | PostgreSQL half of the PHP suite | Notes |
|---|---|---|---|
| 395 design foundation (T379) | 2026-11-19 | run afterwards on the tip of release on 2026-11-20: 725 passed, 2 errors that were a date bug in two tests (fixed in the pull request that adds this file) | Firefox opening Invoeren is close to the 1000 ms limit on this 11-core machine (p95 996 ms): check the performance job first on the 2 vCPU runner |

## Running the PostgreSQL half locally

The central PostgreSQL on host `postgres` serves the project database. The PHP suite uses `RefreshDatabase`, so it must never run against the `public` schema, which holds the development data. `config/database.php` reads `DB_SCHEMA` for the PostgreSQL search path (default `public`), so a throwaway schema keeps the suite apart:

1. Create the schema: `CREATE SCHEMA ci_scratch` in the project database (the project role owns it).
2. Export the connection of the CI job and the schema: `DB_CONNECTION=pgsql DB_SCHEMA=ci_scratch DB_HOST=postgres DB_PORT=5432 DB_DATABASE=omc-studio DB_USERNAME=omc-studio DB_PASSWORD=<from .env> DB_URL=`.
3. Check the path before running anything: `php artisan tinker --execute="echo DB::select('show search_path')[0]->search_path;"` must print `ci_scratch`.
4. `php artisan migrate --force`, then `php artisan test` through `.scratch/heavy-run`.
5. Drop the schema: `DROP SCHEMA ci_scratch CASCADE`, and check that the `public` schema still holds its tables.
