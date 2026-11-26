# 0005: Store percentages as whole basis points and amounts as whole cents

Date: 2026-11-18
Status: accepted

## Decision

Percentages are whole basis points (10000 = 100%) and amounts are whole cents, everywhere: storage, calculation, transport. Rounding remainders when splitting an amount are distributed with the largest remainder method.

## Motivation

Totals must close to the cent (H7) and an Allocation set must be exactly 100% (H5); decimal or floating representations make both rules depend on rounding. Rejected: decimal percentages and decimal amounts. Settled by kickoff choice K8.
