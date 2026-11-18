# 0017: Use one fixed, bundled font list for both renderers

Date: 2026-11-18
Status: accepted (product owner, addendum K14 and decision 37, 2026-11-18)

## Decision

Canvas text uses only fonts from a fixed list shipped with the application: Inter and Space Grotesk, the fonts of the design. The HTML view and the drawing library load the same font files from the application's own server. Users cannot add fonts.

## Motivation

Two renderers only draw the same text lines when they measure with the same files (H11), and fonts from outside would break H1 and H15. Rejected: system fonts and any font service. Rejected: the list of addendum A8 (Inter, Arimo, Gelasio), because the design uses Space Grotesk for titles and amounts on the canvas.
