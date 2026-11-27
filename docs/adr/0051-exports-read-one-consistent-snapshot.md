# 0051 — Exports read one consistent snapshot

Date: 2026-11-27
Status: accepted

## Decision

The full export and the single-canvas export read all their data inside one read-only transaction (`ConsistentRead`): `REPEATABLE READ READ ONLY` on PostgreSQL, a single deferred read transaction on SQLite, whose first read fixes the snapshot in WAL mode. A nested call inside an open transaction joins it. The transaction is always rolled back and never writes, so it does not take part in the serialised IMMEDIATE writes of ADR 0032. JSON encoding and the download response happen after the transaction has ended.

## Motivation

Separate reads let a concurrent edit show half of itself, such as a figure for a fiscal year that the export does not list, or an allocation line naming an element that was replaced after the elements were read. Such a file fails its own import. One snapshot makes a concurrent edit appear complete or not at all, and keeps encoding time out of the transaction so a slow download holds no snapshot open.
