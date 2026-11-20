# 0047: A newer Snapshot schema counts as damaged, and the full export keeps the stored bytes

Date: 2026-11-20
Status: accepted (integrator choice, phase 6, after the review of D3.8)

## Decision

A Snapshot is immutable and checksummed over its stored bytes (FR-078). When a Snapshot is read, the checksum is verified on those bytes first, and only then is the content upgraded in memory, one version step at a time, to the schema this code knows. A Snapshot whose `schema_version` is newer than the code knows, or whose content does not have the shape of its version, is treated as damaged: the page shows the damaged notice and a system notice is recorded, nothing is thrown at the user, and nothing is rewritten. The full export writes the stored bytes of a Snapshot (decoded as they are, never the upgraded content) next to its own `schema_version` and `checksum`, and the full import accepts every version the code can upgrade and stores the bytes and checksum unchanged.

## Motivation

The checksum proves that nobody changed a Snapshot, so rewriting or re-encoding its content after an upgrade would break that proof and make old Snapshots look damaged. Exporting the upgraded content next to the old checksum would fail the verification on import and lose Snapshots in a wipe and restore. A newer schema cannot be drawn faithfully by older code, and a wrong picture of a closed year is worse than a visible notice, so it is refused as damaged until the code that knows it is installed.
