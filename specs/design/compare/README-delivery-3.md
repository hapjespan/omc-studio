# Delivery 3 screens: design conformance (T387)

Built from `trial/d2-release` at 29d7fb7, served on 127.0.0.1:8260 with SQLite and neutral seed data (organisations "Voorbeeld", "Stuurgroep canvas" and the sample organisation; two snapshots, one marked imported; two identical closing-check notices). Screens captured with Playwright (chromium, width 1440, full page, signed in as administrator) before and after the change: `<screen>-d3-before.png` and `<screen>-d3-after.png` in this folder for versies, versies-formulier, export-import, export-import-rapport, importeren, csv-importeren, voorbeelddata, voorbeelddata-dialoog and systeemmeldingen. No board exists for these screens, so the contract tokens (`design-contract.md`) and the foundation components decide; decisions 40, 45 and 46 take precedence (control edges `#848B95`, danger outline `#C0645A`, 2-pixel focus rings). Values are computed styles read in chromium at zoom 100 percent by `tests/e2e/design-delivery-3.spec.ts`.

Status words: closed (with the commit), open (with the reason), deviating (with the reason). Commits are those of branch `feat/d210-d3` (see the commit list in the report of T387).

Foundation gaps for the first session:
- G-1 No panel component: the white panel (`rounded-panel border border-hairline bg-white`) is a class string, as on Kostenposten.
- G-2 `AppButton` renders a button only; "Alles exporteren" must stay a link with `download` (test and accessibility), so its classes repeat the AppButton secondary look. An `href` mode would remove this copy.
- G-3 No file field. `components/fields/FileField.vue` was added next to the other fields (label, hint, error through `Field`, 36 pixel box, 28 pixel native file button). Proposed for the foundation.
- G-4 `Pill` has no danger tone; the import status "fout" uses the warning tone.
- G-5 The shell status region repeats the announced message as visible text under the main content (seen under the Exchange panel); same as F-4 of Toewijzen.
- G-6 `Finance/CostRecordImport.vue` (delivery 2, not in this task) still has the old buttons, bare file input and plain headings; it shares `ImportReport` and now gets its report styling but not its form.

## Findings before the build (all screens)

- D3-01 Buttons. Built `rounded-md border-[#848B95] px-3 py-1.5 text-[13px] font-semibold`: 32 pixels high, weight 600, hex literals, radius 6. Contract: 36 pixels (control), weight 500, tokens. Differed on height, weight and token use.
- D3-02 File inputs. Native control in the browser default font (16px serif-like "Choose File"), no box, no border. Contract: field 36 high, 13px Inter, border `#848B95`.
- D3-03 Panels. None; every form and report sat directly on the grey page. Contract: white panel, 1px `#DCDFE2`, radius 10, padding 16 to 24.
- D3-04 Report tables (counts, errors, lines). Unstyled `<table>`: no padding, header 13px 600 black, no row separators. Contract and Kostenposten: header 11.5/600 secondary `#454C57`, cells 10px 12px, hairline row separators inside a bordered card.
- D3-05 Status text of the report lines ("nieuw", "fout") plain text. Contract: dot or pill, never colour alone.
- D3-06 Page titles `text-[20px] font-semibold` without the 0.2px tracking and not in a header row; page content started at `mt-3` instead of the 16/24 grid.
- D3-07 Versies. List form opened as a full-height overlay sheet (ListForm) instead of the docked panel; create button in the toolbar next to search; raw `<select>` for Boekjaar with the browser arrow; "Geïmporteerd" mark and the view link were never shown to administrators because the first column is the edit button; row "Verwijderen" button per row.
- D3-08 Voorbeelddata. Bare paragraphs on the page, outlined danger button with hand-written classes, no panel; flash message plain text.
- D3-09 Systeemmeldingen. Filter fields on the page with raw `<select>` elements, notice cards `border-[#848B95]` radius 6 (contract card border `#DCDFE2`, radius 10 for panels), "Gezien" button 32 high with hand-written classes, empty state a plain sentence.
- D3-10 Snapshot banner. Fill `#EEF1F5`, border `#848B95`, radius 6, system-font text. Contract surface `#F6F7F5`, hairline `#DCDFE2`, radius 8, Space Grotesk for titles.

## Versies (`Versions/Index.vue`, `SnapshotForm.vue`)

- D3-07 closed: table in the shared `ListView` white panel (white, 1px rgb(220,223,226), radius 10px), title 20px/600 Space Grotesk in a header row with the primary "Momentopname maken" button (36px, ink fill, radius 6px, plus icon), form as the docked `ListDock` panel (360px wide, white, radius 10px, padding 16px) with `TextField`, `NativeSelect`, `TextArea`, primary "Opslaan" and "Annuleren" at 36px; archive and delete moved into the panel under the consequence text (Kostenposten pattern), so the row "Verwijderen" button is gone and `snapshot-form.spec.ts` opens the row first (markup change, intent kept: states what goes, removes the row, no undo). Columns "Geïmporteerd" (Pill, 11.5px) and "Openen" (link for every role, name in the accessible name) added; empty remark and maker read "geen" in muted.
- Table header 11.5px/600 `#454C57`, cells 10px 12px 13px, name 600: measured.
- Status: closed (see commit list).

## Volledige export en import (`Admin/Exchange.vue`)

- D3-03, D3-01, D3-02 closed: two white panels (radius 10px, padding 24px) with Space Grotesk 16px headings, "Controleren" primary and "Importeren" secondary (36px, disabled `#E6E8E6`), file field with label, hint and refusal through `FileField`, report in a white panel with `CountsReport`. "Alles exporteren" is a link with the secondary look, 36px, weight 500, radius 6px (gap G-2).
- Open: G-5 (visible status text under the panel).

## Importeren (`Overview/ImportCanvas.vue`, `CsvImportPanel.vue`, `ImportReport.vue`, `CountsReport.vue`)

- D3-01 to D3-05 closed: the entry is a 36px `AppButton`; the panel is white (radius 10px, padding 24px, max width 576px); fields are the foundation `TextField`, `SelectField` and `FileField`; the reports are tables inside a bordered card (radius 8px, header 11.5/600 `#454C57`, cells 10px 12px, hairline separators); `CountsReport` serves both JSON screens, the error total is green at 0 and red above; line statuses are `Pill`s (gap G-4). The Overview page itself is not part of this task.
- The structure and allocation CSV panels share `CsvImportPanel`; measured on Toewijzen.

## Voorbeelddata (`Admin/SampleData.vue`)

- D3-08 closed: title row, white panel (radius 10px, padding 24px), danger `AppButton` (text rgb(161,42,30), edge rgb(192,100,90), 36px), dialog buttons `AppButton`, flash message as the success `Callout`, absent state in muted text inside the panel.

## Systeemmeldingen (`Admin/SystemNotices.vue`)

- D3-09 closed: filters in a white panel (padding 16px, three columns) with `NativeSelect` and `TextField` at 36px, "Filteren" primary; each notice a white panel (radius 10px, padding 16px) with secondary-coloured labels; "Gezien" an `AppButton` small (28px) and, once seen, a success `Pill` with a check; empty state a panel with a muted sentence.

## Momentopname banner (`canvas/SnapshotBanner.vue`)

- D3-10 closed: surface `#F6F7F5`, 1px `#DCDFE2`, radius 8px, Space Grotesk 14px/600 title, year line 12.5px secondary. The component sits under `canvas/` but is named in this task; the other `canvas/` files are not touched.

## Open: after the Bekijken merge

- `Canvas/SnapshotView.vue` (selector, header placement of the banner) and `canvas/ExportMenu.vue` (with the overlay switch) belong to the Bekijken builder; their conformance is open until that merge. Reason: another builder is changing those files now.
- Product owner remarks on these screens are recorded here as further findings once he has seen them next to the boards (T387 accept).
