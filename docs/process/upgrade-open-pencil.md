# Upgrading the OpenPencil pins

The drawing library is young and breaks between releases (K-2). `@open-pencil/core` and `@open-pencil/vue` are pinned to one exact version each, in `package.json` and in `package-lock.json`, and only `resources/js/edit-engine/` may change when the library does (H14, ADR 0016). The product owner approves each upgrade.

## Run

    sh scripts/upgrade-open-pencil.sh 0.17.0
    sh scripts/upgrade-open-pencil.sh --check

The first form installs the given exact version of both packages with `--save-exact`, then runs every suite under `tests/js/edit-engine/` and the picture comparison `tests/js/layout/pictureCompare.test.ts` (S6: round trip, restrictions, Shift move intent, target choice, push-back, cancel, no library reparenting, undo, picture). The second form runs the same suites against the pins as they are.

## Refusals

- A version that is not exact (`^0.17.0`, `latest`, `0.17`) is refused before anything changes.
- A directory without adapter suites is refused.
- On a red suite the script restores `package.json` and `package-lock.json` from a copy made at the start, reinstalls, and exits with 1. Nothing of the failed upgrade stays.

## After a green run

Commit `package.json` and `package-lock.json` together with the adapter changes the upgrade needed, run `npm run verify` and the end-to-end specs of Tekenen on the three engines, and record the upgrade in the pull request. The suites are found by directory, so a new adapter suite added under `tests/js/edit-engine/` is part of the next run without editing the script.
