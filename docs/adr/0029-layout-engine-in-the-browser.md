# 0029: Compute the canvas layout and its text lines in one TypeScript engine in the browser

Date: 2026-11-18
Status: proposed (orchestrator, phase 4); confirmation at the phase 4 gate

## Decision

One pure TypeScript module in `resources/js/layout/` computes the Automatic layout, applies stored Manual layout (from DT), breaks every name into lines with the bundled fonts, grows blocks and derives the layout Warnings. It measures text through a `TextMeasurer` port (canvas 2D in the browser, a fixed-width fake in tests). The HTML view and the drawing adapter draw the lines it returns and never wrap text themselves. An Element without a Manual layout is placed by the engine at draw time, so Invoeren writes no Layout. The server never measures text; its Layout checks use stored values, a stored height being a minimum (ADR 0020), and, for a parent without a Manual layout, its automatic width, which never depends on text and is computed by one small function present in PHP and TypeScript and tested against one fixture.

## Motivation

FR-107 computes the layout from structure, order and the font files, and H11 asks the same text lines in two renderers with different text engines (browser and Skia). Breaking lines once and handing both renderers the result makes that reachable; measuring needs the fonts, which live in the browser. Rejected: layout on the server (no font-shaping stack in PHP that matches the browser) and letting each renderer wrap its own text (different line breaks). Accepted: two browsers may break a line differently; H11 holds within one browser.
