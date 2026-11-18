# Contract: files in and out (D2 and D3)

Headers and labels inside files are user-facing; their Dutch text lives in `lang/nl.json` under `exchange.*` (ADR 0031). File names from neutral words, slugs and dates only (FR-088).

## CSV (FR-084)

Semicolon separated; UTF-8, an optional byte order mark accepted on import; decimal comma, no thousands separator on export; double quotes around a field holding a semicolon, quote or line break; a field starting with `=`, `+`, `-`, `@`, tab or carriage return is prefixed with an apostrophe on export.

| File | Header (exact) | Delivery |
|---|---|---|
| Cost records | `code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte` | import D2, export D3 |
| Allocations | `kostenpost_code;jaar;doel_pad;percentage` with `doel_pad` as `Area > Domein > Cluster > Activiteit` by display name | D3 |
| Structure | `area;domein;cluster;activiteit;omschrijving;modaliteit` | D3 |

## Canvas JSON (US27, FR-081)

```json
{
  "format": "omc-studio-canvas",
  "format_version": 1,
  "exported_at": "2026-11-18T16:40:00+01:00",
  "organisation": { "name": "...", "owners": [], "applications": [], "fiscal_years": [2026] },
  "cost_records": [ { "id": "uuid", "code": "LC-001", "...": "...", "yearly_figures": [ { "year": 2026, "budget_cents": 1250000 } ] } ],
  "canvas": {
    "name": "...", "statements": [], "elements": [ { "id": "uuid", "parent_id": null, "area": "primary_activities", "...": "..." } ],
    "layout": null,
    "allocation_sets": [ { "cost_record_id": "uuid", "year": 2026, "lines": [ { "element_id": "uuid", "basis_points": 3333 } ] } ],
    "finalised_years": [ { "year": 2026, "finalised_at": "..." } ]
  }
}
```

Amounts in cents and percentages in basis points, exactly as stored (ADR 0005); no user names or e-mail addresses (pii-inventory): "Definitief door" is not exported. `layout` is null before DT and for a Canvas in Automatic layout; from DT it holds the payload of `layout-payload.md` without the version fields.

## The Unknown allocation Domain in files

Exported as an Element with `"is_unknown_allocation": true` and no children or lines. On import it is mapped onto the system Domain that creating the new Canvas makes (FR-043a); a file with more than one such Element, with children or lines on it, or with a Modality on it is refused in the dry run. "Opslaan als sjabloon" and Template exports leave it out.

## Full JSON (US28, FR-083)

`format: "omc-studio-full"`, `format_version: 1`, a list of Organisations each in the Canvas JSON shape with all its Canvases (archived included), Snapshots with `content` and `checksum` as stored, and Templates other than "Leeg canvas". No Users, second-factor data, Audit log or System notices (SD-12).

## Import order and checks

Dry run and commit run the same validator (FR-085): format and version, size (FR-086), every field rule through the same rule objects as the forms, structure rules (FR-033, FR-035, FR-043a), references, the 100% rule, Finalised years, name and Cost record conflicts; the report names the location in the file (`cost_records[3].yearly_figures[0].budget_cents`). Commit validates again and writes in one transaction.
