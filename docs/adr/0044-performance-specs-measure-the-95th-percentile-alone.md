# 0044: Performance specs measure the 95th percentile of twenty samples, alone, in their own CI job

Date: 2026-11-19
Status: accepted (integrator choice, phase 6, after performance specs turned several unrelated pull requests red, one of them documentation only)

## Decision

The one-second limit of SC-020 stays. The end-to-end specs that check it (`@performance`: Invoeren opens and adds an Element on 500 Elements, Bekijken shows 500 Elements) take twenty samples per scenario inside one test and assert that the 95th percentile, by nearest rank, is below 1000 ms. One slow sample in twenty is allowed; two are not. The median, the maximum and every sample are printed with the machine description.

Times are taken inside the page where possible, so the test harness does not count: the add-an-Element sample runs from the Enter key press to the new row in the DOM through a `MutationObserver`, and Bekijken uses `performance.now()` of the page; Playwright's own locator polling over 500 rows cost 0.5 to 0.8 seconds on Firefox and made the same code look slow. Invoeren opening is timed from the navigation to the first row, with the click on the Area included.

CI runs these specs in their own job, "Playwright performance on three engines", with one worker and no retries, after nothing else has run on that runner. The functional job excludes them with `--grep-invert '@performance|Secure session cookie'`. A pull request that only changes files under `specs/`, `docs/` or `*.md` skips both Playwright jobs through the job `scope`; every push to `release` runs them. ADR 0049 limits a pull request to Chromium and skips the performance job there.

## Motivation

A single measurement on a shared two core runner, next to fully parallel specs of three engines, varied from 0.9 to 2.2 seconds for the same code: the same commit failed and then passed. A limit that fails at random teaches people to rerun, and a rerun hides a real regression. SC-020 states the limit as the response on an ordinary laptop, which is a typical figure, not the worst single run. The 95th percentile of repeated samples measures that figure, keeps the limit at one second, and still fails when the code is slow, because a regression moves every sample, not one. Running alone with one worker removes the competition for cores that the functional job creates.

## Consequences

The performance job takes about as long as twenty page loads per scenario and engine. The limit is not raised and no retry or skip is used. If this job still fails on a quiet runner, the code is slow and is fixed, not the measurement.
