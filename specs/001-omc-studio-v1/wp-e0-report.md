# WP-E0 technical proof: report

## Purpose

Answer the open points of addendum A1 chapter 8 with evidence from working code,
before any further work on Tekenen (FR-115). The proof code lives in `proof/wp-e0/`,
outside the delivered source, and is excluded from the delivered image.

## Rules for this report

1. The go or no-go decision is the product owner's. This report recommends, it does not decide.
2. Every point is reported honestly, failures and partial results included. A criterion is never bent to fit a result.
3. The customer's name appears nowhere in this report, in the proof code or in the repository.
4. Every verdict carries evidence: a command with its output, a test, a log or a screenshot.
5. Verdicts are `met`, `partly met`, `not met` or `pending`.

## Summary

| Point | What is proven | Verdict |
| --- | --- | --- |
| P1 | Install and build with npm inside Docker, pinned exactly, telemetry, AI, collaboration and cloud off | partly met |
| P2 | Build a scene from JSON: 3 Domains, 1 Cluster, 10 Activities with text, colour and font | pending |
| P3 | Read back position, size and style per node, keeping the Element identifier | pending |
| P4 | Notice changes from the host within 100 ms | pending |
| P5 | Restrict the library: no create, delete or reparent, Areas locked, shortcuts inert | pending |
| P6 | No outgoing traffic while loading and using, under the policy of FR-090 | pending |
| P7 | Equal to the HTML view within 1 pixel, same wrapped lines | pending |
| P8 | Undo and redo per finished action | pending |
| P9 | Load time of the Tekenen bundle within 3 seconds | pending |
| P10 | Shift move recognised, target found, library kept from reparenting | pending |

## P1: install and build

**Verdict: partly met.** The install and the build are clean and reproducible, the versions are
pinned exactly, and no telemetry, collaboration or cloud code is reachable. Two points fall short
of "switched off by configuration": the web font providers are on by default and need an explicit
call to turn them off, and the built bundle carries outside URL strings that the FR-098 asset check
refuses. Both are decided in P6, not here.

### What was installed

Direct dependencies of `proof/wp-e0/package.json`, every one pinned exactly, with
`save-exact=true` and `ignore-scripts=true` in `proof/wp-e0/.npmrc`:

| Package | Version | Why |
| --- | --- | --- |
| `@open-pencil/core` | 0.16.0 | The headless SDK: editor, canvas, text, layout |
| `@open-pencil/scene-graph` | 0.16.0 | The scene model used by P2, P3 and P7 |
| `@open-pencil/canvaskit` | 0.16.0 | The project's own CanvasKit build, Skia with the PDF backend |
| `@fontsource/inter` | 5.3.0 | Inter font files, locally bundled (ADR 0017, K14) |
| `@fontsource/space-grotesk` | 5.3.0 | Space Grotesk font files, locally bundled (ADR 0017, K14) |
| `vite` | 8.3.8 | The project's bundler, same major as the root (R-1) |

Deliberately not installed: `@open-pencil/cloud`, `@open-pencil/mcp`, `@open-pencil/cli`,
`@open-pencil/harness` (cloud, model context and tooling), and `@open-pencil/vue`. The addendum
names `@open-pencil/vue` in its package list, but the adapter contract and ADR 0016 build the
property panel ourselves on the headless SDK, and `@open-pencil/vue` adds a drag-and-drop stack,
`fuse.js` and `nanostores` that only serve the library's own user interface. The fonts are not in
the repository yet, so the `@fontsource` packages supply the files; they ship the woff2 files in
the package and fetch nothing.

The repository holds no font files and no font manifest for Inter or Space Grotesk yet. DT needs
them in the delivered source as well, served from our own server (FR-112).

Transitive `@open-pencil/*` packages pulled in by core: `design-jsx`, `dom-css`, `emit`, `fig`,
`kiwi`, `pen`, all at 0.16.0. Core declares them with a caret. The exact pin for those is the
committed `package-lock.json` plus `npm ci`; a bare `npm install` on a fresh checkout would be
free to take a later 0.16.x. An `overrides` block could pin them in `package.json` as well; it was
not added, because `npm ci` is what the build and CI use.

### Clean build in Docker

Run inside this development container (the Docker context of this project), from an empty
`node_modules`, with no manual step before, during or after:

```
rm -rf proof/wp-e0/node_modules proof/wp-e0/dist
cd proof/wp-e0 && npm ci && npm run build
```

| Step | Exit code | Duration |
| --- | --- | --- |
| `npm ci` | 0 | 15 s, 144 packages added, 145 audited |
| `npm run build` | 0 | 2 s, 1143 modules transformed |

Environment: Node v24.22.3, npm 11.19.1, Linux 7.0.14-linuxkit, container `omc-studio-dev`.

Tail of the build log:

```
vite v8.3.8 building client environment for production...
transforming...
[plugin rolldown:vite-resolve] Module "fs" has been externalized for browser compatibility,
  imported by ".../node_modules/canvaskit-wasm/bin/canvaskit.js".
[plugin rolldown:vite-resolve] Module "path" has been externalized for browser compatibility,
  imported by ".../node_modules/canvaskit-wasm/bin/canvaskit.js".
(two more lines of the same kind for bin/full/canvaskit.js)
✓ 1143 modules transformed.
rendering chunks...
computing gzip size...
dist/proof.js  2,592.63 kB │ gzip: 696.45 kB
✓ built in 1.51s
```

The four externalisation lines are warnings, not errors: `canvaskit-wasm` carries a Node loader
beside its browser loader. The build exits 0.

The entry `proof/wp-e0/main.ts` imports `createEditor` from `@open-pencil/core/editor` and
`SceneGraph` from `@open-pencil/scene-graph` and builds both, so the build exercises the SDK
rather than an empty file.

### Versions exact

`npm run check-pins` in `proof/wp-e0` refuses any range that is not an exact version, refuses a
lockfile entry that differs from the pin, and prints the resolved tree:

```
check-pins passed, @open-pencil in the tree: @open-pencil/canvaskit@0.16.0, @open-pencil/core@0.16.0,
@open-pencil/design-jsx@0.16.0, @open-pencil/dom-css@0.16.0, @open-pencil/emit@0.16.0,
@open-pencil/fig@0.16.0, @open-pencil/kiwi@0.16.0, @open-pencil/pen@0.16.0,
@open-pencil/scene-graph@0.16.0
```

`tests/js/proof/p1-pins.test.ts` runs the same check in the project's Vitest suite and also asserts
that this report holds a P1 verdict.

### Supply chain

- Tree size: 145 packages (144 installed plus the proof package itself), 213 MB on disk, of which
  `@open-pencil/core` is 11.8 MB unpacked and `@open-pencil/canvaskit` 9.8 MB.
- Install scripts: `ignore-scripts=true` is set, so none run. Had they run, these would have:
  `core-js` (`postinstall`), and the `prepare` scripts of `@lezer/common`, `@lezer/highlight`,
  `@lezer/javascript`, `@lezer/lr`, `acorn`, `culori`, `dedent`, `dompurify`, `fontoxpath`,
  `lightningcss`, `jsep`, `prsc`, `specificity`, `tinyexec`, `whynot` and `xspattern`. A `prepare`
  script does not run for a published package, so `core-js` is the only real one; it is wrapped in
  a `try`/`catch` that swallows failures.
- `npm audit`: 3 high severity findings, all the same chain:
  `image-size` (0.6.3 to 2.0.2, denial of service through infinite loops in the JXL, HEIF and ICNS
  parsers) is pulled in by `pptxgenjs`, which is pulled in by `@open-pencil/core`. The only fix npm
  offers is `@open-pencil/core@0.13.2`, a downgrade across two breaking releases. The vulnerable
  parser is only reached by a PowerPoint export, which this application never calls. Recorded as an
  accepted finding of the proof; it blocks nothing here, but it does fail a
  `npm audit --audit-level=high` run over `proof/wp-e0`, so CI must not audit the proof directory
  as if it were delivered code.

### Telemetry, AI, collaboration and cloud

Investigated by reading the installed `dist` of every `@open-pencil` package, not the README.

| Concern | Finding | Switch |
| --- | --- | --- |
| Telemetry | `core/dist/canvas/renderer/tiles/telemetry.js` only calls `emitNavigationTrace`, which writes a `performance.mark` and calls listeners from an in-process set that starts empty. Nothing leaves the browser. | None needed, off by construction. No switch exists and none is required. |
| Analytics, crash reporting | No analytics, Sentry, PostHog, Mixpanel or Amplitude client. The word "sentry" appears only as a field name in the Figma file format schema in `kiwi` and `fig`. | Not present. |
| Collaboration | No Yjs, no WebRTC, no WebSocket, no Trystero, no PeerJS, and no `new WebSocket(` anywhere in the tree. "Multiplayer" appears only as a Figma file format field name in `kiwi`. | Not present, off by construction. |
| Cloud | `@open-pencil/cloud` is not installed and nothing in the installed packages imports it. | Off by construction. |
| AI | `core/dist/tools/ai-adapter.js` holds no network call; the bundled `ai` and `@ai-sdk/provider` packages under `core/dist/node_modules/` are referenced only by the type declaration `tools/ai-adapter.d.ts`, never by shipped JavaScript. The host would have to supply a model itself. `core/dist/constants.js` does hold the key pages of seven model providers as strings. | No switch exists, and none is needed at runtime: no request is made. The strings do reach the bundle, see the next section. |
| Icons | `EditorOptions.icons` defaults to the Iconify API (`https://api.iconify.design`). | A real switch: pass an own `IconProvider` to `createEditor`. The adapter must always pass one (FR-110, H15). |
| Stock photos | `core/dist/tools/stock-photo/providers.js` calls `api.unsplash.com` and `api.pexels.com` through `ofetch`. Only reached through the stock photo tool, which the adapter never offers. | Off by construction as long as the tool is not called. No configuration switch found. |
| Web fonts | `core/dist/text/web-fonts.js` resolves fonts through `unifont` from Google Fonts, Fontsource, Bunny and Fontshare. `DEFAULT_WEB_FONT_PROVIDER_SETTINGS` is `google: true, fontsource: true, bunny: false, fontshare: false`, so two providers are **on by default**. | Two switches: `FontManager.setOnlineFontProviders({ google: false, fontsource: false, bunny: false, fontshare: false })` and `WebFontResolver.setRemoteFetch(null)`. In a browser the resolver also refuses remote work without a fetcher (`IS_BROWSER && !this.remoteFetch` returns null), which is a second line of defence, not the first. Bundled fonts go through `FontManager.setBundledFontLocator`, which maps a file name to a URL on our own server. |

Outgoing network surface of the whole installed tree, by file: `core/dist/icons/api.js`,
`core/dist/tools/stock-photo/providers.js` and `apply.js`, `core/dist/text/web-fonts.js`,
`core/dist/text/fonts.js` (the bundled font fetch, our own server) and the CanvasKit loader in
`@open-pencil/canvaskit`. There is no other `fetch(`, `XMLHttpRequest` or `sendBeacon` call.

### Outside URL strings in the built bundle (FR-098)

Running the project's own asset check over the proof bundle shows the strings that reach the
output:

```
node scripts/check-assets.mjs proof/wp-e0/dist
```

It reports 25 outside URLs: seven model provider key pages from `core/dist/constants.js`, the web
font provider endpoints of `unifont` (Google Fonts, Fontsource, Bunny, Fontshare, Typekit,
jsDelivr), `https://api.iconify.design`, two `opentype.js` issue links in error messages and two
`w3.org` XML namespace identifiers.

Measured, not assumed:

- A bundle of `@open-pencil/scene-graph` alone is 142 kB and passes the asset check with no finding.
- Replacing `unifont` with a module of the same shape at build time removes every web font provider
  URL and shrinks the bundle from 2592 kB to 2319 kB. What remains is the seven model provider key
  pages, the Iconify endpoint, the two issue links and the two namespace identifiers.

So FR-098 on the Tekenen bundle is reachable, but not by configuration alone: it needs build-time
replacements plus a decision on strings that are never requested. That decision belongs to P6
(T024) and the product owner, and is deliberately not taken here.

### What this means for the rest of WP-E0

1. The adapter must always pass its own `IconProvider` and must call `setOnlineFontProviders` with
   every provider false before the first text node is laid out.
2. The fonts of ADR 0017 are not in the repository. P2 and P7 use the `@fontsource` files from
   `proof/wp-e0/node_modules`; DT must bring them into the delivered source.
3. Useful entry points found while reading: `createEditor` and `EditorOptions` in
   `@open-pencil/core/editor`, `TOOL_SHORTCUTS` and `EDITOR_TOOLS` for the shortcut list of P5,
   `getNodeEditCapability` and `assertNodeEditable` for the restrictions of P5, `editor/graph-events`
   for P4 and `editor/undo` for P8.
