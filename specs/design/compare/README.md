# Design comparison: built screens against the boards

Built from `trial/d2-release` at 13f5ecd, served on 127.0.0.1:8212 with SQLite and seeded neutral data (organisation "Voorbeeld BV", canvas "Voorbeeldcanvas", fiscal year 2026, ten cost records KP-001 to KP-010 with the board's names and amounts). Built screens were captured with Playwright (chromium, viewport width 1440, full page, signed in as administrator). Board values were measured by loading `omc-studio-design.html` in chromium and reading `getComputedStyle` inside the board frames; built values the same way on the real elements. Colours are given as rgb or hex. "Contract" is `specs/001-omc-studio-v1/design-contract.md`.

Pairs (board left, built right in each row of the folder):

| Screen | Board | Built |
|---|---|---|
| Kostenposten | `kostenposten-board.png` (1440 x 990) | `kostenposten-built.png` (side panel open), `kostenposten-table-built.png` (organisation page, panel closed), `kostenposten-canvas-built.png` (Canvas variant with summary cards) |
| Toewijzen | `toewijzen-board.png` (1440 x 1390) | `toewijzen-built.png` (1440 x 2994; KP-010 selected, set of Personeel 25%, Werkplek & beheer 40%, Financiele administratie voeren 15%) |
| Bekijken, financial overlay | `bekijken-financieel-board.png` (1440 x 1450) | `bekijken-financieel-built.png` (Domain "Opvang & onderwijs" selected, side panel open), `bekijken-financieel-viewport-built.png` |

The Kostenposten board is the Canvas variant (summary cards, finalise card). The board shows the form open next to the table; in the build the form is a modal overlay that dims and covers the table, so the open-panel capture and the table capture are separate files.

## Shared findings (apply to every screen, referenced as S-xx)

- S-01 Font. Board and contract: Inter for body, Space Grotesk for headings and amounts. Built: the page body computes to `-apple-system` (system stack) on every built screen except the canvas cards and the direction band, which use Inter. Measured: board body text `Inter 13px/18.2px`, built `-apple-system 13px/19.5px`. Renders from: no body font set in `resources/css/app.css`; Inter is only loaded in `resources/css/fonts.css` and used in `resources/js/canvas/` and `resources/js/layout/`.
- S-02 Line height. Board 18.2px at 13px (1.4); built 19.5px (1.5, the Tailwind default). Same files.
- S-03 Page background. Contract and board: `#F2F3F1` page, white panels. Built: the whole shell is white (`bg-white` in `resources/js/shell/AppShell.vue`), no panel/page contrast anywhere.
- S-04 Top bar. Board and contract: white, 56px high, hairline `#DCDFE2` at the bottom, one row with logo mark, "OMC Studio", breadcrumb "Voorbeeld BV / Voorbeeldcanvas" with pill, tabs on the same row (active: ink text, 2px ink underline), a boxed "Boekjaar 2026 open" selector and the role as plain text. Built: about 79px high, two rows (brand and breadcrumb above, tabs below), border `#848B95` (contract hairline is `#DCDFE2`), no logo mark, tabs underline `#1F3A5F` navy (not ink `#171B21`), year selector is a native select with a bold "Boekjaar" label, the role is a bordered button. Renders from: `resources/js/shell/AppShell.vue`, `TopBar.vue`, `Tabs.vue`, `YearSelector.vue`, `UserMenu.vue`.
- S-05 Primary and secondary buttons. Board: 36px high, radius 6, weight 500, secondary border `#DCDFE2` (contract `#848B95`), primary ink fill. Built: 34px high, weight 600 on most, transparent background (not white) and border `#848B95`, padding 6px 12px. Pages use ad hoc classes `rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold` per button instead of one button component.
- S-06 Mode switch active colour. Contract and board: active segment ink `#171B21` with white text, one segmented control. Built Bekijken: two separate bordered links, active filled navy `rgb(31,58,95)` (`#1F3A5F`); only the overlay switch uses ink. Renders from: `resources/js/shell/ModeSwitch.vue`.
- S-07 Focus accent and links. Contract accent `#2459D6`. Built link "Nu toewijzen" `rgb(31,79,180)` (`#1F4FB4`), blue, underlined; board shows ink 600 underlined.

## Kostenposten (board 4)

Files: `resources/js/pages/Finance/CostRecords.vue`, `CostRecordForm.vue`, `YearlyFiguresForm.vue`, `FinaliseCard.vue`, `FiscalYearsPanel.vue`, `resources/js/components/list/ListView.vue`, `FilterBar.vue`, `SortHeader.vue`, `resources/js/components/overlays/SidePanel.vue`, `resources/js/components/fields/*`.

Layout and proportions
- K-01 Page structure. Board: header row (title 20px left, "Overnemen van 2025", "Importeren uit CSV", ink "+ Nieuw" right-aligned), then a row of four equal cards (three summary cards plus the finalise card, each about 235 x 124), then the filter row, then one white table panel, with the form as a 320px white panel to the right of the table (table 1018px wide, panel 352px). Built: header, two buttons left-aligned ("Boekjaren", "Kostenposten exporteren"), search, filters stacked over three rows, full-width borderless table. Cards in the Canvas variant are stacked vertically in three boxes plus a full-width finalise box. Organisation variant has no summary cards at all.
- K-02 Summary cards. Board: four cards in one row (card 1 `Totaal budget`, 2 `Toegewezen`, 3 `Niet toegewezen`, 4 finalise). Built (`kostenposten-canvas-built.png`): finalise card first and full width (1408 x 112), then three cards in a row; order and widths differ; card heights 85 versus board 124.
- K-03 Form placement. Board: docked panel beside the table, table stays visible. Built: modal side panel 448px (28rem, `shell-detail`) over a dimmed page; the table is covered and unreachable. Renders from `components/overlays/SidePanel.vue`.
- K-04 Form fields. Board: Naam full width; Code and Soort in two columns; Eigenaar full width with hint; Applicatie; "Jaarwaarden 2026" heading; Budget and Laatste prognose in two columns; Run and Change in two columns; Fte and Peildatum in two columns; consequence text; Opslaan and Annuleren side by side. Built: every field full width in one column (Code, Naam, Soort kosten, Leverancier, Eigenaar, Kostenplaats, Applicatie, Omschrijving), so the form is 1300px+ long; fields in the board that the build lacks: Peildatum; fields in the build the board lacks: Leverancier, Kostenplaats, Omschrijving (decision 42 asks for them, the board does not show them).
- K-05 Table columns. Board: checkbox, Naam (name 600 with code beneath), Soort, Eigenaar, Applicatie, Budget (right-aligned, 600), Prognose (with green or amber delta "+2.000"), Toegewezen. Built: checkbox, Naam (link), Code (own column), Budget, Prognose, Toegewezen, Verwijderen button per row. Missing: Soort, Eigenaar, Applicatie columns, the second-line code, right alignment, the delta. Extra: a Verwijderen button on every row.
- K-06 Table container. Board: white panel, border 1px `#DCDFE2`, radius 10, header row 37px high with muted 11.5/600 labels. Built: no panel, no radius, only row hairlines `rgb(213,217,223)`, header text 12.5px/600 ink.
- K-07 Row height. Board: 55 to 74px (two-line names, padding 10 by 12). Built: 42px (padding 6 by 8, measured on `td`).
- K-08 Filter bar. Board: one row: search with magnifier icon ("Zoek op naam of code"), four chips "Soort Alle", "Eigenaar Alle", "Applicatie Alle", "Toewijzing Alle" (36px, radius 6, inline label), checkbox "Toon gearchiveerd" and "10 resultaten" right-aligned. Built: three rows: search label above an empty field with a separate "Nieuw" button, then four native selects with labels above (Soort kosten, Eigenaar, Applicatie, Toewijzing), no icon, no placeholder.
- K-09 Page title. Board "Kostenposten 2026", Space Grotesk 20/28 600, letter spacing -0.2px. Built "Kostenposten: Voorbeeld BV", `-apple-system` 20/30 600, no letter spacing; text also differs (organisation name, no year).

Typography
- K-10 Title face: see S-01; Space Grotesk missing on H1 (built `-apple-system`).
- K-11 Card label. Board `Totaal budget` Inter 11.5/16.1 400 `#5F6772`. Built bold ink 13/19.5 600 (`Totaal budget` is a heading).
- K-12 Card amount. Board `€ 744.000` Space Grotesk 22/30.8 700, -0.22px, whole euros. Built `€ 744.000,00` body font 13px 400, cents.
- K-13 Sub line. Board "Prognose € 752.000" 12/16.8 `#454C57`. Built "Prognose: € 752.000,00" 13/19.5 ink.
- K-14 Table header. Board 11.5/16.1 600 `#454C57`, padding 10px 12px. Built 12.5/18.75 600 `#171B21`, padding 6px 8px; sort headers are 12.5px buttons with padding 2px 4px and radius 6.
- K-15 Cell amounts. Board `€ 84.000` 13/18.2 600, right-aligned. Built `€ 42.000,00` 13/19.5 400, left-aligned, with cents (the board shows whole euros in the table; contract says tables show cents, so the contract and board differ here, the build follows the contract).
- K-16 Name cell. Board name 13/18.2 600 ink, code 11.5/16.1 `#5F6772` below. Built name is an underlined link, 13px 400.
- K-17 Form heading "Jaarwaarden 2026". Board Space Grotesk 13/18.2 600. Built 15px/22.5 600 system font (heading level differs).

Colours and backgrounds
- K-18 Page `#F2F3F1` versus built `#FFFFFF` (S-03). Panels: board white `#FFFFFF` with 1px `#DCDFE2`; built no panel.
- K-19 Form panel. Board white, border `#DCDFE2`, radius 10, padding 16 to 24, page-grey around it. Built `shell-detail` white with a left border `#848B95` on a dimmed backdrop.
- K-20 Status text. Board "100%" green `#1F7A4D` 12/16.8 600 with a 7px dot; "Niet toegewezen" `#8A5200` with an open ring. Built "100%" ink 13px 400 with a filled green dot `#1F7A3D` (not `#1F7A4D`) and "Onbekende allocatie" with filled brown dot `#8A5A00` (not `#8A5200`).
- K-21 Delta colours. Board "+2.000" amber, "-6.000" green. Built: absent.

Spacing, radius, shadow
- K-22 Card padding. Board 14px 16px radius 10 border 1px `#DCDFE2`. Built about 12px, radius 0 (square corners on the boxes), border `#848B95`.
- K-23 Gaps. Board 12px between cards, 16px between sections. Built 12px between cards; sections separated by margins of 12 to 16px but without alignment to one grid.

Components
- K-24 Finalise card. Board: text "Het jaar kan definitief zodra alle kostenposten met budget zijn toegewezen. Nog 2 te gaan." and a grey `#E6E8E6` button with lock icon, "Jaar definitief maken", 179 x 36. Built: heading, sentence, grey button without lock icon, no `#E6E8E6` fill (transparent, 50% text).
- K-25 Selected row. Board: row with grey `#F6F7F5` tint and 2px ink left bar (KP-006). Built: no selected-row state visible, the panel is modal.
- K-26 Row checkbox. Board 16px box in a 24px target with 4px radius. Built native 13px checkbox, no custom target.
- K-27 Form buttons. Board "Opslaan" ink 78 x 36 and "Annuleren" secondary 91 x 36 side by side, "Archiveren" and "Verwijderen" (red `#A12A1E`, outline `#D9B4AF` design / `#C0645A` contract) below a rule with consequence text. Built: "Opslaan" secondary-styled (transparent, bordered, not ink) with no Annuleren; Archiveren and Verwijderen are further down or on the row.
- K-28 Form selects. Board selects look like inputs (36px, radius 6, border `#AEB4BC` design). Built native select chrome (browser arrow), 34px, border `#848B95`.
- K-29 Search field. Board with magnifier icon and placeholder, 253 x 36. Built bare input 194 x 34, no icon, no placeholder.
- K-30 Header buttons "Overnemen van 2025", "Importeren uit CSV", "+ Nieuw" (ink, plus icon). Built "Jaar overnemen" and CSV actions as secondary buttons that touch each other in the Canvas variant (no gap between "Toewijzingen exporteren" and "Jaar overnemen"); "Nieuw" is secondary, not ink.
- K-31 Archive toggle. Board checkbox "Toon gearchiveerd" aligned to the filter row end. Built next to the search button.
- K-32 Hint under Eigenaar. Board 11.5 `#5F6772`. Built present, 12px.

States
- K-33 Focus. Contract: dark plus light 2px rings. Built form focuses Code with a heavy dark ring and selects the text on open (`kostenposten-built.png`); the board shows no focus ring.
- K-34 Hover, disabled, empty. Board shows disabled finalise only; built the disabled state differs (K-24). Empty state not shown on the board.

### Kostenposten summary

| Category | Count |
|---|---|
| Layout and proportions | 9 (K-01 to K-09) |
| Typography | 8 (K-10 to K-17) |
| Colours and backgrounds | 4 (K-18 to K-21) |
| Spacing, radius, shadow | 2 (K-22, K-23) |
| Components | 9 (K-24 to K-32, includes shell via S-04) |
| States | 2 (K-33, K-34) |
| Total | 34 plus S-01 to S-07 |

Five most visible: K-01 (page structure and card row), K-05 (table columns missing, no panel), K-04 (one-column form), K-03 (modal over table), K-08 (filter bar).

### Status after T379 (feat/d210-kosten)

Evidence: `kostenposten-built-v2.png` (1440 pixels, full page, Canvas variant, panel open) next to `kostenposten-board.png`; measured values are asserted in `tests/e2e/design-kostenposten.spec.ts`.

| Finding | Status | Commit and note |
|---|---|---|
| K-01 | opgelost | 7a8b6cf: header row with title left and actions right, then one row of four cards, toolbar, one table panel, form docked right. The organisation page has no summary data (no Canvas), so it shows no cards: bewust afwijkend there. |
| K-02 | opgelost | 7a8b6cf: three summary cards then the finalise card, one row of four equal columns; height 129 against 124. |
| K-03 | opgelost | 2caa564: the form is a docked panel beside the table (`ListDock`), the table stays usable, Escape inside the panel closes it with the unsaved-changes guard. |
| K-04 | opgelost | 7a8b6cf: Naam full width, Code and Soort, Eigenaar, Applicatie, Leverancier and Kostenplaats (decision 42), Omschrijving; Jaarwaarden in two columns, Peildatum present. Label "Soort kosten" kept (specs address it by name). |
| K-05 | opgelost | 2caa564, 7a8b6cf: Soort, Eigenaar, Applicatie columns, code beneath the name, right-aligned amounts, delta; the per-row Verwijderen button moved into the panel as on the board. |
| K-06 | opgelost | 2caa564: white panel, 1px `#DCDFE2`, radius 10, header 11.5/600. |
| K-07 | opgelost | 2caa564: padding 10 by 12 (right 8 to fit cents), two-line rows 54px. |
| K-08 | opgelost | 2caa564: one row with search, four inline-label filter chips, archive toggle and count. |
| K-09 | opgelost | 7a8b6cf: "Kostenposten 2026" in Space Grotesk 20/600 with -0.2px; the organisation name is in the breadcrumb. |
| K-10 | opgelost | 7a8b6cf: Space Grotesk on the H1, card amounts and panel headings (foundation tokens). |
| K-11 | opgelost | 7a8b6cf: card label 11.5 muted, 400. |
| K-12 | opgelost | 7a8b6cf: card amount Space Grotesk 22/700, -0.22px, whole euros (the table keeps cents, see K-15). |
| K-13 | opgelost | 7a8b6cf: sub line 12px `#454C57`, "Prognose € 752.000". |
| K-14 | opgelost | 2caa564: header 11.5/600 `#454C57`. |
| K-15 | bewust afwijkend | Table amounts keep cents ("€ 84.000,00") because the contract (decision 40) shows cents in tables; right-aligned and 600 as on the board (2caa564). |
| K-16 | opgelost | 2caa564: name 13/600 without underline, code 11.5 muted beneath. |
| K-17 | opgelost | 7a8b6cf: "Jaarwaarden 2026" Space Grotesk 13/600. |
| K-18 | opgelost | Foundation page `#F2F3F1`; panels white with `#DCDFE2` (2caa564, 7a8b6cf). |
| K-19 | opgelost | 2caa564: docked panel white, hairline, radius 10, padding 16, on the grey page. |
| K-20 | opgelost | 7a8b6cf: success `#1F7A4D` with filled dot, warning `#8A5200` with open ring, 12/600. The text "Onbekende allocatie" stays (ADR 0008 term and existing specs) where the board says "Niet toegewezen". |
| K-21 | opgelost | 7a8b6cf: delta amber when above budget, green below, signed. |
| K-22 | opgelost | 7a8b6cf: cards 14 by 16, radius 10, hairline border. |
| K-23 | opgelost | 7a8b6cf: 12px between cards, 16px between sections. |
| K-24 | opgelost | 7a8b6cf: finalise card with `AppButton` and lock icon, disabled fill `#E6E8E6`. The sentence keeps "alle kostenposten zijn toegewezen" (finalise-card spec) instead of "met budget": bewust afwijkend in wording only. |
| K-25 | opgelost | 2caa564: selected row `#F6F7F5` with a 2px ink bar. |
| K-26 | bewust afwijkend | 2caa564: 16px box in a 24px target with ink accent; the 4px radius needs a styled checkbox that the foundation lacks (foundation gap). |
| K-27 | opgelost | 7a8b6cf: Opslaan ink and Annuleren side by side, Archiveren and Verwijderen (danger) below a rule with the consequence text. The record and the Jaarwaarden save separately (two resources), so the panel has Opslaan plus Jaarwaarden opslaan: bewust afwijkend. |
| K-28 | bewust afwijkend | 7a8b6cf: selects are 36px, radius 6, chevron, but the border is `#848B95` (decision 45) not `#AEB4BC`; native select kept so keyboard and specs stay as they are (foundation gap: no native select component). |
| K-29 | opgelost | 2caa564: search field with icon and placeholder "Zoek op naam of code", 253 by 36. |
| K-30 | opgelost | 7a8b6cf: ink "+ Nieuw" with plus icon, "Importeren uit CSV", 8px gaps. "Jaar overnemen" keeps its label (the source year is chosen in the dialog): bewust afwijkend in wording only. |
| K-31 | opgelost | 2caa564: archive toggle and count right-aligned in the filter row. |
| K-32 | opgelost | 7a8b6cf: hint 11.5 `#5F6772` (foundation `Field`). |
| K-33 | bewust afwijkend | Focus ring is the dark plus light double ring of decision 46 (foundation); on opening the panel focus goes to its heading, so no text is selected and no ring sits on a field (2caa564). |
| K-34 | opgelost | 7a8b6cf: disabled finalise with lock and `#E6E8E6`; empty table shows "Geen resultaten". |

Follow-up commits: 935f646 and the name column width fix.

## Toewijzen (board 3)

Files: `resources/js/pages/Finance/Allocation.vue`, `CostRecordStatusList.vue`, `AllocationEditor.vue`, `AllocationQuick.vue`, `TargetTree.vue`, `TargetNode.vue`.

Layout and proportions
- T-01 Columns. Board: left list 346px (24 to 370), middle card 611px (390 to 1001), right panel 380px (1021 to 1401), gaps 20px, all three are white panels on grey; left column has no panel itself. Built: grid `1fr 2fr 1fr` with gap 24px, widths about 340 / 700 / 340 at 1440, no panels, no white backgrounds.
- T-02 Page height. Board 1390px: the target tree rows are 44px with a "+" icon button. Built 2994px: every target row wraps (name, kind, amount and a 163px text button "Toevoegen aan verdeling" in a 340px column), rows 48 to 80px high. The right column is more than twice as long as the board's.
- T-03 Header block. Board: left column heading "Kostenposten 2026" and "8 van 10 toegewezen" on one row, then search with icon and a "Filter" button. Built: page H1 "Toewijzen: Voorbeeldcanvas", a "Toewijzingen importeren" button, then the column heading "Kostenposten" and a "Zoeken" label above an empty field.
- T-04 Middle card. Board: white card 611px, padding 20px: eyebrow line "KP-010 · Leverancierscontract · eigenaar ICT" (11.5 muted), title "Licenties kantoorsoftware" Space Grotesk, "Budget 2026 € 54.000,00", at the right an amber box "Nog te verdelen 20,00% € 10.800,00" (about 130 x 96), the stacked bar of lines plus dashed rest (10px high), "Snel" with two buttons. Built: unboxed heading "Verdeling van Licenties kantoorsoftware", status line, three buttons, then the line cards, no budget line, no eyebrow, no bar. "Nog te verdelen: 20,00% € 10.800,00" is a bold paragraph under the lines, not an amber block.
- T-05 Line cards. Board: each card white, border `#DCDFE2`, radius 8, padding 12, colour stripe (ink, slate, grey) on the left, small kind and path line, name 15px bold, percentage input 84 x 36 with "%", amount right, second row "Rest naar deze regel" and an X icon button (36 x 36). Built: bordered box (`#848B95`), radius 6, name 13px bold, path under it, one flex row with a 96px input, "%" and amount and two buttons; no colour stripe.
- T-06 Drop zone. Board: dashed `#9AA1AA` box with centred text "Klik rechts op een doel of sleep het hierheen om een regel toe te voegen", radius 8, 48px high, always shown below the lines. Built: not visible once lines exist; the sentence only shows when the set is empty.
- T-07 Action row. Board: disabled "Opslaan" with lock icon and explanation to its right on one line, "Wijzigingen weggooien" at the far right, red "Set leegmaken" below. Built: "Opslaan" grey without a lock, explanation under it, "Wijzigingen weggooien" and "Set leegmaken" sit in the top button row.
- T-08 Cost record list. Board: cards 346 x 57, name left (13/600) with amount `€ 84.000` right, below the supplier in muted 12px and the status ("● 100%") right-aligned; selected card has a 2px ink border and radius 8. Built: cards with name, amount below it with cents (`€ 42.000,00`), status below that; supplier missing; selected card shows a grey `#EEF1F5`-like fill with a 1px ink-ish border instead of a 2px ink border.
- T-09 Target tree. Board: grouped by Area with a small-caps heading and total ("RICHTEN € 21.000"), each Domain row on a `#F6F7F5` surface with a 10.5px kind label above the name, amount right in a column, "geen" for empty, "In set" with a check icon, "+" button 28 x 28. Built: flat list without Area headings or totals, each row a bordered box with name, "(Domein)" inline, amount inline after the name; "In set" as a plain grey chip; a text button per row.
- T-10 Indentation. Board 16px per level inside the same row surface. Built 16px margin (`ml-4`) plus a new bordered box per level.

Typography
- T-11 Heading "Kostenposten 2026" and "Doelen op het canvas". Board Space Grotesk 13/18.2 600. Built "Doelen op het canvas" 15/22.5 600 system font.
- T-12 Title "Licenties kantoorsoftware". Board Space Grotesk, 24px. Built 13px 600 system font in the list and 15px heading text "Verdeling van ..." in the middle.
- T-13 "Nog te verdelen" label. Board 11.5/16.1 600 `#8A5200`; value "20,00%" Space Grotesk 30/42 700 -0.6px `#8A5200`; amount 12.5/17.5. Built one line 13px bold ink.
- T-14 Percentage input. Board Space Grotesk-like 13 bold right-aligned inside 84 x 36. Built 13px 400 in 96px wide input.
- T-15 Target name. Board 12.5/17.5 600 (Strategie) with kind 10.5 above. Built 13/19.5 600, kind in parentheses 13px `#4B5563`.
- T-16 Status "Concept 80%". Board `#8A5200` Inter 12/normal 600 with ring marker. Built "Concept 80,00%" `#8A5A00` 13px bold with a different percentage format (two decimals).
- T-17 Buttons. Board 13/500. Built 13/400 (secondary) or 600.

Colours and backgrounds
- T-18 Grey page versus white (S-03); panels `#FFFFFF` with 1px `#DCDFE2` radius 10 on the board, none built.
- T-19 Nog-te-verdelen block `#FFF3D6` with radius 8 on the board, absent built.
- T-20 Line stripes ink `#171B21`, `#5F6772` and `#9AA1AA` on the board, absent built.
- T-21 "In set" `#1F7A4D` with check, `#F6F7F5` chip radius 5 on the board; built grey chip text ink.
- T-22 Danger button. Board text `#A12A1E` border `#D9B4AF`. Built "Set leegmaken" at 60% opacity with border `#C0645A` (contract value, but faded because disabled).
- T-23 Status dots. Board green `#1F7A4D` and amber ring `#8A5200`; built `#1F7A3D` and `#8A5A00`.

Spacing, radius, shadow
- T-24 Middle card padding. Board 20px, radius 10; built none.
- T-25 Row padding. Board target rows 8px 10px with 6px gap; built 4px 8px with 4px gap.
- T-26 List cards. Board gap 6px, padding 10px 12px, radius 8; built gap 4px, padding 6px 8px, radius 6.

Components
- T-27 Target add button. Board 28 x 28 "+" icon button; built a 163 x 30 text button "Toevoegen aan verdeling" per row (accessible name carries the target, but the visible label is long).
- T-28 Stacked bar. Board bar of coloured segments with a dashed amber outline for the rest; built none.
- T-29 Search fields. Board with magnifier icon and placeholder; built bare inputs with labels above.
- T-30 Filter button on the list. Board "Filter"; built none.
- T-31 "Snel" quick actions. Board two buttons in a row after the label "Snel" ("Gelijk verdelen over 3 doelen", "Verdelen over activiteiten van een domein"). Built "Snel" is a single button (a menu).
- T-32 Footnote. Board "Bedragen zijn opgeslagen toewijzingen. Het concept links telt pas mee na opslaan." at the end of the right panel. Built a status line at the page bottom ("Toevoegen aan verdeling: ...") and an instruction paragraph at the top of the panel.

States
- T-33 Selected cost record. Board 2px ink border; built 1px border and tinted fill (T-08).
- T-34 Disabled Opslaan. Board `#E6E8E6` with lock and `#5F6772` text; built a mid-grey fill with white text and no lock (colour not measured).
- T-35 Draft state. Board shows "Concept 80%" in the list card; built shows it only in the middle status line.

### Toewijzen summary

| Category | Count |
|---|---|
| Layout and proportions | 10 (T-01 to T-10) |
| Typography | 7 (T-11 to T-17) |
| Colours and backgrounds | 6 (T-18 to T-23) |
| Spacing, radius, shadow | 3 (T-24 to T-26) |
| Components | 6 (T-27 to T-32) |
| States | 3 (T-33 to T-35) |
| Total | 35 plus S-01 to S-07 |

Five most visible: T-02 (page twice as tall, long text buttons), T-04 (no summary card, amber block, bar), T-09 (flat target tree without Area headings), T-01 (no white panels, grey page), T-05 (line cards without stripe, wrong structure).

## Bekijken with financial overlay (board 2)

Files: `resources/js/pages/Canvas/View.vue`, `FigureSidePanel.vue`, `resources/js/canvas/CanvasView.vue`, `Block.vue`, `CanvasCard.vue`, `OverlaySwitch.vue`, `ZoomToolbar.vue`, `ExportMenu.vue`, `ViewDetail.vue`, `resources/js/canvas/overlay/FinancialCard.vue`, `OverlayFilters.vue`, `OverlayLegend.vue`, `Breakdown.vue`, `resources/js/layout/direction.ts`, `resources/js/shell/ModeSwitch.vue`.

Layout and proportions
- B-01 Page header. Board: one row at y 69 to 105: title "Voorbeeldcanvas" (Space Grotesk 20), segmented modes, "Overlay" with segmented Geen / Modaliteit / Financieel, four filter chips and an ink "Exporteren" at the far right. Built: five separate rows (modes; title; Exporteren with "Met overlay" checkbox; zoom controls plus overlay switch; filters; legend), so the artboard starts at y 396 instead of 146.
- B-02 Artboard width. Board: the canvas fills the 1042px content column (24 to 1066) next to a 334px side panel, scaled to fit. Built: the A3 artboard is 1680px wide at 100% zoom, so it overflows the column and is cut off at the side panel (direction band shows only "Missie" and "Visie", the strategic goals column is hidden, Areas run under the panel). Renders from `canvas/CanvasView.vue` (zoom starts at 100%, "Passend maken" is not applied).
- B-03 Canvas page height. Board 1450px; built page height 1088px with the content cut at 1072px (the artboard scroll container ends there), so the lower Areas are not reachable from the page screenshot.
- B-04 Side panel. Board: totals panel 334 x 888 right of the canvas (1082 to 1416), white, radius 10, padding 16, always present. Built: with a Domain selected the detail panel (448px, right edge, full height, border-left `#848B95`) opens and the totals panel ("Totalen van het canvas", 300px) floats over the artboard as a second white block; two panels at once overlap the canvas.
- B-05 Direction band. Board: ink band 1042 x 112 radius 10, four columns: dashed "Logo" box, MISSIE, VISIE, STRATEGISCHE DOELEN (numbered list with bold lead words). Built: 180px high ink band, left padding 256px with the logo area empty, only mission and vision visible, goals clipped; no dashed logo placeholder, no numbered goals visible.
- B-06 Area arrangement. Board: Richten and Beheersen side by side (514 wide each), Actoren / Kanalen / Waardepropositions as three narrow columns left (136 to 148 wide), Primaire activiteiten (597 wide) over Ondersteunende activiteiten right. Built: same arrangement at 1680 scale but Actoren, Kanalen and Waardeproposities items are drawn on top of their group titles (Klanten over Ouders, Digitaal over Ouderportaal, Fysiek over Locatie); text overlaps in all three list Areas.
- B-07 Domain cards. Board: cards fill their grid cell (about 254 x 150 in two columns); built cards have the same structure, but heights are fixed (about 224px for "Plaatsing & planning", 160 for the Richten cards) and leave empty space under the counts.
- B-08 Filter and legend rows. Board legend is a single 25px line "Aandeel van totaal [swatches] laag naar hoog | streep = budget, balk = prognose  Actoren, Kanalen en Waardepropositions dragen geen kosten". Built three stacked lines: "Kleur", "Balk: ...", "Lijst-areas ... hebben geen kosten ...".
- B-09 Per-kind lists in the side panel. Board: bars under each row (63,0% with a bar, amount at right). Built plain lines without bars or percentages.

Typography
- B-10 Title. Board "Voorbeeldcanvas" Space Grotesk 20/28 600 in the page header; the board's breadcrumb span is Inter 13/18.2 600. Built title is `-apple-system` 20px 600 on its own row; breadcrumb item 13px 400 on one row.
- B-11 Domain title. Board `Strategie` Space Grotesk 14/19.6 600. Built Space Grotesk 14/20 600 (matches except line height 20 versus 19.6).
- B-12 Card amount. Board `€ 21.000` Space Grotesk 22/30.8 700 on its own line with "2,8% van totaal" Inter 12/16.8 `#454C57` beside it. Built `€ 21.000 · 2,8% van totaal` as one right-aligned line, 12/16 600 system font, ink; the 22px amount is not shown.
- B-13 Prognose line. Board `Prognose € 20.400 · 97% van budget` Inter 11.5/16.1 `#454C57`. Built `Prognose € 21.000 · 100,0% van budget` 12/16 400 ink, system font, one decimal added.
- B-14 Counts line. Board "2 activiteiten", "5 activiteiten · 1 cluster" Inter 11.5 muted. Built "Activiteiten 2 · Clusters 0", 12px ink, with a zero cluster count shown.
- B-15 Overlay label. Board `Overlay` Inter 12.5/17.5 400 `#454C57`; built 13/19.5 600 ink.
- B-16 Segmented buttons. Board 13px 500, 34px high, padding 0 12px, active ink `#171B21`; built 13px 400, 32px high, padding 6px 12px.
- B-17 Canvas total. Board `€ 744.000` Space Grotesk 30/42 700, -0.6px; built heading "Totalen van het canvas" 15/22.5 600 with a "Budget  € 744.000" line at 13px 400 (the large figure is missing).
- B-18 Section heads in the panel. Board "Per soort", "Per modaliteit", "Run en change" Space Grotesk 13/18.2 600; built "Per soort kosten" system font 13/19.5 600.
- B-19 Small caps. Board "MISSIE" 11px 600 uppercase with letter spacing; built "Missie" 11px 600 `#C9CED6` Inter, not uppercase, no letter spacing.
- B-20 Area header. Board "RICHTEN" Space Grotesk 13px 600 uppercase 0.04em with total at right "€ 21.000 · 2,8%"; built "Totaal € 21.000 · 2,8%" right-aligned without the Area title visible in the header (not verified why; the capture is clipped at the panel).

Colours and backgrounds
- B-21 Page `#F2F3F1` versus white (S-03).
- B-22 Domain card tint. Board `rgba(14,77,100,0.03)` for empty cards, up to 0.28 for the largest; Domain cards visible in both. Built tints present: Strategie `rgb(244,247,248)`, ICT-sized cards darker; the empty-Domain card (Innovatie) is a light grey `#F6F7F5`-like instead of white with hairline border.
- B-23 Cards border. Board 1px `#DCDFE2` on each Domain card; built none on filled cards (tint only).
- B-24 Mode active colour navy `#1F3A5F` versus ink (S-06).
- B-25 Totals-closed callout. Board green `#E7F3EC` panel with check icon and `#1F7A4D` text, radius 6; built plain paragraph "Totalen sluiten: ..." with no background or icon.
- B-26 Bar. Board forecast bar ink on `#DCDFE2` track with amber `#C9A227` part when above budget (the board's over-budget cards show an orange part); built ink fill on light track with a tick, amber missing at 101% (the 101,0% ICT-like cards show a plain ink bar).
- B-27 Modality badge. Board 16 x 16 coloured squares with letter (C, S, A, D) in each card's top-right; built not visible in the capture (the cards seen show no modality letter; not verified in the DOM).
- B-28 Link colour `#1F4FB4` versus board ink (S-07).

Spacing, radius, shadow
- B-29 Domain card padding. Board 12px, radius 8, border 1px; built padding 12px 12px 0 and radius 8, but no border.
- B-30 Totals panel. Board white, border `#DCDFE2`, radius 10, padding 16, 334px wide; built 300px, no radius, no border, no shadow, floating on the artboard.
- B-31 Hairline rules. Board 1px `#DCDFE2` rules between panel sections; built none.

Components
- B-32 Filter chips. Board "Soort Alle" style chips (36px, radius 6, icon chevron) in the header; built labelled native selects ("Soort kosten", "Eigenaar", "Applicatie") with browser chrome, and "Versie Huidig" is absent in the overlay row.
- B-33 Export. Board ink "Exporteren" at far right of the header; built bordered secondary button on its own row with a "Met overlay" checkbox.
- B-34 Breakdown popover. Board "Uitsplitsing per kostenpost" popover: white, 1px ink border, radius 6, padding 8px 10px, rows with percentage and amount; built shows the breakdown in the detail panel only ("Uitsplitsing per kostenpost" list), no hover popover on the card.
- B-35 Selected Domain. The board shows no selected card and no Domain detail panel; the contract says a selected card has a 2px ink border. Built: no 2px ink border on the selected card (not visible in the capture), and the detail panel for a selected Domain (Domain, Naam, Omschrijving, Onderbouwing, Modaliteit, Direct bedrag, Totaal bedrag, Aandeel, Prognose, Run, Change, Onbepaald, FTE) has no counterpart in the board, which shows the canvas totals panel instead.
- B-36 Zoom toolbar. Board none on this board; built "Uitzoomen / 100% / Inzoomen / Passend maken" row of four controls above the legend.
- B-37 Direction logo placeholder. Board dashed box "Logo" 98 x 58; built nothing.

States
- B-38 Hover or focus popover shown on the board for the ICT card; built none (B-34).
- B-39 Empty Domain. Board "Geen kosten toegewezen in 2026" on a white card with hairline; built "€ 0 · 0,0% van totaal" with an ink bar-less card and no sentence.
- B-40 Focus. Contract dark plus light rings; board none; built per `resources/css/focus.css`, not exercised in the capture.

### Bekijken summary

| Category | Count |
|---|---|
| Layout and proportions | 9 (B-01 to B-09) |
| Typography | 11 (B-10 to B-20) |
| Colours and backgrounds | 8 (B-21 to B-28) |
| Spacing, radius, shadow | 3 (B-29 to B-31) |
| Components | 6 (B-32 to B-37) |
| States | 3 (B-38 to B-40) |
| Total | 40 plus S-01 to S-07 |

Five most visible: B-02 (artboard overflow, direction band and Areas clipped by the panel), B-06 (list Areas draw items on top of their group titles), B-01 (five header rows, canvas pushed down 250px), B-04 (two overlapping side panels), B-12 (the large card amount missing).

## Known limits of this comparison

- Values for the board come from the design page rendered in chromium, which equals the captured PNGs; borders and shadows were read from computed style, not from pixels.
- Hover, focus and error states were not driven on the built screens; they are listed from the contract only where the board shows them.
- Some B- and T- entries rest on pixel inspection of the screenshots rather than a computed value (those without measured numbers); re-verify before fixing.
