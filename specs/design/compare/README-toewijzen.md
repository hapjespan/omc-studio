# Toewijzen: design findings and their status (T380)

Built from `trial/d2-release` at 13f5ecd, served on 127.0.0.1:8212 with SQLite and seeded neutral data (organisation "Voorbeeld BV", canvas "Voorbeeldcanvas", fiscal year 2026, ten cost records KP-001 to KP-010 with the board's names and amounts). Built screens were captured with Playwright (chromium, viewport width 1440, full page, signed in as administrator). Board values were measured by loading `omc-studio-design.html` in chromium and reading `getComputedStyle` inside the board frames; built values the same way on the real elements. Colours are given as rgb or hex. "Contract" is `specs/001-omc-studio-v1/design-contract.md`.

Status words: closed (with the commits), deviating (with the reason), open (with the reason). Decision 40 (cents and two decimals on Toewijzen) and decisions 45 and 46 (contrast) take precedence over the board. Evidence after the build: `toewijzen-built-v2.png` (1440 wide, full page, same seed and state as `toewijzen-built.png`).

Foundation gaps for the first session: F-1 IconButton has only the 28px size (board remove button 36px); F-2 no compact inline field with hidden label and a "%" suffix (the percentage input is a native input with the TextField classes); F-3 no token for #9AA1AA; F-4 the shell StatusRegion text is visible at the page bottom; F-5 `main` padding is 16px where the board uses 24px; F-6 the top bar overflows at 320px.

## Toewijzen (board 3)

Files: `resources/js/pages/Finance/Allocation.vue`, `CostRecordStatusList.vue`, `AllocationEditor.vue`, `AllocationQuick.vue`, `TargetTree.vue`, `TargetNode.vue`.

Layout and proportions
- T-01 Columns. Board: left list 346px (24 to 370), middle card 611px (390 to 1001), right panel 380px (1021 to 1401), gaps 20px, all three are white panels on grey; left column has no panel itself. Built: grid `1fr 2fr 1fr` with gap 24px, widths about 340 / 700 / 340 at 1440, no panels, no white backgrounds. **Status: closed (ef8cc99, a59c138): columns 346px, flexible, 380px with 20px gaps; the middle card and the target tree are white panels on the grey page, the list column has no panel.**
- T-02 Page height. Board 1390px: the target tree rows are 44px with a "+" icon button. Built 2994px: every target row wraps (name, kind, amount and a 163px text button "Toevoegen aan verdeling" in a 340px column), rows 48 to 80px high. The right column is more than twice as long as the board's. **Status: closed (ef8cc99, a59c138): rows are 8px 10px with a 28px "+" icon button, and Domains start collapsed except those that hold a line of the set or match the search (ASSUMPTION T380-1 in tasks.md); the page is 1496px tall on the board seed against 1390px on the board.**
- T-03 Header block. Board: left column heading "Kostenposten 2026" and "8 van 10 toegewezen" on one row, then search with icon and a "Filter" button. Built: page H1 "Toewijzen: Voorbeeldcanvas", a "Toewijzingen importeren" button, then the column heading "Kostenposten" and a "Zoeken" label above an empty field. **Status: closed (ef8cc99, a59c138): heading "Kostenposten 2026" with progress, search with icon, Filter toggle (ASSUMPTION T380-2); the page heading is visually hidden because the board has none; the allocation import panel sits below the list.**
- T-04 Middle card. Board: white card 611px, padding 20px: eyebrow line "KP-010 · Leverancierscontract · eigenaar ICT" (11.5 muted), title "Licenties kantoorsoftware" Space Grotesk, "Budget 2026 € 54.000,00", at the right an amber box "Nog te verdelen 20,00% € 10.800,00" (about 130 x 96), the stacked bar of lines plus dashed rest (10px high), "Snel" with two buttons. Built: unboxed heading "Verdeling van Licenties kantoorsoftware", status line, three buttons, then the line cards, no budget line, no eyebrow, no bar. "Nog te verdelen: 20,00% € 10.800,00" is a bold paragraph under the lines, not an amber block. **Status: closed (ef8cc99, a59c138): eyebrow with code, kind and owner, 24px title, budget line, amber block, line bar and the Snel row; the page props carry costKind and owner.**
- T-05 Line cards. Board: each card white, border `#DCDFE2`, radius 8, padding 12, colour stripe (ink, slate, grey) on the left, small kind and path line, name 15px bold, percentage input 84 x 36 with "%", amount right, second row "Rest naar deze regel" and an X icon button (36 x 36). Built: bordered box (`#848B95`), radius 6, name 13px bold, path under it, one flex row with a 96px input, "%" and amount and two buttons; no colour stripe. **Status: closed (ef8cc99, a59c138): stripe, eyebrow, 15px name, 84 x 36 input, rest and remove in a second row; the remove button is the 28px foundation icon button (board 36px, foundation gap F-1).**
- T-06 Drop zone. Board: dashed `#9AA1AA` box with centred text "Klik rechts op een doel of sleep het hierheen om een regel toe te voegen", radius 8, 48px high, always shown below the lines. Built: not visible once lines exist; the sentence only shows when the set is empty. **Status: deviating: the drop zone is always shown, but its border is #848B95 dashed instead of the board #9AA1AA (decision 45, 3:1 on the grey page).**
- T-07 Action row. Board: disabled "Opslaan" with lock icon and explanation to its right on one line, "Wijzigingen weggooien" at the far right, red "Set leegmaken" below. Built: "Opslaan" grey without a lock, explanation under it, "Wijzigingen weggooien" and "Set leegmaken" sit in the top button row. **Status: closed (ef8cc99, a59c138): Opslaan with lock icon and explanation on one row, Wijzigingen weggooien at the right, Set leegmaken below.**
- T-08 Cost record list. Board: cards 346 x 57, name left (13/600) with amount `€ 84.000` right, below the supplier in muted 12px and the status ("● 100%") right-aligned; selected card has a 2px ink border and radius 8. Built: cards with name, amount below it with cents (`€ 42.000,00`), status below that; supplier missing; selected card shows a grey `#EEF1F5`-like fill with a 1px ink-ish border instead of a 2px ink border. **Status: deviating: layout, selected border (2px ink, radius 8) and kind line follow the board; amounts show cents and the status text stays "Toegewezen" / "Onbekende allocatie" (decision 40 and the established terminology).**
- T-09 Target tree. Board: grouped by Area with a small-caps heading and total ("RICHTEN € 21.000"), each Domain row on a `#F6F7F5` surface with a 10.5px kind label above the name, amount right in a column, "geen" for empty, "In set" with a check icon, "+" button 28 x 28. Built: flat list without Area headings or totals, each row a bordered box with name, "(Domein)" inline, amount inline after the name; "In set" as a plain grey chip; a text button per row. **Status: deviating: Area headings with totals from the calculation module, surface rows, kind label above the name, "geen", "In set" with check and "+" follow the board; amounts show cents (decision 40, board whole euros).**
- T-10 Indentation. Board 16px per level inside the same row surface. Built 16px margin (`ml-4`) plus a new bordered box per level. **Status: closed (ef8cc99, a59c138): 16px indentation inside the same row, no box per level.**

Typography
- T-11 Heading "Kostenposten 2026" and "Doelen op het canvas". Board Space Grotesk 13/18.2 600. Built "Doelen op het canvas" 15/22.5 600 system font. **Status: closed (ef8cc99, a59c138): Space Grotesk 13px 600.**
- T-12 Title "Licenties kantoorsoftware". Board Space Grotesk, 24px. Built 13px 600 system font in the list and 15px heading text "Verdeling van ..." in the middle. **Status: closed (ef8cc99, a59c138): 24px Space Grotesk title.**
- T-13 "Nog te verdelen" label. Board 11.5/16.1 600 `#8A5200`; value "20,00%" Space Grotesk 30/42 700 -0.6px `#8A5200`; amount 12.5/17.5. Built one line 13px bold ink. **Status: closed (ef8cc99, a59c138): label 11.5/600, value Space Grotesk 30px 700 -0.6px, colour #8A5200.**
- T-14 Percentage input. Board Space Grotesk-like 13 bold right-aligned inside 84 x 36. Built 13px 400 in 96px wide input. **Status: closed (ef8cc99, a59c138): Space Grotesk 13px bold, right-aligned in 84 x 36.**
- T-15 Target name. Board 12.5/17.5 600 (Strategie) with kind 10.5 above. Built 13/19.5 600, kind in parentheses 13px `#4B5563`. **Status: closed (ef8cc99, a59c138): 12.5px name with the 10.5px kind label above.**
- T-16 Status "Concept 80%". Board `#8A5200` Inter 12/normal 600 with ring marker. Built "Concept 80,00%" `#8A5A00` 13px bold with a different percentage format (two decimals). **Status: deviating: colour #8A5200 and weight 600 follow the board; the percentage keeps two decimals ("Concept 80,00%") by decision 40.**
- T-17 Buttons. Board 13/500. Built 13/400 (secondary) or 600. **Status: closed (ef8cc99, a59c138): buttons are the foundation AppButton (13px, weight 500).**

Colours and backgrounds
- T-18 Grey page versus white (S-03); panels `#FFFFFF` with 1px `#DCDFE2` radius 10 on the board, none built. **Status: closed (ef8cc99, a59c138): grey page, white panels with a 1px #DCDFE2 border and radius 10.**
- T-19 Nog-te-verdelen block `#FFF3D6` with radius 8 on the board, absent built. **Status: closed (ef8cc99, a59c138): #FFF3D6 block with radius 8.**
- T-20 Line stripes ink `#171B21`, `#5F6772` and `#9AA1AA` on the board, absent built. **Status: deviating: stripes ink and #5F6772 as on the board; the third stripe is the token #8A919B instead of #9AA1AA, which the foundation lacks (gap F-3).**
- T-21 "In set" `#1F7A4D` with check, `#F6F7F5` chip radius 5 on the board; built grey chip text ink. **Status: closed (ef8cc99, a59c138): "In set" in #1F7A4D with a check icon.**
- T-22 Danger button. Board text `#A12A1E` border `#D9B4AF`. Built "Set leegmaken" at 60% opacity with border `#C0645A` (contract value, but faded because disabled). **Status: deviating: danger button is the foundation variant, text #A12A1E and edge #C0645A (decision 45).**
- T-23 Status dots. Board green `#1F7A4D` and amber ring `#8A5200`; built `#1F7A3D` and `#8A5A00`. **Status: closed (ef8cc99, a59c138): dots use the tokens #1F7A4D and #8A5200 (ring for open states).**

Spacing, radius, shadow
- T-24 Middle card padding. Board 20px, radius 10; built none. **Status: closed (ef8cc99, a59c138): 20px padding, radius 10.**
- T-25 Row padding. Board target rows 8px 10px with 6px gap; built 4px 8px with 4px gap. **Status: closed (ef8cc99, a59c138): target rows 8px 10px.**
- T-26 List cards. Board gap 6px, padding 10px 12px, radius 8; built gap 4px, padding 6px 8px, radius 6. **Status: closed (ef8cc99, a59c138): cards gap 6px, padding 10px 12px, radius 8.**

Components
- T-27 Target add button. Board 28 x 28 "+" icon button; built a 163 x 30 text button "Toevoegen aan verdeling" per row (accessible name carries the target, but the visible label is long). **Status: closed (ef8cc99, a59c138): 28 x 28 plus icon button with the target in its accessible name.**
- T-28 Stacked bar. Board bar of coloured segments with a dashed amber outline for the rest; built none. **Status: closed (ef8cc99, a59c138): stacked bar of the lines with a dashed warning outline for the rest, 10px high.**
- T-29 Search fields. Board with magnifier icon and placeholder; built bare inputs with labels above. **Status: closed (ef8cc99, a59c138): foundation SearchField with icon and placeholder, label visually hidden.**
- T-30 Filter button on the list. Board "Filter"; built none. **Status: closed (ef8cc99, a59c138): Filter button added.**
- T-31 "Snel" quick actions. Board two buttons in a row after the label "Snel" ("Gelijk verdelen over 3 doelen", "Verdelen over activiteiten van een domein"). Built "Snel" is a single button (a menu). **Status: closed (ef8cc99, a59c138): "Snel" label with two buttons; the Domain split opens a small panel with the foundation SelectField.**
- T-32 Footnote. Board "Bedragen zijn opgeslagen toewijzingen. Het concept links telt pas mee na opslaan." at the end of the right panel. Built a status line at the page bottom ("Toevoegen aan verdeling: ...") and an instruction paragraph at the top of the panel. **Status: open: footnote is in place and the instruction paragraph is removed, but the shell StatusRegion text ("Toevoegen aan verdeling: ...") is visible at the page bottom; that is foundation gap F-4.**

States
- T-33 Selected cost record. Board 2px ink border; built 1px border and tinted fill (T-08). **Status: closed (ef8cc99, a59c138): 2px ink border.**
- T-34 Disabled Opslaan. Board `#E6E8E6` with lock and `#5F6772` text; built a mid-grey fill with white text and no lock (colour not measured). **Status: closed (ef8cc99, a59c138): foundation disabled colours #E6E8E6 with a lock icon.**
- T-35 Draft state. Board shows "Concept 80%" in the list card; built shows it only in the middle status line. **Status: closed (ef8cc99, a59c138): "Concept 80,00%" shows in the selected list card and next to the budget.**

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
