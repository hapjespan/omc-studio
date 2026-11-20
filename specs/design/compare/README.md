# Design comparison: built screens against the product owner's boards and the design contract

Date: 2026-11-19. Built from release `1f6cc5b` (Invoeren, Bekijken and Modality merged; Quick entry and Direction editing are still open pull requests). Nothing was changed in this step.

## How the screenshots were made

- Sample data shaped like the boards: organisation "Voorbeeld BV", canvas "Voorbeeldcanvas", the primary activities, richten, beheersen, supporting activities and the three list Areas with the same domain, activity and group names as the boards, mission, vision and three goals, four users. The seed script is `.scratch/compare-seed.php` (not committed; ask for it if the comparison must be repeated).
- Chromium, viewport 1440 wide, full page, one screenshot per screen: `<screen>-built.png`.
- Where a board exists, a copy sits next to it: `invoeren-board.png` (board 1) and `bekijken-board.png` (board 2, which shows the financial overlay: only its canvas arrangement counts for D1).
- Measurements below are computed styles read from the running page, not estimates from the images.
- Boards do not exist for the sign-in page, the Overview and the user list. For those the reference is the design contract (`specs/001-omc-studio-v1/design-contract.md`), and for the user list with its side panel board 4 (Kostenposten: table with side-panel form) as the nearest board, `specs/design/board-4-kostenposten.png`.

## Findings that apply to every screen

1. Body font: the computed font family of body, headings and controls starts with `-apple-system`, so the system font stack is shown. The contract says Inter for body and Space Grotesk for headings, amounts and page titles. The Inter files are bundled (`resources/css/fonts.css`) but are not applied as the page font. Space Grotesk is applied only where a component names it ("OMC Studio", Area and section headings); the page titles measured as `h1` (sign-in "Inloggen", Overview "Overzicht", Invoeren and Bekijken "Voorbeeldcanvas") are not Space Grotesk.
2. Page background: computed background is transparent, so white. The contract has page `#F2F3F1`, panel and canvas surface `#F6F7F5`, cards white. The board structure (white panels floating on a grey page) does not exist anywhere.
3. Accent colour: the active mode, the active tab underline and the primary button use `#1F3A5F` (navy). The contract has ink `#171B21` for primary buttons, the active segment and the active tab, and `#2459D6` only for focus and selection. The navy appears in no board.
4. Top bar: 80 pixels high in two rows (logo and breadcrumb on top, tabs below, hairline under the tabs). Contract and boards: one row, 56 pixels, with the logo mark, "OMC Studio", the breadcrumb with its "Voorbeelddata" pill, the tabs inline in the middle and the fiscal year selector and user on the right. The logo mark is missing. The user is a bordered button "Administrator"; the boards show plain text ("Beheerder") with no border. Tabs for Kostenposten, Toewijzen and Versies belong to later deliveries; their place in the bar is not prepared.
5. Controls: inputs and buttons measure 34 pixels high; the contract says 36 (32 in dense panels). Radius 6 on controls and 10 on Areas match.
6. Icons: none are used. The boards use a search icon, a plus in "+ Domein", chevrons on the arrow buttons, warning triangles in callouts and a check next to "Alles opgeslagen".
7. Focus ring and the saved state: not compared here (state, not layout).

## Screen 1: sign-in (`sign-in-built.png`; no board, contract only)

1. Layout: a bare 336-pixel column at the top left of the centre (about 40 pixels from the top), with no surface. There is no logo mark and no "OMC Studio" wordmark. The contract names no sign-in screen, but its surface rule (white card, hairline border, radius 10, on the grey page) is not applied.
2. Title "Inloggen": Inter fallback (system font) 20 pixels, 600. Page titles are Space Grotesk 20 to 22, 600 to 700 per the contract.
3. Primary button "Inloggen": full width, 36 high, radius 6, fill `#1F3A5F`. Contract primary: ink `#171B21`.
4. Inputs 34 high with a `#848B95` border (matches the contract's border colour), labels bold above (matches), no hint line.
5. Page background white instead of `#F2F3F1`.

## Screen 2: Overview (`overview-built.png`; no board, contract only)

1. Shell: the two-row top bar of finding 4 applies. Tabs read "Overzicht" and "Beheer"; the active tab has a navy underline instead of ink.
2. Page title "Overzicht" in the system font; contract: Space Grotesk. The section headings ("Voorbeeld BV", "Sjablonen") are Space Grotesk at about 15 pixels, which fits the "domain and card titles" size of the contract.
3. The Canvas card: 258 pixels wide, grey `#848B95` border, radius 10, with a faithful miniature of the canvas on top (the arrangement and Default colours are recognisable). It has no hairline border `#DCDFE2`, no hover state shown, and no status line or pill. The card has the "Acties" button below the date.
4. "Nieuw canvas" is a secondary button; the Overview in the contract's logic has the primary action in ink fill. "Toon gearchiveerd" is a bare checkbox with no 24-pixel target frame visible.
5. The page is a flat white sheet: no panel structure, no spacing rhythm (sections sit 16 to 40 pixels apart without panels).

## Screen 3: Invoeren (`invoeren-built.png` against `invoeren-board.png`, board 1)

Shell and page header
1. Mode switch: two separate bordered buttons "Invoeren" and "Bekijken" with a gap; the board has one joined segmented control with three segments (Invoeren, Tekenen, Bekijken), ink fill on the active one. The active one here is navy. "Tekenen" is absent (later delivery).
2. The page title "Voorbeeldcanvas" sits under the switch; on the board it sits left of the switch on the same line, followed by the switch and the "Alles opgeslagen" state with a green check. The built page shows the switch first, then the title, then the button "Opslaan als sjabloon"; no saved state is visible. The board buttons "Snel invoeren" and "Importeren uit CSV" are on the right; "Snel invoeren" is not on release yet (open pull request), the import is later.

Left panel (Area list)
3. The list has no heading "Area's" and no explanatory sentence under it ("De acht area's liggen vast. Je vult ze met domeinen, clusters en activiteiten."). It is not a panel: the cards float on the white page. Board: a column of 210 to 225 pixels on the grey page.
4. Cards: radius 10, 56 pixels high, name 13 pixels semibold with a count line. The board cards are 48 pixels high with a 12.5-pixel muted count line; the selected card in the built screen has the 2-pixel ink border (matches the board).
5. The count lines differ in wording from the board only through the data, except that the built Koers card counts "5 teksten" (mission, vision, three goals); the board's 3 is a different sample.

Main work area
6. The Area is not inside a container. The board has one container (surface `#F6F7F5`, hairline border, radius 10) holding the Area title (Space Grotesk, about 16 pixels), the search field with a search icon and the primary ink button "+ Domein" on one row. Built: the heading, then "+ Domein" as a labelled text field with a bold label, then "Zoek in deze area" as a second labelled field, then a bare checkbox "Toon gearchiveerd" (not on the board), each stacked and full width. There is no "+ Domein" button at all; the board's primary ink button with a plus is missing.
7. Warnings: built as a bold heading and an amber bar above the tree, in the main column, one line "Leeg cluster: Veiligheid" in plain text. Board: a section at the bottom of the detail panel with the count at the right, and callouts with a triangle icon, bold kind label ("Leeg cluster:") and the name. Also the empty Domain "Huisvesting"-type warnings are not shown for the sample (data dependent).
8. Tree rows: built rows are 34 pixels high, bordered in `#848B95` (a control border colour), with the type label in 12.5 pixels (contract: 10.5 muted), the name bold. Board rows: hairline border `#DCDFE2`, type label 10.5 muted, name regular for Activity and bold for Domain and Cluster; the names of Activities here are bold, which makes Domains and Activities indistinguishable except by the label and the indent.
9. Indent: 24 pixels per level (matches the contract).
10. Arrow buttons: built are text buttons "Omhoog" and "Omlaag", 33 high and about 62 wide, disabled ones grey text with a border; board: two 28 by 28 icon buttons with chevrons. The text labels are clearer to a screen reader but the shape and weight of the row differ strongly.
11. Modality badge: built is a 24-pixel circle with a letter; contract and board: a 16 by 16 square with radius 4 and an 11-pixel 700 letter. The colours match the contract (`#B4540A`, `#6B3FB5`, `#1E5FBF`).
12. Add line: built is a bold label above ("Activiteit of cluster toevoegen aan X, Enter om op te slaan"), then a solid-bordered input with a checkbox "Cluster" at the right, 8 to 10 pixels apart. Board and contract: one dashed-border box (`#8A919B`) with that sentence as grey placeholder text inside it, and the choice "Activiteit | Cluster" next to it (decision 44). The dashed add line does not exist.
13. The "leeg" mark on an empty Cluster is plain italic text; the contract has a warning-colour pill with a triangle.

Detail panel
14. Built: a full-height panel flush with the right edge (a hairline on its left only), with the title, a "Sluiten" button at the top right and "Pad:" with a bold label above the form. Board: a white panel on the page with radius 10 and a margin, no close button, a muted line "Activiteit · Primaire activiteiten › Plaatsing & planning" above a 18-pixel Space Grotesk title.
15. Fields: label bold above, a textarea for the description 90 pixels high (board 64) and for "Onderbouwing" (the board calls it "Onderbouwing modaliteit"). "Hangt onder" renders as a plain input without a select chevron and without the hint "Verplaatsen naar een ander domein of cluster doe je hier."
16. Buttons: "Opslaan" is a secondary (white, bordered) button on its own row; "Archiveren" and "Verwijderen" form a second row. Board: "Opslaan" primary ink fill, "Archiveren" secondary and "Verwijderen" danger on one row, then a divider and the sentence about Tekenen. Missing too: the consequence text ("Geen kosten toegewezen in 2026. Verwijderen kan direct.", needs costs, later delivery) and the sentence linking to Tekenen.
17. The Modality choice is a row of chips with letter badges and the selected chip has the 2-pixel ink border (matches the contract), but with the circular 24-pixel badges of point 11.

## Screen 4: Bekijken without overlay (`bekijken-built.png`, `bekijken-passend-built.png` against `bekijken-board.png`, board 2 arrangement)

`bekijken-built.png` is the page at 100 zoom (the 1680-pixel Artboard runs off the 1440-pixel viewport and is cut; the page has horizontal scrolling). `bekijken-passend-built.png` is after "Passend maken" (56%). `bekijken-detail-built.png` is the detail panel after selecting an Element.

1. Page header: title "Voorbeeldcanvas" without the mode switch beside it; the switch is above it. Toolbar "Uitzoomen / 100% / Inzoomen / Passend maken": three large bold bordered buttons. The board has no zoom toolbar; it has the overlay segment, the filter chips, the legend line and "Exporteren" (all later deliveries). The zoom buttons are an addition that needs a design.
2. Direction band: ink `#171B21` and 180 pixels high at 100% (matches). Missing: the dashed "Logo" box in the first column. Labels read "Missie", "Visie", "Doelen" in mixed case; board and contract: "MISSIE", "VISIE", "STRATEGISCHE DOELEN" as 11-pixel uppercase labels with letter spacing 0.04 to 0.06 em in `#C9CED6`. The goals are shown as three plain lines "Eén kindprofiel.: Van opvang..." with a stray colon after the full stop; the board shows a numbered list with the title in bold followed by the explanation.
3. Area headers: the contract has an uppercase Space Grotesk 13-pixel header ("RICHTEN", "PRIMAIRE ACTIVITEITEN") at the top left of each Area container. Built: for the three list Areas the header sits at the bottom of the container ("ACTOREN", "KANALEN", "WAARDEPROPOSITIES" at the foot), and for the activity Areas (Richten, Beheersen, Primaire, Ondersteunende) no header is visible.
4. Domain titles: in the Domain cards that have Activities (Strategie, Financiële sturing, Plaatsing & planning, Opvang & onderwijs, Ouders & communicatie, Personeel, Financiën, ICT) the Domain name is not visible; the card shows only the modality badge and the Activity cards. Cards without Activities ("Innovatie", "Kwaliteit & naleving", "Huisvesting") show their name. Board: every Domain card shows its title (Space Grotesk 14 pixels, 600) at the top left. The Cluster titles ("Contracten") are likewise missing from the Cluster blocks (only "Veiligheid", an empty Cluster, shows its name).
5. List Areas (Actoren, Kanalen, Waardeproposities): the Group title and the first Item are drawn on top of each other ("Klanten" over "Ouders", "Partners" over "Gemeente", "Toezicht" over "GGD"). Board: the Group in bold with its Items on separate lines below it. This is a visible defect, not a style difference.
6. Colours of the Domain cards: `#ECE8F5` (Richten, Beheersen), `#F6E6CC` (Primaire), `#D9E9F2` (Ondersteunende), white Activity cards with hairline border, Cluster at ink 6%: these match the contract. The modality badge sits at the top right of the Domain card as on the board, as a 16-pixel square: here correct.
7. Area container: white with a very pale hairline and radius 10 (matches); the spacing between Areas follows the engine's output; the board gaps are 12 pixels, the built gaps vary (10 to 14).
8. "Rand A3": drawn as a dashed line below the Artboard with the label at the right, 11 pixels; the contract describes a 1-pixel ink line with a hatch below it. The hatch is not visible in the screenshot (the content fits the Artboard, so only the edge shows).
9. The read-only detail panel (`bekijken-detail-built.png`) opens at the right in the same flush, full-height form as in screen 3 point 14, with "Sluiten". It shows the kind in muted text, a 16-pixel square modality badge with its name, and the labelled values "Naam", "Omschrijving", "Onderbouwing", "Modaliteit" with "Niet ingevuld" for empty ones; the selected Activity has a blue accent frame, and a hover tooltip with name, kind and modality appears beside it. The board shows no read-only panel for this mode; it is not contradicted by the contract, but it has no design.

## Screen 5: user list with side panel (`gebruikers-built.png`; no board, board 4 is the nearest)

1. Page: heading "Gebruikers" in the system font; "Zoeken" as a bold label above a 194-pixel-wide input and the secondary button "Nieuw" and the text "4 resultaten" on one baseline. Board 4: a filter bar with chips ("Soort Alle") and a primary ink button with a plus icon; summary cards above the table (not applicable here).
2. Table: header cells bold 12.5 pixels, rows separated by hairlines, the name as an underlined link in the browser's default underline, no sort indicators, no checkbox, no second line under the name; board 4: sortable headers, row checkbox, the name with its code on a second line, right-aligned numbers. The columns (Naam, E-mailadres, Rol) are spread over the full width with large gaps; board table columns are denser.
3. Side panel: opens as a modal drawer of about 448 pixels from the right edge, full height, with a dark scrim over the whole page (the page behind turns grey) and the first field's text selected. Board 4 and the contract: a docked side panel of 320 to 390 pixels, white, radius 10, inside the page, without a scrim, the page remaining readable.
4. Panel content: title "Mila de Vries", a "Sluiten" button at the top right, the fields "Naam", "E-mailadres", "Rol" (the Role renders as a plain input, no chevron), then one wrapping row of buttons "Opslaan", "Wachtwoord resetten", "Sessies beëindigen", "Verwijderen". Contract for the side-panel form: kind and path above the title, fields, "Opslaan" and "Annuleren", then the consequence text, then "Archiveren" and "Verwijderen" (danger). There is no "Annuleren", no consequence text, and "Opslaan" is secondary not primary.

## Summary for the product owner

- The colours of the canvas (Domain, Cluster and Activity fills), the modality colours, the radius of Areas and controls, the 24-pixel indent and the 2-pixel ink border of the selected Area card follow the contract.
- Everything that gives the boards their look is missing or different: the page and panel backgrounds, the ink accent (navy is used instead), the one-row top bar with logo, the segmented control, the icon buttons, the primary "+ Domein" button, the dashed add lines, the warning callouts with icons, the docked detail panel, Inter as body font and Space Grotesk page titles.
- Two defects are not about style: the Domain, Cluster and Area titles do not show in Bekijken (points 3 and 4 of screen 4) and the list Area Groups overlap their Items (point 5).
