# Status of README screen 3 (Invoeren), T380

T383 merges this file into the README and deletes it.

1. Mode switch: bewust afwijkend. One segmented control in the page header, ink fill on the active segment; the segment "Tekenen" is absent because that mode is built in phase DT, after delivery 1.
2. Page header: bewust afwijkend. Title, switch, "Alles opgeslagen" with a check, then "Snel invoeren", a disabled "Importeren uit CSV" with its reason (aria-describedby) and "Opslaan als sjabloon" at the right. The deviation: "Alles opgeslagen" shows only after a save in this session (never on Koers), not permanently as on the board, so it never claims a save that has not happened. The import is disabled with a reason until delivery 3.
3. Area list: bewust afwijkend. Heading "Area’s", 216 px column and explanatory sentence are built; the deviation: white cards on the grey page without a surrounding white panel, as board 1 shows (the task text said "white panel").
4. Cards: opgelost. Radius 10, hairline border, 2 px ink border when selected, muted count line.
5. Count wording: niet van toepassing (the difference is data only).
6. Area container: bewust afwijkend. Surface panel with title, SearchField and the primary "Domein" button with a plus icon; the deviation: the button reveals the "+ Domein" text field (Enter adds, Escape closes and returns focus to the button) because the board shows no field. "Toon gearchiveerd" is kept under the header, not on the board. The search field has a screen-reader label and a placeholder, no visible label, because board 1 shows none.
7. Warnings: bewust afwijkend. Callouts with a triangle, bold kind label and the name, with the count, at the bottom of the detail panel; the deviation: while nothing is selected there is no panel, so the same list shows in the main column.
8. Tree rows: opgelost. 36 high, hairline border, type label 10.5 muted, bold name for Domain and Cluster, regular for Activity.
9. Indent: opgelost. 24 px per level, as the contract says.
10. Arrows: opgelost. Two 28 by 28 icon buttons with chevrons; the names ":name omhoog" and ":name omlaag" are kept.
11. Modality badge: opgelost. 16 by 16 square, radius 4, 11 px 700 letter (shared ModalityMark, used by the Invoeren rows and the modality chips only).
12. Add line: bewust afwijkend. Dashed box on white with the "Activiteit | Cluster" choice as an accessible radio group; the deviation: the sentence is a persistent visible muted label above the input inside the box, not a placeholder, because the spec requires a visible label (US11, WB5, 3.3.2) and the board shows placeholder-like text. Question for the product owner.
13. "leeg" mark: opgelost. Warning pill with a triangle.
14. Detail panel: bewust afwijkend. White docked panel (radius 10), kind and path to the parent above the title; the deviation: "Sluiten" is kept because the focus-return behaviour and tests need an explicit close control.
15. Fields: opgelost. 3-row textareas, "Onderbouwing modaliteit", "Hangt onder" as a select with chevron and its hint.
16. Buttons: bewust afwijkend. "Opslaan" primary beside "Archiveren" and "Verwijderen" (danger), then a divider and the sentence about Tekenen; the deviations: the consequence text ("Geen kosten toegewezen...") waits for costs (delivery 2), and the Tekenen sentence is plain text, not a link, because the mode is built in phase DT; it conflicts with US11 scenario 7 ("from DT") until then.
17. Modality chips: opgelost. Square 16 px badges, ink selection ring.
