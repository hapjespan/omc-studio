# Evidence index for the phase 7 review

Where each requirement, success criterion and risk is proven: a test, a file or a measurement. Written for T369 and T370 on the branch of delivery 3.9. A cell that says "waits for DT.6" depends on colours, fonts and style of Tekenen (T220 to T225), which a different session builds; those cells and tasks stay open until that work is merged. Test paths are relative to the repository root. "e2e" means Playwright on Chromium, Firefox and WebKit.

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
| SC-021 axe scan and keyboard-only scenario of every screen | `tests/e2e/d2-axe.spec.ts`, `tests/e2e/dt-journey.spec.ts`, `tests/e2e/entry/keyboard-only.spec.ts`, `tests/e2e/view/keyboard.spec.ts`, `tests/e2e/d2-keyboard.spec.ts`, `tests/e2e/drawing-keyboard.spec.ts`, the `expectNoAxeViolations` calls in `tests/e2e/snapshot-view.spec.ts` and `tests/e2e/versions.spec.ts` | axe: met for delivery 1, 2, 3 and DT; `tests/e2e/journey-d3.spec.ts` runs axe on the entry list, the allocation editor, the overlay, the versions list, the Snapshot, the screen after the JSON import and the screen after the PNG export. Keyboard-only: the earlier specs named here plus the PNG export in `journey-d3.spec.ts`, which focuses the export controls and presses Enter (it does not Tab to them), so it is not itself a keyboard-only proof; a full Tab-only walk through the D3 screens is not recorded. The cost record form and the full import page are covered by `tests/e2e/cost-record-form.spec.ts` and `tests/e2e/full-exchange.spec.ts` |
| SC-022 no request leaves the machine | the `guards` fixture of `tests/e2e/support/guards.ts` (outside request and CSP violation asserted after every spec that uses it), `tests/e2e/export-network.spec.ts`, `tests/e2e/drawing-load.spec.ts`, `scripts/check-assets.mjs` over the final build (CI job `js`) | met |
| SC-023 property test of FR-095 | `tests/Unit/Calculation/FigurePropertyTest.php`, `tests/Unit/Calculation/ClosingCheckTest.php` | met |
| SC-024 acceptance session of the product owner | none possible by a machine | unmet by design: the product owner runs it, recorded for him in section 8 |
| SC-025 Domain dragged, resized, coloured, equal after reload | place and size: `tests/e2e/dt-journey.spec.ts`, `tests/e2e/h11-picture.spec.ts`, `tests/e2e/drawing-equal.spec.ts`; structure unchanged: `tests/Feature/Layout/SaveLayoutTest.php`; details in `specs/001-omc-studio-v1/dt-results.md` | place and size met; the colour part waits for DT.6 |
| SC-026 added Element appears without handwork | `tests/Feature/Layout/NewPillTest.php`, `tests/js/layout/placement.test.ts`, `tests/e2e/drawing-frame.spec.ts` | partly met; no single browser journey from Invoeren to Tekenen |
| SC-027 no outside request in a Tekenen session | `tests/e2e/drawing-load.spec.ts`, `tests/e2e/csp-guard.spec.ts`, the guards fixture on every drawing spec | met |
| SC-028 Alles automatisch opmaken equals a new Canvas | `tests/e2e/drawing-reset.spec.ts` (every Area and Element X, Y, B, H) | place and size met; style compare waits for DT.6 |
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

| Measure | Figure | Source |
|---|---|---|
| P-1 D3 server side, 500 Elements and 1000 Cost records, 95th percentile of 20 samples | Versies 4 ms, Snapshot view 33 ms, Canvas JSON export 126 ms, Cost records CSV 2 ms, Allocations CSV 11 ms, full export 183 ms | `tests/Feature/Performance/ExchangeVolumeTest.php` |
| P-1 D3 in the browser, 20 samples, p95 | Versies 111 ms Chromium, 213 ms Firefox, 148 ms WebKit; Snapshot view 474, 813, 577 ms; Canvas JSON export 199, 185, 184 ms | `tests/e2e/performance-d3.spec.ts` |
| P-2 Tekenen, 500 Elements, p95 of 20 samples | first use 1699 ms Chromium, 2111 ms Firefox, 1255 ms WebKit (budget 3000); one automatic save 102, 152, 157 ms (budget 1000) | `tests/e2e/drawing-performance.spec.ts` |
| P-2 layout engine, all 500 Elements Manual, median of 21 runs | under 50 ms | `tests/js/layout/performanceManual.test.ts` |
| P-3 cost record CSV, 5 MB | dry run 1.00 s, commit 3.67 s (limit 10 s) | `tests/Feature/Exchange/CostRecordImportPerformanceTest.php` |
| P-4 JSON import, 47.9 MB, 21002 cost records | full import commit 1.66 s, peak 307 MB (limit 60 s); the canvas import of about 50 MB passes its 60 s limit | `tests/Feature/Exchange/FullImportLargeFileTest.php`, `tests/Feature/Exchange/CanvasImportLargeFileTest.php` |
| P-6 PNG and print, measured once on 500 Elements and 1000 Cost records with the overlay | PNG 3533 ms Chromium, 3506 ms Firefox, 8019 ms WebKit (3.3 to 4.6 MB); print to PDF 321 ms in Chromium (only Chromium produces a print file) | `tests/e2e/performance-d3.spec.ts`, recorded for the product owner |

## 8. Waits for DT.6, unmet and open

| Item | Reason |
|---|---|
| SC-025 colour part | waits for DT.6 (colours, T220 to T225) |
| SC-028 style comparison | waits for DT.6 (style) |
| PNG of a styled Canvas in the end-to-end journey (the journey of T366 exports the PNG of an unstyled Canvas) | waits for DT.6 |
| Rows for style, font and colour requirements (FR-104, FR-105, US36) | waits for DT.6 |
| SC-024 acceptance session | unmet by design, the product owner runs it |
| SC-026 browser journey from Invoeren to Tekenen | not built, the parts are proven separately |
| SC-001 and SC-020 on an ordinary laptop | the product owner's acceptance run (K-6) |

## 9. Acceptance list of kickoff chapter 17 against delivery 3 (T370)

Run on this branch after the last change: the PHP suites `tests/Feature/Exchange`, `tests/Feature/Snapshot`, `tests/Feature/Performance`, `tests/Unit/Snapshot` and `tests/Unit/Calculation` (286 tests passed), and in Chromium the end-to-end specs `snapshot-view`, `snapshot-overlay`, `export-png`, `export-menu`, `export-network`, `full-exchange`, `import-canvas`, `versions`, `print` and `d2-axe` (38 passed); `tests/e2e/journey-d3.spec.ts` on Chromium, Firefox and WebKit (3 passed).

| Criterion | Passing test ids | Unmet or open |
|---|---|---|
| SC-007 | `tests/e2e/d1/full-flow.spec.ts`, `tests/e2e/d2-flow.spec.ts`, `tests/e2e/journey-d3.spec.ts`, `tests/e2e/versions.spec.ts`, `tests/e2e/import-canvas.spec.ts` | none for delivery 3 |
| SC-017 | `tests/Feature/Exchange/FullRoundTripTest.php`, `tests/Feature/Exchange/FullLayoutRoundTripTest.php`, `tests/e2e/full-exchange.spec.ts` | none |
| SC-018 | `tests/Feature/Snapshot/SnapshotViewTest.php`, `tests/e2e/snapshot-view.spec.ts`, `tests/e2e/snapshot-overlay.spec.ts` | none |
| SC-019 | `tests/e2e/export-png.spec.ts`, `tests/e2e/print.spec.ts`, `tests/e2e/export-menu.spec.ts` | the PNG of a styled Canvas waits for DT.6 |
| SC-020 | `tests/Feature/Performance/ExchangeVolumeTest.php`, `tests/e2e/performance-d3.spec.ts` (figures in section 7) | an ordinary laptop is the product owner's run (K-6) |
| SC-021 | `tests/e2e/journey-d3.spec.ts`, `tests/e2e/d2-axe.spec.ts`, `tests/e2e/versions.spec.ts` | none |
| SC-022 | `tests/e2e/export-network.spec.ts`, `tests/e2e/journey-d3.spec.ts` (guards fixture) | none |
