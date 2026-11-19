# Status of README screen 3 (Invoeren), T380

T383 merges this file into the README and deletes it.

1. Mode switch: opgelost. One segmented control in the page header, ink fill on the active segment. "Tekenen" is bewust afwijkend: the mode does not exist before delivery 2.
2. Page header: opgelost. Title, switch, "Alles opgeslagen" with a check, then "Snel invoeren", a disabled "Importeren uit CSV" with its reason (aria-describedby) and "Opslaan als sjabloon" at the right. Bewust afwijkend: "Alles opgeslagen" shows only after a save in this session (never on Koers), not permanently as on the board, so it never claims a save that has not happened. The import is disabled with a reason until delivery 3.
3. Area list: opgelost with a deviation. Heading "Area’s", 216 px column, explanatory sentence. Bewust afwijkend: white cards on the grey page without a surrounding white panel, as board 1 shows (the task text said "white panel").
4. Cards: opgelost. Radius 10, hairline border, 2 px ink border when selected, muted count line.
5. Count wording: nothing to change (data only).
6. Area container: opgelost. Surface panel with title, SearchField and the primary "Domein" button with a plus icon. Bewust afwijkend: the button reveals the "+ Domein" text field (Enter adds, Escape closes and returns focus to the button) because the board shows no field. "Toon gearchiveerd" is kept under the header, not on the board. The search field has a screen-reader label and a placeholder, no visible label, because board 1 shows none.
7. Warnings: opgelost. Callouts with a triangle, bold kind label and the name, with the count, at the bottom of the detail panel. Bewust afwijkend: while nothing is selected there is no panel, so the same list shows in the main column.
8. Tree rows: opgelost. 36 high, hairline border, type label 10.5 muted, bold name for Domain and Cluster, regular for Activity.
9. Indent: unchanged, 24 px per level.
10. Arrows: opgelost. Two 28 by 28 icon buttons with chevrons; the names ":name omhoog" and ":name omlaag" are kept.
11. Modality badge: opgelost. 16 by 16 square, radius 4, 11 px 700 letter (shared ModalityMark, so Bekijken changes too).
12. Add line: opgelost with a deviation. Dashed box on white in the dashed token colour, with the "Activiteit | Cluster" choice as an accessible radio group. Bewust afwijkend: the sentence is a persistent visible muted label above the input inside the box, not a placeholder, because the spec requires a visible label (US11, WB5, 3.3.2) and the board shows placeholder-like text. Question for the product owner.
13. "leeg" mark: opgelost. Warning pill with a triangle.
14. Detail panel: opgelost with a deviation. White docked panel (radius 10), kind and path to the parent above the title. Bewust afwijkend: "Sluiten" is kept because the focus-return behaviour and tests need an explicit close control.
15. Fields: opgelost. 3-row textareas, "Onderbouwing modaliteit", "Hangt onder" as a select with chevron and its hint.
16. Buttons: opgelost with deviations. "Opslaan" primary beside "Archiveren" and "Verwijderen" (danger), then a divider and the sentence about Tekenen. Bewust afwijkend: the consequence text ("Geen kosten toegewezen...") waits for costs (later delivery). The Tekenen sentence is plain text, not a link, because the mode does not exist yet; it conflicts with US11 scenario 7 ("from DT") until delivery 2.
17. Modality chips: opgelost. Square 16 px badges, ink selection ring.
