# 0008: Hold every unallocated cost in a fixed Unknown allocation Domain in Supporting activities

Date: 2026-11-18
Status: accepted (product owner, phase 2 gate, 2026-11-18); the Finalise rule below remains an assumption

## Decision

There is no separate "not allocated" item. Every Canvas holds one system-managed Domain "Onbekende allocatie" (Unknown allocation) in the Area Supporting activities. A Cost record without an Allocation set in a Canvas and Fiscal year counts for 100% in it. The Domain cannot be renamed, edited, moved, archived or deleted, holds no children, and is not a Target: nobody allocates to it explicitly. Its amount is its Direct amount and counts in the Supporting activities Area Total like any Domain. Closing totals (H7) read: sum of Cost records = sum allocated to Targets + the amount in Unknown allocation, to the cent; the Canvas total is the sum of the Area Totals. The storage rule of H5 stands (0 lines or exactly 10000 basis points); only the meaning of 0 lines changes.

Assumption, not confirmed separately by the product owner: a Fiscal year cannot be Finalised while any amount sits in Unknown allocation.

## Motivation

The product owner holds that every budget must be attributed for the Organisation, and answered at the gate: "ja, tot ik hem indeel mag hij onder support activiteiten vallen". Placing the holder in Supporting activities keeps the Canvas total equal to the sum of its Areas and shows the unallocated amount where the product owner expects it. Keeping the empty set as its source avoids writing allocations for every new Cost record in every Canvas. Rejected: a holder outside the eight Areas (the phase 1 reading), a user-managed Domain anyone could rename or delete, and auto-created stored Allocation sets. Deviates from kickoff H5 (meaning of the empty set), 6.4.3.2, 7.3.5, S7 and acceptance criterion 10.
