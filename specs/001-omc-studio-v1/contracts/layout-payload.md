# Contract: Layout save payload (DT)

`PUT /canvases/{canvas}/layout`, JSON body. ADR 0021, FR-102 to FR-105.

```json
{
  "structure_version": 41,
  "layout_version": 17,
  "format_version": 1,
  "areas": {
    "primary_activities": { "x": 560, "y": 400, "width": 1100, "height": 380, "z": 0, "style": {} }
  },
  "elements": {
    "0193c2e4-...": { "mode": "manual", "x": 16, "y": 48, "width": 286, "height": 204, "z": 2,
      "style": { "fill": "#F6E6CC", "fill_opacity": 100, "border_colour": "#171B21", "border_width": 1,
        "border_style": "solid", "corner_radius": 8, "font": "space_grotesk", "font_size": 14,
        "font_weight": "bold", "italic": false, "text_colour": "#171B21",
        "align_horizontal": "left", "align_vertical": "top" } },
    "0193c2e5-...": { "mode": "automatic" }
  }
}
```

- `areas` holds every Area other than Direction that has a Manual layout; `elements` holds every active Element of the Canvas, each `manual` with its values or `automatic` (no row stored). A missing or extra Element with an equal structure version is refused (H12 bypassed, US37.4).
- Style keys and ranges: exactly FR-104 with SD-37 (still a proposal). Unknown keys or values are refused.
- Server checks, in this order, inside one serialised transaction (ADR 0032): structure version (409 `structure_changed`), Layout version (409 `layout_changed`), membership (every Element exists, is active, belongs to the Canvas, none missing or extra), parent containment (x ≥ 0, y ≥ 0, x + width ≤ parent width, the parent's width being its stored width or, without a row, its automatic width from `AutomaticWidths`), Area bounds (x ≥ 0, y ≥ 180, x + width ≤ 1680, y + height ≤ 1188), minimum sizes (width ≥ 40, height ≥ 24, width ≥ largest child x + width), style (FR-104), text contrast (FR-105). Any failure: 422 with the rule and the Element, nothing stored.
- Refusals are validation errors on a `rule` key and version conflicts are a `conflict` key, so the Tekenen page handles them without leaving the page.
- Success: 200 with the new `layout_version`, and `layout_saved_at` set; one Audit log entry per changed Area or Element, merged per Element and user within 60 seconds (FR-113, SD-28).
- `format_version` is upgraded by small functions when read (FR-102); version 1 is the first.
