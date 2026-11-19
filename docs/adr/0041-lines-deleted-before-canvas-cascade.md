# 0041: Delete Allocation lines before the Canvas cascade

Date: 2026-11-19
Status: accepted (integrator choice, D2.1, technical)

## Decision

`allocation_lines.element_id` stays a no-action key. Canvas deletion removes Allocation lines (then sets, Finalised years and notices) model by model before the Canvas row, as ADR 0034 already prescribes; the database does not have to resolve the order itself.

## Motivation

On PostgreSQL the cascades of `canvases` fire in constraint creation order, and the `elements` key precedes the `allocation_sets` key, so the no-action check on a line runs before the set cascade removes it. SQLite checks per statement and does not show this. Rejected: a deferrable key (the refusal would move to COMMIT, against the immediate transactions of ADR 0032), and reordering a D1 migration (invisibly fragile).
