# 0045: PNG export of the Canvas with html-to-image, loaded on demand

Date: 2026-11-19
Status: accepted (integrator choice, phase 6, after the opus review of D3.5)

## Decision

"Exporteren als PNG" renders the Artboard element in the browser with html-to-image 1.11.13, pinned exactly, loaded through a dynamic import so it is not part of the application entry bundle. The image is made at scale 2 from the same-origin fonts, saved through a blob link, and named `canvas-<date>.png` without the Canvas name. Printing is the browser's own print dialog with an A3 landscape named page that applies to the Bekijken page only.

## Motivation

The export must show exactly what Bekijken shows, including the fonts, without a request outside the own origin (ADR 0027). A server-side render would need a headless browser in the delivered image. html-to-image is MIT licensed, has no dependencies, is the maintained successor of dom-to-image, needs no more than the `data:` and `blob:` image sources already allowed by ADR 0039, and `npm audit --omit=dev` reports nothing. html2canvas and dom-to-image are no longer maintained. The file name leaves out the Canvas name because that name can carry a client or product name.
