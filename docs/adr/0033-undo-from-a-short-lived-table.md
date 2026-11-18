# 0033: Hold undo information in a short-lived table, not as soft deletes

Date: 2026-11-18
Status: accepted (orchestrator, phase 4; phase 4 gate approved by the product owner, 2026-11-18)

## Decision

A deletion that offers "Ongedaan maken" writes one `undo_records` row: a random token, the user, the deleted rows with their identifiers and dependents, and an expiry 10 minutes ahead. Undo posts the token; the server accepts it only from the same user before expiry, re-checks every rule the create path checks, inserts the rows with their original identifiers in one transaction and deletes the record, or refuses with the reason. Expired records are removed whenever a new one is written, and a user's records at sign-out. The 10-second message with its pause and its end on leaving the page belong to the screen.

## Motivation

FR-017 asks an exact restore with the same identifiers and refusal when a rule fails now. A table survives two concurrent requests of one user and holds a large bulk deletion without loading it on every request; clean-up during requests needs no scheduler (ADR 0023). Rejected: soft deletes, which every query and uniqueness check would have to skip, and the session, where concurrent requests overwrite each other's records.
