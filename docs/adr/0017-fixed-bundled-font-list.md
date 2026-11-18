# 0017: Use one fixed, bundled font list for both renderers

Date: 2026-11-18
Status: accepted (product owner, addendum K14); which fonts are on the list is open (`[GATE-C37]`)

## Decision

Canvas text uses only fonts from a fixed list shipped with the application. The HTML view and the drawing library load the same font files from the application's own server. Users cannot add fonts.

## Motivation

Two renderers only draw the same text lines when they measure with the same files (H11), and fonts from outside would break H1 and H15. Rejected: system fonts and any font service. The list itself is open: addendum A8 names Inter, Arimo and Gelasio, the design uses Inter and Space Grotesk.
