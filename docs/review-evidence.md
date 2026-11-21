# Evidence index for the phase 7 review

Where each requirement, success criterion and risk is proven: a test, a file or a measurement. Written for T369 and T370 on the branch of delivery 3.9. DT.6 (colours, fonts and style of Tekenen, T220 to T225) is merged into this branch, so the style and colour cells are filled in. Test paths are relative to the repository root. "e2e" means Playwright on Chromium, Firefox and WebKit.

Measurement machine for every figure in this file: linux 7.0.14-linuxkit arm64 (a container in a virtual machine), 11 cores, 8 GiB, headless Chromium, Firefox (headed under Xvfb with WebGL forced) and WebKit of Playwright 1.65.1, PHP 8.4.28, SQLite. These are not ordinary laptop figures (K-6).

## 1. Success criteria SC-001 to SC-029

| Criterion | Evidence | Status |
|---|---|---|
| SC-001 clean machine in 3 minutes | `scripts/prove-clean-start.sh`, `tests/Feature/Setup/CleanStartEvidenceTest.php`, `tests/Feature/Setup/DeliveredStackFilesTest.php`, `tests/e2e/auth/first-start.spec.ts`, build time in `specs/001-omc-studio-v1/wp1-evidence.md` | met on the development host; the 3 minutes on the product owner's laptop are his acceptance run (K-6) |
| SC-002 eight Areas at once | `tests/Feature/Structure/AreasImmutableTest.php`, `tests/e2e/view/draw.spec.ts` | met |
| SC-003 Domains, Activities, Cluster, Hangt onder | `tests/e2e/d1/full-flow.spec.ts`, `tests/Feature/Structure/MoveElementTest.php`, `tests/e2e/entry/parent-select.spec.ts` | met |
| SC-004 no Activity outside a Domain | `tests/Feature/Structure/RefusalMatrixTest.php`, `tests/Feature/Structure/CreateElementTest.php` | met |
| SC-005 quick entry in 1 minute | `tests/e2e/entry/quick-entry-timing.spec.ts`, `tests/Feature/Structure/QuickEntryTest.php` | met |
| SC-006 Areas cannot change | `tests/Feature/Structure/AreasImmutableTest.php`, `tests/Feature/Security/RouteEnumerationTest.php` | met |
| SC-007 every record kind created, changed, deleted | `tests/e2e/d1/full-flow.spec.ts`, `tests/e2e/d2-flow.spec.ts`, `tests/e2e/full-exchange.spec.ts`, `tests/Feature/Authorisation/D2RoutesTest.php`, `tests/Feature/Architecture/NoSilentWritesTest.php`; the kinds of delivery 3 (Snapshot, imports, exports) in `tests/Feature/Snapshot/TakeSnapshotTest.php`, `tests/Feature/Snapshot/UpdateSnapshotTest.php`, `tests/e2e/versions.spec.ts`, `tests/e2e/import-canvas.spec.ts` | met by the tests named and by the delivery 3 journey `tests/e2e/journey-d3.spec.ts` (create, structure, cost, allocate, overlay by hand, Snapshot, JSON out and in, PNG; three engines, three guards) |
| SC-008 last Administrator and Organisation with Canvases | `tests/Feature/Concurrency/LastAdministratorTest.php`, `tests/Feature/Accounts/UserDeletionTest.php`, `tests/e2e/admin/organisations.spec.ts` | met |
| SC-009 deleting a Cost record | `tests/Feature/Finance/CostRecordDeleteTest.php`, `tests/e2e/cost-record-delete.spec.ts` | met |
| SC-010 Replace before delete | `tests/Feature/Finance/ReplaceListEntryTest.php`, `tests/e2e/admin-d2.spec.ts` | met |
| SC-011 33,33 / 33,33 / 33,34 | `tests/Unit/Calculation/LargestRemainderTest.php`, `tests/Unit/Calculation/LineAmountsTest.php` | met |
| SC-012 99,99 and 100,01 refused | `tests/Feature/Allocation/ReplaceSetTest.php`, `tests/e2e/allocation-draft.spec.ts` | met |
| SC-013 roll-up and Canvas total | `tests/Unit/Calculation/RollUpTest.php`, `tests/Feature/Finance/FigureServiceTest.php`, `tests/Feature/Canvas/OverlayReconciliationTest.php` | met |
| SC-014 Unknown allocation as a line | `tests/Feature/Structure/UnknownAllocationProtectionTest.php`, `tests/Unit/Calculation/BreakdownTest.php`, `tests/e2e/design-bekijken-financieel.spec.ts` | met |
| SC-015 Finalise with Unknown allocation refused | `tests/Feature/Allocation/FinalisationTest.php`, `tests/e2e/allocation-finalised.spec.ts` | met |
| SC-016 Redistribution after deleting an allocated Activity | `tests/Feature/Allocation/RedistributionApplyTest.php`, `tests/Feature/Structure/ElementAllocationGuardTest.php`, `tests/e2e/allocation-targets.spec.ts` | met |
| SC-017 full export, wipe, full import identical to the cent | `tests/Feature/Exchange/FullRoundTripTest.php`, `tests/Feature/Exchange/FullLayoutRoundTripTest.php`, `tests/Feature/Exchange/CanvasRoundTripTest.php`, `tests/e2e/full-exchange.spec.ts` | met |
| SC-018 Snapshot shows the old structure and amounts | `tests/Feature/Snapshot/SnapshotViewTest.php`, `tests/Feature/Snapshot/SnapshotLayoutTest.php`, `tests/e2e/snapshot-view.spec.ts`, `tests/e2e/snapshot-overlay.spec.ts` | met |
| SC-019 PNG and PDF hold the whole Canvas, 6 pt | `tests/e2e/export-png.spec.ts`, `tests/e2e/print.spec.ts`, `tests/e2e/export-menu.spec.ts` | met on the sample Canvas; PNG and print timings of a 500 Element Canvas in section 7 |
| SC-020 every screen within 1 second at 500 Elements and 1000 Cost records | `tests/Feature/Performance/ExchangeVolumeTest.php`, `tests/e2e/performance-d3.spec.ts`, `tests/e2e/entry/performance.spec.ts`, `tests/e2e/view/performance.spec.ts`, `tests/Feature/Finance/CalculationPerformanceTest.php`, `tests/e2e/drawing-performance.spec.ts`; figures in section 7 | met on this machine; an ordinary laptop is the product owner's run (K-6) |
| SC-021 axe scan and keyboard-only scenario of every screen | `tests/e2e/d2-axe.spec.ts`, `tests/e2e/dt-journey.spec.ts`, `tests/e2e/entry/keyboard-only.spec.ts`, `tests/e2e/view/keyboard.spec.ts`, `tests/e2e/d2-keyboard.spec.ts`, `tests/e2e/drawing-keyboard.spec.ts`, the `expectNoAxeViolations` calls in `tests/e2e/snapshot-view.spec.ts` and `tests/e2e/versions.spec.ts` | met: axe for delivery 1, 2, 3 and DT; `tests/e2e/journey-d3.spec.ts` runs axe on the entry list, the allocation editor, the overlay, the versions list, the Snapshot, the screen after the JSON import and the screen after the PNG export. Keyboard-only: the earlier specs named here, and `tests/e2e/journey-d3.spec.ts`, which walks Versies (open "Momentopname maken", fill, save, open the Snapshot; archive and delete are not walked), the Snapshot view (the Versie selector and "Openen"), the Overview JSON import ("Importeren uit JSON" through "Importeren") and the PNG export with Tab, Shift+Tab, Enter and the typed text of fields and selects only, through `tabTo` of `tests/e2e/support/keyboard.ts` (no `click()` or `focus()` in these steps); inside the open Exporteren menu the item is reached with ArrowDown, the menu pattern of the library, because Tab closes the menu; the one mouse-free exception is the file chooser, filled with `setInputFiles` like in `tests/e2e/d2-keyboard.spec.ts`. Passed on Chromium, Firefox and WebKit on this branch (the CI run named in section 9, paragraph after the heading). The cost record form and the full import page are covered by `tests/e2e/cost-record-form.spec.ts` and `tests/e2e/full-exchange.spec.ts` |
| SC-022 no request leaves the machine | the `guards` fixture of `tests/e2e/support/guards.ts` (outside request and CSP violation asserted after every spec that uses it), `tests/e2e/export-network.spec.ts`, `tests/e2e/drawing-load.spec.ts`, `scripts/check-assets.mjs` over the final build (CI job `js`) | met |
| SC-023 property test of FR-095 | `tests/Unit/Calculation/FigurePropertyTest.php`, `tests/Unit/Calculation/ClosingCheckTest.php` | met |
| SC-024 acceptance session of the product owner | none possible by a machine | unmet by design: the product owner runs it, recorded for him in section 8 |
| SC-025 Domain dragged, resized, coloured, equal after reload | place and size: `tests/e2e/dt-journey.spec.ts`, `tests/e2e/h11-picture.spec.ts`, `tests/e2e/drawing-equal.spec.ts`; structure unchanged: `tests/Feature/Layout/SaveLayoutTest.php`; details in `specs/001-omc-studio-v1/dt-results.md` | place, size, fill and structure met: `tests/e2e/drawing-colour-acceptance.spec.ts` (move, resize and fill, reload, same place and size within 1 pixel in Bekijken, same fill, structure names unchanged), `tests/e2e/drawing-style.spec.ts`; partly met: figures unchanged after the save have no test (section 8) |
| SC-026 added Element appears without handwork | `tests/e2e/dt-new-element-journey.spec.ts` (an Activity added in Invoeren, placed by Tekenen under the lowest Manual child without overlap, same place in Bekijken within 1 pixel; green on three engines per `specs/001-omc-studio-v1/d2-d3-dt-acceptance.md`), `tests/Feature/Layout/NewPillTest.php`, `tests/js/layout/placement.test.ts`, `tests/e2e/drawing-frame.spec.ts` | met |
| SC-027 no outside request in a Tekenen session | `tests/e2e/drawing-load.spec.ts`, `tests/e2e/csp-guard.spec.ts`, the guards fixture on every drawing spec | met |
| SC-028 Alles automatisch opmaken equals a new Canvas | `tests/e2e/drawing-reset.spec.ts` (every Area and Element X, Y, B, H) | met for place, size and style: `tests/e2e/drawing-colour-acceptance.spec.ts` compares the computed style of a changed Domain after Alles automatisch opmaken with that Domain on a new Canvas of the same content |
| SC-029 Tekenen usable within 3 seconds | `tests/e2e/drawing-performance.spec.ts`; section 7 | met on this machine |

## 2. Security requirements, OWASP ASVS 5.0 level 2 (docs/constraints.md section 6)

| Chapter | Evidence |
|---|---|
| V1 Encoding and sanitization | template escaping by Vue; `tests/Feature/Exchange/CostRecordCsvExportTest.php` and `tests/Unit/Exchange/CsvWriterTest.php` (formula prefixes), `tests/Feature/Architecture/NoSilentWritesTest.php` |
| V2 Validation and business logic | `tests/Feature/Structure/RefusalMatrixTest.php`, `tests/Feature/Security/D1RefusalCoverageTest.php`, `tests/Feature/Layout/SaveLayoutTest.php`, `tests/Unit/Layout/LayoutValidatorTest.php`, `tests/Feature/Exchange/CanvasImportFaultyFilesTest.php`, `tests/Feature/Concurrency/LastAdministratorTest.php` |
| V3 Web frontend security | `tests/Feature/Security/HeadersTest.php`, `tests/Feature/Security/DrawingCspTest.php`, `tests/e2e/csp-guard.spec.ts`, `tests/e2e/security/secure-cookie.spec.ts`, `tests/e2e/security/history.spec.ts` |
| V4 API and web service | `tests/Feature/Security/RouteEnumerationTest.php` (every state-changing route), anti-forgery in `tests/Feature/Setup/InertiaBootTest.php` |
| V5 File handling | `tests/Feature/Exchange/CsvUploadRefusalTest.php`, `tests/Feature/Exchange/CanvasImportHardeningTest.php`, `tests/Unit/Rules/CsvUploadRuleTest.php`, logo tests in `tests/Feature/Organisations` |
| V6 Authentication | `tests/Feature/Accounts/SignInServiceTest.php`, `tests/Feature/Accounts/OneTimePasswordTest.php`, `tests/Unit/Rules/PasswordRuleTest.php`, `tests/Feature/Accounts/SecondFactorChallengeTest.php`, `tests/Feature/Accounts/TotpReplayTest.php` |
| V7 Session management | `tests/Feature/Accounts/SessionLifetimeTest.php`, `tests/Feature/Accounts/SessionTerminatorTest.php`, `tests/Feature/Security/SessionHandlerTest.php`, `tests/e2e/auth/session-timer.spec.ts`, `tests/e2e/auth/reauth.spec.ts` |
| V8 Authorization | `tests/Feature/Authorisation/D2RoutesTest.php`, `tests/Feature/Security/RouteEnumerationTest.php` (a Reader refused on every write route), `tests/Feature/Layout/DrawingRouteTest.php` |
| V9 Self-contained tokens | out of scope, exemption of ADR 0025 (no tokens are issued) |
| V10 OAuth and OIDC | out of scope, exemption of ADR 0025 (no outside identity provider) |
| V11 Cryptography | `tests/Feature/Accounts/SecondFactorEnableTest.php` (secret encrypted at rest), `tests/Unit/Snapshot/SnapshotContentTest.php` (checksum), `tests/Feature/Snapshot/DamagedSnapshotTest.php` |
| V12 Secure communication | exemption of ADR 0024: loopback and SSH tunnel; `docker-compose.yml` publishes on `127.0.0.1` only, checked in `tests/Feature/Setup/DeliveredStackFilesTest.php` |
| V13 Configuration | `tests/Feature/Setup/DeliveredStackFilesTest.php`, `tests/Unit/Setup/DatabaseSchemaConfigTest.php`, `tests/Feature/Operations/BackupTest.php`, the forbidden-names check in `scripts/check-forbidden-names.mjs` with its tests in `tests/js/scripts/checks.test.ts`; no list of forbidden names exists in the repository, so in CI that check reports a skip (the only skip the gate allows, named in `ALLOWED_SKIPS`) and has not run against a real list |
| V14 Data protection | `tests/Feature/Security/HeadersTest.php` (no-store on authenticated responses), `tests/Feature/Exchange/FullExportTest.php` (no user data in any export), `specs/001-omc-studio-v1/pii-inventory.md` |
| V15 Secure coding and architecture | `tests/Feature/Architecture/FillableTest.php`, `tests/Feature/Architecture/IndexesTest.php`, `scripts/check-audit.mjs` with `tests/js/scripts/check-audit.test.ts`, `scripts/check-imports.mjs`, `tests/js/edit-engine/boundary.test.ts` |
| V16 Security logging and error handling | `tests/Unit/Audit/SecurityLogTest.php`, `tests/Feature/Security/ForbiddenLogTest.php`, `tests/Feature/Audit` |
| V17 WebRTC | out of scope, collaboration is off in the drawing library, `tests/js/edit-engine/boundary.test.ts` |

## 3. WCAG baseline WB1 to WB12 (spec, "Accessibility baseline")

Automated: every screen passes axe with the WCAG 2.2 AA tags through `expectNoAxeViolations` in `tests/e2e/support/guards.ts`; the spec files named in SC-021 call it on each state they reach.

| Item | Evidence |
|---|---|
| WB1 keyboard | the keyboard-only specs of SC-021, `tests/e2e/drawing-move-keyboard.spec.ts` (the Shift move without a pointer), `tests/e2e/dt-journey.spec.ts` |
| WB2 visible focus | `tests/e2e/components/foundation.spec.ts`, `tests/e2e/shell/foundation.spec.ts` |
| WB3 contrast | axe, `tests/Unit/Layout/TextContrastTest.php`, `tests/Unit/Support/ColourTest.php`, `tests/e2e/view/default-colours.spec.ts` |
| WB4 target size | `tests/e2e/components/foundation-gaps.spec.ts`, `tests/e2e/components/foundation.spec.ts` |
| WB5 form errors | `tests/e2e/components/fields.spec.ts`, `tests/e2e/drawing-refusal.spec.ts` (invalid field with focus) |
| WB6 colour not the only carrier | `tests/e2e/drawing-shift-label.spec.ts` (target label as text), the pill "nieuw" in `tests/e2e/drawing-frame.spec.ts`, modality letters in `tests/e2e/entry/modality.spec.ts` |
| WB7 status messages | `tests/e2e/components/status.spec.ts`, `tests/e2e/drawing-offline.spec.ts` (failure announced, success shown not announced), `tests/e2e/drawing-move-announce.spec.ts`, `tests/e2e/drawing-pushback.spec.ts` |
| WB8 dialogs and panels | `tests/e2e/components/overlays.spec.ts`, `tests/e2e/drawing-move-dialog.spec.ts`, `tests/e2e/drawing-structure-changed.spec.ts` |
| WB9 structure and titles | `tests/e2e/view/titles.spec.ts`, `tests/e2e/shell/landmarks.spec.ts` |
| WB10 resize and reflow | `tests/e2e/shell/reflow.spec.ts`, `tests/e2e/shell/topbar-narrow.spec.ts`, the 320 pixel test in `tests/e2e/drawing-frame.spec.ts` |
| WB11 dragging has an alternative | the X, Y, B, H fields and arrow keys in `tests/e2e/drawing-keyboard.spec.ts` and `tests/e2e/drawing-move-resize.spec.ts`, "Verplaatsen naar" in `tests/e2e/drawing-move-keyboard.spec.ts` |
| WB12 hover content | `tests/e2e/view/hover.spec.ts`, Escape on cut Direction text in `tests/e2e/drawing-direction.spec.ts` |

## 4. OWASP Top 10 (2021)

| Category | Evidence |
|---|---|
| A01 Broken access control | `tests/Feature/Authorisation/D2RoutesTest.php`, `tests/Feature/Security/RouteEnumerationTest.php`, `tests/Feature/Layout/DrawingRouteTest.php` (Reader redirected, Template and archived Canvas) |
| A02 Cryptographic failures | V11 above: encrypted second-factor secrets, checksummed Snapshots, bcrypt passwords in `tests/Feature/Accounts/SignInServiceTest.php` |
| A03 Injection | V1 above: parameter binding, formula neutralisation, `tests/Feature/Exchange/CanvasImportHardeningTest.php` |
| A04 Insecure design | the pure calculation module (`tests/Unit/Calculation/FigurePropertyTest.php`), serialised writes (`tests/Feature/Concurrency/SerialisedWriteTest.php`), server-side layout validation (`tests/Unit/Layout/LayoutValidatorTest.php`) |
| A05 Security misconfiguration | `tests/Feature/Setup/DeliveredStackFilesTest.php`, `tests/Feature/Security/HeadersTest.php`, `tests/Feature/Security/DrawingCspTest.php` |
| A06 Vulnerable and outdated components | `scripts/check-audit.mjs` (high findings outside the four accepted of ADR 0040 fail), `composer audit` in the CI job `audit`, exact pins and `scripts/upgrade-open-pencil.sh --check` |
| A07 Identification and authentication failures | V6 and V7 above, `tests/Feature/Accounts/SignInPageTest.php` (lockout), `tests/Feature/Accounts/MustChangePasswordTest.php` |
| A08 Software and data integrity failures | Snapshot checksum (`tests/Feature/Snapshot/DamagedSnapshotTest.php`), import validation before any write (`tests/Feature/Exchange/CanvasImportFaultyFilesTest.php`, `tests/Feature/Exchange/FullImportTest.php`), the adapter import boundary (`scripts/check-imports.mjs`) |
| A09 Security logging and monitoring failures | V16 above, System notices in `tests/Feature/Notices` |
| A10 Server-side request forgery | no outgoing connection exists (ADR 0027): `scripts/check-assets.mjs`, the guards fixture, `tests/e2e/export-network.spec.ts` |

## 5. Risks K-1 to K-8 (specs/001-omc-studio-v1/plan.md)

| Risk | Evidence |
|---|---|
| K-1 OpenPencil cannot meet P2, P3, P6 or P7 | `specs/001-omc-studio-v1/wp-e0-report.md`, `docs/adr/0040-wp-e0-decision.md` (go), `tests/e2e/drawing-load.spec.ts`, `tests/e2e/drawing-equal.spec.ts` |
| K-2 breaking releases of the drawing library | exact pins in `package.json`, `scripts/upgrade-open-pencil.sh`, `tests/js/edit-engine/upgrade.test.ts` |
| K-3 two browsers break a line differently | `tests/e2e/h11-picture.spec.ts` and `tests/e2e/drawing-equal.spec.ts` per engine, `tests/e2e/layout/measurer.spec.ts` |
| K-4 the 500 Element Canvas overflows the A3 Artboard | `tests/e2e/view/overflow.spec.ts`, `tests/js/layout/warnings.test.ts`, `tests/js/layout/overlap.test.ts`, speed in section 7 |
| K-5 50 MB JSON import memory | `tests/Feature/Exchange/CanvasImportLargeFileTest.php`, `tests/Feature/Exchange/FullImportLargeFileTest.php` (peak memory printed) |
| K-6 CI runners are not the reference machine | every performance test prints the machine; the acceptance run is on the product owner's laptop |
| K-7 expired throttle files remain | `tests/Feature/Accounts/SignInServiceTest.php` (keys are a keyed hash and a count only) |
| K-8 undo token valid for 10 minutes | `tests/Feature/Undo`, `tests/e2e/components/list-undo.spec.ts` |

## 6. Open assumptions and gaps

The full table with advice and cost of reversal is at the top of `specs/001-omc-studio-v1/tasks.md`. Reversal of QS-8 touches only T210, T213, T214, T216, T217 and T219.

| Id | Subject |
|---|---|
| QS-8 | a Shift-moved block wider than its target is made exactly as wide as the target (decided by the product owner 2026-11-19, to be confirmed in the running app) |
| QS-9 | undo and redo of a Shift move ask the confirmation too (decided by the product owner 2026-11-19) |
| DT3-1 | the text offset limit between Tekenen and Bekijken measures 1.013 px in Firefox and WebKit, the test allows 1.05 px |
| DT5-1, DT7-4 | provisional Dutch texts of the Shift move refusals and of Tekenen, like D1.2-1 |
| DT9-1 to DT9-3 | the Lagen tree stays expanded, the note under Lagen, no tool icons (board 1b comparison) |
| GAP referrer back() | resolved by ADR 0046 (Referrer-Policy same-origin) |

## 7. Measurements

Remeasured on 2026-11-21 on this branch. The logs are local files of the measurement machine, not committed: `L1` is `/workspace/omc-studio/.scratch/evidence-ev/f-composer-test.log` (full PHP suite, 1662 tests passed), `L2` are `/workspace/omc-studio/.scratch/evidence-ev/pw-performance-chromium.log`, `-firefox.log` and `-webkit.log` (every `@performance` spec, one worker, one engine at a time, on the build before the last stacking order change of `CanvasView.vue`, which does not touch these specs). Figures that earlier versions of this file gave and that no retained log shows were replaced by these or marked.

| Measure | Figure | Source |
|---|---|---|
| P-1 D3 server side, 500 Elements and 1000 Cost records, 95th percentile of 20 samples | Versies 3 ms, Snapshot view 37 ms, Canvas JSON export 122 ms, Cost records CSV 1 ms, Allocations CSV 13 ms, full export 167 ms | `tests/Feature/Performance/ExchangeVolumeTest.php`, printed line in L1 |
| P-1 D3 in the browser, 20 samples, p95 | Versies 75 ms Chromium, 157 ms Firefox, 126 ms WebKit; Snapshot view 360, 547, 660 ms; Canvas JSON export 342, 162, 221 ms | `tests/e2e/performance-d3.spec.ts`, L2 |
| P-2 Tekenen, 500 Elements, p95 of 20 samples | first use 1278 ms Chromium, 1562 ms Firefox, 1014 ms WebKit (budget 3000); one automatic save 88, 104, 116 ms (budget 1000) | `tests/e2e/drawing-performance.spec.ts`, L2 |
| P-2 layout engine, all 500 Elements Manual, median of 21 runs | under 50 ms is the limit the test asserts; the median itself is not printed and no log of it is retained | `tests/js/layout/performanceManual.test.ts` |
| P-3 cost record CSV, 5 MB | dry run 0.67 s, commit 2.95 s (limit 10 s) | `tests/Feature/Exchange/CostRecordImportPerformanceTest.php`, L1 |
| P-4 JSON import, 47.9 MB, 21002 cost records | full import commit 1.87 s, peak 472 MB (limit 60 s); the canvas import of about 50 MB passes its 60 s limit, its figure is not printed | `tests/Feature/Exchange/FullImportLargeFileTest.php`, L1; `tests/Feature/Exchange/CanvasImportLargeFileTest.php` |
| P-6 PNG and print, measured once on 500 Elements and 1000 Cost records with the overlay | PNG 3734 ms Chromium, 3717 ms Firefox, 6783 ms WebKit (0.6 to 1.7 MB); print to PDF 340 ms in Chromium (only Chromium produces a print file) | `tests/e2e/performance-d3.spec.ts`, L2, recorded for the product owner |

## 8. Unmet and open

DT.6 is merged; nothing waits for it any more. The style, font and colour requirements are proven as follows.

| Requirement | Evidence |
|---|---|
| FR-104 style holds only the listed properties, US36 | `tests/e2e/drawing-style.spec.ts`, `tests/e2e/drawing-text-style.spec.ts`, `tests/e2e/drawing-apply-style.spec.ts`, `tests/js/edit-engine/style.test.ts`, `tests/js/edit-engine/stylePayload.test.ts`, `tests/js/edit-engine/pasteStyle.test.ts`, `tests/js/edit-engine/applyStyle.test.ts` |
| FR-105 contrast of text on the effective fill | `tests/Unit/Layout/TextContrastTest.php`, `tests/e2e/drawing-contrast.spec.ts`, `tests/js/edit-engine/autoText.test.ts` |
| FR-112 only the two shipped fonts | `tests/e2e/drawing-text-style.spec.ts`, `tests/e2e/drawing-equal.spec.ts` |

| Item | Reason |
|---|---|
| SC-025 figures unchanged after a Layout save | not asserted by a test; structure is (`tests/Feature/Layout/SaveLayoutTest.php`), figures are not touched by the save code but have no before and after check |
| FR-077 counts per Domain in the Modaliteit overlay (T312 reopened) | the counts strip is drawn under the Domain fill and is not visible in the running app; `tests/e2e/overlay-modality.spec.ts` asserts the text only and passes; the layout decision is open as `[ASSUMPTION T312-1]` in `specs/001-omc-studio-v1/tasks.md`, the visibility assertion comes with the fix |
| SC-024 acceptance session | unmet by design, the product owner runs it |
| SC-001 and SC-020 on an ordinary laptop | the product owner's acceptance run (K-6) |

## 9. Acceptance list of kickoff chapter 17 against delivery 3 (T370)

Run on this branch on 2026-11-21, logs in `/workspace/omc-studio/.scratch/evidence-ev/` (local, not committed): the PHP suites `tests/Feature/Exchange`, `tests/Feature/Snapshot`, `tests/Feature/Performance`, `tests/Unit/Snapshot` and `tests/Unit/Calculation` (286 tests passed, `php-section9.log`), and in Chromium the end-to-end specs `snapshot-view`, `snapshot-overlay`, `export-png`, `export-menu`, `export-network`, `full-exchange`, `import-canvas`, `versions`, `print` and `d2-axe` (38 passed, `f-pw-section9-chromium.log`); `tests/e2e/journey-d3.spec.ts` at 512c6df: green on Chromium, Firefox and WebKit in CI run 44896755846, job "Playwright on three engines". The local journey logs (`f-pw-journey-chromium.log`, `f-pw-journey-firefox.log`, `f-pw-journey-webkit.log`, 1 passed each) are kept only as the remark "(revision a60715a, before 1b49410)": they were written before commit 1b49410 changed the walk order and prove an older spec.

| Criterion | Passing test ids | Unmet or open |
|---|---|---|
| SC-007 | `tests/e2e/d1/full-flow.spec.ts`, `tests/e2e/d2-flow.spec.ts`, `tests/e2e/journey-d3.spec.ts`, `tests/e2e/versions.spec.ts`, `tests/e2e/import-canvas.spec.ts` | none for delivery 3 |
| SC-017 | `tests/Feature/Exchange/FullRoundTripTest.php`, `tests/Feature/Exchange/FullLayoutRoundTripTest.php`, `tests/e2e/full-exchange.spec.ts` | none |
| SC-018 | `tests/Feature/Snapshot/SnapshotViewTest.php`, `tests/e2e/snapshot-view.spec.ts`, `tests/e2e/snapshot-overlay.spec.ts` | none |
| SC-019 | `tests/e2e/export-png.spec.ts`, `tests/e2e/print.spec.ts`, `tests/e2e/export-menu.spec.ts`, `tests/e2e/png-style.spec.ts` and the styled PNG step of `tests/e2e/journey-d3.spec.ts` (a pixel of the fill in the downloaded file) | none |
| SC-020 | `tests/Feature/Performance/ExchangeVolumeTest.php`, `tests/e2e/performance-d3.spec.ts` (figures in section 7) | an ordinary laptop is the product owner's run (K-6) |
| SC-021 | `tests/e2e/journey-d3.spec.ts` (axe and the keyboard-only walk of Versies, the Snapshot view, the JSON import and the PNG export), `tests/e2e/d2-axe.spec.ts`, `tests/e2e/versions.spec.ts` | none for delivery 3 |
| SC-022 | `tests/e2e/export-network.spec.ts`, `tests/e2e/journey-d3.spec.ts` (guards fixture) | none |
