# WP-E0 technical proof: report

## Purpose

Answer the open points of addendum A1 chapter 8 with evidence from working code, before any further work on Tekenen (FR-115). The proof code lives in `proof/wp-e0/`, outside the delivered source, and is excluded from the delivered image (`.dockerignore`). On a go the adapter is written fresh in DT.

## Rules for this report

1. The go or no-go decision is the product owner's. This report recommends, it does not decide.
2. Every point is reported honestly, failures and partial results included. A criterion is never bent to fit a result.
3. The customer's name appears nowhere in this report, in the proof code or in the repository.
4. Every verdict carries evidence: a command with its output, a test, a log or a screenshot.
5. Verdicts are `met`, `met in the adapter`, `partly met`, `not met` or `pending`.

## Summary

| Point | What is proven | Verdict |
| --- | --- | --- |
| P1 | Install and build with npm inside Docker, pinned exactly, telemetry, AI, collaboration and cloud off | partly met |
| P2 | Build a scene from JSON: 3 Domains, 1 Cluster, 10 Activities with text, colour and font | met |
| P3 | Read back position, size and style per node, keeping the Element identifier | met |
| P4 | Notice changes from the host within 100 ms | met in the adapter |
| P5 | Restrict the library: no create, delete or reparent, Areas locked, shortcuts inert | met in the adapter |
| P6 | No outgoing traffic while loading and using, under the policy of FR-090, and the asset check of FR-098 | partly met: traffic met, asset check not met |
| P7 | Equal to the HTML view within 1 pixel, same wrapped lines | met, one deviation of 0.03 px in Chromium |
| P8 | Undo and redo per finished action | met |
| P9 | Load time of the Tekenen bundle within 3 seconds | met |
| P10 | Shift move recognised, target found, library kept from reparenting | met in the adapter |

**Recommendation (details in the last section): by the rule as written this is a no-go, because the asset check half of P6 fails. I recommend the product owner decides one question about FR-098; if the answer is yes, every other point is met or solvable in the adapter and the recommendation is go.**

## Environment of every measurement

| Item | Value |
| --- | --- |
| Where | the development container of this project, a Linux container in a virtual machine (kernel 7.0.14-linuxkit), 11 cores, headless, software rendering |
| Node and npm | v24.22.3 and 11.19.1 |
| Browsers | Playwright 1.65.1 builds: Chromium 159.0.8154.6, Firefox 159.0, WebKit 27.2 (Linux build of WebKit, not Safari) |
| Firefox | has no WebGL headless in this container; it runs headed under `xvfb-run` with `webgl.force-enabled`, then WebGL 2 works. Chromium and WebKit run headless. |
| Added to the container for this proof | the Playwright Firefox and WebKit browsers, the system libraries `playwright install-deps` names, and `xauth`. The dev container Dockerfile is unchanged; the project's e2e matrix (research R-22: three engines) needs the same later, see the advice at the end. |
| Run the proof | `cd proof/wp-e0 && npm ci && npm run build:page`, then `npm run test:proof` in the root (42 unit tests) and `xvfb-run -a npx playwright test -c proof/wp-e0/playwright.config.ts` (36 browser tests, three engines) |
| Result of the last full run | 42 of 42 unit tests, 36 of 36 browser tests |

The page is served by `proof/wp-e0/serve.mjs`, which reads the content security policy from `app/Http/Middleware/SecurityHeaders.php` and adds `'wasm-unsafe-eval'` to `script-src` for the proof page only (the relaxation the task names). The policy header is therefore the application's own, not a copy.

## P1: install and build

**Verdict: partly met.** The install and the build are clean and reproducible and the versions are pinned exactly. Three things fall short of "telemetry, AI, collaboration and cloud off by configuration, without handwork": the vendored package needs two build-time repairs, two capabilities are on by default and must be switched off by the adapter, and the audit reports four high findings.

### What is installed

Direct dependencies of `proof/wp-e0/package.json`, every one exact, with `save-exact=true` and `ignore-scripts=true` in `proof/wp-e0/.npmrc`:

| Package | Version | Why |
| --- | --- | --- |
| `@open-pencil/core` | 0.16.0 | The headless SDK: editor, canvas, text, undo, alignment |
| `@open-pencil/scene-graph` | 0.16.0 | The scene model |
| `@open-pencil/vue` | 0.16.0 | **Needed.** The pointer and mouse handling (selecting, dragging, resizing, drop targets) is in this package, not in `core`. The first install of T019 left it out; the proof showed that without it nothing can be dragged. |
| `vue` | 3.5.45 | Peer of `@open-pencil/vue` (3.5.41 or later) |
| `@vitejs/plugin-vue` | 6.0.11 | The Vue single file component of the proof page |
| `@expo-google-fonts/space-grotesk` | 0.4.1 | The Space Grotesk TrueType files; none exist in the repository yet (FR-112 and ADR 0017 need them in the delivered source) |
| `vite` | 8.3.8 | Same major as the root (R-1) |

Inter comes from `@open-pencil/core/assets` (static TrueType files Regular, Medium, SemiBold, Bold, ExtraBold). Not installed on purpose: `@open-pencil/cloud`, `mcp`, `cli`, `harness`. `@open-pencil/canvaskit` and the two `@fontsource` packages were installed first and removed again: the library imports `canvaskit-wasm` 0.41.1 itself and Skia's TrueType loader does not read the woff2 files `@fontsource` ships.

Transitive packages (`@open-pencil/design-jsx`, `dom-css`, `emit`, `fig`, `kiwi`, `pen`, `canvaskit-wasm` and the rest) are declared with a caret by the library. The exact pin for those is the committed `package-lock.json` plus `npm ci`; a bare `npm install` would be free to take a later release. An `overrides` block is not added.

### Clean build in Docker

Run in this container from an empty `node_modules` (and no `dist`), with no manual step:

```
rm -rf proof/wp-e0/node_modules proof/wp-e0/dist proof/wp-e0/dist-page
cd proof/wp-e0 && npm ci && npm run build && npm run build:page
```

| Step | Exit code | Duration | Result |
| --- | --- | --- | --- |
| `npm ci` | 0 | 23 s | 192 packages added |
| `npm run build` (library entry) | 0 | 1 s | 1143 modules, `dist/proof.js` 2592 kB (gzip 696 kB) |
| `npm run build:page` (proof page) | 0 | 1 s | 2534 modules, 13.4 MB of assets in total, of which the CanvasKit WebAssembly is 7.2 MB and the five font files 1.2 MB |

On disk the installed tree is 291 MB. `npm run check-pins` passes: no range in `package.json`, and every `@open-pencil/*` entry of the lockfile equals the version resolved. `tests/js/proof/p1-pins.test.ts` runs the same check in Vitest.

### What "without handwork" needed

The build of the page does not pass on the vendored package as published. Two files in `@open-pencil/core/dist` start a worker with `new URL("./worker.ts", import.meta.url)`, but the published package holds `worker.js` and no `.ts` file (`kiwi/fig/session/client.js` and `io/formats/fig/export.js`). Vite fails with `UNRESOLVED_ENTRY`. The proof repairs it with a four line transform in `proof/wp-e0/vite.config` for the page (`vite.page.config.ts`), which rewrites the extension for `@open-pencil/` files. The workers belong to the `.fig` file format, which Tekenen never uses. DT must carry the same repair, or pin a later release if the defect is fixed.

### Supply chain

- Tree: 192 packages, 291 MB. Install scripts do not run (`ignore-scripts=true`). The only real install script in the tree is `core-js` `postinstall`; the `prepare` scripts of other packages do not run for published packages.
- `npm audit`: **4 high findings**, one chain: `image-size` (denial of service through infinite loops in the JXL, HEIF and ICNS parsers) from `pptxgenjs` from `@open-pencil/core` from `@open-pencil/vue`. The only fix npm offers is a downgrade across two breaking releases. The parser is reached through a PowerPoint export only, which the application never calls. Recorded as accepted for the proof; CI must not run `npm audit --audit-level=high` over `proof/wp-e0` as if it were delivered code. For DT the same finding applies to the adapter's dependency set and is for the product owner's attention.

### Telemetry, AI, collaboration, cloud

Investigated by reading the installed `dist` of the packages, then confirmed by the network log of P6.

| Concern | Finding | Switch |
| --- | --- | --- |
| Telemetry | The only trace code writes a `performance.mark` and calls an empty in-process listener set. | Off by construction. |
| Analytics, crash reporting | None. The word "sentry" is a Figma file format field name. | Not present. |
| Collaboration | No Yjs, WebRTC, WebSocket, Trystero or PeerJS. | Not present. |
| Cloud | `@open-pencil/cloud` not installed, not imported. | Off by construction. |
| AI | No network call in shipped code; the model provider SDK is referenced by a type declaration only. Seven provider key pages exist as strings. | Nothing to switch; strings are discussed under P6. |
| Icons | `EditorOptions.icons` defaults to the Iconify API. | Pass an own `IconProvider`. Not exercised by the proof page (no icons drawn); **the adapter must always pass one**. |
| Stock photos | Calls two photo APIs, only through a tool the adapter never offers. | Off by construction while the tool is unused. |
| Web fonts | The provider settings default to `google: true, fontsource: true`. | `FontManager.setOnlineFontProviders` with every provider false and `setWebFontFetch(null)`, both called by `proof/wp-e0/fonts.ts`. |

Observation (P6 test `P6 observation`): with the switches left at their defaults and a text node in the unbundled font Roboto, the page sent nothing and no CSP violation occurred in three engines, because the browser build of the library does not fetch web fonts without a fetcher the host provides. The switches are therefore a second line of defence; the content security policy (`connect-src 'self'`) is a third.

## P2: scene from data

**Verdict: met** in Chromium, Firefox and WebKit.

A JSON fixture (`proof/wp-e0/fixtures/scene.json`) holds the artboard (1680 by 1188), a Direction band and logo, 3 Domains, 1 Cluster and 10 Activities with text, colour and Inter or Space Grotesk, weights 400, 600 and 700. `layout.ts` turns it into blocks with one shared line breaker (`linebreak.ts`, a word longer than the width breaks) and `scene.ts` builds the scene graph with the fixture's identifiers; fonts are the bundled files only. A word longer than its box is in the fixture (`act-2`).

Evidence:
- `tests/js/proof/p2-scene.test.ts` (4 tests): counts, identifiers, colour, font and text of every node, growth of an Activity with its text.
- `tests/e2e/proof/p2-picture.spec.ts`: for each of the 14 blocks the pixel at its inner bottom right corner equals the fixture colour (tolerance 2 per channel), and every text box holds drawn text (more than 30 ink pixels).
- Screenshots: `wp-e0-evidence/p2-scene-chromium.png`, `-firefox.png`, `-webkit.png`.

Finding: Space Grotesk and the other Inter weights do not load by themselves. The library's font manager only knows its own Inter files, so the proof registers every face with `fontManager.markLoaded` before the first render (`fonts.ts`). Without it Space Grotesk silently falls back to Inter. DT must do the same for the fixed font list (ADR 0017).

## P3: read back

**Verdict: met.**

`roundtrip.ts` reads place, size, fill, text colour, font family, size and weight and the text lines of every block back from the scene graph, keyed by the Element identifier (the node identifier is the Element identifier; the text node is `<id>#text`).

Evidence:
- `tests/js/proof/p3-roundtrip.test.ts` (3 tests): the read data equals the layout data for all 16 blocks without difference, reading, rebuilding and reading again gives the same data, and a change made through the graph reads back.
- `tests/e2e/proof/p3-roundtrip.spec.ts` in three engines: the data read from the live scene after the library has rendered it equals the data put in. The library does not touch what it draws.

## P4: change notice

**Verdict: met in the adapter.** The library has events, but the useful one needs a flush for the keyboard.

`changes.ts` listens to the scene graph events (updated, created, deleted, reparented, reordered) and emits one `FinishedAction` with the touched identifiers when the editor's `history:changed` fires, which is once per finished action. `changedIds` compares two read-backs and is the fallback the contract allows (addendum 7.4); it is tested but not needed.

Measured in the browser (`tests/e2e/proof/p4-changes.spec.ts`, evidence `wp-e0-evidence/p4-latency-<engine>.json`), time from the finished action to the notice:

| Action | Chromium | Firefox | WebKit | Limit |
| --- | --- | --- | --- | --- |
| Mouse drag, mouse released (8 runs) | at most 1.6 ms | at most 1 ms | at most 1 ms | 100 ms |
| Command (`updateNodeWithUndo`) | 0.7 ms | 1 ms | 1 ms | 100 ms |
| Keyboard nudge followed by `flushNudge()` | 0.9 ms | 0 ms | 1 ms | 100 ms |
| Keyboard nudge left to the library | **306 ms** | **302 ms** | **302 ms** | over the limit |

The library batches nudges and commits after about 300 ms. The adapter must call `flushNudge()` when the key is released (a finished action). Without it P-7 is missed for the keyboard.

Finding: the library's pointer handling is driven by `mousemove` and `mouseup`, not by pointer events.

## P5: restrictions

**Verdict: met in the adapter.** The library alone does all the forbidden things; the adapter prevents them.

Control: `tests/js/proof/p5-control.test.ts` shows that without restrictions the library creates a shape, reparents a node, deletes the selection and switches to the rectangle tool.

`restrict.ts` wraps the instance methods of the scene graph and the editor's tool switch: `createNode`, `createNodeWithId`, `cloneTree` and `createInstance` are refused, `deleteNode` and `reparentNode` do nothing, `insertChildAt` and `reorderChild` are refused across parents, the Direction band, the logo and every text node are locked, updates and previews of children are clamped inside their parent (position and size), and only the select and hand tools are accepted. Every refusal is logged. A `privileged` call lets the adapter itself reparent for the Shift move (P10).

Evidence:
- `tests/js/proof/p5-restrict.test.ts` (10 tests): create by every route, delete, reparent, locked parts, clamping, tools, shortcut list.
- `tests/e2e/proof/p5-restrict.spec.ts` in three engines: every inert key is pressed with a selection, a plain drag onto another Domain and a drag of the locked Direction band run with the mouse, and a double click on text and on a block; the node set, the parents, the Direction band and the active tool stay as they were. The library's own drop attempt is refused once (`refusals {"reparent":1}`).

Library shortcuts recorded (the keys the library's own application maps, from `TOOL_SHORTCUTS`):

| Key | Library tool | Status |
| --- | --- | --- |
| V | select | kept |
| H | hand | kept |
| F, A | frame | inert |
| Shift+S | section | inert |
| R | rectangle | inert |
| O | ellipse | inert |
| L | line | inert |
| T | text | inert |
| P | pen | inert |
| Shift+P | pencil | inert |
| C | comment | inert |
| Delete, Backspace | delete | inert |
| Ctrl+D, Ctrl+C, Ctrl+X, Ctrl+V | duplicate, copy, cut, paste | inert |
| Ctrl+G, Ctrl+Alt+K | group, create component | inert |

Finding: `@open-pencil/vue` binds none of these keys itself (its window key handlers cover Space, Escape, modifiers and guide deletion). The shortcuts are bound by the library's own application, which we do not ship, so they are inert by construction; the guards above also refuse the commands behind them. Image, AI and collaboration tools have no key and no UI in the SDK we use.

Finding for kickoff requirement 18.4 (keyboard operation): because the SDK binds no keys, keyboard operation of Tekenen (moving, sizing, selecting, undo) is entirely the adapter's work, built on `nudgeSelected`, `flushNudge`, `alignNodes`, `distributeNodes`, `undoAction` and `redoAction`, which the SDK does offer.

Alignment and distribution exist in the SDK (`alignNodes`, `distributeNodes`), so the adapter does not have to compute them (addendum 7.5 is not needed).

Not checked: whether a double click on text opens the library's text edit state (the node set and the tool did not change).

## P6: no outgoing traffic

**Verdict: partly met.** The traffic half is met in three engines. The FR-098 asset check half is not met on the bundle as shipped, which under the rule of the plan is a no-go.

### Traffic

`tests/e2e/proof/p6-network.spec.ts`: a browser context aborts every request outside the page's own origin and records it; the page loads, a block is dragged, the wheel zooms in and out, a double click, undo and redo, zoom to fit, three seconds idle.

| Engine | Requests outside the own server | WebSockets | CSP violations | Own requests |
| --- | --- | --- | --- | --- |
| Chromium | 0 | 0 | 0 | 11 |
| Firefox | 0 | 0 | 0 | 11 |
| WebKit | 0 | 0 | 0 | 11 |

The own requests are the page, one script chunk set, the stylesheet, the CanvasKit WebAssembly and five font files. Evidence: `wp-e0-evidence/p6-network-<engine>.json` (it also records the policy header in force). The policy is the application's with `'wasm-unsafe-eval'` added to `script-src`; the proof page runs with it, so the relaxation T024 names is needed and sufficient. No `unsafe-inline` is needed for styles: the page sets no inline style attribute (the first build did, and the policy refused it, which is how it was found).

### Asset check

`node scripts/check-assets.mjs proof/wp-e0/dist-page` exits 1 with 139 findings, 38 distinct URLs (`wp-e0-evidence/p6-assets.json`, test `tests/js/proof/p6-assets.test.ts` records the finding):

| Category | Distinct URLs | Is it requested at runtime |
| --- | --- | --- |
| Provider endpoints that would be requested if enabled (Google Fonts, Fontsource, Bunny, Fontshare, Typekit, jsDelivr, Iconify, photo APIs) | 13 | no: switches off, and the policy refuses |
| AI provider key pages | 7 | no: strings only |
| XML namespace identifiers | 4 | no: identifiers, not addresses |
| Documentation and example links (issue links, `api.example.com`, a profiler schema link) | 14 | no: strings only |

FR-098 says an outside URL inside the drawing library is a WP-E0 finding for the product owner, not an allowlist entry. It is reported here and not allowlisted. Measured earlier in T019: replacing the `unifont` module at build time removes the web font URLs; a library bundle of the scene graph alone passes the check. Rewriting the other strings in the vendored code so the check passes was deliberately not done: it would change vendor code to satisfy a check without changing behaviour, and it would have to be redone for every library upgrade.

## P7: equal to the HTML view

**Verdict: met, with one deviation of 0.03 px in Chromium.** The criterion needed a text rendering setting that is not the browser default.

`html-view.ts` renders the same blocks as absolutely positioned HTML boxes with one line element per line, using the same line breaker as the drawing. `tests/e2e/proof/p7-equal.spec.ts` renders both, takes screenshots at 100% zoom (device scale 1) and compares per block and per text line, from pixels, not from the data. Evidence: `wp-e0-evidence/p7-<engine>.json` and `p7-canvas-<engine>.png`.

| Measure (largest difference over the 14 blocks and 17 text lines) | Chromium | Firefox | WebKit | Limit |
| --- | --- | --- | --- | --- |
| Box position and size, from the pixels of the fill, drawing against HTML against the fixture | 0 px | 0 px | 0 px | 1 px |
| Wrapped lines: same text, same count, no extra line | equal | equal | equal | equal |
| Left edge of a line's ink | 0 px | 0 px | 0 px | 1 px |
| Right edge of a line's ink | 1 px | 1 px | 1 px | 1 px |
| Vertical ink centre of a line | **1.03 px** | 0.02 px | 0.02 px | 1 px |

Findings:
- **Without `text-rendering: geometricPrecision` the drawing and the browser's text differ by up to 9 px on a 318 px line (2.8%)** in Chromium on Linux: the browser rounds glyph advances to whole pixels, the drawing library does not. With the property set on both the HTML lines and the canvas measuring context (`measure.ts`) the widths agree within 1 px in all three engines. This is a requirement for D1's layout engine and for the HTML view that D3 uses for the PNG: the contract `layout-engine.md` says "canvas 2D `measureText`"; it must also say that the context uses `textRendering = 'geometricPrecision'` and the HTML uses the same CSS property. Recorded here; not yet written into the contract.
- In Chromium, Space Grotesk text sits 1.03 px lower in the drawing than in the HTML (limit 1 px; Inter sits within 0.2 px). It is above the limit by 0.03 px. The cause is the browser's rounding of ascent and descent; a per-font vertical offset of the text node in the adapter would remove it. The test accepts 1.1 px for Chromium only and says so in its name; Firefox and WebKit are held to 1 px.
- Firefox and WebKit give the same differences to many digits. Both draw the canvas through the same Skia code and both lay out the HTML text with unrounded advances, so identical results are plausible, but they were not independently cross-checked.
- Edge not tested: a line whose browser width is within 1 px of the box width could be wrapped once more by the library (its text node has a fixed width). The adapter should give the text node a small width margin, or turn automatic wrapping off.

## P8: undo and redo

**Verdict: met.**

`history.ts` performs the five finished actions the way the SDK expects (preview, then commit): move (`commitMove`), size (`commitResize`), style (`updateNodeWithUndo`), align (`alignNodes`) and distribute (`distributeNodes`).

Evidence:
- `tests/js/proof/p8-history.test.ts` (7 tests): each action is undone once and the state equals the one before, a second undo changes nothing; each is redone once and equals the one after, a second redo changes nothing; five actions in a row step back and forward one action at a time; a multi block move is one step.
- `tests/e2e/proof/p8-history.spec.ts` in three engines: a real mouse drag is undone and redone as one step.

## P9: load time and memory

**Verdict: met**, on a machine that is not an ordinary laptop (see the environment).

`tests/e2e/proof/p9-load.spec.ts`: five cold loads in fresh browser contexts per case, from navigation to "usable" (the scene drawn, two animation frames later, one action committed). Evidence: `wp-e0-evidence/p9-load-<engine>.json`, which also holds the machine description.

| Case | Chromium | Firefox | WebKit | Limit |
| --- | --- | --- | --- | --- |
| Scene of the fixture, median ready | 313 ms | 337 ms | 330 ms | 3000 ms |
| Scene of the fixture, worst wall time | 547 ms | 527 ms | 406 ms | 3000 ms |
| Same with the CPU slowed 4 times (Chromium only) | worst 1020 ms | not available | not available | 3000 ms |
| 500 blocks, median ready | 440 ms | 482 ms | 407 ms | 3000 ms |
| 500 blocks, worst time of a committed action | 1 ms | 0 ms | 1 ms | 1000 ms |
| Page weight | 12.3 MB | 12.3 MB | 12.3 MB | n/a |
| JavaScript heap after load | 16.3 MB (500 blocks: 31.6 MB) | not exposed | not exposed | n/a |

Honest limits: the server is on the loopback address, so there is no network time; a 12 MB page over a real connection needs its own estimate (the CanvasKit file is 7.2 MB, 2.9 MB compressed). The 500 block action time is a command on the scene graph, not a measured drag and render of 500 blocks. The software renderer is probably slower than a laptop GPU, and the 4 times CPU slowdown covers the processor only. The proof page loads Vue and the SDK together; in the application the Vue runtime is already loaded by the shell, so the page is an upper bound for the on-demand bundle.

## P10: Shift move

**Verdict: met in the adapter.** The library has no notion of this move; its own Shift behaviour is an axis lock.

`shift-move.ts`:
- `judge` finds the innermost Domain or Cluster under the pointer for a single selected Activity or Cluster (a Cluster under the pointer counts as its Domain for a Cluster), refuses another kind of Area, a Cluster not under a Domain and an existing name, and treats the current parent, empty space and a Domain as a plain drag. Label `Naar <doel>` (with ` (wordt smaller)` when the block is wider than the target) or `Kan hier niet: <reden>`.
- `landing` pushes the block inside the target and narrows a wider block to the target's width.
- `attachShiftMove` strips the Shift flag from the mouse moves the library sees (so it never applies its axis lock), disables the library's drop target highlight, shows the label at the pointer, and on release over a valid target with Shift held swallows the release, cancels the library's gesture, emits the move intent `{kind: 'move', elementId, targetId, layout}`, previews the block in the target (a privileged reparent) and keeps the original place so `revertMove` puts it back.

Evidence:
- `tests/js/proof/p10-shift.test.ts` (9 tests): target choice, innermost choice, refusal reasons, Cluster as Domain, plain drag cases, name, narrowing text, landing.
- `tests/e2e/proof/p10-shift.spec.ts`, nine runs (three tests in three engines). Control: without the adapter the library locks a Shift-drag to one axis (x stays, y moves). With the adapter a Shift-drag moves on both axes, shows "Naar Supporting activities" over a valid Domain and "Kan hier niet: ander soort gebied" over the Financial Domain, emits exactly one intent on release over the valid Domain (inside the target's width), does not emit one over the invalid Domain, without Shift or with two blocks selected, leaves the library's reparent attempts at zero for the Shift release, restores the scene byte for byte through `revertMove` with nothing left to undo, and keeps a Shift-click without movement as add to selection. Screenshots: `wp-e0-evidence/p10-label-valid-<engine>.png` and `p10-label-invalid-<engine>.png`.

Findings:
- Shift pressed before the mouse button goes down starts a selection in the library, not a drag. The proof decides at release (as FR for the Shift move says), so Shift held at release is what counts.
- `commitMove` (record the move on the undo stack after the server accepted) is not exercised by the proof: it is adapter work in DT and depends on the server request that does not exist yet.

## Findings for the product owner and for the spec

1. **FR-098 and the library's URL strings** (P6): 38 distinct URL strings in the Tekenen bundle, none requested at runtime, none allowed by the asset check as written. The decision is the product owner's.
2. **`@open-pencil/vue` is needed** in the exact pins of DT (P1); the property panel of ADR 0016 is built on it or on the SDK core, but the drag, drop, selection and resize input is only in the Vue package.
3. **Layout engine contract** (P7): the text measuring context and the HTML text need `geometricPrecision`.
4. **Four high audit findings** in the dependency set (P1), reached through an export the application does not call.
5. **A packaging defect of the library** (P1): two worker URLs name a file that is not in the package; DT needs the build repair or a later release.
6. **Fonts are not in the repository** (P2): FR-112 and ADR 0017 need the Inter and Space Grotesk files in the delivered source; the proof took Inter from the library's assets and Space Grotesk from an npm package.
7. **Keyboard operation** (P5): the SDK binds no keys; kickoff 18.4 is the adapter's work.
8. **Three engines in CI** (environment): Firefox needs a virtual display or a software GL setup for WebGL; the container needed system libraries for WebKit and Firefox.

## Recommendation

Under the rule of the task list (go when P1 to P10 are met, where P4, P5, P10 and alignment may be met in the adapter; no-go when P2, P3, P6 or P7 fail; other misses go to the product owner):

- P2, P3 and P7 are met (P7 within 0.03 px of its limit in one engine).
- P4, P5, P8, P9 and P10 are met, P4, P5 and P10 in the adapter.
- P1 falls under "other misses": it is partly met and goes to the product owner (audit, pins of transitive packages, the repair, the `vue` package).
- **P6 fails its second half**: the asset check of FR-098 reports outside URL strings in the bundle. By the rule, a P6 failure is a no-go.

**My recommendation: ask the product owner one question. Does FR-098 apply to URL strings in the vendored code that are never requested, given that the proof shows zero outside requests in three engines and the application's policy (`connect-src 'self'`) would refuse one? If yes, the answer is no-go and Tekenen stops (FR-115). If no, because the policy and a runtime network test guard the real risk, then FR-098 is amended to say so, and my recommendation is go, with the findings above carried into DT as tasks.** I lean to go on that amendment: the runtime evidence is strong and the alternative (rewriting vendor code at build time) adds maintenance risk at every upgrade for no change in behaviour. That is advice; the decision is the product owner's.

## Request for the product owner's decision

Please decide, and record the decision through T030 (`docs/adr/0040-wp-e0-decision.md`):

1. **Go or no-go for Tekenen (DT).** A no-go stops DT only; D1, D2 and D3 do not wait for it (FR-115).
2. **If go: the reading of FR-098** (never-requested URL strings in vendored code: amended or enforced by a build step).
3. **If go: whether the four audit findings in the library's dependency chain are accepted.**

## Evidence index

All files are under `specs/001-omc-studio-v1/wp-e0-evidence/`: `p2-scene-<engine>.png`, `p4-latency-<engine>.json`, `p6-network-<engine>.json`, `p6-observation-<engine>.json`, `p6-assets.json`, `p7-<engine>.json`, `p7-canvas-<engine>.png`, `p9-load-<engine>.json`, `p10-<engine>.json`, `p10-label-valid-<engine>.png`, `p10-label-invalid-<engine>.png`. Tests: `tests/js/proof/` (9 files, 42 tests) and `tests/e2e/proof/` (8 files, 36 runs over three engines). Code: `proof/wp-e0/`.
