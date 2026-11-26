# 0034: Write the Audit log through a model observer, with explicit entries for sets and Layout

Date: 2026-11-18
Status: accepted (orchestrator, phase 4; phase 4 gate approved by the product owner, 2026-11-18)

## Decision

Models carry an `Audited` trait whose observer writes one entry per created, changed or deleted record with old and new values, labelled with the action set by the service through a request-scoped `AuditContext` (archive, restore, undo, finalise, reopen, redistribution, carry over, import). Secret columns are excluded and recorded as an event name only. Allocation sets and Layout write their entries explicitly: a set is one entry over many line rows, and Layout merges per Element and user within 60 seconds.

Because Eloquent events do not fire for database cascades or query-builder mass writes, services change and delete audited records model by model, children first; audited foreign keys do not cascade, except under the hard deletion of a Canvas or Organisation, whose entries are removed anyway (FR-021). An architecture test fails on a mass update or delete against an audited table.

## Motivation

FR-019 requires an entry for every write; an observer cannot be forgotten by a new write path, which explicit calls can. The two exceptions are the only places where one entry spans several rows. Rejected: explicit calls everywhere (a missed call is a silent gap) and database triggers (no actor, not portable between SQLite and PostgreSQL).
