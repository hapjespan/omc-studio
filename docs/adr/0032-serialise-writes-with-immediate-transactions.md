# 0032: Serialise guarded writes with IMMEDIATE SQLite transactions and row locks

Date: 2026-11-18
Status: accepted (orchestrator, phase 4; phase 4 gate approved by the product owner, 2026-11-18)

## Decision

The SQLite connection runs in WAL mode with a busy timeout of 15 seconds and `transaction_mode` IMMEDIATE, so every write transaction holds the write lock from its start. Every guarded write also locks its aggregate row with `lockForUpdate()`, which does nothing on SQLite and locks on PostgreSQL in CI, in the fixed order Organisation, Canvas, User. Lock set per write path: the Organisation for Yearly figures, Cost records, lists and Fiscal years; the Canvas for sets, structure, Statements, Layout and Redistribution; Organisation then Canvas for Finalise, Reopen, Carry over and imports; every Administrator row by id for a role change or deletion. Imports check field rules before the write transaction so it stays short, and throttling uses the file cache so sign-in does not wait on the database lock. A test runs each guard with two processes on a file database and with two connections on PostgreSQL.

## Motivation

The spec requires that two concurrent requests on the last Administrator or on a set and a Finalise end with one refusal (FR-010, edge cases). SQLite's default deferred transactions let both read the same state and fail late. At two writing users (L-1) a short wait costs nothing. Rejected: version columns on every guarded row (more code for the same effect at this load) and cache mutexes (a second locking mechanism).
