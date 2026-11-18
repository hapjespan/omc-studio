# 0015: Draw Bekijken as HTML from the stored layout and load the drawing library only in Tekenen

Date: 2026-11-18
Status: accepted (product owner, source decision a, 2026-11-18)

## Decision

Bekijken draws the Canvas as its own HTML view from the stored layout, equal to the drawing in Tekenen within 1 pixel at 100% zoom (H11). Invoeren draws no canvas. The drawing library is loaded only in Tekenen, as a separate bundle. PNG export and printing come from the HTML view.

## Motivation

Overlays, focus, hover breakdowns and the keyboard model are plain HTML; a WASM canvas would make each of them harder and slower to load for every Reader. Keeping the library to one mode limits the dependency (H14) and keeps Bekijken, export and printing working when the technical proof ends in no-go. Rejected: Bekijken as a read-only view of the drawing itself (kickoff v1.2 S6) and PNG or PDF through the drawing library (kickoff v1.2 10.4).
