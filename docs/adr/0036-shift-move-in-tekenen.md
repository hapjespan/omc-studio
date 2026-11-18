# 0036: Let Tekenen move a Cluster or Activity to another Domain with Shift, as its own structure request

Date: 2026-11-18
Status: accepted (product owner, source point 53, 2026-11-18); scope questions QS-1 to QS-7 open in `specs/001-omc-studio-v1/plan.md`. Amends H12, addendum K13 and 6.3, kickoff v1.2 6.5.7, ADR 0014 and ADR 0021.

## Decision

In Tekenen, dragging a Cluster (with its Activities) or an Activity with Shift held and releasing it over another Domain moves it there; without Shift a block stays inside its Domain. The panel field "Verplaatsen naar domein" does the same for keyboard and single-pointer users (SD-42). The move is not part of the Layout save: the adapter turns the drop into one move request, queued with the automatic saves, carrying the structure and Layout versions. The server runs the structure service's move with the rules of Invoeren (FR-037, FR-043a), writes the parent change and the moved Elements' Layout change in one transaction (their Manual layout goes, per addendum 6.2, unless QS-4 is answered otherwise), raises the structure version, audits old and new "Hangt onder", and returns both versions. Allocation lines stay on the moved identifiers. Until QS-1 to QS-7 are answered the server accepts only a Cluster or Activity of an activity Area moving to another Domain of the same Area while no year of the Canvas is Finalised, and refuses the rest. Undo and redo send the reverse or repeated move through the same route. The Layout save keeps refusing any parent that differs from the structure, and the drawing library never reparents a node by itself (WP-E0 P5, P10).

## Motivation

The product owner wants to regroup the picture where they see it, without switching to Invoeren. One move service for both modes keeps H2 to H4 and the Target rule in one place (constitution III), and a separate request keeps the Layout save a pure Layout check, so a malformed save still cannot smuggle a structure change in. Rejected: carrying new parents inside the whole-Layout save (structure and Layout rules in one payload, and the H12 bypass check could no longer tell a move from tampering), and Shift moves in the browser only until the next save (a second, unchecked structure change path).
