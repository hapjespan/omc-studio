# 0013: Start every canvas in automatic layout and let Tekenen override it per element

Date: 2026-11-18
Status: accepted (product owner, kickoff v1.2 K1 and addendum K1, 2026-11-18)

## Decision

Every Canvas starts in automatic layout, computed from its structure, and can always return to it. From the Tekenen delivery on, an Administrator may move, resize and colour Elements in Tekenen; an Element changed there keeps its manual layout until it is set back to automatic. Domains no longer have a width in columns: in automatic layout all Domain cards of an Area have the same width. Supersedes ADR 0001.

## Motivation

The product owner wants to arrange the picture by hand, and a new Canvas must still work at once without dragging everything first. Automatic layout as the starting position gives both, and stays the fallback if the technical proof (WP-E0) ends in no-go. Rejected: a fixed automatic layout only (ADR 0001) and a free drawing without an automatic start. The column width disappears because neither kickoff v1.2 nor the design has it and size is set in Tekenen.
