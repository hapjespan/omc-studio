# Grilling output, phase 1

Date: 2026-11-18. Input for phase 2. Base: `specs/kickoff.md` v1.1 (confirmed at kickoff) and `specs/PROFILE.md`.
Greenfield project: `/speckit-converge` was skipped.

## Round 1

| Nr | Question | Answer | Status |
|---|---|---|---|
| Q1 | Fiscal year finalisation per canvas or per organisation | Per canvas, because allocations are per canvas. Deleting a cost record or its yearly figures is blocked as soon as at least one canvas of the organisation has that year finalised. | Decided, ADR 0007 |
| Q2 | Every canvas sees all cost records of its organisation | Yes: a canvas is a complete view on the organisation's whole budget. Product owner, verbatim: "ja, maar beter in een domein onbekende allocatie; dit [niet toegewezen] zou niet mogen bestaan, het moet voor de organisatie toegekend worden". | Decided; reading is an assumption, see below |
| Q3 | Supplier linked to an Actor item | Dropped in version 1. Supplier is free text. | Decided, ADR 0009, deviation from kickoff 7.1 and 9 |
| Q4 | Users and roles global or per organisation | Global. One role per user, valid for every organisation. | Decided |
| Q5 | Second factor | Optional TOTP per account in delivery 1, with recovery codes. An Administrator can reset another user's second factor. | Decided, ADR 0010 |
| Q6 | What a template is | A canvas with status "template" holding structure and direction block, without allocations. Starting from a template copies structure and direction block. An Administrator can save an existing canvas as a template. Only "Leeg canvas" ships. | Decided |
| Q7 | Copying a canvas | Same organisation: structure, direction block, theme, fiscal years and allocations are copied; finalised status is not. Other organisation: structure, direction block and theme only. Snapshots are never copied. | Decided |
| Q8 | Retention of audit log and user data | The audit log lives as long as its canvas or organisation and goes with a hard delete of it. When a user is deleted, their audit entries stay, pseudonymised as "verwijderde gebruiker". Lawful basis: legitimate interest (accountability for financial changes). Users are the only personal data; owner and supplier are organisation names (kickoff 18.2). | Decided |
| Q9 | Incident notification | Not decided. The product owner makes separate arrangements on who reports what, how and within which window. No contact or window is to be invented. Technical side agreed: a closing-totals (H7) failure is logged without personal data and is visible in S12. | Open, owner: product owner |
| Q10 | Known deviations at the first client | None at this moment. Kickoff and assumptions A1 to A6 stand. | Decided |

## Assumptions to show at the phase 2 gate

These are the orchestrator's reading of the Q2 answer, not yet confirmed verbatim by the product owner.

1. **No separate "niet toegewezen" item exists.** A cost record without its own allocation set in a canvas falls for 100% into a fixed domain "Onbekende allocatie" (Unknown allocation), so the whole budget is always attributed. Recorded in ADR 0008 as a deviation from kickoff H5 (meaning of the empty set) and 7.3.5.
2. **A fiscal year cannot be finalised while any amount sits in Unknown allocation.** Equivalent in effect to kickoff 7.2.7, restated in the new term.
3. **Placement of Unknown allocation** (worked out by the orchestrator on request): it belongs to the canvas, outside the eight fixed areas, so H2, the canvas layout of 6.5 and the area totals stay unchanged. It is not a target: nobody allocates to it explicitly, and it cannot be renamed, moved, archived or deleted. It is shown as its own domain card in the financial overlay and as its own line in the side panel.
4. **H7 restated:** sum of cost records = sum allocated to targets + sum in Unknown allocation, to the cent. The storage rule of H5 is unchanged: per cost record per year, 0 lines or exactly 10000 basis points. Only the meaning of 0 lines changes, from "not allocated" to "in Unknown allocation".
5. **Follow-on wording changes:** kickoff 6.4.3.2 ("terugzetten naar niet toegewezen") becomes "back to Unknown allocation"; the S7 filter "status toewijzing" and acceptance criterion 10 use the new term.

## Open items carried into phase 2 and 3

- Q9 incident arrangement, owner product owner.
- The list of forbidden names for the H8 test (kickoff 16.7), delivered by the product owner as a local file outside version control.
