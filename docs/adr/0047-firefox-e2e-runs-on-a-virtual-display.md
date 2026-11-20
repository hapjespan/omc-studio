# 0047: Firefox end-to-end runs on a virtual display

Date: 2026-11-20
Status: accepted

## Decision

The Playwright configuration starts a virtual X display (Xvfb on `:99`) when no `DISPLAY` is set, Xvfb is installed and the platform is Linux, and exports it as `DISPLAY`. The Firefox project already runs headed when a display exists, so it now does so on every Linux machine that has Xvfb. Chromium and WebKit are unaffected.

## Motivation

The drawing surface of Tekenen is a CanvasKit canvas that needs a WebGL context, and the drawing library has no software fallback. Headless Firefox in the development container cannot create a WebGL context (`FEATURE_FAILURE_WEBGL_EXHAUSTED_DRIVERS`), so the surface stayed blank. Four specs failed on Firefox for that reason alone: `drawing-equal` (both variants), `drawing-move-resize` (dragging a block), `drawing-restrictions` (double-click hint) and `drawing-selection` (click on the surface). On a display, Mesa provides the context and the four pass unchanged. A real Firefox always has a display, so a headed run is closer to the browser the product targets than a headless one, and no test tolerance changes.

## Consequences

`tests/e2e/support/display.ts` holds the helper and `playwright.config.ts` calls it first. A second run reuses a display that is already there (the socket check). A machine without Xvfb behaves as before.
