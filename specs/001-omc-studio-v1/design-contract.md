# Design contract: OMC Studio version 1

Date: 2026-11-18. Source: the product owner's design `specs/design/omc-studio-design.html` (answer A to `[GATE-D1]`), five boards 1440 pixels wide, captured offline as `specs/design/board-*.png`. Values were measured from the rendered boards (computed styles), not guessed. The design is a visual reference: its React code is not taken over (ADR 0012), and the file stays in `specs/design/` only (FR-090, FR-098).

Where this contract and the spec differ, the spec wins. The product owner's decisions on the differences (`source-conflicts.md`, points 32 to 52) are applied below; where they change the design, the contract says so. The navigation mapping is ADR 0019.

## Boards

| Board | Screenshot | Shows |
|---|---|---|
| 1 Invoeren | `board-1-invoeren.png` | Mode switch, Area list with counts, tree of one Area with arrows, detail form, Warnings |
| 1b Tekenen | `board-1b-tekenen.png` | Layers panel, drawing surface on an 8-pixel dot grid, property panel, bottom toolbar |
| 2 Bekijken met financiële overlay | `board-2-bekijken-met-financiele-overlay.png` | Overlay and filter bar, legend line, Direction band, Area totals, Domain cards with bar and breakdown, side panel with totals |
| 3 Toewijzen | `board-3-toewijzen.png` | Cost record list with status, set with "Nog te verdelen", Target tree with amounts |
| 4 Kostenposten | `board-4-kostenposten.png` | Summary cards, finalise card, filter bar, table, side-panel form |

## Colours

Roles, with the contrast measured against their usual background (WCAG 2.2: 4.5:1 text, 3:1 UI edges and graphics).

| Role | Value | Contrast |
|---|---|---|
| Ink: primary text, primary button, Direction band, active states | `#171B21` | 17.3:1 on white |
| Secondary text | `#454C57` | 8.7:1 on white |
| Muted text, hints, type labels | `#5F6772` | 5.7:1 on white, 5.1:1 on page |
| Page background | `#F2F3F1` | |
| Panel and canvas surface | `#F6F7F5` | |
| Surface (cards, panels, inputs) | `#FFFFFF` | |
| Hairline borders of cards and panels (decorative) | `#DCDFE2` | 1.3:1 on white, not a control edge |
| Borders of secondary buttons | `#848B95` (design: `#DCDFE2`) | 3.4:1 on white, 3.1:1 on page, decision 45 |
| Input, select and textarea borders | `#848B95` (design: `#AEB4BC`) | 3.4:1 on white, decision 45 |
| Dashed borders (add lines, drop zones) | `#8A919B` | 3.2:1 on white |
| Success text and check ("Alles opgeslagen", 100%) | `#1F7A4D` | 5.3:1 on white |
| Success callout background | `#E7F3EC` | |
| Warning text (Concept, empty Cluster) | `#8A5200` | 5.8:1 on `#FFF3D6` |
| Warning callout background | `#FFF3D6` | |
| Danger text and outline (Verwijderen) | `#A12A1E` on white, outline `#C0645A` (design: `#D9B4AF`) | text 7.3:1; outline 4.0:1, decision 45 |
| Focus and selection accent | `#2459D6` | 6.0:1 on white; selected layer row `#E3EBFB` |
| Forecast above budget (bar part) | `#9A7811` (design: `#C9A227`) | 4.1:1 on white, 3.1:1 on the track, decision 45 |
| Bar track | `#DCDFE2` | |
| Modality Common, Adaptive, Specialized, Distinct | `#1F7A4D`, `#1E5FBF`, `#B4540A`, `#6B3FB5` | white letter on each: 5.3, 6.1, 5.0, 7.0 :1 |

Default canvas colours (ADR 0018), shown where an Element has no style of its own:

| Area | Domain fill | Cluster block | Activity |
|---|---|---|---|
| Direction band | `#171B21`, white text, labels `#C9CED6` (10.9:1) | | |
| Richten, Beheersen | `#ECE8F5`, ink text 14.3:1 | ink at 6% over the Domain fill | white with hairline border, ink text 17.3:1 |
| Primaire activiteiten | `#F6E6CC`, ink text 14.1:1 | ink at 6% over the Domain fill | white with hairline border, ink text 17.3:1 |
| Ondersteunende activiteiten | `#D9E9F2`, ink text 13.9:1 | ink at 6% over the Domain fill | white with hairline border, ink text 17.3:1 |
| Actoren, Kanalen, Waardeproposities | no fill: Group in bold, Items as plain lines | | |
| Area container | white, hairline border, radius 10 | | |

Financial overlay: Domain cards are tinted `#0E4D64` at an opacity rising with Share from 3% to 28%; ink text keeps at least 10.5:1 on the darkest step. The legend "Aandeel van totaal laag naar hoog" shows the scale.

## Typography

- Body: Inter. Headings, amounts and canvas titles: Space Grotesk. Both are bundled with the application, never loaded from outside (H1), and both are the font list for canvas text (ADR 0017).
- Weights 400, 500, 600 and 700.

| Use | Face | Size / line height | Weight |
|---|---|---|---|
| Body text, form values, table cells | Inter | 13 / 18.2 | 400 |
| Small text, secondary lines | Inter | 12.5 / 17.5, 12 / 16.8 | 400 |
| Hints, meta lines | Inter | 11.5 / 16.1 | 400 |
| Type labels in the tree ("Domein", "Activiteit") | Inter | 10.5 / 14.7 | 400 |
| Field labels, button text | Inter | 13 | 500 to 600 |
| Small caps labels ("MISSIE", section heads) | Inter | 11, letter spacing 0.04 to 0.06 em, uppercase | 600 |
| Modality letter | Inter | 11 / 11 | 700 |
| Area header on the canvas | Space Grotesk | 13, letter spacing 0.04 em, uppercase | 600 |
| Domain and card titles | Space Grotesk | 14 / 19.6 | 600 |
| Page title ("Voorbeeldcanvas") | Space Grotesk | 20 to 22 | 600 to 700 |
| Card amount ("€ 115.500") | Space Grotesk | 22 / 30.8 | 700 |
| Large figure ("€ 744.000", "20,00%") | Space Grotesk | 30 / 42 | 700 |

## Spacing, radius and elevation

- Spacing steps: 2, 4, 6, 8, 10, 12, 16, 20 and 24 pixels. Rows and controls use 10 by 12 padding; cards use 12 to 16; panels 16 to 24; gaps are mostly 6, 8 and 10.
- Radius: 6 on controls and rows, 8 on cards and large buttons, 10 on panels and Areas, 4 to 5 on small buttons and badges, fully round on pills and status dots.
- Elevation: flat. Borders carry structure; the only shadow is `0 1px 3px rgba(0,0,0,.12)` on floating surfaces (bottom toolbar, popovers).

## Shell

- Top bar, white, 56 pixels high, hairline at the bottom. It holds:
  - the logo mark and "OMC Studio";
  - the breadcrumb "Organisatie / Canvas" with the badge "Voorbeelddata" on sample data;
  - the tabs Canvas, Kostenposten, Toewijzen, Versies and Beheer, where the active tab is ink with a 2-pixel underline;
  - the Fiscal year selector "Boekjaar 2026 open";
  - the user's role and menu on the right.
- Direction band: fixed place across the top, 180 pixels high on the Artboard, locked in Tekenen, one arrangement in every mode, four columns side by side: logo, mission, vision, strategic goals (decision 38; board 1b stacks the goals below, which does not apply).
- Artboard: fixed, 1680 by 1188 pixels, landscape A3, never growing or scaling (ADR 0020). Content beyond its bottom edge sits below a 1-pixel ink line labelled "Rand A3" (11 px muted text) on a hatch of ink at 6% in 45-degree lines, and a warning callout above the canvas reads "Niet alles past op het A3-tekenvlak: <n> blokken vallen erbuiten".
- Page header: the title, then the mode switch Invoeren | Tekenen | Bekijken (a segmented control; the active segment is ink with white text), then the save state and the actions on the right.
- Body: a left panel of 210 to 225 pixels (Area list, Layers, cost record list), the main work area, and a detail panel of 320 to 390 pixels on the right. All three are white panels on the page background.

## Components

- **Buttons**: primary is an ink fill with white text, 36 high, radius 6. Secondary is white with a hairline border at 36, 32 or 28 high. Danger is white with red text and outline. Icon buttons are 28 by 28. Disabled buttons are `#E6E8E6` with a lock icon and an explanation next to them ("Opslaan kan zodra er 0,00% te verdelen is").
- **File field** (proposed by T387, not in the design): label above in 13/600, a 36-pixel box with the `#848B95` edge and a 28-pixel native file button inside, hint in 11.5 muted and the refusal in 12.5 danger below, both tied with `aria-describedby`; the browser's own button label stays untranslated.
- **Segmented control**: used for the modes, the overlay choice and the Modality choice (Common, Adaptive, Specialized, Distinct, Geen, each with its letter badge). The selected Modality has a 2-pixel ink border.
- **Inputs**: label above in 13/600, field 36 high (32 in dense panels), hint below in 11.5 muted. Amounts carry "€ " inside the field. Percentages carry "%" after it.
- **Filter chips**: a select button showing "Soort Alle".
- **Badges**: modality letter square, 16 by 16 pixels. Pills "Voorbeelddata", "nieuw" and "leeg" (warning colour).
- **Status**: a dot plus text, never the dot alone: "● 100%", "○ Niet toegewezen" (to be read as "Onbekende allocatie", decision 26), "○ Concept 80%".
- **Tree row (Invoeren)**: type label, name, modality badge, then the up and down arrow buttons. Children are indented by 24 pixels per level. Each Domain ends with a dashed add line, "Activiteit of cluster toevoegen aan <domein>, Enter om op te slaan", with the choice "Activiteit | Cluster" next to it (decision 44).
- **Area list item**: name plus a count line ("4 domeinen · 13 activiteiten"). The selected item has a 2-pixel ink border.
- **Callouts**: warning (amber background, triangle icon, bold kind plus name), success (green background, check, "Totalen sluiten: ...").
- **Summary card** (Kostenposten): small label, large amount, sub line.
- **Table**: row checkbox, sortable headers, the name with its code on a second line, right-aligned amounts, and a forecast delta in green or warning colour.
- **Side-panel form**: kind and path above the title, fields, "Opslaan" and "Annuleren", then the consequence text, then "Archiveren" and "Verwijderen".
- **Domain card (financial overlay)**: title and modality badge, the amount, "x van totaal", and a bar where the track is the budget, the fill is the forecast and a stripe marks the budget. Below that, "Prognose € … · 101% van budget" and the counts. A card without costs reads "Geen kosten toegewezen in 2026". On hover or focus, a popover "Uitsplitsing per kostenpost" shows the allocated percentage and amount per cost record.
- **Allocation**: an amber "Nog te verdelen" block with the percentage and amount, and a stacked bar of the lines plus a dashed rest. Each line card shows its colour stripe, kind and path, name, percentage input, amount, "Rest naar deze regel" and remove. Below them sits a dashed drop zone.
- **Target tree** (Toewijzen): the Area heading with its total, rows of kind, name, amount and a "+" button, and "In set" for Targets already in the set.
- **Layers panel** (Tekenen): the Element tree with disclosure arrows, the selected row in accent tint, and the "nieuw" pill.
- **Property panel** (Tekenen): "Plek en grootte" with X, Y, B and H in a 2-by-2 grid; "Kleur" with Vulling, Rand and Tekst, each a swatch plus a hex field; "Hoeken"; "Stijl naar alle domeinen hier" and "Terug naar automatische plek"; and a note linking to Invoeren. Beyond the board it holds the full list of addendum chapter 5 (decision 33): fill opacity, border width and style, font, size, weight, italic, text alignment, order, copy and paste style, same size, grid.
- **Shift move** (Tekenen, added after the design, ADR 0036): for a selected Cluster, Activity or Item the property panel adds "Verplaatsen naar", a labelled select of valid targets by path with the button "Verplaatsen" (SD-42); while Shift-dragging, a pill next to the target reads "Naar <doel>" or, in the warning colours, "Kan hier niet: <reden>"; every move opens the dialog "Verplaatsen naar <doel>?" with "Verplaatsen" (primary) and "Annuleren", in the dialog pattern of this contract.
- **Bottom toolbar** (Tekenen): floating and centred, with "Selecteren en verplaatsen", "Schuiven", "Uitlijnen", "Gelijk verdelen" and "Zoom 78%". The selection shows a 1-pixel accent frame with square handles and a size label ("286 × 204").

## States

| State | Look |
|---|---|
| Selected row, area or card | 2-pixel ink border |
| Active tab | ink text, 2-pixel ink underline |
| Active segment | ink fill, white text |
| Saved | green check and "Alles opgeslagen" or "Opmaak opgeslagen"; Tekenen saves automatically (ADR 0021), shows "Opslaan…" while sending and "Opmaak niet opgeslagen" with "Opnieuw proberen" on failure |
| Unsaved allocation | "Concept 80%" in warning colour, never stored (H5) |
| Disabled | grey fill, lock icon, explanation next to it |
| Empty | muted sentence ("Geen kosten toegewezen in 2026", "leeg") |
| Warning | amber callout or pill |
| Focus | a dark and a light ring of 2 pixels each (WB2, decision 46), replacing the design's single `#2459D6` outline |
| Checkbox | 16-pixel box inside a 24-pixel target (WB4, decision 46) |

## Formats seen in the design

On the canvas and in the overlay amounts are whole euros ("€ 84.000") and percentages have one decimal ("2,8%"); the detail panel, tables, forms and Toewijzen show cents ("€ 54.000,00") and two decimals (decision 40). Each amount is rounded on its own and shows the same everywhere; the line "Totalen sluiten" shows its amounts in cents (decision 52). The financial overlay draws its figures inside the card at the card's place and size (decision 39). The Cost record form also shows Supplier, cost centre, description and remark (decision 42). Long names wrap and cards grow (decision 48).
