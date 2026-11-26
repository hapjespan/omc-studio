# 0028: Calculate every figure in one pure PHP module on the server

Date: 2026-11-18
Status: accepted (orchestrator, phase 4; phase 4 gate approved by the product owner, 2026-11-18)

## Decision

All figures (allocation amounts, roll up, Unknown allocation, Shares, breakdowns, FTE) and the closing check come from one module in `app/Calculation/` that reads no database and performs no I/O. Controllers build its input and pass its output to the pages; Finalise, Snapshots and exports use the same output. The browser never calculates figures; it only mirrors largest-remainder splitting for the line amounts of an unsaved set in Toewijzen, tested against the same fixture as the PHP code.

## Motivation

The server must refuse Finalise and Snapshots on a failed closing check and record a System notice (FR-068, FR-071, FR-079), so the one authoritative calculation has to run where those decisions are made. A pure module is the seam for the unit and property tests of FR-093 and FR-095. Rejected: calculating in the browser (the server would need a second implementation to guard Finalise) and SQL views (largest remainder with ties in set order is engine-specific, against ADR 0022).
