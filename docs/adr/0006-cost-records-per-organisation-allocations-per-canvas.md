# 0006: Keep cost records per organisation and allocations per canvas

Date: 2026-11-18
Status: accepted

## Decision

The application holds several Organisations, each with several Canvases. Cost records and their Yearly figures belong to the Organisation; Allocation sets belong to a Canvas. Every Canvas of an Organisation covers all of that Organisation's Cost records, so one Cost record can be allocated differently per Canvas.

## Motivation

Multiple organisations cost little extra and make the application reusable for other clients (K9). Per-canvas allocation lets one budget be viewed through several operating models, and a Canvas being a complete view of the budget keeps its totals comparable with the Organisation's books (grilling Q2). Rejected: cost records per canvas, which would duplicate budgets across canvases.
