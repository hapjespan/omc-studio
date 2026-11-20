# 0043: The 10 second import limit (P-3) binds the delivered engine, PostgreSQL in CI only has a sanity limit

Date: 2026-11-19
Status: accepted (integrator choice, phase 6, after three CI runs with the PostgreSQL commit at 8.0, 8.2 and 11.4 seconds; confirmed by the product owner 2026-11-19)

## Decision

The test of the 5 MB Cost record import asserts 10 seconds for dry run and commit on SQLite, the engine the application is delivered with. On any other engine it asserts 30 seconds, as a guard against a runaway, and still asserts that every row was written.

## Motivation

ADR 0022 runs the suite on PostgreSQL to prove that the SQL is portable, not to measure the delivered product. The import is already chunked: on PostgreSQL the 96,000 inserts (rows and audit entries, each with its indexes) take 6 to 7 seconds on an idle 11 core machine and 8 to 11 seconds on the 2 core CI runner, where PostgreSQL and the test compete for the same cores. A chunk size of 1,500 instead of 400 did not change that. The delivered engine takes 4 to 5 seconds on the same runner, with the limit intact.
