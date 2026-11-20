# Design comparison: built screens against the product owner's boards and the design contract

## Result after D1.20

Screenshots after D1.20 come from the merged release (`f506384`), except the three Bekijken ones (`bekijken-after.png`, `bekijken-passend-after.png`, `bekijken-detail-after.png`), which were retaken from the branch that carries the breadcrumb and detail panel fixes (commit 1938656, merged with this file). They use the same sample data as the first round, Chromium, viewport 1440 wide, full page; `bekijken-after.png` is viewport sized because the canvas scrolls inside its own container. The boards are in `specs/design/`; the copies next to this file are the ones used for the first round.

| Screen | Built (before) | After | Board |
|---|---|---|---|
| Sign-in | [sign-in-built.png](sign-in-built.png) | [sign-in-after.png](sign-in-after.png) | none |
| Overview | [overview-built.png](overview-built.png) | [overview-after.png](overview-after.png) | none |
| Invoeren | [invoeren-built.png](invoeren-built.png) | [invoeren-after.png](invoeren-after.png) | [invoeren-board.png](invoeren-board.png) |
| Bekijken, 100% | [bekijken-built.png](bekijken-built.png) | [bekijken-after.png](bekijken-after.png) | [bekijken-board.png](bekijken-board.png) |
| Bekijken, Passend maken | [bekijken-passend-built.png](bekijken-passend-built.png) | [bekijken-passend-after.png](bekijken-passend-after.png) | [bekijken-board.png](bekijken-board.png) |
| Bekijken, detail panel | [bekijken-detail-built.png](bekijken-detail-built.png) | [bekijken-detail-after.png](bekijken-detail-after.png) | none |
| User list with side panel | [gebruikers-built.png](gebruikers-built.png) | [gebruikers-after.png](gebruikers-after.png) | `specs/design/board-4-kostenposten.png` (nearest) |

### Measured values (computed styles)

| Measure | Before | After |
|---|---|---|
| Body font | system stack (`-apple-system`) | Inter, 16 pixels; controls Inter 13 |
| Page titles (h1) | system font, 20 pixels | Space Grotesk 22 pixels, 600 |
| Page background | transparent (white) | `rgb(242, 243, 241)` = `#F2F3F1` |
| Primary button and accent | navy `#1F3A5F` | ink `rgb(23, 27, 33)` = `#171B21`, 36 high, radius 6 |
| Top bar | 80 pixels, two rows | 56 pixels, one row |
| Control height | 34 pixels | 36 pixels (input and primary button) |
| Icon button (arrows) | text buttons 33 by 62 | 28 by 28, radius 4, border `#848B95` when enabled (decision 45), hairline `#DCDFE2` only when disabled |
| Tree row | 34 pixels, border `#848B95` | 38 pixels, border `#DCDFE2`, radius 6 |
| Area card | radius 10, border `#848B95`, 56 high | radius 10, border `#DCDFE2`, 50 high |

### Findings per status (47 findings)

| Status | Count |
|---|---|
| opgelost | 30 |
| bewust afwijkend | 15 |
| niet van toepassing | 2 |
| open | 0 |

What the screenshots show, honestly: the sign-in page, Overview, Invoeren, Bekijken and the user list now read as one visual language and Invoeren is close to board 1. The remaining visible differences are the ones recorded as "bewust afwijkend" (taller add line with a visible label, the "Alles opgeslagen" state only after a save, no Tekenen segment, the zoom toolbar in Bekijken, the Sluiten buttons). The original findings and their text follow unchanged, each with its status line.


## First round, 2026-11-19

Date: 2026-11-19. Built from release `1f6cc5b` (Invoeren, Bekijken and Modality merged; Quick entry and Direction editing are still open pull requests). Nothing was changed in this step.

## How the screenshots were made

- Sample data shaped like the boards: organisation "Voorbeeld BV", canvas "Voorbeeldcanvas", the primary activities, richten, beheersen, supporting activities and the three list Areas with the same domain, activity and group names as the boards, mission, vision and three goals, four users. The seed script is `.scratch/compare-seed.php` (not committed; ask for it if the comparison must be repeated).
- Chromium, viewport 1440 wide, full page, one screenshot per screen: `<screen>-built.png`.
- Where a board exists, a copy sits next to it: `invoeren-board.png` (board 1) and `bekijken-board.png` (board 2, which shows the financial overlay: only its canvas arrangement counts for D1).
- Measurements below are computed styles read from the running page, not estimates from the images.
- Boards do not exist for the sign-in page, the Overview and the user list. For those the reference is the design contract (`specs/001-omc-studio-v1/design-contract.md`), and for the user list with its side panel board 4 (Kostenposten: table with side-panel form) as the nearest board, `specs/design/board-4-kostenposten.png`.

## Findings that apply to every screen

1. Body font: the computed font family of body, headings and controls starts with `-apple-system`, so the system font stack is shown. The contract says Inter for body and Space Grotesk for headings, amounts and page titles. The Inter files are bundled (`resources/css/fonts.css`) but are not applied as the page font. Space Grotesk is applied only where a component names it ("OMC Studio", Area and section headings); the page titles measured as `h1` (sign-in "Inloggen", Overview "Overzicht", Invoeren and Bekijken "Voorbeeldcanvas") are not Space Grotesk.
Status: opgelost. Inter is the computed body font and Space Grotesk the page title font (22 pixels, 600), measured after.
2. Page background: computed background is transparent, so white. The contract has page `#F2F3F1`, panel and canvas surface `#F6F7F5`, cards white. The board structure (white panels floating on a grey page) does not exist anywhere.
Status: opgelost. The body background computes to rgb(242, 243, 241), that is `#F2F3F1`; the Overview, Invoeren and detail panels sit on `#F6F7F5` or white with a hairline and radius 10.
3. Accent colour: the active mode, the active tab underline and the primary button use `#1F3A5F` (navy). The contract has ink `#171B21` for primary buttons, the active segment and the active tab, and `#2459D6` only for focus and selection. The navy appears in no board.
Status: opgelost. Primary buttons, the active segment and the active tab are ink `#171B21`; the navy `#1F3A5F` is gone.
4. Top bar: 80 pixels high in two rows (logo and breadcrumb on top, tabs below, hairline under the tabs). Contract and boards: one row, 56 pixels, with the logo mark, "OMC Studio", the breadcrumb with its "Voorbeelddata" pill, the tabs inline in the middle and the fiscal year selector and user on the right. The logo mark is missing. The user is a bordered button "Administrator"; the boards show plain text ("Beheerder") with no border. Tabs for Kostenposten, Toewijzen and Versies belong to later deliveries; their place in the bar is not prepared.
Status: bewust afwijkend. The bar is one row of 56 pixels with the logo mark, "OMC Studio", the breadcrumb, the tabs and the role as plain text with a menu chevron. Deviations: the tabs Kostenposten, Toewijzen and Versies and the fiscal-year selector belong to delivery 2 (the fiscal-year slot exists and shows only when a fiscal year is passed), and the "Voorbeelddata" pill is never shown in delivery 1, because no sample canvas exists yet (the slot is built, delivery 3 supplies the flag). In Bekijken the breadcrumb showed only the canvas name; the page now uses the shared canvas prop, so it reads "Voorbeeld BV / Voorbeeldcanvas" as in Invoeren.
5. Controls: inputs and buttons measure 34 pixels high; the contract says 36 (32 in dense panels). Radius 6 on controls and 10 on Areas match.
Status: opgelost. Inputs and buttons measure 36 pixels high, icon buttons 28 by 28. The edge colour of secondary buttons and inputs is `#848B95` on purpose (decision 45), which goes before the boards `#DCDFE2` and `#AEB4BC`.
6. Icons: none are used. The boards use a search icon, a plus in "+ Domein", chevrons on the arrow buttons, warning triangles in callouts and a check next to "Alles opgeslagen".
Status: opgelost. Search icon, plus in "+ Domein" and "Nieuw", chevrons on the arrow buttons and selects, warning triangles in the callouts and pills are all visible; the check next to "Alles opgeslagen" shows after a save (see screen 3 point 2).
7. Focus ring and the saved state: not compared here (state, not layout).
Status: niet van toepassing. Not a layout finding; the focus ring of decision 46 is covered by the foundation tests.

## Screen 1: sign-in (`sign-in-built.png`; no board, contract only)

1. Layout: a bare 336-pixel column at the top left of the centre (about 40 pixels from the top), with no surface. There is no logo mark and no "OMC Studio" wordmark. The contract names no sign-in screen, but its surface rule (white card, hairline border, radius 10, on the grey page) is not applied.
Status: opgelost. A white panel with hairline and radius 10 on the grey page, with the logo mark and "OMC Studio" (`pages/Auth/AuthPanel.vue`, also used by the second-factor and new-password screens).
2. Title "Inloggen": Inter fallback (system font) 20 pixels, 600. Page titles are Space Grotesk 20 to 22, 600 to 700 per the contract.
Status: opgelost. Space Grotesk 22 pixels, 600.
3. Primary button "Inloggen": full width, 36 high, radius 6, fill `#1F3A5F`. Contract primary: ink `#171B21`.
Status: opgelost. Ink fill `#171B21`, 36 high.
4. Inputs 34 high with a `#848B95` border (matches the contract's border colour), labels bold above (matches), no hint line.
Status: opgelost. Inputs are 36 high with the `#848B95` edge; labels stay bold above; no hint line is specified.
5. Page background white instead of `#F2F3F1`.
Status: opgelost. The page token `#F2F3F1` is on the body.

## Screen 2: Overview (`overview-built.png`; no board, contract only)

1. Shell: the two-row top bar of finding 4 applies. Tabs read "Overzicht" and "Beheer"; the active tab has a navy underline instead of ink.
Status: opgelost. Closed by the shell: one row of 56 pixels, the active tab has an ink underline.
2. Page title "Overzicht" in the system font; contract: Space Grotesk. The section headings ("Voorbeeld BV", "Sjablonen") are Space Grotesk at about 15 pixels, which fits the "domain and card titles" size of the contract.
Status: opgelost. Space Grotesk 22 pixels, 600.
3. The Canvas card: 258 pixels wide, grey `#848B95` border, radius 10, with a faithful miniature of the canvas on top (the arrangement and Default colours are recognisable). It has no hairline border `#DCDFE2`, no hover state shown, and no status line or pill. The card has the "Acties" button below the date.
Status: bewust afwijkend. Hairline border, radius 8 (card token), a border-only hover and a pill for "Gearchiveerd" are built. Deviation: there is no status line because delivery 1 has no status data to show.
4. "Nieuw canvas" is a secondary button; the Overview in the contract's logic has the primary action in ink fill. "Toon gearchiveerd" is a bare checkbox with no 24-pixel target frame visible.
Status: opgelost. "Nieuw canvas" is primary ink with a plus icon; "Toon gearchiveerd" uses the shared check box, a 16-pixel box in a 24-pixel target (decision 46).
5. The page is a flat white sheet: no panel structure, no spacing rhythm (sections sit 16 to 40 pixels apart without panels).
Status: opgelost. Each organisation and the Templates sit on a surface panel with a hairline.

## Screen 3: Invoeren (`invoeren-built.png` against `invoeren-board.png`, board 1)

Shell and page header
1. Mode switch: two separate bordered buttons "Invoeren" and "Bekijken" with a gap; the board has one joined segmented control with three segments (Invoeren, Tekenen, Bekijken), ink fill on the active one. The active one here is navy. "Tekenen" is absent (later delivery).
Status: bewust afwijkend. One segmented control in the page header with ink fill on the active segment, beside the title. Deviation: "Tekenen" is absent because that mode is built in phase DT, after delivery 1.
2. The page title "Voorbeeldcanvas" sits under the switch; on the board it sits left of the switch on the same line, followed by the switch and the "Alles opgeslagen" state with a green check. The built page shows the switch first, then the title, then the button "Opslaan als sjabloon"; no saved state is visible. The board buttons "Snel invoeren" and "Importeren uit CSV" are on the right; "Snel invoeren" is not on release yet (open pull request), the import is later.
Status: bewust afwijkend. Title, switch, "Alles opgeslagen" with a check, then "Snel invoeren", a disabled "Importeren uit CSV" with its reason beside it, and "Opslaan als sjabloon". Deviation: "Alles opgeslagen" shows only after a save in this session (not in the screenshot), not permanently as on the board, so it never claims a save that has not happened; the import is disabled until delivery 3.

Left panel (Area list)
3. The list has no heading "Area's" and no explanatory sentence under it ("De acht area's liggen vast. Je vult ze met domeinen, clusters en activiteiten."). It is not a panel: the cards float on the white page. Board: a column of 210 to 225 pixels on the grey page.
Status: bewust afwijkend. Heading "Area’s", a 216-pixel column and the explanatory sentence are built. Deviation: white cards on the grey page without a surrounding white panel, as the board itself shows (the task text said "white panel").
4. Cards: radius 10, 56 pixels high, name 13 pixels semibold with a count line. The board cards are 48 pixels high with a 12.5-pixel muted count line; the selected card in the built screen has the 2-pixel ink border (matches the board).
Status: opgelost. Radius 10, hairline border, 2-pixel ink border when selected, muted count line; the screenshot matches the board.
5. The count lines differ in wording from the board only through the data, except that the built Koers card counts "5 teksten" (mission, vision, three goals); the board's 3 is a different sample.
Status: niet van toepassing. The difference is the sample data only.

Main work area
6. The Area is not inside a container. The board has one container (surface `#F6F7F5`, hairline border, radius 10) holding the Area title (Space Grotesk, about 16 pixels), the search field with a search icon and the primary ink button "+ Domein" on one row. Built: the heading, then "+ Domein" as a labelled text field with a bold label, then "Zoek in deze area" as a second labelled field, then a bare checkbox "Toon gearchiveerd" (not on the board), each stacked and full width. There is no "+ Domein" button at all; the board's primary ink button with a plus is missing.
Status: bewust afwijkend. A surface panel holds the title, the search field with its icon and the primary "Domein" button with a plus icon. Deviations: the button reveals the "+ Domein" text field (Enter adds, Escape closes and returns focus to the button) because the board shows no field; "Toon gearchiveerd" stays under the header (not on the board, and still a bare check box); the search field has a screen-reader label and a placeholder but no visible label.
7. Warnings: built as a bold heading and an amber bar above the tree, in the main column, one line "Leeg cluster: Veiligheid" in plain text. Board: a section at the bottom of the detail panel with the count at the right, and callouts with a triangle icon, bold kind label ("Leeg cluster:") and the name. Also the empty Domain "Huisvesting"-type warnings are not shown for the sample (data dependent).
Status: bewust afwijkend. Callouts with a triangle, bold kind label and the name, with the count, sit at the bottom of the detail panel as on the board. Deviation: while nothing is selected there is no panel, so the same list shows in the main column.
8. Tree rows: built rows are 34 pixels high, bordered in `#848B95` (a control border colour), with the type label in 12.5 pixels (contract: 10.5 muted), the name bold. Board rows: hairline border `#DCDFE2`, type label 10.5 muted, name regular for Activity and bold for Domain and Cluster; the names of Activities here are bold, which makes Domains and Activities indistinguishable except by the label and the indent.
Status: opgelost. Rows measure 38 pixels with the hairline border, the type label is 10.5 pixels muted, the name is bold for Domain and Cluster and regular for Activity; the screenshot matches the board.
9. Indent: 24 pixels per level (matches the contract).
Status: opgelost. 24 pixels per level, as the contract says.
10. Arrow buttons: built are text buttons "Omhoog" and "Omlaag", 33 high and about 62 wide, disabled ones grey text with a border; board: two 28 by 28 icon buttons with chevrons. The text labels are clearer to a screen reader but the shape and weight of the row differ strongly.
Status: opgelost. Two 28 by 28 icon buttons with chevrons; the accessible names "<name> omhoog" and "<name> omlaag" are kept. The border of an enabled button is `#848B95` (decision 45: a control edge needs 3:1) where the board draws a hairline, which is a contract decision and not a defect.
11. Modality badge: built is a 24-pixel circle with a letter; contract and board: a 16 by 16 square with radius 4 and an 11-pixel 700 letter. The colours match the contract (`#B4540A`, `#6B3FB5`, `#1E5FBF`).
Status: opgelost. 16 by 16 square, radius 4, letter 11 pixels 700, in the rows and the modality chips.
12. Add line: built is a bold label above ("Activiteit of cluster toevoegen aan X, Enter om op te slaan"), then a solid-bordered input with a checkbox "Cluster" at the right, 8 to 10 pixels apart. Board and contract: one dashed-border box (`#8A919B`) with that sentence as grey placeholder text inside it, and the choice "Activiteit | Cluster" next to it (decision 44). The dashed add line does not exist.
Status: bewust afwijkend. A dashed box with the choice "Activiteit | Cluster" as an accessible radio group. Deviation: the sentence is a persistent visible label above the input inside the box, not a placeholder, because the spec requires a visible label (US11, WB5, 3.3.2); the box is therefore taller than on the board. Question for the product owner.
13. The "leeg" mark on an empty Cluster is plain italic text; the contract has a warning-colour pill with a triangle.
Status: opgelost. A warning pill with a triangle ("leeg").

Detail panel
14. Built: a full-height panel flush with the right edge (a hairline on its left only), with the title, a "Sluiten" button at the top right and "Pad:" with a bold label above the form. Board: a white panel on the page with radius 10 and a margin, no close button, a muted line "Activiteit · Primaire activiteiten › Plaatsing & planning" above a 18-pixel Space Grotesk title.
Status: bewust afwijkend. A white docked panel (radius 10) with the kind and the path above the title. Deviation: "Sluiten" is kept because the focus-return behaviour and its tests need an explicit close control.
15. Fields: label bold above, a textarea for the description 90 pixels high (board 64) and for "Onderbouwing" (the board calls it "Onderbouwing modaliteit"). "Hangt onder" renders as a plain input without a select chevron and without the hint "Verplaatsen naar een ander domein of cluster doe je hier."
Status: opgelost. Three-row textareas, "Onderbouwing modaliteit", and "Hangt onder" as a select with a chevron and its hint.
16. Buttons: "Opslaan" is a secondary (white, bordered) button on its own row; "Archiveren" and "Verwijderen" form a second row. Board: "Opslaan" primary ink fill, "Archiveren" secondary and "Verwijderen" danger on one row, then a divider and the sentence about Tekenen. Missing too: the consequence text ("Geen kosten toegewezen in 2026. Verwijderen kan direct.", needs costs, later delivery) and the sentence linking to Tekenen.
Status: bewust afwijkend. "Opslaan" primary beside "Archiveren" and "Verwijderen" (danger), then a divider and the Tekenen sentence. Deviations: the consequence text ("Geen kosten toegewezen in 2026...") waits for costs (delivery 2); the Tekenen sentence is plain text, not a link, because the mode is built in phase DT, and it conflicts with US11 scenario 7 ("from DT") until then.
17. The Modality choice is a row of chips with letter badges and the selected chip has the 2-pixel ink border (matches the contract), but with the circular 24-pixel badges of point 11.
Status: opgelost. Square 16-pixel badges and the ink selection ring.

## Screen 4: Bekijken without overlay (`bekijken-built.png`, `bekijken-passend-built.png` against `bekijken-board.png`, board 2 arrangement)

`bekijken-built.png` is the page at 100 zoom (the 1680-pixel Artboard runs off the 1440-pixel viewport and is cut; the page has horizontal scrolling). `bekijken-passend-built.png` is after "Passend maken" (56%). `bekijken-detail-built.png` is the detail panel after selecting an Element.

1. Page header: title "Voorbeeldcanvas" without the mode switch beside it; the switch is above it. Toolbar "Uitzoomen / 100% / Inzoomen / Passend maken": three large bold bordered buttons. The board has no zoom toolbar; it has the overlay segment, the filter chips, the legend line and "Exporteren" (all later deliveries). The zoom buttons are an addition that needs a design.
Status: bewust afwijkend. The mode switch now sits beside the title as on the board. Deviation: the zoom toolbar (icon buttons of 28 pixels, the zoom value, "Passend maken") is an addition the board does not show; the board has the overlay controls, which are later deliveries. Question for the product owner.
2. Direction band: ink `#171B21` and 180 pixels high at 100% (matches). Missing: the dashed "Logo" box in the first column. Labels read "Missie", "Visie", "Doelen" in mixed case; board and contract: "MISSIE", "VISIE", "STRATEGISCHE DOELEN" as 11-pixel uppercase labels with letter spacing 0.04 to 0.06 em in `#C9CED6`. The goals are shown as three plain lines "Eén kindprofiel.: Van opvang..." with a stray colon after the full stop; the board shows a numbered list with the title in bold followed by the explanation.
Status: opgelost. The dashed "Logo" box, uppercase labels "MISSIE", "VISIE" and "STRATEGISCHE DOELEN" (11 pixels, 0.05 em, `#C9CED6`), and the goals as a numbered list with a bold title and no stray colon. At 100 percent the right column is cut by the viewport (horizontal scroll), as before.
3. Area headers: the contract has an uppercase Space Grotesk 13-pixel header ("RICHTEN", "PRIMAIRE ACTIVITEITEN") at the top left of each Area container. Built: for the three list Areas the header sits at the bottom of the container ("ACTOREN", "KANALEN", "WAARDEPROPOSITIES" at the foot), and for the activity Areas (Richten, Beheersen, Primaire, Ondersteunende) no header is visible.
Status: opgelost. The Area headers are uppercase Space Grotesk 13 at the top left of every container (visible in both screenshots).
4. Domain titles: in the Domain cards that have Activities (Strategie, Financiële sturing, Plaatsing & planning, Opvang & onderwijs, Ouders & communicatie, Personeel, Financiën, ICT) the Domain name is not visible; the card shows only the modality badge and the Activity cards. Cards without Activities ("Innovatie", "Kwaliteit & naleving", "Huisvesting") show their name. Board: every Domain card shows its title (Space Grotesk 14 pixels, 600) at the top left. The Cluster titles ("Contracten") are likewise missing from the Cluster blocks (only "Veiligheid", an empty Cluster, shows its name).
Status: opgelost. Domain and Cluster titles show in every card ("Strategie", "Plaatsing & planning", "Contracten").
5. List Areas (Actoren, Kanalen, Waardeproposities): the Group title and the first Item are drawn on top of each other ("Klanten" over "Ouders", "Partners" over "Gemeente", "Toezicht" over "GGD"). Board: the Group in bold with its Items on separate lines below it. This is a visible defect, not a style difference.
Status: opgelost. The Group titles sit in bold above their Items in Actoren, Kanalen and Waardeproposities; nothing overlaps.
6. Colours of the Domain cards: `#ECE8F5` (Richten, Beheersen), `#F6E6CC` (Primaire), `#D9E9F2` (Ondersteunende), white Activity cards with hairline border, Cluster at ink 6%: these match the contract. The modality badge sits at the top right of the Domain card as on the board, as a 16-pixel square: here correct.
Status: opgelost. The colours already matched the contract and still do.
7. Area container: white with a very pale hairline and radius 10 (matches); the spacing between Areas follows the engine's output; the board gaps are 12 pixels, the built gaps vary (10 to 14).
Status: opgelost. The gaps are the layout engine’s fixed 12 pixels; the spread seen earlier was a measurement artefact.
8. "Rand A3": drawn as a dashed line below the Artboard with the label at the right, 11 pixels; the contract describes a 1-pixel ink line with a hatch below it. The hatch is not visible in the screenshot (the content fits the Artboard, so only the edge shows).
Status: opgelost. A 1-pixel solid ink line with an 11-pixel muted label; the hatch appears only on overflow, and the sample content fits.
9. The read-only detail panel (`bekijken-detail-built.png`) opens at the right in the same flush, full-height form as in screen 3 point 14, with "Sluiten". It shows the kind in muted text, a 16-pixel square modality badge with its name, and the labelled values "Naam", "Omschrijving", "Onderbouwing", "Modaliteit" with "Niet ingevuld" for empty ones; the selected Activity has a blue accent frame, and a hover tooltip with name, kind and modality appears beside it. The board shows no read-only panel for this mode; it is not contradicted by the contract, but it has no design.
Status: opgelost. The panel is white, docked and radius 10 as in Invoeren, and it now shows the kind and the parent path above the title ("Activiteit · Primaire activiteiten › Plaatsing & planning") as Invoeren does; "Sluiten" stays a text button, as in Invoeren. The read-only panel has no board.

## Screen 5: user list with side panel (`gebruikers-built.png`; no board, board 4 is the nearest)

1. Page: heading "Gebruikers" in the system font; "Zoeken" as a bold label above a 194-pixel-wide input and the secondary button "Nieuw" and the text "4 resultaten" on one baseline. Board 4: a filter bar with chips ("Soort Alle") and a primary ink button with a plus icon; summary cards above the table (not applicable here).
Status: bewust afwijkend. Search field with icon, the result count and the primary ink "Nieuw" with a plus icon. Deviations: the Users spec defines no filters, so no filter chips show (they are built in the list component); the search label is a placeholder, not a visible label.
2. Table: header cells bold 12.5 pixels, rows separated by hairlines, the name as an underlined link in the browser's default underline, no sort indicators, no checkbox, no second line under the name; board 4: sortable headers, row checkbox, the name with its code on a second line, right-aligned numbers. The columns (Naam, E-mailadres, Rol) are spread over the full width with large gaps; board table columns are denser.
Status: bewust afwijkend. Sortable headers (the chevron shows on the sorted column only), the name with the email on a second line, hairline rows, hover and selected row (tint plus a 2-pixel ink left border). Deviations: no row checkbox (the Users spec has no bulk actions); the E-mailadres column and its sort are removed because the name column carries the email, so sorting by email is gone; the columns stay wide because the table fills the free width next to the panel.
3. Side panel: opens as a modal drawer of about 448 pixels from the right edge, full height, with a dark scrim over the whole page (the page behind turns grey) and the first field's text selected. Board 4 and the contract: a docked side panel of 320 to 390 pixels, white, radius 10, inside the page, without a scrim, the page remaining readable.
Status: bewust afwijkend. A docked panel of 360 pixels, white, radius 10, hairline, no scrim; the list reserves the space so the page stays readable. Deviation: the panel still traps focus and blocks pointer input behind it (WB8 and the existing overlay test). Question for the product owner.
4. Panel content: title "Mila de Vries", a "Sluiten" button at the top right, the fields "Naam", "E-mailadres", "Rol" (the Role renders as a plain input, no chevron), then one wrapping row of buttons "Opslaan", "Wachtwoord resetten", "Sessies beëindigen", "Verwijderen". Contract for the side-panel form: kind and path above the title, fields, "Opslaan" and "Annuleren", then the consequence text, then "Archiveren" and "Verwijderen" (danger). There is no "Annuleren", no consequence text, and "Opslaan" is secondary not primary.
Status: bewust afwijkend. Kind above an 18-pixel title, fields (the Role is a select with a chevron), "Opslaan" primary and "Annuleren", the non-destructive actions, then below a divider the consequence text and the danger "Verwijderen". Deviations: on open the first field is selected; the consequence text is not shown on the user’s own row, where "Verwijderen" is disabled; the Organisations panel has no consequence text and no "Verwijderen" (deleting goes through the row action and the typed-name dialog).

## Summary of the first round, 2026-11-19

- The colours of the canvas (Domain, Cluster and Activity fills), the modality colours, the radius of Areas and controls, the 24-pixel indent and the 2-pixel ink border of the selected Area card follow the contract.
- Everything that gives the boards their look is missing or different: the page and panel backgrounds, the ink accent (navy is used instead), the one-row top bar with logo, the segmented control, the icon buttons, the primary "+ Domein" button, the dashed add lines, the warning callouts with icons, the docked detail panel, Inter as body font and Space Grotesk page titles.
- Two defects are not about style: the Domain, Cluster and Area titles do not show in Bekijken (points 3 and 4 of screen 4) and the list Area Groups overlap their Items (point 5).

## Questions for the product owner and their decisions

1. Add line (screen 3 point 12): the board shows the sentence "Activiteit of cluster toevoegen aan ..." as placeholder-like text in a dashed line, the spec (WB5, US11, 3.3.2) requires a visible label. Built: a persistent label above the input inside the dashed box. Which wins? Decision of the product owner, 2026-11-20: confirmed for now, as built (a visible label inside the dashed box).
2. Search field (screen 3 point 6, screen 5 point 1): the board shows no visible label; built has a screen-reader label and a placeholder only. Is that acceptable against WB5? Decision of the orchestrator (technical), 2026-11-20: accepted (search field with icon, placeholder and an accessible name).
3. "+ Domein" (screen 3 point 6): the board shows a primary button only. Built: the button reveals a text field, Enter adds, Escape closes. Is a reveal wanted, or a dialog or an immediate empty row? Decision of the product owner, 2026-11-20: confirmed for now, as built (the button reveals the field).
4. "Alles opgeslagen" (screen 3 point 2): the board shows it permanently. Built: only after a save in this session. Should it always show? Decision of the product owner, 2026-11-20: confirmed for now, as built (only after a save in this session).
5. The Tekenen sentence in the detail panel (screen 3 point 16) against US11 scenario 7 ("from DT"): the sentence is plain text now. Does it show before DT exists, and is it a link later? Decision of the orchestrator (technical), 2026-11-20: the sentence stays plain text until Tekenen exists and then becomes a link.
6. The consequence text of the detail panel ("Geen kosten toegewezen in 2026. Verwijderen kan direct.") needs costs; it appears in delivery 2. Confirm that its absence in delivery 1 is accepted. Decision of the orchestrator (technical), 2026-11-20: accepted, the consequence text comes with delivery 2.
7. Side panel of the admin lists (screen 5 point 3): it traps focus and blocks the pointer behind it. Must it become truly non-modal (click another row while it is open)? That changes the focus-trap test (WB8). Decision of the product owner, 2026-11-20: confirmed for now, as built (the panel keeps the focus trap).
8. Users list (screen 5 point 2): the E-mailadres column and its sort are removed because the name column carries the email. Is losing the sort by email acceptable? Decision of the orchestrator (technical), 2026-11-20: the sort on e-mail is back as its own column; an existing function does not disappear in a design round.
9. Zoom toolbar in Bekijken (screen 4 point 1): the board shows no zoom controls. Keep the toolbar as built, move it, or drop it? Decision of the product owner, 2026-11-20: confirmed for now, as built (the zoom toolbar stays).
10. Direction labels (screen 4 point 2): "STRATEGISCHE DOELEN" is used in every place (band, hover text, detail panel). Confirm. Assumption, stays open for the product owner: "STRATEGISCHE DOELEN" is used in every place until he says otherwise.
11. "Toon gearchiveerd" in Invoeren (`ArchivedToggle.vue`) and the add line still use a bare check box instead of the shared 24-pixel target (carry-over of T382). Fix before D1.19? Decision of the orchestrator (technical), 2026-11-20: done for the archived toggle (shared 24-pixel target); the add line has no check box any more, its choice is a radio group with a large target.
12. Area list in Invoeren (screen 3 point 3): white cards on the grey page without a surrounding panel, as the board shows. The task text said "white panel". Keep the board form? Decision of the orchestrator (technical), 2026-11-20: the Area list stays as the board shows it.
