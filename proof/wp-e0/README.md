# WP-E0 technical proof

Throwaway proof code for the technical proof of addendum A1 chapter 8 (P1 to P10).
It answers whether the drawing library can carry Tekenen, before DT is built.

This directory is not delivered code:

- It has its own `package.json` and `package-lock.json`. The root `package.json` is untouched,
  so the delivered application gains no dependency from this proof.
- `.dockerignore` excludes `proof/`, so nothing here reaches the image.
- On a go, the adapter is written fresh in `resources/js/edit-engine/` (ADR 0016, H14). Nothing
  from this directory is promoted as is. On a no-go the directory stays as evidence until phase 7.

## Rules this directory keeps

- Every direct dependency is pinned exactly. `.npmrc` sets `save-exact=true`, so `npm install`
  cannot add a caret, and `ignore-scripts=true`, so no dependency runs an install script.
- `npm ci` is the install command. The lockfile is the pin for the transitive
  `@open-pencil/*` packages, which the library itself declares with a caret.
- No outgoing request at run time. Fonts and the CanvasKit WebAssembly come from the own server
  (H15, FR-111).

## Commands

```
cd proof/wp-e0
npm ci
npm run build
npm run check-pins
```

`npm run build` bundles `main.ts`, which builds a scene graph and an editor, so the build
exercises the SDK. `npm run check-pins` refuses any dependency range that is not an exact
version and any lockfile entry that differs from the pin; `node check-pins.mjs --self-check`
runs its own assertions.

`tests/js/proof/` holds the Vitest tests of this proof and `tests/e2e/proof/` the browser tests;
both import from here and run from the root of the repository.

## Findings

Every finding, verdict and piece of evidence lives in
`specs/001-omc-studio-v1/wp-e0-report.md`. That report is the deliverable, this directory is
only the machinery behind it.
