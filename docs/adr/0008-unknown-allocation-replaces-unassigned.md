# 0008: Hold every unallocated cost in a fixed Unknown allocation

Date: 2026-11-18
Status: proposed; confirmation by the product owner at the phase 2 gate

## Decision

There is no separate "not allocated" item. A Cost record without an Allocation set in a Canvas and Fiscal year counts for 100% in Unknown allocation, a fixed holder of the Canvas that sits outside the eight Areas, is shown like a Domain, is not a Target, and cannot be renamed, moved, archived or deleted. Closing totals (H7) read: sum of Cost records = sum allocated to Targets + sum in Unknown allocation, to the cent. The storage rule of H5 stands (0 lines or exactly 10000 basis points); only the meaning of 0 lines changes. A Fiscal year cannot be Finalised while any amount sits in Unknown allocation.

## Motivation

The product owner holds that every budget must be attributed for the Organisation, so "not allocated" should not exist as a category of its own. Keeping the holder outside the Areas leaves the fixed Areas (H2), the canvas layout and the Area totals untouched, and keeping the empty set as its source avoids writing allocations for every new Cost record in every Canvas. Rejected: a real Domain element inside one of the Areas, which would distort that Area's totals, and auto-created stored allocation sets. Deviates from kickoff H5 (meaning of the empty set), 6.4.3.2, 7.3.5, S7 and acceptance criterion 10.
