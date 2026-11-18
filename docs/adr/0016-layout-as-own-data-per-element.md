# 0016: Store layout as our own data per Element, behind one adapter around a pinned drawing library

Date: 2026-11-18
Status: accepted (product owner, addendum H13, H14, K11, K12, 2026-11-18)

## Decision

The database is the only source. Each Element's and Area's layout (position, size, order in front or behind, and its Element style with the properties of addendum chapter 5) is stored as our own validated data per Element; the server refuses unknown keys. No drawing-library document is stored as truth: Tekenen builds its scene from model plus layout when it opens and reads it back into our data when it saves. OpenPencil is reached only through one adapter in `resources/js/edit-engine/`; no file outside it imports `@open-pencil/*`, versions are pinned exactly, and the property panel is our own, built on the headless SDK, showing only properties our layout data can hold. Telemetry, AI, collaboration, cloud and outside icons are not built in or are switched off (H15).

## Motivation

A second file format would be a second truth that could disagree with the structure, the Audit log and the server's checks. Our own data per Element lets the server enforce H12 and keeps Snapshots, export and copy simple. The library is young with breaking releases; one adapter and exact pins make an upgrade a deliberate step with its own tests. Rejected: a drawing document per Canvas (`layout_doc`, kickoff v1.2 chapter 9) and using the library's own panels.
