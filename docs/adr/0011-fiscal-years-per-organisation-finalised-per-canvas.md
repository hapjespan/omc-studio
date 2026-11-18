# 0011: Keep fiscal years per organisation and their finalised state per canvas

Date: 2026-11-18
Status: proposed; confirmation by the product owner at the phase 2 gate

## Decision

A Fiscal year belongs to the Organisation: Yearly figures can only be entered for one of its Fiscal years, and a Fiscal year can only be deleted while it holds no Yearly figures. Whether a Fiscal year is Finalised is held per Canvas. While a year is Finalised in any Canvas, the Organisation's Yearly figures of that year cannot be created, changed or deleted, and that Canvas's Allocation sets of that year cannot change; structure, Modality and Statements stay editable.

## Motivation

Yearly figures are shared by every Canvas of the Organisation (ADR 0006) while finalising is per Canvas (ADR 0007), so a Fiscal year table per Canvas, as kickoff chapter 9 sketches, would leave the delete rule of kickoff 8A ("only without Yearly figures") without a meaning. Freezing the shared Yearly figures is the only way a Finalised Canvas keeps its figures, because a changed Budget would otherwise land in its Unknown allocation. Structure stays editable because the money inputs, not their presentation, are what Finalised protects; a Snapshot is the frozen picture. Rejected: Fiscal years per Canvas, and freezing the whole Canvas structure.
