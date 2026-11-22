# 0049 — Pull request CI runs Chromium only

Date: 2026-11-22
Status: accepted

## Decision

On `pull_request` events the job "Playwright on three engines" runs the functional specs on Chromium alone and the job "Playwright performance on three engines" is skipped. On a push to `main`, `release` or `integration/**` both jobs run as before: three engines, the performance specs alone with one worker and no retries.

## Motivation

The three engine run and the performance run dominate the time of every pull request. The push to `release` still proves every engine and every budget before a change counts as delivered. The engine list is one variable at job level (`ENGINES`), so the install step, the loop and the push path stay a single definition. Job names are unchanged and the performance job is skipped at job level: GitHub counts a skipped job as passing for a required check, as it already does for a documentation-only change. Budgets, retries, workers and timeouts of the push path are untouched.
