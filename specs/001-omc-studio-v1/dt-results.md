# Delivery DT: results of SC-025 to SC-029

Recorded on dt/b after DT.7, T236 and T237. Machine for every measurement: linux 7.0.14-linuxkit arm64 (a container in a virtual machine), 11 cores, 8 GiB, headless Chromium, Firefox (headed under Xvfb, WebGL forced) and WebKit of Playwright 1.65.1, one engine at a time, own application server and own SQLite database. These are not ordinary laptop figures (K-6).

| Criterion | Result | Evidence | Open |
|---|---|---|---|
| SC-025 place and size | met on three engines: a Domain moved and resized in Tekenen (X 44, Y 64, B 320) keeps place and size after a reload and Bekijken draws it within 1 pixel | `tests/e2e/dt-journey.spec.ts`, `tests/e2e/h11-picture.spec.ts`, `tests/e2e/drawing-equal.spec.ts` (text within 1.05 pixel, assumption DT3-1) | none |
| SC-025 colour | waiting for DT.6 (colours and style) | none yet | build and record after DT.6 merges |
| SC-025 structure unchanged | met: a Layout save never changes a parent and never raises the structure version | `tests/Feature/Layout/SaveLayoutTest.php`, `tests/Feature/Layout/StructureVersionTest.php` | figures unchanged from D2 are not checked here |
| SC-026 added Element appears | partly met: the pill "nieuw" for Elements added since the last save, and placement without handwork in the engine | `tests/Feature/Layout/NewPillTest.php`, `tests/e2e/drawing-frame.spec.ts`, `tests/js/layout/placement.test.ts` | no single browser journey adds an Element in Invoeren and then opens Tekenen |
| SC-027 no outside request | met on three engines: loading and a full session (select, move, resize, align, undo, Shift move, reset) send nothing outside the own origin and raise no `securitypolicyviolation` | `tests/e2e/drawing-load.spec.ts`, `tests/e2e/csp-guard.spec.ts`, and the automatic guard fixture on every drawing spec | none |
| SC-028 Alles automatisch opmaken | met for place and size: after the reset every Area and Element has the X, Y, B and H of a new Canvas with the same content (every row of Lagen compared) on three engines | `tests/e2e/drawing-reset.spec.ts` | style compare waits for DT.6; no picture compare |
| SC-029 first use within 3 seconds | met on this machine: 500 Elements, 20 samples, p95 1699 ms Chromium, 2111 ms Firefox, 1255 ms WebKit (median 1382, 1144, 1088 ms); one automatic save p95 102, 152 and 157 ms | `tests/e2e/drawing-performance.spec.ts`, `tests/js/layout/performanceManual.test.ts` (all 500 Elements Manual, median of 21 runs under 50 ms) | an ordinary laptop is not available here (K-6) |
