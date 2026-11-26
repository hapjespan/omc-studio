# 0007: Finalise fiscal years per canvas

Date: 2026-11-18
Status: accepted

## Decision

A Fiscal year is Finalised and Reopened per Canvas. Deleting a Cost record or its Yearly figures for a year is blocked as soon as at least one Canvas of the Organisation has that year Finalised.

## Motivation

Allocations are per Canvas (ADR 0006), so finalising them is too; but Yearly figures are shared by all Canvases of the Organisation, so a finalised year anywhere protects them. Rejected: finalising per Organisation, which would freeze Canvases whose allocation is still in progress. Settled in grilling Q1.
