# Status of the README findings, screens 1, 2 and 5 (T382)

For T383: merge these statuses into `README.md` and delete this file. Findings that apply to every screen (shell, top bar) belong to T379 and are not listed.

## Screen 1: sign-in

1. Layout, logo mark, wordmark: opgelost. `pages/Auth/AuthPanel.vue`, white panel with hairline and radius 10 on the grey page, LogoMark and the app name from `app.title`. Also used by the second-factor and new-password screens.
2. Title font: opgelost. Space Grotesk 22, 600.
3. Primary button: opgelost. Ink fill through AppButton.
4. Inputs: opgelost. The foundation fields are 36 high with the edge border; the labels stay bold above. No hint line: none is specified.
5. Page background: opgelost. Page token on the body.

## Screen 2: Overview

1. Shell, two-row bar and navy underline: niet van toepassing for T382, belongs to T379 and the shell.
2. Page title font: opgelost.
3. Canvas card: opgelost for the hairline border, radius 8 (card token), a border-only hover and a Pill for "Gearchiveerd". Niet van toepassing for a status line: delivery 1 has no status data to show.
4. "Nieuw canvas" primary with plus icon: opgelost. "Toon gearchiveerd" 24-pixel target: opgelost through the shared `ui/CheckBox.vue` (16-pixel box in a 24-pixel target, WB4, decision 46), also used for the list bulk checkboxes. Not covered: the checkboxes in `pages/Canvas/ArchivedToggle.vue` and `AddLine.vue` (T380 files) still use a bare input.
5. Panel structure: opgelost. Each organisation and the Templates sit on a surface panel with a hairline.

## Screen 5: user list with side panel

1. Filter bar and primary "Nieuw": opgelost for the bar layout (search, filter chips in the "Soort Alle" form, count, primary ink "Nieuw" with plus icon on the right). Niet van toepassing: the Users spec defines no filters, so no chips show on this screen (the chips are built and tested through the list component).
2. Table: opgelost for sortable headers with chevrons, name with the email on a second line, hairline rows, hover and selected row (tint plus 2-pixel ink left border), right alignment available through `align: 'right'`. Niet van toepassing: the row checkbox, since the Users spec has no bulk actions. Bewust afwijkend: the E-mailadres column and its sort are removed because the name column carries the email; sorting by email is therefore gone from Users. Column spacing stays wide: the table fills the free width next to the panel; denser columns would need a fixed column width design.
3. Docked panel: opgelost visually: 360 pixels (the detail-panel token), white, radius 10, hairline, no scrim, list reserves the space so the page stays readable, full-width sheet below 48rem. Bewust afwijkend: the panel still traps focus and blocks pointer input behind it (WB8 and the existing overlay test "Tab never leaves the overlay"). Question for the product owner: is a truly non-modal panel wanted (click another row while it is open)? That needs the focus-trap assertion changed.
4. Panel content: opgelost. Kind above an 18-pixel title, fields, "Opslaan" primary and "Annuleren" (through the discard check), the non-destructive actions, then below a divider the consequence text and the danger "Verwijderen". The consequence text is not shown on the user's own row, where "Verwijderen" is disabled. The Role is a select with a chevron. Bewust afwijkend: on open the first field is selected (kept from the existing focus handling). The Organisations panel has no consequence text and no "Verwijderen" (deleting goes through the row action and the typed-name dialog, "Archiveren" sits below a divider).
