# Kostenposten (board 4): status of findings K-01 to K-34 after T379

Branch feat/d210-kosten. Statuses: closed (with the commit), open (with the reason) or deviating (with the reason). The findings themselves are in README.md of this folder (D1 comparison). Evidence: `kostenposten-built-v2.png` (1440 pixels, full page, Canvas variant, panel open) next to `kostenposten-board.png`; measured values are asserted in `tests/e2e/design-kostenposten.spec.ts`.

Known rough edge: with the panel open one long name ("Leerlingadministratie") can break inside the word at 1440 pixels.

| Finding | Status | Commit and note |
|---|---|---|
| K-01 | deviating | 7a8b6cf, 39721e8: title, card row, toolbar, one table panel and the docked form follow the board. Deviations: (a) the header carries six actions where the board has three, so with the panel open they wrap to a second row; the foundation has no menu component to fold the secondary actions into; (b) the organisation page has no summary data, so no cards. |
| K-02 | closed | 7a8b6cf: three summary cards then the finalise card, one row of four equal columns; height 129 against 124. |
| K-03 | closed | 2caa564: the form is a docked panel beside the table (`ListDock`), the table stays usable, Escape inside the panel closes it with the unsaved-changes guard. |
| K-04 | closed | 7a8b6cf: Naam full width, Code and Soort, Eigenaar, Applicatie, Leverancier and Kostenplaats (decision 42), Omschrijving; Jaarwaarden in two columns, Peildatum present. Label "Soort kosten" kept (specs address it by name). |
| K-05 | closed | 2caa564, 7a8b6cf: Soort, Eigenaar, Applicatie columns, code beneath the name, right-aligned amounts, delta; the per-row Verwijderen button moved into the panel as on the board. |
| K-06 | closed | 2caa564: white panel, 1px `#DCDFE2`, radius 10, header 11.5/600. |
| K-07 | closed | 2caa564: padding 10 by 12 (right 8 to fit cents), two-line rows 54px. |
| K-08 | closed | 2caa564: one row with search, four inline-label filter chips, archive toggle and count. |
| K-09 | closed | 7a8b6cf: "Kostenposten 2026" in Space Grotesk 20/600 with -0.2px; the organisation name is in the breadcrumb. |
| K-10 | closed | 7a8b6cf: Space Grotesk on the H1, card amounts and panel headings (foundation tokens). |
| K-11 | closed | 7a8b6cf: card label 11.5 muted, 400. |
| K-12 | deviating | 39721e8: the amount keeps Space Grotesk 22/700 with -0.22px, but shows cents ("€ 744.000,00") and the Totaal budget card equals the hand sum to the cent (spec.md FR-013 and decision 40 allow whole euros only on the canvas and overlay). |
| K-13 | closed | 7a8b6cf: sub line 12px `#454C57`, "Prognose € 752.000". |
| K-14 | closed | 2caa564: header 11.5/600 `#454C57`. |
| K-15 | deviating | Table amounts keep cents (decision 40); right-aligned and 600 as on the board (2caa564). |
| K-16 | closed | 2caa564: name 13/600 without underline, code 11.5 muted beneath. |
| K-17 | closed | 7a8b6cf: "Jaarwaarden 2026" Space Grotesk 13/600. |
| K-18 | closed | Foundation page `#F2F3F1`; panels white with `#DCDFE2` (2caa564, 7a8b6cf). |
| K-19 | closed | 2caa564, 39721e8: docked panel white, hairline, radius 10, padding 16, 360px wide (the foundation detail-panel token; the board shows 352). |
| K-20 | deviating | 7a8b6cf: colours `#1F7A4D` and `#8A5200` with filled dot and open ring. The text "Onbekende allocatie" stays (ADR 0008 term and existing specs) where the board says "Niet toegewezen". |
| K-21 | closed | 7a8b6cf: delta amber when above budget, green below, signed. |
| K-22 | closed | 7a8b6cf: cards 14 by 16, radius 10, hairline border. |
| K-23 | closed | 7a8b6cf: 12px between cards, 16px between sections. |
| K-24 | deviating | 7a8b6cf: finalise card with `AppButton` and lock icon, disabled fill `#E6E8E6`. The sentence keeps "zijn toegewezen" (finalise-card spec) where the board says "met budget". |
| K-25 | closed | 2caa564: selected row `#F6F7F5` with a 2px ink bar. |
| K-26 | deviating | 2caa564: 16px box in a 24px target with an ink accent; the 4px radius needs a styled checkbox the foundation lacks. |
| K-27 | deviating | 7a8b6cf, 39721e8: Opslaan ink and Annuleren side by side, Archiveren and Verwijderen (danger) below a rule with the consequence text. The record and the Jaarwaarden save separately (two resources), so there are two save buttons. Annuleren with unsaved edits asks for confirmation like Escape. |
| K-28 | deviating | 39721e8: one shared `NativeSelect` with chevron (36px, radius 6) used by the form and the filter chips. Border is `#848B95` (decision 45). It stays native because the foundation `SelectField` (reka) cannot hold an empty value item and the specs drive selects with `selectOption`. |
| K-29 | closed | 2caa564: search field with icon and placeholder "Zoek op naam of code", 253 by 36. |
| K-30 | deviating | 7a8b6cf: ink "+ Nieuw" with plus icon, "Importeren uit CSV", 8px gaps. "Jaar overnemen" keeps its label because the source year is chosen in the dialog. |
| K-31 | closed | 2caa564: archive toggle and count right-aligned in the filter row. |
| K-32 | closed | 7a8b6cf: hint 11.5 `#5F6772` (foundation `Field`). |
| K-33 | deviating | Focus ring is the dark plus light double ring of decision 46; on opening the panel focus goes to its heading, so no text is selected and no ring sits on a field (2caa564). Unsaved edits are guarded on Escape, Annuleren, row switch, Nieuw and year change (39721e8). |
| K-34 | closed | 7a8b6cf: disabled finalise with lock and `#E6E8E6`; empty table shows "Geen resultaten". |

## Foundation gaps

- No Checkbox component with a styled 16px box in a 24px target (K-26).
- No styled native select in the foundation; `SelectField` cannot hold an empty value item and the specs use `selectOption` (K-28). `components/fields/NativeSelect.vue` was added by this task.
- No menu component to fold secondary header actions into (K-01).
- AppShell `main` has p-4 where the board has 24px side padding.
