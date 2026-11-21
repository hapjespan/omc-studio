# Feature Specification: OMC Studio version 1

**Feature Branch**: `docs/phase-2-specify`

**Created**: 2026-11-18

**Status**: Draft. The phase 2 gate was approved on 2026-11-18 while kickoff v1.2, addendum A1 and the design were being processed; the product owner then decided every source difference marked `[GATE-Cn]`; on 2026-11-18 the product owner answered the phase 4 questions: the Shift move in Tekenen (`[GATE-C53]`), automatic siblings (`[GATE-C54]`), `[GATE-U1]`, ADR 0011 and SD-25 to SD-41 confirmed; the product owner answered the Shift-move questions QS-1 to QS-7 the same day. Two assumptions await the product owner's confirmation: (QS-8, decided by the product owner 2026-11-19) (a block wider than its target is made as wide as the target) and (QS-9, decided by the product owner 2026-11-19) (undo and redo of a Shift move ask the same confirmation); see `plan.md`.

**Input**: Phase 1 grilling output (`specs/grilling-phase-1.md`), kickoff repo version 1.3 (`specs/kickoff.md`), the product owner's kickoff v1.2 (`specs/kickoff-v1.2-po.md`), addendum A1 (`specs/kickoff-addendum-edit-mode.md`), the design (`specs/design/`, contract in `design-contract.md`), `docs/CONTEXT.md`, ADR 0001 to 0021, constitution v1.0.0. Differences between the sources: `source-conflicts.md`.

## Reading guide

- **Precedence**: the product owner's gate answers recorded in kickoff repo version 1.3 come first; then, for screens and names, kickoff v1.2 and the design, and for technique, addendum A1 (with "Bewerk" read as Invoeren and "Edit" as Tekenen); then this spec, the ADRs and the kickoff. Kickoff paragraphs are cited as "kickoff 7.2" (repo version 1.3), "v1.2 K10" or "addendum K10", because v1.2 and the addendum give K10, A7 and A8 different meanings.
- **Deliveries**: every story carries its delivery. D1 is Invoeren and Bekijken on automatic layout (kickoff WP0 to WP4, front end in Vue 3, ADR 0012). The technical proof WP-E0 for Tekenen runs right after the project setup (WP1) and ends in go or no-go (section "Technical proof WP-E0"). DT is Tekenen, its own delivery after D1 (addendum WP-E1 to WP-E4); clauses in DT that need a later delivery are tagged "from D2" or "from D3" and are built and tested once that delivery exists. D2 is "financial layer visible" (WP5 to WP8), D3 is export, import, snapshots, sample data and final review (WP9 to WP11). A no-go stops DT only. A delivery contains no stub of a later one. `[SD-1]`
- **Roles**: every write action is for the Administrator only; a Reader only reads, exports, signs in and out, and manages their own password and second factor (FR-009). "The server refuses" means the refusal also holds for a request sent directly to the server, bypassing the screens, and leaves stored data unchanged.
- **Labels**: quoted Dutch text is the screen label. All screen text lives in the messages file; code and data use the English glossary terms of `docs/CONTEXT.md`. The modes are "Invoeren" (Entry mode), "Tekenen" (Drawing mode) and "Bekijken" (View mode) (ADR 0014).
- **Look**: every screen follows `design-contract.md`, also screens the design does not show.
- **Hard rules added by addendum A1** (H1 to H10 of the kickoff stand): H11 the three modes show the same canvas, within 1 pixel at 100% zoom; H12 Tekenen changes Layout and never creates, deletes or renames an Area, Element, Statement or text; the one structure change it makes is moving one Cluster, Activity or Item to another parent of the same kind of Area by Shift-dragging it or through its keyboard alternative, always after a confirmation and under the same structure rules as Invoeren (amended by the product owner, `[GATE-C53]`, ADR 0036); H13 the database is the only source, Layout is the application's own data; H14 the drawing library sits behind one adapter in one directory, versions pinned exactly; H15 Tekenen makes no request outside the application's own server.
- **QS-8 and QS-9**: confirmed by the product owner on 2026-11-19 as written (a Shift-moved block wider than its target is made exactly as wide as the target; undo and redo of a Shift move ask the same confirmation). They are decisions, not assumptions.
- **Gate markers**: `[GATE-U1]` to `[GATE-U4]` and `[GATE-D1]` mark requirements touched by the phase 2 gate answers; `[GATE-U1]` now marks the Finalise rule, confirmed by the product owner on 2026-11-18. `[GATE-Cn]` marks a difference between the sources, numbered as in `source-conflicts.md`; points 32 to 52 were decided by the product owner after the gate (for 51 partly as the orchestrator's recorded reading of the product owner's words), points 53 and 54 after the phase 4 plan. `[SD-n]` marks a decision this spec took under kickoff 20.4. `[GATE-U]`, `[GATE-D1]` and `[SD-n]` are listed in "Phase 2 gate" at the end; `[GATE-Cn]` in `source-conflicts.md`.
- **Formats** `[GATE-C40]`: on the canvas and in the overlay (cards, Area headers, side panel, hover breakdown) amounts show as whole euros `€ 1.235` and percentages with one decimal `12,4%`; in the detail panel, tables, forms, Toewijzen and the line "Totalen sluiten" amounts show as `€ 1.234,56` and percentages as `33,33%`. Every calculation and the closing check stay to the cent (H7). Other formats:  FTE `1,50`, dates `18-11-2026`, times 24-hour, time zone Europe/Amsterdam.

## User Scenarios & Testing *(mandatory)*

### Accessibility baseline (WCAG 2.2 AA)

Every user-facing story below lists the baseline items it must pass plus its own criteria. Each item is a test.

- **WB1 Keyboard**: every function of the screen is operable with the keyboard alone, in a logical focus order, without a keyboard trap (2.1.1, 2.1.2, 2.4.3). A "Naar hoofdinhoud" skip link is the first focusable element (2.4.1).
- **WB2 Visible focus**: the focused element shows an outline of at least 2 CSS pixels with a contrast of at least 3:1 against the colours next to it, drawn as a dark and a light ring so it stays visible on every canvas colour; a sticky menu or open side panel never covers the focused element entirely (2.4.7, 2.4.11).
- **WB3 Contrast**: text has a contrast of at least 4.5:1, large text (24 px, or 18.66 px bold) at least 3:1; borders of inputs and buttons, state indicators, focus indicators and chart bars at least 3:1 against their background (1.4.3, 1.4.11).
- **WB4 Target size**: every pointer target is at least 24 by 24 CSS pixels, or has 24 pixels of clear space around its centre (2.5.8).
- **WB5 Form errors**: every input has a visible label (3.3.2); on a refused save, each invalid field shows a message next to it that names the field and says how to fix it ("Budget: vul een bedrag in van € 0,00 tot € 999.999.999,99, met een komma voor de centen"), the message is programmatically tied to the field, and focus moves to the first invalid field (3.3.1, 3.3.3, 4.1.2). Entered values stay in the form after a refusal (3.3.7).
- **WB6 Colour is not the only carrier**: every meaning shown by colour is also shown by text, a letter or an icon with a text alternative (1.4.1).
- **WB7 Status messages**: success, refusal, undo and progress messages are announced to assistive technology without moving focus (4.1.3); the exceptions are the undo button of FR-017, which receives focus because the deleted row it replaces is gone, and the successful automatic save of Tekenen, which is shown but not announced (User Story 37).
- **WB8 Dialogs and panels**: opening a dialog or side panel moves focus into it, Escape closes it unless it holds unsaved input (then it asks to discard), and focus returns to the control that opened it (2.4.3).
- **WB9 Structure**: every screen has a unique page title "<screen> - <application name>" (2.4.2), the page language is Dutch (3.1.1), landmarks for top bar, left panel, main content and detail panel, and one level-1 heading (1.3.1, 2.4.6).
- **WB10 Resize and reflow**: all screens except the canvas itself work at 200% text size and at a width of 320 CSS pixels without horizontal scrolling (1.4.4, 1.4.10); the canvas is a two-dimensional diagram and scrolls in both directions instead, with its own zoom.
- **WB11 Dragging has an alternative**: everything that can be done by dragging can also be done with single clicks or the keyboard (2.5.7).
- **WB12 Hover content**: content that appears on hover also appears on keyboard focus, stays while the pointer is over it, and closes with Escape (1.4.13).

---

### User Story 1 - Start the application and sign in (Priority: P1, D1)

The product owner starts the application on a clean machine with Docker in two commands, reads the one-time Administrator password from the container log, signs in, and is made to choose a new password.

**Why this priority**: nothing else is reachable without it (H9, kickoff 18.1).

**Independent Test**: on a machine with only Docker, run the two commands, sign in with the logged password, set a new one, sign out and sign in again.

**Acceptance Scenarios**:

1. **Given** a clean machine with Docker and a copy of the repository, **When** the user runs `cp env.example .env` and `docker compose up -d`, **Then** the image is built once on that machine, and from the moment it exists the sign-in screen answers within 3 minutes at `http://localhost:8080` (the build needs an internet connection once; the README states that and the one-time build time measured in WP1, ADR 0035), the database holds the eight Areas, the four Modalities and the Template "Leeg canvas", and the container log shows the Administrator e-mail address and a one-time password exactly once.
2. **Given** the application was started before, **When** the containers restart, **Then** no seed runs again, no new password is logged and all data is unchanged.
3. **Given** the sign-in screen, **When** the Administrator enters the logged e-mail address and one-time password, **Then** the screen "Nieuw wachtwoord kiezen" opens and no other screen is reachable until a new password is saved.
4. **Given** the screen "Nieuw wachtwoord kiezen", **When** the user enters an 11-character password, **Then** saving is refused with "Wachtwoord: gebruik minstens 12 tekens".
5. **Given** the screen "Nieuw wachtwoord kiezen", **When** the user enters a password that is on the bundled list of common passwords, **Then** saving is refused with "Wachtwoord: dit wachtwoord is te bekend, kies een ander".
6. **Given** a signed-in user, **When** they choose "Uitloggen", **Then** the session ends and the browser's back button shows the sign-in screen, not the previous page.
7. **Given** the sign-in screen, **When** a wrong password is entered for an existing account or any password for a non-existing e-mail address, **Then** both show the same message "E-mailadres of wachtwoord is onjuist".
8. **Given** five failed sign-in attempts for one e-mail address within one minute, **When** a sixth attempt is made, even with the right password, **Then** it is refused with "Te veel pogingen. Probeer het over 60 seconden opnieuw" and the event is written to the security log without the e-mail address.
9. **Given** a signed-in user idle for 118 minutes, **When** the warning "Je sessie verloopt over 2 minuten" appears with "Sessie verlengen", **Then** activating it keeps the session; without it, at 120 minutes the next action leads to the sign-in screen and, after signing in, back to the page they were on.
9a. **Given** a user who signed in almost 12 hours ago, **When** 10 minutes remain, **Then** a dialog "Je sessie eindigt over 10 minuten. Bevestig je wachtwoord om door te werken" appears; confirming the password (and a second-factor code when active) starts a new 12-hour period on the same page without losing input, which meets WCAG 2.2.1 by extension; without it, at 12 hours the next request leads to the sign-in screen with "Je sessie is na 12 uur beëindigd. Log opnieuw in" and, after signing in, back to the page they were on; in that last 10 minutes the idle warning offers this dialog instead of "Sessie verlengen" (ADR 0037).
10. **Given** a signed-in user on "Mijn account", **When** they change their password, **Then** the current password is required, every other session of that user ends, and the security log records the change.
11. **Given** an Administrator who lost their password, **When** the documented console command for a password reset is run inside the container with their e-mail address, **Then** a new one-time password is printed once and must be replaced at the next sign-in; with the option for the second factor, the command also removes that Administrator's second factor and recovery codes.

**Accessibility**: WB1 to WB9. The password fields accept paste and the browser's password manager (3.3.8). The refusal after too many attempts states the waiting time in text.

---

### User Story 2 - Optional second factor (Priority: P1, D1)

Any user can turn on a TOTP second factor with recovery codes; an Administrator can reset another user's second factor (ADR 0010).

**Why this priority**: part of the constitution's base slice; client financial data is behind the sign-in.

**Independent Test**: enable the second factor with an authenticator app, sign out, sign in with password and code, sign in once with a recovery code, then have an Administrator reset it.

**Acceptance Scenarios**:

1. **Given** a user without a second factor on "Mijn account", **When** they choose "Tweede factor aanzetten" and confirm their password, **Then** a QR code generated by the application itself (no outside service) and the same key as text are shown, and the factor is not active until a valid 6-digit code is entered.
2. **Given** the confirmation step, **When** a valid code is entered, **Then** the factor is active and 8 single-use recovery codes are shown once, with "Kopiëren" and "Downloaden als tekstbestand".
3. **Given** a user with an active factor, **When** they sign in with the right password, **Then** a second step asks for the 6-digit code or a recovery code, and only then is the session started.
4. **Given** the code step, **When** a code is entered for a time step equal to or earlier than the last accepted one for that user, **Then** it is refused with "Code: deze code is al gebruikt, wacht op de volgende code".
5. **Given** the code step, **When** a recovery code is entered, **Then** sign-in succeeds, that recovery code no longer works, and "Mijn account" shows "Nog 7 herstelcodes".
6. **Given** five wrong codes within one minute, **When** a sixth is entered, **Then** it is refused with the 60-second waiting message.
7. **Given** a user with an active factor, **When** they choose "Nieuwe herstelcodes" and confirm their password, **Then** 8 new codes are shown once and all earlier codes stop working.
8. **Given** a user with an active factor, **When** they choose "Tweede factor uitzetten" and confirm their password, **Then** the factor and its recovery codes are removed.
9. **Given** an Administrator on the Users list, **When** they choose "Tweede factor resetten" for another user and confirm, **Then** that user's factor and recovery codes are removed, their sessions end, and the Audit log and security log record who reset whose factor.
10. **Given** an Administrator, **When** they look at their own row, **Then** "Tweede factor resetten" is not offered for themselves; they use "Mijn account".
11. **Given** a user signed in on two devices, **When** on one of them they turn the second factor on or off or choose "Nieuwe herstelcodes", **Then** the session on the other device ends, and the security log records it (ADR 0037).

**Accessibility**: WB1 to WB9. The QR code has the text key next to it as its alternative; the code field accepts paste and has `autocomplete="one-time-code"` behaviour (3.3.8); the recovery codes are selectable text, not an image.

---

### User Story 3 - Application shell (Priority: P1, D1)

Every screen renders inside one frame built once: a top bar with tabs, a left panel where the screen has one, the main area and a detail panel on the right (constitution base slice, design, ADR 0019).

**Why this priority**: every other screen renders into it.

**Independent Test**: sign in as Administrator and as Reader, walk every tab and Beheer entry with the keyboard, and open and close the detail panel.

**Acceptance Scenarios**:

1. **Given** a signed-in user, **When** any screen opens, **Then** the top bar shows the application name (a setting, default "OMC Studio") linking to "Overzicht", the breadcrumb "<organisatie> / <canvas>" when a Canvas is open (the Organisation links to "Overzicht"), the badge "Voorbeelddata" on the sample Organisation, and on the right the user's role with a menu holding "Mijn account" and "Uitloggen".
2. **Given** an Administrator with an open Canvas, **When** the top bar is shown, **Then** it holds the tabs "Canvas", "Kostenposten" (from D2), "Toewijzen" (from D2), "Versies" (from D3) and "Beheer"; the active tab is marked by a 2-pixel underline and by `aria-current`. `[GATE-C43]`
2a. **Given** no Canvas is open (the overview, Beheer, Mijn account), **When** the top bar is shown, **Then** it holds the tabs "Overzicht" and, for an Administrator, "Beheer". `[SD-31]`
2b. **Given** any user with an open Canvas, or an Administrator on "Kostenposten" of an Organisation (from D2), **When** the top bar is shown, **Then** it holds one selector "Boekjaar <jaar>", with an open Canvas showing that year's state for it, "open" or "definitief"; Kostenposten, Toewijzen and the financial overlay all use this year.
3. **Given** an Administrator, **When** "Beheer" opens, **Then** it lists "Gebruikers", "Organisaties", "Eigenaren" and "Applicaties" (both from D2), "Logboek", "Systeemmeldingen" (from D2), "Volledige export en import" and "Voorbeelddata verwijderen" (both from D3).
4. **Given** a Reader, **When** a Canvas is open, **Then** the tabs are only "Canvas" (with Bekijken only) and "Versies" (from D3), and no write control appears in any screen outside "Mijn account".
5. **Given** "Canvas", **When** it opens, **Then** the page header shows the Canvas name and the mode switch "Invoeren", "Tekenen" (from DT) and "Bekijken" for an Administrator, with the chosen mode in the address, so reloading keeps the mode (ADR 0014).
6. **Given** a list or the canvas, **When** a row or Element is selected, **Then** the detail panel opens on the right with its fields, and closing it returns focus to the selected row or Element.
7. **Given** a Reader, **When** they request the address of an Administrator screen or of Invoeren or Tekenen directly, **Then** the screen "Geen toegang" (403) is shown for Administrator screens and Bekijken opens for the modes, and the security log records the refused screen request.
8. **Given** any user, **When** they request an address that does not exist, **Then** the screen "Pagina niet gevonden" (404) is shown in Dutch with a link to "Overzicht".
9. **Given** a screen width of 320 CSS pixels, **When** the shell is shown, **Then** the tabs collapse behind a "Menu" button, a left panel stacks above the main area, and the detail panel covers the main content instead of sitting next to it.

**Accessibility**: WB1 to WB10. The tabs are links in a navigation landmark with `aria-current` on the active one; the mode switch is a group labelled "Modus" with one link per mode the user may use; the Fiscal year selector is a labelled select whose state is text.

---

### User Story 4 - One management list for every kind of record (Priority: P1, D1)

All record lists share one list component with search, sort, filters, a side-panel form, confirmed deletion, bulk actions, archiving and a ten-second undo (kickoff 8A points 1 to 9).

**Why this priority**: every CRUD screen of the product is built from it; built once, used everywhere.

**Independent Test**: on the Users list (D1) and the Organisations list (D1), exercise every list behaviour below.

**Acceptance Scenarios**:

1. **Given** a list, **When** text is typed in "Zoeken", **Then** only rows whose text columns contain it, ignoring case, remain, and "12 resultaten" shows the count.
2. **Given** a list, **When** a column header is activated, **Then** rows sort ascending by it, a second activation sorts descending, and the header announces the sort direction.
3. **Given** a list with filters, **When** a filter is set, **Then** the rows and the count update and the filter shows as a removable chip.
4. **Given** a list, **When** "Nieuw" is chosen, **Then** an empty form opens in the side panel, and saving adds the row without leaving the screen.
5. **Given** a row, **When** it is opened, **Then** its form opens in the same side panel; saving with an empty required field is refused per WB5.
6. **Given** a row, **When** "Verwijderen" is chosen, **Then** a confirmation names the consequence in numbers ("Verwijdert ook 4 jaarwaarden en 6 toewijzingssets") or states that there is none.
7. **Given** a confirmed deletion of a kind other than Canvas, Organisation, Snapshot, Template or User, **When** it succeeds, **Then** a message with "Ongedaan maken" appears for 10 seconds, focus moves to that button, the countdown pauses while the button has focus or the pointer is over the message, activating it restores the record with everything the deletion removed, and when the message closes focus moves to the list's search field.
8. **Given** an undo whose restore would now break a rule (a name taken meanwhile, a year Finalised meanwhile, a parent removed), **When** "Ongedaan maken" is activated, **Then** nothing is restored and the message names the rule.
9. **Given** three selected rows of which one is blocked, **When** "Verwijderen" or "Archiveren" is chosen for the selection, **Then** each row follows its own rules and the result reads "2 verwijderd, 1 overgeslagen" followed by the name and reason of each skipped row; "Ongedaan maken" after a bulk deletion restores every row that action deleted.
10. **Given** a list of a kind that can be archived, **When** it opens, **Then** archived rows are hidden; "Toon gearchiveerd" shows them marked "Gearchiveerd" (text, not only colour), each with "Terugzetten".
11. **Given** any create, change, delete, archive, restore or undo through a list, **When** it succeeds, **Then** the Audit log holds one entry per record with the old and new values.

**Accessibility**: WB1 to WB10, plus: the select-all checkbox and each row checkbox have labels naming the row; the undo countdown satisfies 2.2.1 by pausing on focus and hover; sortable headers are buttons.

---

### User Story 5 - Manage users and roles (Priority: P1, D1)

An Administrator creates, changes and deletes local accounts, each with one role valid for every Organisation (grilling Q4).

**Why this priority**: a Reader account is how colleagues look along (A2); roles guard every write.

**Independent Test**: create a Reader, sign in as that Reader, verify every write is refused, delete the Reader.

**Acceptance Scenarios**:

1. **Given** the Users list, **When** an Administrator creates a user with name, e-mail address and role, **Then** a 16-character one-time password is shown once in the side panel with "Kopiëren", and the new user must replace it at first sign-in.
2. **Given** an existing user "a@example.org", **When** another user is saved with "A@Example.org", **Then** saving is refused with "E-mailadres: dit adres is al in gebruik".
3. **Given** a user, **When** an Administrator chooses "Wachtwoord resetten", **Then** a new one-time password is shown once, the user's sessions end, and the old password stops working.
4. **Given** exactly two Administrators, **When** each changes the other to Reader or deletes the other at the same moment, **Then** one succeeds and the other is refused with "Er moet minstens één beheerder blijven".
5. **Given** a signed-in Administrator, **When** they open their own row, **Then** "Verwijderen" and the role field are disabled with the explanation "Je kunt je eigen account niet verwijderen of je eigen rol wijzigen", and a direct request gets the same message.
6. **Given** a user who made changes, **When** that user is deleted, **Then** their sessions end at once, and every Audit log entry about or by them shows "verwijderde gebruiker" instead of their name and e-mail address.
7. **Given** a Reader, **When** any write request is sent directly to the server, **Then** it is refused with status 403 and nothing changes.
8. **Given** a user whose role is changed, **When** they make their next request, **Then** the new role applies.
9. **Given** the Users list, **When** an Administrator chooses "Sessies beëindigen" for another user and confirms, **Then** every session of that user ends, nothing else changes, and the Audit log and security log record who ended whose sessions; the action is not offered on their own row (ADR 0037).
10. **Given** an Administrator on their own row, **When** they change their own e-mail address, **Then** saving asks for "Huidig wachtwoord", and a wrong or empty one is refused with "Huidig wachtwoord: vul je huidige wachtwoord in om je e-mailadres te wijzigen" (ADR 0037).

**Accessibility**: WB1 to WB10. The one-time password is selectable text with a copy button whose result is announced (WB7).

---

### User Story 6 - Manage organisations (Priority: P1, D1)

An Administrator manages Organisations: name and logo; delete only when empty, otherwise archive (kickoff 8A).

**Why this priority**: every Canvas and Cost record belongs to an Organisation.

**Independent Test**: create an Organisation with a logo, create a Canvas in it, try to delete it, archive it, restore it.

**Acceptance Scenarios**:

1. **Given** the Organisations list, **When** an Administrator creates "Stichting Noord" with a PNG logo of 200 KB, **Then** it appears in the list and on "Overzicht", and its logo shows in the Direction band of its Canvases.
2. **Given** an existing "Stichting Noord", **When** "stichting noord " is saved as a new name, **Then** it is refused with "Naam: er bestaat al een organisatie met deze naam".
3. **Given** an upload of a 2 MB file, a GIF, or a file named `logo.png` that is not an image, **When** it is saved, **Then** it is refused with "Logo: kies een PNG- of JPEG-afbeelding van maximaal 1 MB en 4000 bij 4000 pixels"; the same holds for an image larger than 4000 by 4000 pixels.
4. **Given** an Organisation with at least one Canvas or Cost record, **When** deletion is attempted, **Then** it is refused with "Deze organisatie heeft nog canvassen of kostenposten. Archiveer haar, of verwijder die eerst".
5. **Given** an Organisation without Canvases and Cost records, **When** deletion is confirmed, **Then** it is removed with its Owners, Applications, Fiscal years and its Audit log entries, and no undo is offered.
6. **Given** an archived Organisation, **When** the overview opens, **Then** it and its Canvases are hidden unless "Toon gearchiveerd" is on, and while archived all its data is read-only.

**Accessibility**: WB1 to WB10. The logo has the Organisation name as its text alternative.

---

### User Story 7 - Audit log (Priority: P1, D1)

Every create, change and delete is recorded with old and new value and who did it; an Administrator reads it in "Logboek" (kickoff 18.6, grilling Q8).

**Why this priority**: accountability for financial changes is the lawful basis for keeping user data (Q8).

**Independent Test**: rename an Element, open "Logboek", find the entry with old and new name, filter it by Canvas and user.

**Acceptance Scenarios**:

1. **Given** an Element renamed from "Inkoop" to "Inkoop en contractbeheer", **When** "Logboek" opens, **Then** the newest entry shows date and time, the user's name, "Gewijzigd", "Element", the Canvas, and the field "Naam" with old and new value.
2. **Given** the Audit log, **When** it is filtered by Organisation, Canvas, kind of record, user or date range, **Then** only matching entries remain, newest first, with the count.
3. **Given** any entry, **When** an Administrator looks for an edit or delete action, **Then** there is none, and a direct change request to the server is refused.
4. **Given** a change to a user's password or second factor, **When** the entry is shown, **Then** it says what happened ("Wachtwoord gewijzigd") and never holds a password, password hash, second-factor key or recovery code.
5. **Given** a Canvas deleted with confirmation, **When** the Audit log is read, **Then** none of that Canvas's entries remain (Q8) except one entry "Canvas verwijderd" with actor, time, Canvas name and the counts of what went with it; the same holds for a deleted Organisation.

**Accessibility**: WB1 to WB10. Old and new values are presented as a labelled pair, not distinguished by colour alone.

---

### User Story 8 - Overview and canvas lifecycle (Priority: P1, D1)

The overview shows Organisations with their Canvases as cards with a miniature; an Administrator creates a Canvas from a Template or as a copy, archives, restores and deletes it (S2, S3, grilling Q7).

**Why this priority**: the entry point of all canvas work.

**Independent Test**: create a Canvas from "Leeg canvas", copy it within and across Organisations, archive and delete it.

**Acceptance Scenarios**:

1. **Given** two Organisations with Canvases, **When** "Overzicht" opens, **Then** each Organisation is a section with its active Canvases as cards showing a miniature of the eight Areas and their Domains, the name, and the date of last change, and, for an Administrator (from D2), a link "Kostenposten" to that Organisation's Cost records, also for an Organisation without Canvases. `[GATE-C43]`
2. **Given** "Nieuw canvas", **When** an Administrator enters name "Operating model 2027", chooses the Organisation and start "Sjabloon: Leeg canvas", **Then** Invoeren opens on the new Canvas with the eight Areas, and Bekijken shows them in the fixed arrangement with the Default colours.
3. **Given** a Canvas name already used in that Organisation, ignoring case and surrounding spaces, **When** it is saved, **Then** it is refused with "Naam: dit canvas bestaat al in deze organisatie".
4. **Given** a Canvas with Elements, Statements, Allocation sets and a Finalised year, **When** it is copied within its Organisation, **Then** the copy holds the same active Elements with all their fields, Statements and Allocation sets, and from DT their Layout, no year of the copy is Finalised, no Snapshot is copied, and archived Elements are not copied.
5. **Given** the same Canvas, **When** it is copied to another Organisation, **Then** the copy holds active Elements and Statements only, and from DT their Layout, and every Cost record of the target Organisation sits in Unknown allocation in it.
5a. **Given** a Canvas, **When** "Naam wijzigen" is used with a free name, **Then** the card and every mode show the new name and the Audit log records old and new name.
6. **Given** a Canvas, **When** "Archiveren" (the default action on the card) is chosen, **Then** it disappears from the overview unless "Toon gearchiveerd" is on, is read-only while archived, and "Terugzetten" brings it back.
7. **Given** a Canvas, **When** "Verwijderen" is chosen, **Then** the dialog names what goes with it ("12 elementen, 40 toewijzingssets, 2 momentopnamen") and enables the delete button only after the Canvas name is typed exactly; afterwards no undo is offered.
8. **Given** a Reader, **When** the overview opens, **Then** cards open Bekijken, and no create, copy, archive or delete action is shown.

**Accessibility**: WB1 to WB10. Each card is one focusable link named after the Canvas, with its actions in a menu button labelled "Acties voor <canvasnaam>"; the miniature is decorative and hidden from assistive technology.

---

### User Story 9 - Templates (Priority: P2, D1)

A Template is a Canvas without Organisation holding only Elements and Statements; an Administrator saves a Canvas as Template, and starts new Canvases from one (grilling Q6).

**Why this priority**: speeds up a second operating model; only "Leeg canvas" ships.

**Independent Test**: save a filled Canvas as a Template, start a new Canvas from it, rename and delete the Template.

**Acceptance Scenarios**:

1. **Given** a Canvas with Elements, Allocation sets and, from DT, a Manual layout, **When** an Administrator chooses "Opslaan als sjabloon" with name "Kinderopvang basis", **Then** a Template holds its active Elements with all their fields and its Statements, without Allocation sets, Fiscal year state, Snapshots or Layout. `[SD-25]`
2. **Given** the Template, **When** a new Canvas starts from it, **Then** the Canvas holds copies of those Elements and Statements in Automatic layout; later changes to the Template do not affect it.
3. **Given** "Overzicht", **When** an Administrator looks at the section "Sjablonen", **Then** every Template except "Leeg canvas" is a card that opens in Invoeren with structure and Direction only, without financial functions; Template names are unique among Templates, ignoring case.
4. **Given** the Template "Leeg canvas", **When** an Administrator tries to edit, rename or delete it, **Then** those actions are not offered and a direct request is refused, because "Leeg canvas" is how an empty Canvas starts.
5. **Given** another Template, **When** deletion is confirmed by typing its name, **Then** it is removed; Canvases started from it are unaffected.
6. **Given** a Reader, **When** "Overzicht" opens, **Then** the section "Sjablonen" is not shown.

**Accessibility**: WB1 to WB10, with the card pattern of User Story 8.

---

### User Story 10 - Structure rules of the element tree (Priority: P1, D1)

The server holds every structure rule of kickoff 6.2 and 6.4 for Domains, Clusters and Activities, and for Groups and Items in list Areas (H2, H3, H4, ADR 0003).

**Why this priority**: every figure later traces to one unambiguous place in this tree.

**Independent Test**: send each forbidden structure request directly to the server and verify the refusal and that nothing changed.

**Acceptance Scenarios**:

1. **Given** an Activity create request whose parent is an Area, missing, another Activity, or a Domain or Cluster of another Canvas, **When** it is sent, **Then** it is refused with "Een activiteit hoort onder een domein of een cluster".
2. **Given** a Cluster create request under a Cluster, **When** it is sent, **Then** it is refused with "Een cluster kan niet in een ander cluster".
3. **Given** a Cluster create request in Actors, Channels or Value propositions, **When** it is sent, **Then** it is refused with "In deze area bestaan geen clusters".
4. **Given** any Element create request in Direction, **When** it is sent, **Then** it is refused with "In Koers staan geen elementen".
5. **Given** any request to create, rename, reorder or delete an Area, **When** it is sent, **Then** no such route exists or it is refused, and the eight Areas are unchanged.
6. **Given** a Domain "Personeel" with an Activity "Werving", **When** a second "werving " is added under the same Domain, **Then** it is refused with "Naam: er staat al een element met deze naam op deze plek"; the same name under another parent is allowed.
7. **Given** a name containing ">", **When** it is saved, **Then** it is refused with "Naam: het teken > is niet toegestaan".
8. **Given** an Activity, **When** it is moved to a Domain or Cluster in another Area of the same kind, **Then** it moves with its fields and identifier; a move into an Area of the other kind is refused with "Verplaatsen kan alleen naar een area van dezelfde soort".
9. **Given** a Cluster with three Activities, **When** it is moved to another Domain of an activity Area, **Then** its Activities move with it.
10. **Given** a Domain, **When** it is moved to another Area of the same kind, **Then** its Clusters and Activities move with it and any Allocation lines stay on the same identifiers.
11. **Given** an Element without Allocation lines and without children, **When** deletion is confirmed, **Then** it is removed.
12. **Given** a Domain with two Activities and no Allocation lines anywhere in its subtree, **When** deletion is confirmed after "Verwijdert ook 2 activiteiten", **Then** the Domain and its Activities are removed.
13. **Given** a Cluster with Activities, **When** deletion is chosen, **Then** the dialog offers "Activiteiten ook verwijderen" and "Activiteiten terugzetten onder het domein"; the second keeps the Activities with their Allocation lines and needs Redistribution only for lines on the Cluster itself, and it is refused, naming the Activities, when the Domain already holds an Activity with the same name.
14. **Given** an Element with Allocation lines on itself or in its subtree (from D2), **When** deletion is chosen, **Then** plain deletion is refused and the Redistribution dialog of User Story 22 opens.
15. **Given** an archived Element, **When** "Toon gearchiveerd" is on in Invoeren and "Terugzetten" is chosen, **Then** it returns under its former parent at the end of the order; it is refused with the reason when that parent is no longer active or its name is now taken there.
16. **Given** the Domain "Onbekende allocatie" in Supporting activities, **When** a request renames, edits, moves, reorders, archives or deletes it, adds a child to it, moves an Element into it, or uses it as a Target, **Then** it is refused with "Onbekende allocatie wordt door het systeem beheerd" and nothing changes.

**Accessibility**: refusal messages are shown and announced per WB5 and WB7 in the screens that send these requests (User Stories 11 and 12).

---

### User Story 11 - Invoeren: enter structure and fields (Priority: P1, D1)

In Invoeren an Administrator builds the structure of one Area at a time as a tree, orders it with arrow buttons, moves Elements through "Hangt onder", and edits fields in the detail panel; there is no drawing surface and no dragging (ADR 0014, kickoff v1.2 S4, design board 1). `[SD-17]`

**Why this priority**: "create and assign it yourself" is the reason the product exists.

**Independent Test**: on a new Canvas add a Domain, Activities and a Cluster, move an Activity into the Cluster through "Hangt onder", reorder with the arrows, all with the keyboard only.

**Acceptance Scenarios**:

1. **Given** a Canvas, **When** Invoeren opens, **Then** the left panel lists the eight Areas, each with a count line ("4 domeinen · 13 activiteiten", "3 groepen", "3 teksten" for Direction), and the first activity Area is chosen.
2. **Given** a chosen activity Area, **When** "+ Domein" is used and "Inkoop" is typed and Enter pressed, **Then** a Domain row "Inkoop" appears at the end of the tree and focus moves to its add line.
3. **Given** a Domain, **When** a name is typed in its add line "Activiteit of cluster toevoegen aan <domein>, Enter om op te slaan" and Enter is pressed, **Then** a new Activity row appears at the end of that Domain's Activities, and the line is empty and focused again; with the choice "Cluster" next to the line (default "Activiteit") a Cluster row appears at the end of its Clusters instead. `[GATE-C44]`
4. **Given** the tree, **When** it shows, **Then** each row shows its kind ("Domein", "Cluster", "Activiteit", or "Groep" and the Item label in list Areas), its name, its Modality letter, and the buttons "Omhoog" and "Omlaag"; Activities of a Domain come first, then its Clusters with their Activities indented one level deeper.
5. **Given** a row, **When** "Omhoog" or "Omlaag" is activated, **Then** it swaps with its neighbour of the same kind within the same parent (Activities among Activities, Clusters among Clusters), the change is saved and audited, and focus stays on the same button of the moved row, or moves to its other arrow when that button became disabled; the first row of a kind has "Omhoog" disabled and the last has "Omlaag" disabled.
6. **Given** a selected Activity, **When** "Hangt onder" in the detail panel is set to the Cluster "Contracten" of the same Domain and "Opslaan" is chosen, **Then** the Activity moves to the end of that Cluster; the list of "Hangt onder" offers every allowed parent by path ("Primaire activiteiten > Opvang > Peuters").
7. **Given** a selected Element, **When** the detail panel shows, **Then** it holds the path, the name, the description, the Modality as a group of buttons "Common", "Adaptive", "Specialized", "Distinct" and "Geen" each with its letter, and Rationale (activity Areas only), icon (list Areas only), "Hangt onder", a line on its costs (from D2: "Geen kosten toegewezen in 2026. Verwijderen kan direct."), "Opslaan", "Archiveren" and "Verwijderen", and the note "Plek, grootte en kleur op het canvas regel je in Tekenen" (from DT).
8. **Given** a changed field, **When** "Opslaan" is chosen, **Then** it is saved, the header shows "Alles opgeslagen", and a refused value shows the message next to its field (WB5).
9. **Given** an Element without Allocation lines in its subtree, **When** "Archiveren" is confirmed, **Then** it and its subtree are archived; with Allocation lines (from D2) the Redistribution dialog of User Story 22 opens instead.
10. **Given** an empty Cluster "Peuters", a Domain "Facilitair" without Activities, and a Domain "ICT" partly below the bottom edge of the Artboard, **When** the detail panel shows "Waarschuwingen", **Then** it lists with their count "Leeg cluster: Peuters", "Domein zonder activiteiten: Facilitair" ("Groep zonder items" in list Areas) and "Past niet op het tekenvlak: ICT", each as a callout, the tree marks "Peuters" with "leeg", and activating a warning selects that Element.
11. **Given** "Zoek in deze area", **When** text is typed, **Then** only rows whose name contains it, ignoring case, remain with their parents.
12. **Given** a Canvas with 500 Elements, **When** Invoeren opens or an Element is added, **Then** the result is visible within one second.
13. **Given** an archived Canvas or a Reader, **When** the address of Invoeren is requested, **Then** Bekijken opens instead.
14. **Given** Supporting activities, **When** its tree shows, **Then** it ends with the Domain "Onbekende allocatie" marked "Systeem", without arrows, add line, "Opslaan", "Archiveren" or "Verwijderen"; its detail panel explains "Hier staan kostenposten die in dit canvas nog niet zijn ingedeeld".

**Accessibility**: WB1 to WB10. The tree is a list of rows reachable with Tab in reading order; every arrow button is named after its row ("Inkoop omhoog"); the add line has a visible label; "Hangt onder" is a labelled select; the save state is a status message (WB7). Nothing here needs dragging, so WB11 is met by design.

---

### User Story 12 - Quick entry (Priority: P2, D1)

An Administrator types many Elements at once as indented lines into one Area, sees a preview and saves all of it (S5).

**Why this priority**: acceptance criterion 5, three Domains with fifteen Activities within a minute.

**Independent Test**: paste 18 indented lines into Primary activities, check the preview, save, and time it.

**Acceptance Scenarios**:

1. **Given** "Snel invoeren" for an activity Area, **When** lines are typed where no indent is a Domain, one indent (a tab or two spaces) is a Cluster when lines with two indents follow it and an Activity otherwise, and two indents is an Activity in that Cluster, **Then** the preview shows the resulting tree with each line marked "nieuw" or "bestaat al".
2. **Given** a first-level line naming an existing Domain of that Area, **When** previewed, **Then** it is marked "bestaat al" and its lines below are added to that Domain; for "Onbekende allocatie" the line is marked "Regel 1: Onbekende allocatie wordt door het systeem beheerd" instead.
3. **Given** a line with two indents directly under a line without indent, a line with three indents, or the first line indented, **When** previewed, **Then** that line is marked with "Regel 4: inspringing past niet bij de regel erboven" and saving is disabled.
4. **Given** a list Area, **When** a line has two indents, **Then** it is refused with "Regel 6: in deze area bestaan geen clusters".
5. **Given** two lines with the same name under the same parent, or a name containing ">", or a name over 150 characters, **When** previewed, **Then** each offending line is marked with its own message.
6. **Given** a preview without errors, **When** "Opslaan" is chosen, **Then** all Elements are created at once or, if anything is refused at save time, none are.
7. **Given** 3 Domains with 5 Activities each, **When** a practised user enters them, **Then** they are on the Canvas within one minute.
8. **Given** empty lines, **When** previewed, **Then** they are ignored.

**Accessibility**: WB1 to WB10. The text area accepts the Tab key as indent; Escape followed by Tab leaves the text area, and this is stated in the instruction above the field. Line errors are listed below the preview with the line number, each linked to the line.

---

### User Story 13 - Direction block (Priority: P2, D1)

An Administrator writes, in Invoeren under the Area "Koers", the mission, the vision and an ordered list of strategic goals of a Canvas (kickoff 6.1 point 1, 8A).

**Why this priority**: the top band of every Canvas.

**Independent Test**: enter a mission, a vision and three goals, reorder them, delete one, clear the vision.

**Acceptance Scenarios**:

1. **Given** the Direction band, **When** "Missie" is edited and saved, **Then** the text shows in the band; clearing it removes it and the band shows "Nog geen missie".
2. **Given** the Direction band, **When** "Visie" is edited, **Then** it behaves the same as the mission.
3. **Given** "+ strategisch doel", **When** a title and an explanation are entered, **Then** the goal is added at the end; the title is required and at most 150 characters.
4. **Given** three goals, **When** one is moved with "Omhoog" or "Omlaag", **Then** the order changes and is saved.
5. **Given** a goal, **When** deletion is confirmed, **Then** it is removed and "Ongedaan maken" is offered for 10 seconds.
6. **Given** a mission over 2000 characters, **When** it is saved, **Then** it is refused with "Missie: gebruik maximaal 2000 tekens".

**Accessibility**: WB1 to WB12. Character counters are announced only when the limit is reached.

---

### User Story 14 - Modality on elements (Priority: P2, D1)

An Administrator classifies Domains, Clusters and Activities of activity Areas with one of four Modalities and a Rationale (K7, kickoff 6.3).

**Why this priority**: the analysis frame the modality overlay shows in D2.

**Independent Test**: set "Adaptive" with a Rationale on an Activity, see the mark, clear it.

**Acceptance Scenarios**:

1. **Given** an Element in an activity Area, **When** "Modaliteit" is set to "Adaptive" in the detail panel, **Then** the Element shows a mark with the letter "A" and its colour, and "Onderbouwing" becomes editable.
2. **Given** an Element with a Modality and a Rationale, **When** the Modality is set to "Geen", **Then** both are cleared after confirmation that the Rationale goes too.
3. **Given** an Element in a list Area, **When** the detail panel shows, **Then** no Modality field is present, and a direct request setting one is refused.
4. **Given** the four Modalities, **When** they are listed, **Then** they read Common (C, green), Adaptive (A, blue), Specialized (S, orange), Distinct (D, purple), in that order, and cannot be changed by users.

**Accessibility**: WB1 to WB10. The mark always shows the letter; the mark has a dark and a light outline so it meets 3:1 against any canvas colour, and the letter meets 4.5:1 against the mark.

---

### User Story 15 - Default colours (Priority: P3, D1)

Every Canvas shows the Default colours of the design; there is no theme screen (ADR 0018). From DT, Element style set in Tekenen overrides them per Element.

**Why this priority**: presentation; nothing has to be set before the Canvas reads well.

**Independent Test**: open a new Canvas in Bekijken and compare the colours with `design-contract.md`.

**Acceptance Scenarios**:

1. **Given** any Canvas, **When** Bekijken shows it, **Then** every Area, Domain, Cluster and Activity without Element style has the Default colour of its Area and level from `design-contract.md`.
2. **Given** any background colour on the canvas, **When** text without a text colour of its own is drawn on it, **Then** it is ink `#171B21` or white, whichever has the higher contrast, so it meets 4.5:1; the labels of the Direction band use `#C9CED6` per `design-contract.md`.
3. **Given** any route, **When** a request tries to change a Default colour, **Then** no such route exists; in D1 colours cannot be changed at all, and after a no-go on WP-E0 a separate decision follows (nothing is prepared). `[GATE-C49]` `[GATE-C50]`

**Accessibility**: WB3 holds for every Default colour pair; the pairs and their measured contrast are listed in `design-contract.md`.

---

### User Story 16 - Bekijken (Priority: P2, D1)

Any user opens a Canvas read-only in Bekijken with zoom and a detail panel; overlays follow in D2, Snapshots and export in D3, the stored Manual layout in DT (S6, ADR 0015). `[SD-16]`

**Why this priority**: the Reader's main screen and the base for every overlay.

**Independent Test**: as Reader open a Canvas, zoom, select an Element and read its fields.

**Acceptance Scenarios**:

1. **Given** a Canvas in Automatic layout, **When** Bekijken opens, **Then** it draws the Automatic layout of kickoff 6.5 on the Artboard of 1680 by 1188 pixels as in design board 2: the Direction band on top in four columns side by side, logo, mission, vision and strategic goals, the same in every mode `[GATE-C38]`; Richten and Beheersen next to each other; Actors, Channels and Value propositions as three columns on the left; Primary and Supporting activities stacked on the right. Domain cards of one Area have equal width and flow in rows; inside a card come its Activities, then its Clusters as tinted blocks with their Activities. Names wrap within their block's width and blocks grow in height, never in width `[GATE-C48]`; content that does not fit is shown beyond the Artboard per scenario 8; the Direction band has a fixed height of 180 pixels, and its text that does not fit is cut off with "…", shown in full on hover, focus and in the detail panel, and warned. `[SD-38]` `[SD-40]`
2. **Given** Supporting activities, **When** Bekijken shows it, **Then** its last card is "Onbekende allocatie".
3. **Given** the view, **When** an Element is selected, **Then** the detail panel shows its fields read-only.
4. **Given** the view, **When** "Inzoomen" or "Uitzoomen" is used, **Then** the zoom changes in steps of 10% between 50% and 200%, and "Passend maken" fits the whole Canvas in the visible area at any zoom from 10% to 200%.
5. **Given** the view (from D2), **When** "Overlay" is set to "Geen", "Modaliteit" or "Financieel", **Then** the matching overlay of User Story 25 or 26 is drawn.
6. **Given** a Canvas with a Manual layout (from DT), **When** Bekijken opens, **Then** it draws every Element at its stored place and size within 1 pixel of Tekenen at 100% zoom (H11, User Story 38).
7. **Given** a Canvas with 500 Elements, **When** Bekijken opens, **Then** it is visible within one second, whether or not they fit on the Artboard.
8. **Given** content that grows below the bottom edge of the Artboard, **When** Bekijken shows the Canvas, **Then** the Artboard keeps its size and is not scaled; its edge is drawn as a line marked "Rand A3"; the content beyond it is drawn below the edge on a hatched background; above the canvas a notice reads "Niet alles past op het A3-tekenvlak: <n> blokken vallen erbuiten", for every role; and nothing is refused or made smaller. In D1 this is the whole answer, because Tekenen does not exist yet; from DT the notice adds "Maak blokken kleiner of verplaats ze in Tekenen" for Administrators. `[GATE-C51]` `[SD-39]`

**Accessibility**: WB1 to WB12. The notice of scenario 8 comes before the canvas in reading order and is announced once on opening; the line "Rand A3" and its label meet WB3, and the hatch is decorative and never lowers text below WB3. Tab moves between Areas and the toolbar; within an Area the arrow keys move between Elements as a tree (Up and Down within a parent, Right into children, Left to the parent); Enter opens the detail panel; text colour on every canvas colour meets WB3.

---

### User Story 17 - Owners and applications (Priority: P2, D2)

An Administrator keeps per Organisation a list of Owners (organisational units or roles) and a list of Applications; a value in use is deleted only after "Vervangen door" (kickoff 8A, acceptance 6d). `[SD-15]`

**Why this priority**: every Cost record needs an Owner; Applications are its optional attribute (K3).

**Independent Test**: create two Owners, use one on a Cost record, delete it through "Vervangen door".

**Acceptance Scenarios**:

1. **Given** "Eigenaren", **When** an Administrator creates "Afdeling ICT", **Then** it is available on Cost records of that Organisation only; the name field shows the hint "Afdeling of rol, geen persoonsnaam".
2. **Given** "Applicaties", **When** "Planningssysteem" is created with Supplier "Leverancier X", **Then** it is available on Cost records of that Organisation only.
3. **Given** a name already in the same list of the Organisation, ignoring case and surrounding spaces, **When** saved, **Then** it is refused with "Naam: deze naam staat al in de lijst".
4. **Given** an Owner used by 12 Cost records, **When** deletion is chosen, **Then** plain deletion is refused and "Vervangen door" asks for another Owner; confirming moves all 12 Cost records to it and deletes the Owner in one step, with an Audit log entry per changed Cost record.
5. **Given** an Application in use, **When** deletion is chosen, **Then** "Vervangen door" offers the other Applications and "Geen applicatie".
6. **Given** an Owner or Application not in use, **When** deletion is confirmed, **Then** it is removed and "Ongedaan maken" is offered.

**Accessibility**: WB1 to WB10.

---

### User Story 18 - Fiscal years, cost records and yearly figures (Priority: P1, D2)

An Administrator keeps the Organisation's Fiscal years and Cost records with their Yearly figures in one table per year (S7, kickoff 7.1, ADR 0006, ADR 0007). `[SD-2]` `[SD-14]`

**Why this priority**: the money that the financial layer attributes.

**Independent Test**: add Fiscal year 2026, create a Cost record with Yearly figures, edit them, delete them, delete the Cost record.

**Acceptance Scenarios**:

1. **Given** "Kostenposten" of an Organisation, **When** "Boekjaar toevoegen" is used with 2026, **Then** 2026 becomes selectable; 1999, 2101 and a year already present are refused with "Boekjaar: kies een jaar van 2000 tot en met 2100 dat nog niet bestaat".
2. **Given** a Fiscal year with Yearly figures, **When** its deletion is attempted, **Then** it is refused with "Dit boekjaar heeft nog jaarwaarden"; without Yearly figures it is deleted after confirmation.
3. **Given** the table for 2026, **When** it opens, **Then** it lists every active Cost record with code, name, Budget, Forecast and allocation status, with search and filters on Cost kind, Owner, Application and allocation status; a Cost record without Yearly figures in 2026 shows "geen jaarwaarden".
4. **Given** "Kostenposten" opened from a Canvas, **When** the table shows, **Then** the column "Toegewezen" shows the status for that Canvas as a dot with the text "100%" or "Onbekende allocatie", and above the table the cards, in cents like the table, "Totaal budget" (with the Forecast), "Toegewezen" (amount and "8 van 10 kostenposten"), "Onbekende allocatie" (amount, count and Share) and the Finalise card of User Story 24 are shown. Opened from "Overzicht" for an Organisation, the table has no "Toegewezen" column, no allocation cards and no Finalise card. `[GATE-C43]` The form shows every field of FR-048 and FR-049, Supplier, cost centre, description and remark included. `[GATE-C42]`
5. **Given** "Nieuw", **When** an Administrator enters code "LC-001", name "Licentie planning", Cost kind "Leverancierscontract", Supplier "Leverancier X", Owner "Afdeling ICT", cost centre "4100", Application "Planningssysteem" and a description, **Then** the Cost record is saved; name, Cost kind and Owner are required.
6. **Given** code "LC-001" in the Organisation, **When** another Cost record is saved with "lc-001", **Then** it is refused with "Code: deze code bestaat al in deze organisatie"; an empty code is allowed on any number of Cost records.
7. **Given** a Cost record, **When** Yearly figures for 2026 are entered with Budget "€ 12.500,00", Forecast "13.000", Run "60" and Change "30", FTE "1,5", reference date and remark, **Then** they are saved and the Undetermined share shows "10,00%".
8. **Given** Run "70" and Change "40", **When** saved, **Then** it is refused with "Run en change: samen maximaal 100%".
9. **Given** an amount "12.50", "-5" or "1000000000", **When** saved, **Then** it is refused with "Budget: vul een bedrag in van € 0,00 tot € 999.999.999,99, met een komma voor de centen".
10. **Given** Yearly figures for 2026 with Allocation sets in two Canvases, **When** their deletion is confirmed after "Verwijdert ook 2 toewijzingssets", **Then** the Yearly figures and both sets are removed and the closing check still passes in both Canvases.
11. **Given** a Cost record with Yearly figures in 2025 and 2026 and five Allocation sets, **When** its deletion is confirmed after "Verwijdert ook 2 jaarwaarden en 5 toewijzingssets", **Then** all of them are removed and "Ongedaan maken" restores all of them.
12. **Given** 2026 Finalised in any Canvas of the Organisation, **When** Yearly figures of 2026 are created, changed or deleted, **Then** it is refused with "2026 is definitief in canvas <naam>. Heropen het jaar eerst"; deleting a Cost record with Yearly figures in 2026 is refused with "2026 is definitief in canvas <naam>. Heropen het jaar eerst, of archiveer de kostenpost".
13. **Given** a Cost record, **When** it is archived, **Then** it is hidden from the table and from "Toewijzen" unless "Toon gearchiveerd" is on, its own fields and Yearly figures are read-only, its Allocation sets stay editable (including through Redistribution and Carry over) and its Owner and Application follow "Vervangen door", and its Yearly figures and Allocation sets keep counting in every figure. `[SD-9]`
14. **Given** the Owner field, **When** it shows, **Then** it carries the hint "Afdeling of rol, geen persoonsnaam".

**Accessibility**: WB1 to WB10. Amount fields state the expected format in their label hint ("bijvoorbeeld 1.234,56").

---

### User Story 19 - Import cost records from CSV (Priority: P2, D2)

An Administrator imports Cost records with their Yearly figures from a semicolon-separated file, sees a dry run report per line, and imports only a clean file, all at once (kickoff 12.3). `[SD-8]`

**Why this priority**: the first Organisation's budget lives in spreadsheets.

**Independent Test**: import a file with 20 lines, then the same file with two changed amounts and one faulty line.

**Acceptance Scenarios**:

1. **Given** a UTF-8 file, with or without byte order mark, with header `code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte`, **When** "Importeren" runs the dry run, **Then** the report lists per line "nieuw", "wordt bijgewerkt", "ongewijzigd" or the errors of that line, and the totals of each.
2. **Given** a line whose code exists in the Organisation, **When** imported, **Then** that Cost record and its Yearly figures for that year are updated; a new code adds a Cost record.
3. **Given** a line without code, **When** the dry run runs, **Then** it reports "Regel 7: code ontbreekt; bij importeren is een code verplicht".
4. **Given** an Owner or Application name not yet in the Organisation's lists, **When** the dry run runs, **Then** the report lists it under "Wordt aangemaakt" and the import creates it. `[GATE-U3]`
5. **Given** a Cost kind other than Leverancierscontract, Personeel, Project or Overig (ignoring case), a year that is not a Fiscal year of the Organisation, an amount "12.50", run_pct plus change_pct above 100, two lines with the same code and year, or two lines with the same code but different Cost record fields, **When** the dry run runs, **Then** each is reported on its own line as "Regel <n>, <kolom>: <fout>. <herstel>", for the amount "Regel 5, budget: '12.50' is geen bedrag. Gebruik een komma voor de centen, bijvoorbeeld 12,50".
6. **Given** a line for a year Finalised in any Canvas of the Organisation that would change Yearly figures, **When** the dry run runs, **Then** it reports "Regel 12: 2026 is definitief in canvas <naam>".
7. **Given** a line with empty `jaar` and empty `budget`, **When** imported, **Then** only the Cost record fields are created or updated; a filled `jaar` with an empty `budget` is an error.
8. **Given** a dry run with at least one error, **When** the report shows, **Then** "Importeren" is disabled; with no errors it imports every line in one step, or nothing if anything fails at that moment.
9. **Given** a file over 5 MB, a file that is not CSV text, or a header that differs from the required columns, **When** uploaded, **Then** it is refused with "Bestand: kies een CSV-bestand van maximaal 5 MB met de kolommen <kolommen>" and nothing is read further.

**Accessibility**: WB1 to WB10. The report is a table with a heading per section; the error count is announced when the dry run finishes.

---

### User Story 20 - Allocate a cost record (Priority: P1, D2)

An Administrator allocates a Cost record per Canvas and Fiscal year over Targets to exactly 100%, with a remaining counter, "rest naar deze regel" and quick actions; the set is saved whole or not at all (S8, H5, H6, kickoff 7.2).

**Why this priority**: the core of the financial layer.

**Independent Test**: allocate budget 230,00 over three Activities at 33,33 / 33,33 / 33,34, save, and try to save 99,99% directly against the server.

**Acceptance Scenarios**:

1. **Given** "Toewijzen" for a Canvas and year, **When** it opens, **Then** the left column lists the Cost records with Yearly figures in that year with a status "Toegewezen" or "Onbekende allocatie" (dot plus text), the middle shows the set of the chosen Cost record with "Nog te verdelen", and the right shows the tree of Targets with their Total amount.
2. **Given** an empty set, **When** three Targets are added by clicking or dragging and 33,33 / 33,33 / 33,34 are entered, **Then** "Nog te verdelen" reads "0,00%" and "Opslaan" is enabled.
3. **Given** lines totalling 99,99% or 100,01%, **When** looking at "Opslaan", **Then** it is disabled; and the same set sent directly to the server is refused with "De verdeling moet precies 100,00% zijn; nu 99,99%".
4. **Given** a line of 0 basis points, a negative value, a value that is not a whole number of basis points, or the same Target twice, **When** sent to the server, **Then** the whole set is refused with "Regel <doel>: kies een percentage van 0,01% tot 100,00%" or "Doel <naam> staat twee keer in de verdeling", and the stored set is unchanged.
5. **Given** a Target in Actors, Channels, Value propositions or Direction, an archived Element, or an Element of another Canvas, **When** sent as a Target, **Then** the set is refused with "Doel <naam> kan geen kosten dragen".
6. **Given** lines at 50,00% and 30,00%, **When** "Rest naar deze regel" is used on the second, **Then** it becomes 50,00% and the counter 0,00%.
7. **Given** three Targets in the set, **When** "Gelijk verdelen" is used, **Then** they become 33,34% / 33,33% / 33,33%, the extra basis point going to the first line in set order.
8. **Given** a Domain with 6 active Activities including those in its Clusters, **When** "Verdelen over activiteiten van domein" is used with that Domain, **Then** the set in the screen is replaced by 6 lines at 16,67% / 16,67% / 16,67% / 16,67% / 16,66% / 16,66%; a Domain without Activities gives "Dit domein heeft geen activiteiten".
9. **Given** a saved set, **When** "Set leegmaken" is confirmed, **Then** the whole set is removed, the Cost record shows "Onbekende allocatie", and "Ongedaan maken" is offered.
10. **Given** a Cost record without Yearly figures in the year, an archived Canvas or Organisation, or a year Finalised in this Canvas, **When** a set is sent, **Then** it is refused with "Geen jaarwaarden in 2026 voor deze kostenpost", "Dit canvas is gearchiveerd" or "2026 is definitief. Heropen het jaar eerst"; an archived Cost record can still be allocated.
11. **Given** a saved set, **When** the Audit log is read, **Then** one entry shows the old and the new set as lists of Target and percentage.
12. **Given** a domain line and an Activity line under that same Domain in one set, **When** saved, **Then** it is accepted (H6, mixed levels).
13. **Given** unsaved changes to the set of the chosen Cost record, **When** the left list shows, **Then** that Cost record reads "Concept <percentage>" in the warning colour, nothing is stored (H5), "Opslaan" stays disabled with the text "Opslaan kan zodra er 0,00% te verdelen is. Een kostenpost is altijd 0% of 100% toegewezen.", and "Wijzigingen weggooien" restores the stored set.
14. **Given** the screen, **When** it shows, **Then** "Nog te verdelen" shows the percentage and its amount, each line shows its Target's kind and path, its percentage and its amount, "Snel" offers "Gelijk verdelen over <n> doelen" and "Verdelen over activiteiten van een domein", and the right column "Doelen op het canvas" has a search field and marks Targets already in the set with "In set".

**Accessibility**: WB1 to WB12. Every Target in the tree has an "Toevoegen aan verdeling" button; percentage inputs are labelled with the Target's path; "Nog te verdelen" is a live region announced after each change.

---

### User Story 21 - Figures that close to the cent (Priority: P1, D2)

For a Canvas, Fiscal year and filter, the figures are calculated by a module without database access, with largest-remainder rounding, roll up, Unknown allocation, Shares and breakdowns, and are shown only when the closing check passes (kickoff 7.3, H7, ADR 0005, ADR 0008). `[SD-6]`

**Why this priority**: "figures that do not close" is the one defect the definition of done forbids outright.

**Independent Test**: unit tests on the calculation module for every rule below, plus the property test of FR-095.

**Acceptance Scenarios**:

1. **Given** Budget € 230,00 allocated 33,33 / 33,33 / 33,34 to three Activities, **When** calculated, **Then** the line amounts are € 76,66 / € 76,66 / € 76,68, together exactly € 230,00.
2. **Given** a Domain with an Activity, a Cluster with two Activities, and lines on the Domain itself and on one Activity of the Cluster, **When** calculated, **Then** each Element's Direct amount is its own lines, its Total amount is its Direct amount plus the Totals of its children, and the Area Total is the sum of its Domain Totals.
3. **Given** two Cost records with Yearly figures, one without an Allocation set, **When** calculated, **Then** that one's Budget and Forecast are the Direct amount of the Unknown allocation Domain, the Supporting activities Area Total includes them, and the Canvas total equals the sum of all Area Totals and equals the sum of all Budgets.
4. **Given** a Cost record with Budget € 1.000,00, Run 60% and Change 30% allocated 50/50, **When** calculated, **Then** each line holds Run € 300,00, Change € 150,00 and Undetermined € 50,00.
5. **Given** Elements with Modalities and a Domain with Modality whose Activity also has one, **When** the Modality breakdown is calculated, **Then** each Modality holds the sum of Direct amounts of Elements with it, nothing is counted twice through parents, the line "Geen modaliteit" holds the Direct amounts of Elements without one, and the line "Onbekende allocatie" completes the Canvas total. `[GATE-C41]` `[SD-18]`
6. **Given** an Organisation without Cost records, **When** calculated, **Then** all amounts are € 0,00, the check passes, and Shares show "-" instead of a division by zero.
7. **Given** a Cost record with 100% on one Target, **When** calculated, **Then** that Target's Direct amount equals the Budget exactly.
8. **Given** a Cost record without Forecast, **When** calculated, **Then** its Forecast counts as equal to its Budget. `[SD-5]` `[GATE-U3]`
9. **Given** a filter on Cost kind "Personeel", **When** calculated, **Then** only matching Cost records count, the Canvas total is their Budget sum, Shares are relative to that total, and the closing check holds for that subset.
10. **Given** an injected discrepancy of one cent in a test, **When** the closing check runs, **Then** no figure is shown, the screen shows "De cijfers sluiten niet. Er worden geen cijfers getoond; de beheerder ziet de melding onder Systeemmeldingen", and a System notice records time, Canvas, year, filter, failed check and difference in cents, without personal data.
11. **Given** a Canvas with 500 Elements and 1000 Cost records, **When** the figures are requested, **Then** they show within one second.

**Accessibility**: covered by the screens that show the figures (User Stories 20 and 25).

---

### User Story 22 - Redistribute before removing (Priority: P1, D2)

Removing an Element that carries Allocation lines on itself or in its subtree opens "Herverdelen": per Fiscal year, move the affected percentages to one other Target or send the affected Cost records back to Unknown allocation; afterwards the Element is archived (kickoff 6.4, acceptance 12).

**Why this priority**: removing structure must never break H5 or H7.

**Independent Test**: allocate two Cost records partly to an Activity in two years, delete the Activity, choose a different option per year, and check every set is still 0% or 100%.

**Acceptance Scenarios**:

1. **Given** an Activity carrying lines in 2025 and 2026, **When** deletion is chosen, **Then** "Herverdelen" lists both years with the number of affected Cost records and percentages, and per year the choices "Overzetten naar ander doel" and "Terug naar onbekende allocatie".
2. **Given** a Domain whose Activities A1 (30%) and A2 (20%) are in one set, **When** "Overzetten naar ander doel" with Target T is chosen, **Then** T gets 50% in that set, added to any line T already had, and the set still totals 100,00%.
3. **Given** "Terug naar onbekende allocatie" for a year, **When** confirmed, **Then** every affected Cost record loses its whole set for that year in this Canvas, never a remainder.
4. **Given** the chosen Target is the Element being removed, one of its descendants, archived, or not a Target, **When** confirmed, **Then** it is refused with "Kies een doel buiten het element dat je verwijdert" or "Doel <naam> kan geen kosten dragen".
5. **Given** an affected year Finalised in this Canvas, **When** "Herverdelen" opens, **Then** it is refused with "2026 is definitief. Heropen het jaar eerst".
6. **Given** all choices made, **When** confirmed, **Then** the sets change, the Element and its subtree are archived, and the Audit log holds an entry per changed set and per archived Element, all in one step.
7. **Given** a completed Redistribution, **When** the figures are calculated, **Then** the closing check passes and the archived Elements hold no lines.

**Accessibility**: WB1 to WB10. The per-year choice is a labelled radio group; the Target picker is the same keyboard-operable Target tree as in "Toewijzen".

---

### User Story 23 - Carry over to another year (Priority: P2, D2)

An Administrator copies, in one Canvas, Allocation sets and optionally Yearly figures from one Fiscal year into another, for each Cost record whose target year is still empty, and gets a result report (kickoff 7.4).

**Why this priority**: saves re-entering a whole year.

**Independent Test**: carry 2026 into 2027 with "Ook jaarwaarden overnemen" on, for Cost records in each of the outcome situations below.

**Acceptance Scenarios**:

1. **Given** source 2026 and target 2027 with "Ook jaarwaarden overnemen" on, **When** run, **Then** each Cost record with Yearly figures in 2026 and none in 2027 gets copies of its 2026 Yearly figures, and, if it has a set in this Canvas for 2026 and none for 2027, a copy of that set.
2. **Given** "Ook jaarwaarden overnemen" off, **When** run, **Then** only sets are copied, to Cost records that already have Yearly figures in 2027 and no set for 2027 in this Canvas; a Cost record without Yearly figures in 2027 is skipped with "geen jaarwaarden in 2027".
3. **Given** a Cost record that already has a set for 2027 in this Canvas, or, with the option on, Yearly figures in 2027, **When** run, **Then** it is skipped with "doeljaar niet leeg". **Given** an archived Cost record, with the option on or off, **When** run, **Then** it is skipped with "gearchiveerd" and nothing of it is copied, neither its set nor its Yearly figures. Decided by the product owner on 2026-11-21: an archived Cost record is never carried over, and it appears once, as skipped.
4. **Given** a 2026 set with a line on an Element that is no longer a Target, **When** run, **Then** nothing is copied for that Cost record and it is listed under "Handmatig toewijzen".
5. **Given** 2027 Finalised in this Canvas, or, with the option on, in any Canvas of the Organisation, **When** run, **Then** the whole run is refused with "2027 is definitief in canvas <naam>. Heropen het jaar eerst".
6. **Given** a finished run, **When** the result shows, **Then** it reads "Overgenomen: 34, overgeslagen: 5, handmatig toewijzen: 2" with the names and reasons, each Cost record counted once in exactly one of the three, and everything was written in one step.

**Accessibility**: WB1 to WB10. The result lists are tables with headings.

---

### User Story 24 - Finalise and reopen a fiscal year (Priority: P2, D2)

An Administrator finalises a Fiscal year per Canvas once nothing sits in Unknown allocation, which freezes the money inputs of that year, and can reopen it (kickoff 7.2 point 7, ADR 0007, ADR 0008). The rule that a year can only be Finalised when Unknown allocation is empty was confirmed by the product owner on 2026-11-18. `[GATE-U1]` `[SD-3]`

**Why this priority**: acceptance criterion 11; finalised figures are what the product owner reports.

**Independent Test**: try to finalise 2026 with one Cost record in Unknown allocation, allocate it, finalise, try to change a set and a budget, reopen.

**Acceptance Scenarios**:

1. **Given** the panel "Boekjaren" in "Kostenposten", **When** it opens, **Then** each Fiscal year shows per Canvas "Open" or "Definitief sinds 18-11-2026 door <naam>", with "Heropenen" for a Finalised one; finalising happens through the card of scenario 10.
2. **Given** a Cost record with Yearly figures in 2026 and no set in this Canvas, **When** a Finalise request for 2026 reaches the server, **Then** it is refused with "Nog 1 kostenpost in onbekende allocatie" and its name, also when the Cost record is archived.
3. **Given** a failing closing check, **When** "Definitief maken" is chosen, **Then** it is refused with "De cijfers sluiten niet; definitief maken kan niet".
4. **Given** 2026 Finalised in Canvas A, **When** a set of Canvas A for 2026 is saved or removed, or a Redistribution or Carry over touches 2026 in Canvas A, **Then** it is refused with "2026 is definitief. Heropen het jaar eerst".
5. **Given** 2026 Finalised in Canvas A, **When** Yearly figures of 2026 of any Cost record of the Organisation are created, changed or deleted, **Then** it is refused as in User Story 18 scenario 12.
6. **Given** 2026 Finalised in Canvas A, **When** sets of Canvas B for 2026 are changed, **Then** that is allowed.
7. **Given** 2026 Finalised in Canvas A, **When** Elements of Canvas A are renamed, moved or given a Modality, **Then** that is allowed, and the 2026 figures of Canvas A follow the new structure with the same Canvas total. `[SD-3]`
8. **Given** a Finalised year, **When** "Heropenen" is confirmed, **Then** it is editable again and the Audit log records who reopened it.
9. **Given** "Toewijzen" for a Finalised year, **When** it opens, **Then** a banner says "2026 is definitief" and every edit control is disabled.
10. **Given** "Kostenposten" of a Canvas for an open year, **When** it shows, **Then** a card explains "Het jaar kan definitief zodra alle kostenposten zijn toegewezen. Nog <n> te gaan." with "Jaar definitief maken", disabled while <n> is above 0, and the Fiscal year selector in the top bar shows "open" or "definitief". `[GATE-U1]`

**Accessibility**: WB1 to WB10. Status is text, not only an icon.

---

### User Story 25 - Financial overlay (Priority: P1, D2)

In Bekijken, the financial overlay shows per Domain its Total amount, Share and a Forecast-against-Budget bar, coloured by Share, with a breakdown per Cost record, and a side panel with totals; filters for Cost kind, Owner and Application, for the year of the top bar (S9, acceptance 9 and 10).

**Why this priority**: the point of the product: what the organisation does and what that costs, in one picture.

**Independent Test**: with sample figures, compare every overlay number with a hand calculation and with the sum of all Cost records.

**Acceptance Scenarios**:

1. **Given** the overlay "Financieel" for 2026, **When** shown, **Then** each Domain card in an activity Area shows its Total amount, its Share ("van totaal"), a bar whose fill is the Forecast and whose stripe marks the Budget, the line "Prognose € … · <n>% van budget", and its number of Activities and Clusters; a Domain without costs reads "Geen kosten toegewezen in 2026"; each activity Area header shows the Area Total and Share; a legend line explains the colour scale, the bar and that list Areas carry no costs. Amounts show as whole euros and percentages with one decimal `[GATE-C40]`. Every card keeps its place and size and the figures are drawn inside it, covering its Activities; figures that do not fit are left out in the order counts, forecast line, bar, Share, and the full figures show on hover, focus and in the detail panel. `[GATE-C39]` `[SD-33]`
2. **Given** Domains with different Shares, **When** shown, **Then** the card colour intensity rises with Share while the amounts and Share stay readable per WB3.
3. **Given** a Domain card, **When** the pointer rests on it or it receives focus, **Then** a breakdown "Uitsplitsing per kostenpost" lists each Cost record contributing to its Total with the percentage of that Cost record allocated within the Domain's subtree and the amount in whole euros; selecting the card shows the same breakdown in cents in the detail panel, together with Direct amount, Total amount, Forecast, Run, Change, Undetermined and FTE.
4. **Given** Cost records without a set, **When** the overlay shows, **Then** the Domain card "Onbekende allocatie" in Supporting activities shows their amount and Share like any Domain, the Supporting activities Area Total includes it, and the side panel shows it as its own line.
5. **Given** the side panel, **When** shown, **Then** it holds the Canvas total of Budget and Forecast with the Forecast as a percentage of the Budget, "Toegewezen" and "Onbekende allocatie" each with count and amount, a link "Nu toewijzen" (Administrator only), the line "Totalen sluiten: <toegewezen> + <onbekende allocatie> = <totaal>" in cents, totals per Cost kind, per Modality (with "Geen modaliteit" and "Onbekende allocatie" `[GATE-C41]`), and Run, Change and Undetermined. Every amount is rounded on its own and shows the same wherever whole euros show; the line "Totalen sluiten" shows its three amounts in cents, so it proves the figures close to the cent. `[GATE-C52]`
6. **Given** all Cost records of the Organisation for 2026, **When** their Budgets are summed by hand, **Then** the sum equals to the cent the card "Totaal budget" on "Kostenposten" for 2026 without filters, and the calculated Canvas total and sum of the four activity Area Totals, and the overlay shows that total in whole euros (acceptance 9 and 10).
7. **Given** a filter on Owner "Afdeling ICT", **When** applied, **Then** every number recalculates for that subset, and the active filters show above the canvas.
8. **Given** list Areas, **When** the overlay shows, **Then** they show no amounts.
9. **Given** FTE on Cost records, **When** a Domain is selected, **Then** the detail panel shows its FTE as the sum of each Cost record's FTE times its allocated percentage within the Domain's subtree, with two decimals. `[SD-19]`
10. **Given** a failing closing check, **When** the overlay is chosen, **Then** no amount is shown and the message of User Story 21 scenario 10 appears.

**Accessibility**: WB1 to WB12. Colour intensity never carries the Share alone; the bar has a text equivalent; the side panel is a list of labelled values, not a chart only.

---

### User Story 26 - Modality overlay (Priority: P2, D2)

In Bekijken, the modality overlay shows a mark per Element, a legend and counts per Domain (S10, kickoff 18.4).

**Why this priority**: the analysis frame of K7 made visible.

**Independent Test**: classify Elements, open the overlay, compare marks and counts.

**Acceptance Scenarios**:

1. **Given** the overlay "Modaliteit", **When** shown, **Then** each Domain, Cluster and Activity with a Modality carries a mark with its colour and letter C, A, S or D, and a legend lists the four Modalities with colour and letter.
2. **Given** a Domain whose Clusters and Activities hold 3 Common, 1 Adaptive and 2 without Modality, **When** shown, **Then** the Domain card shows "C 3, A 1, S 0, D 0, geen 2".
3. **Given** list Areas, **When** shown, **Then** they show no marks.

**Accessibility**: WB1 to WB12. Marks always show the letter; the legend is text.

---

### User Story 27 - Canvas export and import as JSON (Priority: P1, D3)

Any user exports a Canvas as one JSON file with everything needed to rebuild it; an Administrator imports such a file as a new Canvas after an error-free dry run, all at once (kickoff 12.1, 12.2). `[SD-13]`

**Why this priority**: backup, copy and transfer of a Canvas between machines.

**Independent Test**: export a Canvas, import it into a new Organisation, compare every figure of every year.

**Acceptance Scenarios**:

1. **Given** a Canvas, **When** "Exporteren als JSON" is used, **Then** a file `<organisatie-slug>-<canvas-slug>-2026-11-18.json` downloads holding a format version, the Canvas, its Statements, all Elements including archived ones and, from DT, its Layout, the Organisation's Owners, Applications, Fiscal years, Cost records with Yearly figures, this Canvas's Allocation sets with line order, and its Finalised years.
2. **Given** "Importeren" on the overview with a JSON file, **When** the Administrator chooses a target Organisation or "Nieuwe organisatie" with a name, and a Canvas name, **Then** a dry run reports format version, structure rules, references, the 100% rule, name conflicts and Cost record conflicts, each with its location in the file.
3. **Given** a Cost record in the file whose identifier exists in the target Organisation, **When** the dry run runs, **Then** it is reused if its fields and the Yearly figures in the file are equal, and reported as "Kostenpost LC-001 verschilt van de bestaande" otherwise; a code held by a different Cost record of the target Organisation is an error too.
4. **Given** Yearly figures in the file for a year Finalised in a Canvas of the target Organisation, that would be created or changed, **When** the dry run runs, **Then** it is an error.
5. **Given** a dry run without errors, **When** "Importeren" is chosen, **Then** a new Canvas with new identifiers for Canvas and Elements is created with everything from the file, Cost records that are not reused get new identifiers with their sets mapped to them, missing Owners and Applications are created by name, all in one step or not at all.
6. **Given** a file of an unknown format version, over 50 MB, or not JSON, **When** uploaded, **Then** it is refused with "Bestand: kies een JSON-export van deze applicatie van maximaal 50 MB" and nothing is written.
7. **Given** a file in which any field breaks a rule of this spec (a name over 150 characters or with ">", a colour not in `#RRGGBB` form, Run and Change above 100%, an amount out of range), **When** the dry run runs, **Then** each is reported with its location in the file.
8. **Given** an export of a Canvas imported into an empty Organisation, **When** the figures of every year are compared, **Then** every Element amount, Unknown allocation and breakdown is identical to the cent.

**Accessibility**: WB1 to WB10, with the report layout of User Story 19.

---

### User Story 28 - Full export and import (Priority: P2, D3)

An Administrator exports all Organisations with everything in them to one JSON file, and imports such a file into an application that holds no Organisation (S12, acceptance 13). `[SD-12]`

**Why this priority**: acceptance criterion 13, export, wipe everything and import gives identical figures.

**Independent Test**: full export, delete every Organisation, full import, compare all figures.

**Acceptance Scenarios**:

1. **Given** "Volledige export", **When** used, **Then** one file holds every Organisation including archived ones with Owners, Applications, Fiscal years, Cost records, Canvases with all their content and Finalised years, every Snapshot with its checksum, and every Template except "Leeg canvas"; it holds no Users, no second-factor data, no Audit log and no System notices.
2. **Given** an application that still holds an Organisation, **When** a full import is attempted, **Then** it is refused with "Volledige import kan alleen in een lege applicatie. Verwijder eerst alle organisaties".
3. **Given** an application without Organisations, **When** a full import passes its dry run and is confirmed, **Then** everything in the file is created in one step or nothing is; a Template name already present gets the suffix " (geïmporteerd)".
4. **Given** a full export, a wipe of every Organisation and a full import, **When** the figures of every Canvas, year and filter are compared, **Then** they are identical to the cent, and every Snapshot passes its checksum.
5. **Given** a full export, **When** it is made, **Then** the security log records who exported, without file contents.

**Accessibility**: WB1 to WB10.

---

### User Story 29 - CSV exports, structure import and allocation import (Priority: P2, D3)

Any user exports Cost records and Allocation sets as CSV; an Administrator imports structure and Allocation sets from CSV, each after a dry run, all at once (kickoff 12.3 to 12.5). `[SD-8]`

**Why this priority**: work in a spreadsheet and bring it back.

**Independent Test**: export Cost records and allocations, re-import both unchanged and see "ongewijzigd" everywhere; import a structure file of 40 lines.

**Acceptance Scenarios**:

1. **Given** "Kostenposten exporteren", **When** used, **Then** a CSV with the import columns of User Story 19 holds one line per Cost record with a code and Fiscal year, plus one line with empty `jaar` and `budget` for each such Cost record without Yearly figures, with semicolons, decimal commas, no thousands separators, in UTF-8; the download page states how many Cost records without code were left out.
2. **Given** "Toewijzingen exporteren" for a Canvas, **When** used, **Then** a CSV `kostenpost_code;jaar;doel_pad;percentage` holds one line per Allocation line in set order, `doel_pad` as `Area > Domein > Cluster > Activiteit` with display names, and the download page states how many Cost records without code were left out.
3. **Given** an allocation CSV, **When** the dry run runs, **Then** lines are grouped per code and year into sets that replace the existing sets; a set not totalling 100,00%, an unknown code or path, a path to a non-Target, or a Finalised year is reported per Cost record.
4. **Given** a structure CSV `area;domein;cluster;activiteit;omschrijving;modaliteit` chosen with "Importeren uit CSV" in Invoeren with Area by display name or key, **When** the dry run runs, **Then** each line creates the missing Elements of its path and reuses existing ones by name, and description and Modality (name or letter) apply to the deepest Element of the line when filled.
5. **Given** a structure line with a cluster in a list Area, a Modality in a list Area, a missing `domein`, or an unknown Area, **When** the dry run runs, **Then** each is reported on its line.
6. **Given** any CSV dry run with an error, **When** the report shows, **Then** "Importeren" is disabled; without errors the import is written in one step or not at all.
7. **Given** a Cost record export re-imported unchanged, **When** the dry run runs, **Then** every line reads "ongewijzigd".
8. **Given** a Cost record named "=SOM(A1)" or a Supplier starting with "+", "-", "@", a tab or a carriage return, **When** exported to CSV, **Then** the field starts with an apostrophe so a spreadsheet shows it as text.
9. **Given** a Reader, **When** the "Exporteren" menu of Bekijken opens, **Then** it holds PNG, print, Canvas JSON, Cost records CSV and Allocations CSV for that Canvas and its Organisation; the same exports are on "Kostenposten" for Administrators.

**Accessibility**: WB1 to WB10.

---

### User Story 30 - Picture and print (Priority: P2, D3)

Any user exports the Canvas as PNG and prints it to PDF on landscape A3, with or without the active overlay (kickoff 12.6, acceptance 15).

**Why this priority**: the picture goes into reports and meetings.

**Independent Test**: export a Canvas of 120 Elements with the financial overlay to PNG and to PDF and read every name at A3 size.

**Acceptance Scenarios**:

1. **Given** Bekijken with the financial overlay, **When** "Exporteren als PNG" is used with "Met overlay" on, **Then** a PNG of the whole Canvas, drawn from the HTML view of Bekijken (ADR 0015) independent of zoom and scroll position, downloads holding the Artboard and any content beyond it, at twice the Artboard's pixel size, so that printed with the Artboard as an A3 page every text is at least 6 pt; when content lies beyond the Artboard its edge is drawn as a line. `[SD-24]`
2. **Given** "Afdrukken", **When** the browser prints a Canvas of any size, **Then** the Artboard prints one to one on one landscape A3 page, so every text of at least 10 pixels is at least 6 pt; content beyond the Artboard prints on the next landscape A3 pages below it, and the print dialog first warns "Niet alles past op het A3-tekenvlak: <n> blokken komen op een volgende pagina"; the print holds no top bar, left panel or detail panel, and holds the overlay when "Met overlay" is on.
3. **Given** a PNG or PDF, **When** inspected, **Then** its file name and content name no reference application, platform or consultancy (H8), and it was produced without any request leaving the machine (H1).

**Clarifications**: "Met overlay" is on by default when an overlay is chosen in Bekijken and absent when there is none, so the picture and the print equal the screen (`[ASSUMPTION overlay-switch]`); neither carries the overlay legend or the active filters, and whether a caption with year, overlay and filters should be added is open for the product owner (`[ASSUMPTION overlay-legend]`).

**Accessibility**: WB1 to WB9. The export options are labelled controls; the print layout keeps the text contrast of WB3.

---

### User Story 31 - Snapshots (Priority: P1, D3)

An Administrator records a Canvas with its figures for one Fiscal year as an immutable Snapshot; anyone can open, compare by eye and export it (S11, kickoff 9.5, acceptance 14).

**Why this priority**: acceptance criterion 14; history that later changes cannot touch.

**Independent Test**: take a Snapshot, rename and re-allocate Elements, open the Snapshot and see the old structure and amounts.

**Acceptance Scenarios**:

1. **Given** "Versies" of a Canvas, **When** "Momentopname maken" is used with name, Fiscal year and remark, **Then** a Snapshot is stored holding the Canvas name, Statements, Elements with, from DT, their Layout, and the figures of that year with the Cost records and sets behind them, and a checksum.
2. **Given** a failing closing check for that year, **When** a Snapshot is requested, **Then** it is refused with "De cijfers sluiten niet; een momentopname kan niet".
3. **Given** a Snapshot, **When** Elements are renamed, moved, archived and re-allocated afterwards, **Then** opening the Snapshot shows the structure and amounts of the moment it was taken, with the banner "Momentopname <naam> van 18-11-2026".
4. **Given** Bekijken, **When** "Versie" is set to a Snapshot, **Then** the same overlays, detail panel, PNG export and print work on it, read-only.
5. **Given** a Snapshot, **When** an Administrator edits it, **Then** only name, remark and archive status are editable, and a direct request to change its content is refused.
6. **Given** stored Snapshot content altered outside the application, **When** it is opened, **Then** the checksum check fails, the screen shows "Deze momentopname is beschadigd en wordt niet getoond", and a System notice is recorded.
7. **Given** a Snapshot, **When** deletion is confirmed, **Then** it is removed without undo.
8. **Given** a Reader, **When** "Versies" opens, **Then** they can list, open and export Snapshots and nothing else.

**Accessibility**: WB1 to WB12. The Snapshot banner is a landmark region announced on opening.

---

### User Story 32 - Sample data (Priority: P2, D3)

A fresh installation holds the sample Organisation "Voorbeeld BV" with a small filled Canvas and ten Cost records, clearly marked, removable with one button (kickoff 13, H9).

**Why this priority**: every screen shows something on first start.

**Independent Test**: start clean, walk every screen with the sample data, remove it.

**Acceptance Scenarios**:

1. **Given** a first start, **When** the overview opens, **Then** "Voorbeeld BV" shows with the label "Voorbeeld" and one Canvas with Statements, at least two Domains with Activities in each activity Area, at least one Cluster, Modalities, Groups and Items in the list Areas, and ten Cost records in the current Fiscal year of which eight are allocated and two sit in Unknown allocation.
2. **Given** "Voorbeelddata verwijderen" in "Beheer", **When** confirmed, **Then** the sample Organisation and everything in it are removed, and a restart does not bring it back.
3. **Given** the repository, **When** searched, **Then** it holds no data of the first client.

**Accessibility**: WB1 to WB10.

---

### User Story 33 - Backup, restore and manual (Priority: P2, D3)

The product owner makes and restores a backup with one command each, and reads a Dutch README of at most two pages (kickoff 10.7, 10.8).

**Why this priority**: client data lives in one file on one machine.

**Independent Test**: `make backup`, change data, `make restore`, see the data from the moment of backup.

**Acceptance Scenarios**:

1. **Given** a running application, **When** `make backup` runs, **Then** the database file and the uploads are copied to a folder named with date and time, and the command prints that folder.
2. **Given** a backup folder, **When** `make restore` runs with it, **Then** the application holds exactly the data of that backup after it restarts.
3. **Given** the README, **When** read, **Then** in plain Dutch and at most two printed pages it covers starting, first sign-in, the password reset command, backup and restore, import, and frequently asked questions.

**Accessibility**: not user-facing in the application; the README uses headings and plain language.

---

### User Story 34 - System notices (Priority: P2, D2)

An Administrator reads the System notices: closing-check failures, damaged Snapshots and repeated lockouts, recorded without personal data (FR-023, grilling Q9).

**Why this priority**: the technical side of the incident arrangement; a figures failure must be visible, not only logged.

**Independent Test**: inject a one-cent discrepancy in a test, open "Systeemmeldingen", find the notice.

**Acceptance Scenarios**:

1. **Given** a failed closing check, **When** "Systeemmeldingen" opens, **Then** the newest notice shows date and time, "Cijfers sluiten niet", Canvas, Fiscal year, filter, the failed check and the difference in cents.
2. **Given** a damaged Snapshot that was opened, **When** "Systeemmeldingen" opens, **Then** a notice shows "Momentopname beschadigd" with Canvas and Snapshot name.
3. **Given** the list, **When** filtered on kind, Canvas or date range, **Then** only matching notices remain with the count; no notice can be edited or deleted through any route; only "Gezien" can be set.
4. **Given** a Reader, **When** the address is requested, **Then** "Geen toegang" is shown.
5. **Given** a notice not yet marked "Gezien", **When** an Administrator opens any screen, **Then** the top bar shows "1 nieuwe systeemmelding" linking to the list; after "Gezien" on that notice the warning is gone for every Administrator.
6. **Given** the same closing check failing on ten page loads, **When** "Systeemmeldingen" opens, **Then** there is one notice with "10 keer, laatst om 16:40", not ten notices.
7. **Given** 10 lockouts of FR-004 within 60 minutes, for any e-mail addresses, **When** "Systeemmeldingen" opens, **Then** one notice "Herhaalde blokkades bij inloggen" shows the count and the time of the last one, without any e-mail address or user; nine lockouts within 60 minutes give no notice. `[SD-41]`

**Accessibility**: WB1 to WB10, on the shared list of User Story 4.

---

### User Story 35 - Tekenen: select, move and resize (Priority: P1, DT)

In Tekenen an Administrator arranges existing Areas and Elements on the fixed Artboard `[GATE-C34]` `[GATE-C36]`: select, move, resize, align, distribute and order, with undo and redo. The one structure change is moving one Cluster, Activity or Item to another parent with Shift, after a confirmation (scenarios 16 to 25, `[GATE-C53]`); nothing else in the structure changes (H12 as amended, ADR 0013, ADR 0016, ADR 0020, ADR 0036, design board 1b).

**Why this priority**: the reason for the Tekenen delivery.

**Independent Test**: move and resize an Area and a Domain, move a Cluster with five Activities, align three Activities, undo, all once with the pointer and once with the keyboard only.

**Acceptance Scenarios**:

1. **Given** an Administrator on a Canvas, **When** "Tekenen" is chosen, **Then** the left panel "Lagen" shows the seven Areas other than Direction with their Element trees, the middle shows the Artboard of 1680 by 1188 pixels on an 8-pixel dot grid, the right panel shows the properties of the selection, and the bottom toolbar holds "Selecteren en verplaatsen", "Schuiven", "Uitlijnen", "Gelijk verdelen" and the zoom; the header shows undo, redo, the save state, "Tekenvlak: OpenPencil, lokaal meegeleverd", "Alles automatisch opmaken" and "Passend maken".
2. **Given** a Domain, **When** it is dragged and resized, **Then** its place and size follow, a label shows its size ("286 × 204"), and "Plek en grootte" shows X, Y, B and H in whole pixels relative to its parent.
3. **Given** a selected Area (not Direction) or Element, **When** X, Y, B or H is typed in the panel, or an arrow key is pressed (moves 1 pixel, with Shift 8 pixels; with Alt the arrow keys change width and height instead, the browser's own use of Alt plus an arrow being prevented while the Artboard or "Lagen" has focus, which a browser test proves), **Then** it moves or resizes the same way as by dragging.
4. **Given** a Cluster with five Activities, or an Area with its Domains, **When** it is moved, **Then** its children move with it.
5. **Given** a width below 40 or a height below 24 pixels, or a width below the largest x plus width of its children, **When** it is entered or dragged, **Then** the width and the height each stop at their limit.
6. **Given**, other than a Shift move of scenario 16, a Cluster, Activity or Item dragged or moved by keyboard partly outside its parent to the left, right or top, a Domain outside its Area to the left, right or top, or an Area outside the Artboard or onto the Direction band on its stored place and size, **When** the action ends, **Then** it is pushed back inside and the user is told which were pushed back; a child dragged below its parent makes the parent grow instead. `[SD-34]`
7. **Given** a selection by Shift-click, by a selection frame, or in "Lagen" by Space on a row and Ctrl+Space to add or remove further rows, **When** "Uitlijnen" with "links" is used on three Activities and then "Gelijk verdelen" vertically, **Then** they get equal X and equal spacing, and one undo restores the state before "Gelijk verdelen". Aligning works on one or more selected items, relative to the selection or to the parent; distributing needs three or more; "Gelijke breedte" and "Gelijke hoogte" need two or more and follow the first selected.
8. **Given** a selected Element, **When** "Naar voren" or "Naar achteren" is used, **Then** it moves one step in front of or behind its siblings within the same parent.
9. **Given** "Raster" on, **When** an item is moved or resized, **Then** its place and size snap to the 8-pixel grid; with "Raster" off they do not. Snapping to other items is offered only if WP-E0 shows the drawing library provides it.
10. **Given** Tekenen, **When** any shortcut the drawing library defines for a new shape, text, pen, component, image, AI or collaboration (the list WP-E0 records) is used, or Delete or Backspace is pressed, **Then** nothing is created, deleted or renamed, and those tools are not shown.
11. **Given** a double-click on an Element's text, **When** it happens, **Then** the text stays unchanged and the hint "Tekst wijzig je in Invoeren" appears and is announced.
12. **Given** the Direction band (the Area Direction with its Statements) or the logo, **When** a drag, resize or move by keyboard is tried, **Then** nothing changes; they cannot be selected for Layout, and the server refuses any Layout for Direction. The band has a fixed place across the top and a fixed height of 180 pixels; its text wraps inside it, text that does not fit is cut off with "…", shown in full on hover, focus and in the detail panel, and listed as the Warning "Koerstekst past niet in de koersbalk". `[SD-40]`
13. **Given** an Element added in Invoeren since Tekenen last saved, **When** "Lagen" shows, **Then** its row carries the pill "nieuw" until that Element is selected or changed in Tekenen. `[SD-26]`
14. **Given** "Undo" and "Redo" (buttons and Ctrl+Z, Ctrl+Shift+Z), **When** used, **Then** each undoes or redoes one finished action: a drag, a resize, a property change, an alignment.
15. **Given** a Reader, an archived Canvas or a Snapshot (from D3), **When** Tekenen is requested, **Then** Bekijken opens instead; a Template opens Invoeren; a Finalised year (from D2) does not block Tekenen.
16. **Given** one selected block (a Cluster with its Activities, an Activity, or an Item in a list Area), **When** it is dragged and released with Shift held over a valid target, **Then** a dialog "Verplaatsen naar <doel>?" names the target by its path, and nothing is stored before "Verplaatsen" is chosen. Valid targets: for a Cluster, any Domain of an activity Area other than its own; for an Activity, any Domain or Cluster of an activity Area other than its current parent, also within its own Domain (into a Cluster, or out of it to Domain level); for an Item, any Group of a list Area other than its own. The target is the innermost valid target under the pointer at release (for a Cluster, a Cluster under the pointer counts as its Domain). A block never changes kind of Area (activity Areas among themselves, list Areas among themselves), and a whole Domain is never moved with Shift: a Domain is only slid within its own Area, and moved to another Area through "Hangt onder" in Invoeren. `[GATE-C53]` A Shift-drag always acts on the one block under the pointer when the drag starts: the selection becomes that block, so several blocks are never moved at once; a Shift-click without movement keeps adding to the selection (scenario 7).
17. **Given** the dialog of scenario 16, **When** "Verplaatsen" is chosen, **Then** the block becomes a child of the target at the end of its order, a Cluster's Activities go with it, its Allocation lines (from D2) stay on the same identifiers so its amounts now count in the new Domain and, across Areas, in the new Area Total, the structure version rises, and the Audit log records the old and new "Hangt onder". The block keeps its size and style and stays where it was released, as a Manual layout relative to its new parent; a Cluster's Activities keep their place inside it. This deviates from addendum 6.2 and SD-29, which still hold for "Hangt onder" in Invoeren. `[GATE-C53]`
18. **Given** the dialog of scenario 16, **When** "Annuleren" or Escape is chosen, **Then** nothing is stored and the block returns to its former place, parent and size.
19. **Given** a Shift-drag, **When** the block is released partly outside its target on the left, top or right, **Then** before the dialog it is pushed inside the target's width (x at least 0, y at least 0, x plus width at most the target's width), keeping its size; released below the target's content, the target grows in height. A block wider than its target is made exactly as wide as the target, its height, style and place otherwise kept; its Manual children are first pushed left and then narrowed where they would stick out, its automatic children re-flow; the label while dragging reads "Naar <doel> (wordt smaller)", and after the move "<blok> is smaller gemaakt tot de breedte van <doel>" is shown and announced. (QS-8, decided by the product owner 2026-11-19): the orchestrator's advice, to be confirmed by the product owner. Elements in Automatic layout in the target stay automatic under the lowest Manual child and may jump (User Story 38 scenario 3a); a block that ends up overlapping another is listed as a Warning (FR-106a).
20. **Given** a Shift-drag over a target that would be refused (the Domain "Onbekende allocatie", a target under which its name is already used, an Area of the other kind), **When** it is released, **Then** no dialog opens, the block returns to its place, and the reason is shown and announced with the messages of Invoeren ("Onbekende allocatie wordt door het systeem beheerd", "Naam: er staat al een element met deze naam op deze plek", "Verplaatsen kan alleen naar een area van dezelfde soort"). While dragging, the target shows "Naar <doel>" or "Kan hier niet: <reden>" as text, not by colour alone; the label is shown, not announced, and only the refusal at release is announced.
21. **Given** a drag that is not a Shift move (released without Shift, over its current parent or over no valid target, over the Direction band, or of a Domain), **When** it ends, **Then** nothing changes in the structure and the block is pushed back inside its own parent per scenario 6.
22. **Given** a selected Cluster, Activity or Item, **When** "Verplaatsen naar" in the panel is set to a valid target by its path and "Verplaatsen" is chosen, **Then** the same dialog of scenario 16 opens and the move follows scenarios 17 and 18, the block keeping its size and style and placed at the target's left edge under the target's lowest Manual child, or at the top of the target when it has none; the targets offered follow the same rules as the drag, including the narrowing of scenario 19 (QS-8, decided by the product owner 2026-11-19). This is the keyboard and single-pointer alternative (WB11, 2.5.7). `[SD-42]`
23. **Given** a Shift move, **When** "Undo" is used, **Then** the block returns to its former parent, its former place in the order and its former Layout (Automatic again if it had no Manual layout before), through the same rules; when that is refused now (for example the name was taken meanwhile), nothing changes, the reason is shown, the undo and redo history of the session is cleared and the drawing reloads from the stored data; "Redo" moves it again the same way. Undo and redo of a move also ask "Verplaatsen naar <doel>?" first, and "Annuleren" leaves the undo history unchanged. (QS-9, decided by the product owner 2026-11-19): the orchestrator's advice, to be confirmed by the product owner.
24. **Given** a newer structure version than Tekenen loaded, **When** a confirmed Shift move is sent, **Then** it is refused and the user chooses as in User Story 37 scenario 5; with "Opmaak toepassen op wat nog bestaat", after loading, the dialog asks again when the block and its target still exist and the move is still valid, and otherwise the message "De verplaatsing is niet opgeslagen" appears. Given a newer Layout version only, the move is refused, the drawing reloads as in User Story 37 scenario 6, and the message adds "De verplaatsing is niet opgeslagen". While the dialog is open or a confirmed move is being sent, Tekenen takes no other action.
25. **Given** a confirmed Shift move, **When** it is sent, **Then** it goes as its own request in the same one-at-a-time order as the automatic saves, and the parent change and the moved block's Layout are written in one transaction or not at all (ADR 0036); a Finalised year (from D2) does not block it, and that year's figures follow the new structure with the same Canvas total (FR-072).

**Accessibility**: WB1 to WB12; for WB10 the panels reflow and the Artboard scrolls in both directions like the canvas. "Lagen" is the text alternative of the drawing (1.1.1): every Area other than Direction and every Element can be selected there with the keyboard, and selection in the drawing and in "Lagen" stay the same. Every drag has a keyboard and single-pointer alternative through the X, Y, B and H fields and the arrow keys, and the Shift move through "Verplaatsen naar" (2.5.7, scenario 22). The confirmation dialog follows WB8: focus moves into it on "Verplaatsen", Enter confirms, Escape cancels, and focus returns to the control that opened it (the "Verplaatsen" button of the panel, the Undo or Redo button, or, for a pointer drag, the block's row in "Lagen", the keyboard proxy of the drawing). A Shift move is announced with the moved block and its new parent (WB7). Resize handles may be smaller than 24 pixels because the fields are an equivalent control (2.5.8 exception). Push-back, alignment, undo and failed or refused saves are announced (WB7); a successful automatic save is shown but not announced, so it does not interrupt after every nudge.

---

### User Story 36 - Tekenen: colours, fonts and style (Priority: P2, DT)

An Administrator sets the style of an Area or Element, copies it, or applies it to all Activities of a Domain or all Domains of an Area; the Default colours stay underneath (ADR 0018, addendum chapter 5). `[GATE-C33]`

**Why this priority**: colour is the other half of arranging the picture, and with no theme screen the only place to set it.

**Independent Test**: style a Domain, copy its style to another, apply it to all Domains of its Area, set a text colour that fails contrast, see it refused.

**Acceptance Scenarios**:

1. **Given** a selected Area or Element, **When** "Kleur" shows, **Then** it holds "Vulling" (colour with opacity 0 to 100%), "Rand" (colour, width 0 to 8 pixels, "doorgetrokken" or "gestreept"), "Hoeken" (0 to 64 pixels), and, for the title of a Domain or Cluster and the text of an Activity, "Tekst" (font Inter or Space Grotesk, size 10 to 48 pixels, "normaal" or "vet", "cursief", colour) and "Uitlijning" (left, centre, right; top, middle, bottom). Each colour is a swatch with a hex field (`#RRGGBB`). `[SD-37]`
2. **Given** a changed fill of a Domain, **When** the change is saved, **Then** the Domain shows that fill in Tekenen and Bekijken alike, and (from D3) in the PNG export.
3. **Given** a Domain with a style, **When** "Stijl kopiëren" is used on it and "Stijl plakken" on another Domain, **Then** the second gets the same style; pasting onto another kind is not offered.
4. **Given** a Domain with a style, **When** "Stijl naar alle domeinen hier" or, for an Activity, "Stijl naar alle activiteiten in dit domein" is confirmed, **Then** all of them get the same style, and their places and sizes are unchanged.
5. **Given** a text colour with a contrast below 4.5:1 on the Element's fill (after opacity, over the fill behind it), **When** it is chosen, **Then** the panel shows the ratio, keeps the chosen value in the field marked invalid with focus on it, and the server refuses the save with "Tekst <naam>: kies een tekstkleur met meer contrast, nu <x>:1, minstens 4,5:1". `[SD-27]`
6. **Given** a value "#12345" or "rood", **When** typed, **Then** the field keeps it, shows "Vulling <naam>: gebruik de vorm #RRGGBB" and nothing is saved.
7. **Given** an Element with its own dark text colour on a light fill, **When** its fill is changed to a dark colour, **Then** its text colour returns to automatic (white) in the same action and the user is told, so a dark fill with white text is reachable. `[SD-27]`

**Accessibility**: WB1 to WB10. Every field is labelled with property and name; the contrast ratio is text.

---

### User Story 37 - Tekenen: saving and back to automatic (Priority: P1, DT)

Tekenen saves automatically after every finished action; the server checks the whole Layout; Layout can go back to automatic for an Element, a Domain or the whole Canvas (ADR 0021, addendum 6.3, design board 1b). `[GATE-C32]` `[GATE-C47]`

**Why this priority**: a Layout the server did not check could break H11, H12 or H13.

**Independent Test**: make changes and reload, send faulty Layouts directly to the server, open Tekenen twice, and set the whole Canvas back to automatic and compare with a new Canvas of the same content.

**Acceptance Scenarios**:

1. **Given** a finished action in Tekenen, **When** it ends, **Then** the whole Layout of the Canvas is sent with the structure version and the Layout version Tekenen last loaded or last saved successfully, written in one transaction; saves go one at a time, and actions that finish while a save is under way are sent together in the next save with the version that save returned, and the header shows "Opmaak opgeslagen"; while sending it shows "Opslaan…". There is no Save button.
2. **Given** a request sent directly to the server with an unknown or archived Element, an Element of another Canvas, an Element missing from the Canvas, a parent different from the structure, a child outside its parent's width, an Area outside the Artboard or overlapping the Direction band, a size below the limits of User Story 35, or a style key or value outside FR-104, **When** it arrives, **Then** the whole save is refused with the reason and nothing is stored. A Layout save never changes a parent; a parent changes only through a Shift move (User Story 35 scenarios 16 to 25).
3. **Given** a save refused for a rule of FR-103 to FR-105, **When** the refusal arrives, **Then** the drawing returns to the last stored Layout (undoing every action in that save), a value typed in the panel stays in its field marked invalid with focus on it (WB5), and the reason is shown and announced.
4. **Given** a Layout missing an Element or holding an extra one while the structure version is unchanged, **When** it is saved, **Then** it is refused because H12 was bypassed, and Tekenen rebuilds its drawing from the stored data.
5. **Given** someone changed the structure after Tekenen was opened (a newer structure version), **When** the next save is sent, **Then** saving pauses and the user chooses between "Opmaak toepassen op wat nog bestaat" and "Opnieuw laden".
6. **Given** two Administrators in Tekenen on the same Canvas, **When** one saves after the other did (a newer Layout version), **Then** that save is refused, the drawing reloads with the stored Layout, and the message "Iemand anders heeft de opmaak gewijzigd. De tekening is opnieuw geladen" appears. `[SD-35]`
7. **Given** a save that fails because the server cannot be reached or the session ended, **When** it fails, **Then** the header shows "Opmaak niet opgeslagen" with "Opnieuw proberen", and leaving Tekenen asks "Opnieuw proberen" or "Wijziging weggooien".
8. **Given** a selected Element or Area, **When** "Terug naar automatische plek" is confirmed, **Then** its place, size and style return to the Automatic layout.
9. **Given** a selected Domain, **When** "Domein terug naar automatisch" is confirmed, **Then** the Domain and every Element in it return to automatic place, size and style. `[SD-36]`
10. **Given** a Canvas with Manual layout, **When** "Alles automatisch opmaken" is confirmed, **Then** every Area other than Direction and every Element has exactly the place, size and style of a new Canvas with the same content.
11. **Given** a saved Layout, **When** the Audit log is read, **Then** one entry per changed Area or Element shows its old and new place, size and style, with changes by the same user within 60 seconds merged. `[SD-28]`

**Accessibility**: WB1 to WB10. The save state is shown; only failures and refusals are announced (WB7), naming the Element; each confirmation is a dialog per WB8.

---

### User Story 38 - Layout in Invoeren and Bekijken (Priority: P1, DT)

Bekijken draws the stored Layout equal to Tekenen; names always wrap and Elements grow to hold them; structure changes in Invoeren place Elements in the drawing without handwork (H11, ADR 0020, addendum 6.2, kickoff v1.2 6.5.7). `[GATE-C48]`

**Why this priority**: the three modes must show one Canvas.

**Independent Test**: on a Canvas with Manual layout add, move, archive and rename Elements in Invoeren, then compare Tekenen and Bekijken.

**Acceptance Scenarios**:

1. **Given** an Area and a Domain moved and resized in Tekenen, **When** Bekijken opens, **Then** both are at the same place and size within 1 pixel at 100% zoom, with the same text lines.
2. **Given** a parent whose children are all in Automatic layout, **When** a new Element is added in Invoeren, **Then** it is placed automatically.
3. **Given** a parent with a child in Manual layout, **When** a new Element is added in Invoeren, **Then** it is placed under the lowest child at the size Automatic layout gives that kind of Element, and the parent grows in height when needed with the message "Opmaak aangepast".
3a. **Given** a parent with one child in Manual layout and other children in Automatic layout, **When** it is drawn, **Then** the automatic children stay automatic and are placed in their order under the lowest Manual child at the size Automatic layout gives their kind; when another sibling becomes Manual, they may jump. `[GATE-C54]`
4. **Given** an Element moved to another parent through "Hangt onder", **When** it arrives, **Then** it and its subtree lose their Manual layout and are placed as a new Element would be. `[SD-29]`
5. **Given** an Element archived or deleted, **When** that happens, **Then** its Layout goes with it; undo of a deletion restores it; an Element restored from archive is placed as a new Element would be.
6. **Given** a name longer than its Element's width, **When** drawn in any mode, **Then** it wraps onto further lines and the Element grows in height, also when its size was set by hand; its parents grow with it; Elements with a Manual layout do not move, Elements in Automatic layout re-flow.
7. **Given** growth or a move that makes Elements or Areas overlap, **When** Invoeren shows "Waarschuwingen", **Then** it lists "Overlapt: <naam> en <naam>". `[SD-34]`
8. **Given** content that grows beyond the bottom of the Artboard, **When** drawn in Tekenen or Bekijken, **Then** the Artboard keeps its size, the content is drawn beyond the edge as in User Story 16 scenario 8, it is listed as "Past niet op het tekenvlak: <naam>", and nothing is refused, scaled or made smaller; the Administrator resolves it by making Domains, Clusters and Activities smaller or arranging them differently. `[GATE-C51]`
9. **Given** the overview, **When** a Canvas card shows its miniature, **Then** it is drawn from the stored Layout and colours.

**Accessibility**: as User Story 16.

---

### User Story 39 - Layout travels with copies, Snapshots and exports (Priority: P2, DT; scenarios 2 to 5 from D3)

The Layout goes with a Canvas copy, a Snapshot, a Canvas JSON export and import, and a full export and import (addendum 6.1 point 5).

**Why this priority**: a picture that is lost on copy or restore is not kept.

**Independent Test**: arrange a Canvas, copy it, take a Snapshot, export and import it, and compare the drawing each time.

**Acceptance Scenarios**:

1. **Given** a Canvas with Manual layout, **When** it is copied within or across Organisations, **Then** the copy has the same Layout on its new Elements.
2. **Given** a Snapshot taken of it (from D3), **When** the Snapshot is opened later after the Layout changed, **Then** it shows the Layout of its moment; a Snapshot without Layout shows the Automatic layout.
3. **Given** a Canvas JSON export (from D3), **When** it is imported, **Then** the new Canvas has the same Layout mapped onto its new Element identifiers, and every Layout value passes the checks of User Story 37 scenario 2 in the dry run.
4. **Given** a Canvas JSON file without Layout (from D3), **When** imported, **Then** it is accepted and the Canvas starts in Automatic layout.
5. **Given** a full export, a wipe and a full import (from D3), **When** the Canvases are compared, **Then** every Layout is identical.

**Accessibility**: as User Stories 27 and 31.

---

### Technical proof WP-E0 (DT, go or no-go)

Before any further Tekenen work, a minimal working example in the project setup (Laravel, Inertia, Vue 3, Vite, Docker) answers addendum chapter 8. It runs right after the project setup. Each point gets a short report with evidence (screenshot, test or log).

| Point | To prove | Go when |
|---|---|---|
| P1 | Install and build with the chosen package manager inside Docker | Clean build without handwork, versions pinned exactly |
| P2 | Build a drawing from our data: 3 Domains, 1 Cluster, 10 Activities with text, colour and font | The picture matches the input |
| P3 | Read back place, size and style per node with the Element identifier | Round trip without difference |
| P4 | Notice changes from the host | By an event or by comparing, within 100 ms of the action |
| P5 | Restrict: the library itself never creates, deletes or reparents a node; Direction band and logo locked; children kept inside their parent | Through the SDK or the adapter, shown by a test |
| P6 | No outgoing traffic while loading and using, under the content security policy of FR-090, and the asset check of FR-098 on the Tekenen bundle | Network log shows only the own server, the asset check passes (never-requested strings in vendored code excepted, FR-098) |
| P7 | Equal to the HTML view | Place and size within 1 pixel; the same wrapped text lines with Inter and Space Grotesk, and the same growth of Elements |
| P8 | Undo and redo over drag, size, style and alignment | Works per action |
| P9 | Load time of the Tekenen bundle | First use within 3 seconds on an ordinary laptop |
| P10 | Shift move: recognise a drag with Shift held, find the Domain under the pointer, show the target label, and keep the library from reparenting the node or applying its own Shift behaviour (such as an axis lock) | Through the SDK or the adapter, shown by a test |

Go: every point P1 to P10 met, where P4, P5, P10 and the alignment and distribution of User Story 35 may be met in the adapter instead of the SDK. No-go: P2, P3, P6 or P7 not achievable. Any other point not met (P1, P8, P9 or P10) goes to the product owner with the report, who decides go or no-go. A no-go stops DT and goes to the product owner with the report; D1 to D3 continue on Automatic layout, and no other drawing library is chosen without the product owner.

---

### Edge Cases

- A Cost record with Budget € 0,00 and an Allocation set: all its line amounts are € 0,00 and the closing check passes.
- A Cost record with Budget € 0,01 over three Targets: one Target gets € 0,01 (the largest remainder, first in set order on a tie), the others € 0,00.
- The largest Budget € 999.999.999,99 at 1 basis point: calculated exactly, without overflow.
- An Organisation with one Canvas whose last Canvas is deleted: its Cost records and Yearly figures stay; their Allocation sets in that Canvas go with it.
- A new Canvas in an Organisation with 1000 Cost records: every Cost record sits in Unknown allocation of the new Canvas until allocated.
- An Element moved between Areas of the same kind while a year is Finalised: Canvas total unchanged, Area Totals move with it.
- Supporting activities without any Domain of the user's own: it still shows the Domain "Onbekende allocatie", and no warning is raised for it.
- A Domain whose only children are empty Clusters: it shows both warnings, "Leeg cluster" and "Domein zonder activiteiten".
- Two Administrators saving the same record: the last save wins, both saves are in the Audit log; Layout follows SD-35 instead. `[SD-22]`
- Two Administrators saving a set and finalising the same year at once: one of the two is refused, so a Finalised year never holds a Cost record in Unknown allocation.
- Undo after the page is left: not offered; the deletion stands.
- A Template saved from a Canvas with archived Elements: the archived Elements are not in the Template.
- A Cost record whose Owner is removed through "Vervangen door" while its year is Finalised: allowed, Owner is not a Yearly figure; the Owner filter shows the new Owner.
- A JSON import of the same file twice into one Organisation: the second creates a second Canvas and reuses the Cost records.
- A Snapshot of a year that is not Finalised: allowed; the Snapshot records its moment.
- A list Area Domain moved to an activity Area: refused, same kind only. `[SD-11]`
- Two Administrators in Tekenen on the same Canvas: the later save on an older Layout version is refused and that drawing reloads (DT, User Story 37 scenario 6). `[SD-35]`
- The Unknown allocation Domain in Tekenen: its Layout and style can be changed like any Domain; its structure cannot, and nothing can be Shift-moved into it (DT). `[SD-30]`
- A Shift move while another Administrator edits the same Canvas in Invoeren: whichever reaches the server second sees a newer structure version; a Shift move is then refused with the choice of User Story 37 scenario 5 (DT).
- A Shift move of an Activity carrying Allocation lines: the lines stay on the Activity, the old Domain's Total drops and the new Domain's Total rises by the same amount (and the Area Totals, when it crosses Areas); the Canvas total and the closing check are unchanged, also in a Finalised year (DT, from D2).
- A Shift move whose confirmation dialog is still open while another Administrator changes the structure: the confirmed move meets a newer structure version and follows User Story 35 scenario 24 (DT).
- The forbidden names file for the H8 check absent: the check reports "overgeslagen: lijst verboden namen ontbreekt" visibly and does not count as passed. `[GATE-U4]`

## Requirements *(mandatory)*

### Functional Requirements

#### Accounts, sign-in and roles (D1)

- **FR-001**: System MUST authenticate with local accounts only: e-mail address (unique, compared ignoring case) and password. No outside identity provider, e-mail sending or other outside service is used (H1).
- **FR-002**: Passwords MUST be 12 to 128 characters, without composition rules, MUST be refused when they appear on a bundled list of at least 10,000 common passwords, and MUST be stored only as a salted hash of a deliberately slow algorithm. Password fields MUST accept paste.
- **FR-003**: One-time passwords (first Administrator, new user, reset) MUST be 16 random characters, shown once, MUST be replaced before any other screen is reachable, and MUST expire 24 hours after they were made; an expired one needs a new reset.
- **FR-004**: After 5 failed attempts for one e-mail address within one minute, sign-in for that address MUST be refused for 60 seconds; the same holds for second-factor codes per account. Wrong e-mail and wrong password MUST give the same message.
- **FR-005**: Sessions MUST expire after 120 minutes without activity, with a warning 2 minutes before that offers "Sessie verlengen" (WCAG 2.2.1), MUST get a new identifier at sign-in, and MUST end at sign-out; all other sessions of a user MUST end when they change their password, and all sessions when their password or second factor is reset or the user is deleted. Sessions MUST also end 12 hours after sign-in, whatever the activity; one account MAY hold any number of sessions at once. All other sessions of a user MUST end when they turn their second factor on or off or regenerate their recovery codes; an Administrator MUST be able to end every session of another user; changing one's own e-mail address MUST require the current password (ADR 0037). Session cookies MUST be HttpOnly and SameSite=Lax, with the Secure attribute on by default and switchable by setting. Every state-changing request MUST carry a valid anti-forgery token.
- **FR-006**: The optional second factor MUST be time-based one-time passwords (6 digits, 30-second step, one step of clock tolerance either way), MUST remember the last accepted time step per user and refuse any code for that step or an earlier one, MUST show its QR code generated inside the application, and MUST come with 8 single-use recovery codes stored only as hashes. Turning it on, off, and regenerating codes MUST require the current password.
- **FR-007**: An Administrator MUST be able to reset another user's second factor, never their own through that action.
- **FR-008**: A console command run inside the container MUST reset a named Administrator's password to a new one-time password, printed once, and on request also remove that Administrator's second factor and recovery codes.
- **FR-009**: Each user MUST have exactly one role, Administrator or Reader, valid for every Organisation. A Reader MAY only: sign in and out, change their own password, manage their own second factor, read the overview, Bekijken with overlays and filters and Snapshots, and request every export (PNG, print, Canvas JSON, Cost records CSV, Allocations CSV). The server MUST refuse every other write request from a Reader.
- **FR-010**: A user MUST NOT be able to delete their own account or change their own role, and the last Administrator MUST NOT be deleted or changed to Reader, also when two such requests arrive at the same moment.
- **FR-011**: When a user is deleted, their sessions MUST end at once, and wherever they are named (Audit log actor, Audit log values of entries about that user, "Definitief door", "Gemaakt door" of a Snapshot) the text "verwijderde gebruiker" MUST appear instead of their name and e-mail address (grilling Q8).

#### Shell and presentation (D1)

- **FR-012**: Every screen MUST render inside the shell of User Story 3: top bar with tabs, a left panel where the screen has one, main area and detail panel, laid out per `design-contract.md` (ADR 0019). The application name MUST come from a setting, default "OMC Studio".
- **FR-013**: Every screen text MUST come from the Dutch messages file. Amounts MUST show as `€ 1.234,56`, percentages as `33,33%`, FTE as `1,50`, dates as `18-11-2026`, in time zone Europe/Amsterdam. On the canvas and in the overlay, amounts MUST show as whole euros (`€ 1.235`, each amount rounded half up on its own, so the same amount shows the same wherever whole euros show, also where a list of rounded amounts does not add up to its rounded total `[GATE-C52]`) and percentages with one decimal (`12,4%`); the detail panel, tables, forms, Toewijzen and the line "Totalen sluiten" in the side panel MUST show cents and two decimals. `[GATE-C40]`
- **FR-014**: Amount input MUST accept digits with an optional decimal comma and at most two decimals, with optional dots as thousands separators only in groups of three; percentage input MUST accept 0 to 100 with at most two decimals; FTE input 0 to 9.999,99 with at most two decimals. Anything else MUST be refused with the expected format in the message.
- **FR-015**: Text input MUST be trimmed; a value empty after trimming is empty. Lengths: every name and title 1 to 150 characters, code and cost centre at most 50, Supplier at most 150, descriptions, Rationales, remarks, mission, vision and goal explanations at most 2000.

#### Management lists, undo and archive (D1)

- **FR-016**: Every record list MUST be built on one shared list component offering the behaviour of User Story 4: search, sort per column, filters, result count, side-panel form, confirmation naming the consequence in numbers, bulk delete and archive with a per-row result, archived rows hidden by default with "Toon gearchiveerd" and "Terugzetten".
- **FR-017**: After a deletion of anything other than a Canvas, Organisation, Snapshot, Template or User (whose pseudonymisation cannot be reversed), "Ongedaan maken" MUST be offered for 10 seconds, pausing while it has focus or pointer hover, and MUST end when the page is left; when it ends, focus moves to the list's search field. After a bulk deletion, undo restores every row of that action. Undo MUST restore exactly what the deletion removed, including dependent records, with the same identifiers, in one step, and MUST be refused with the reason when the restore would break any rule as it stands at that moment.
- **FR-018**: Archiving MUST apply to Organisations, Canvases, Cost records, Snapshots and Elements (directly per FR-040a, or through Redistribution). An archived Organisation or Canvas MUST be hidden by default and read-only until restored. An archived Cost record MUST be hidden by default with its own fields and Yearly figures read-only, while its Allocation sets stay writable (directly, through Redistribution and through Carry over) and "Vervangen door" still reassigns it. Everything archived MUST keep counting in every figure it was part of. `[SD-9]`

#### Audit log, security log and System notices

- **FR-019**: Every create, change, delete, archive, restore, undo, Finalise, Reopen, Redistribution, Carry over and imported record MUST write one Audit log entry per affected record holding time, actor, action, kind of record, its identifier and label, Organisation and Canvas where applicable, and each changed field with old and new value (D1 for D1 data, each later delivery for its own data).
- **FR-020**: Audit log entries MUST NOT hold passwords, password hashes, second-factor keys or recovery codes; such changes MUST be recorded as an event name only.
- **FR-021**: The Audit log MUST be read-only: no route changes or deletes an entry. Entries MUST be removed only together with the hard deletion of their Canvas or Organisation (grilling Q8); that deletion MUST leave one entry with actor, time, name and the counts of what was removed.
- **FR-022**: A security log MUST record sign-in success and failure, refusal after too many attempts, sign-out, password change and reset, second factor on, off and reset, sessions ended by an Administrator, role change, user creation and deletion, refused access, imports and full exports, with time, event and user identifier, and without e-mail address, name, typed input, password, code or network address. It MUST be a file inside the application's storage on the machine, readable only by whoever operates the machine, not shown in any screen. Entries older than 90 days MUST be removed automatically. `[SD-20]`
- **FR-023**: Closing-check failures, Snapshot checksum failures and repeated lockouts (10 sign-in or second-factor lockouts of FR-004 within 60 minutes across the application, no user identifier or e-mail address recorded) MUST be recorded as System notices (time, Canvas, year, filter or Snapshot, failed check, difference in cents, or for lockouts their count), without personal data, listed read-only for Administrators under "Systeemmeldingen" (D2, User Story 34). A failure that repeats records one notice per fact (Canvas, year, filter, check or Snapshot; for lockouts one notice while it is not marked "Gezien"), updating its last occurrence and count. While a notice is not marked "Gezien", every Administrator MUST see a warning in the top bar on every screen. `[GATE-U2]` `[SD-41]`

#### Organisations, Canvases and Templates (D1)

- **FR-024**: An Organisation MUST have a name unique ignoring case, a slug derived from the name (lowercase letters, digits and hyphens, unique) used in file names, and an optional logo: PNG or JPEG, at most 1 MB and 4000 by 4000 pixels, verified on content, re-encoded by the application (which removes embedded metadata), stored under a random name outside the publicly served files, and served with a fixed image content type.
- **FR-025**: An Organisation MUST be deletable only without Canvases and Cost records; deletion MUST remove its Owners, Applications, Fiscal years and Audit log entries, except the deletion entry of FR-021. Otherwise it MUST be archived instead.
- **FR-026**: A Canvas MUST belong to one Organisation, MUST have a name unique within it ignoring case, and MUST always show the eight Areas, which are never stored per Canvas and never copied.
- **FR-027**: A new Canvas MUST start from a Template, receiving copies of its Elements with all fields and its Statements, in Automatic layout with the Default colours.
- **FR-028**: Copying a Canvas within its Organisation MUST copy active Elements with all fields, Statements and Allocation sets; copying to another Organisation MUST copy active Elements and Statements only. From DT both MUST also copy the Layout of the copied Elements and the Artboard. A copy MUST NOT hold Finalised years, Snapshots or archived Elements (grilling Q7).
- **FR-029**: Deleting a Canvas MUST require typing its exact name and MUST remove its Elements, Statements, Layout, Allocation sets, Finalised years, Snapshots and Audit log entries, except the deletion entry of FR-021.
- **FR-030**: A Template MUST be a Canvas without Organisation holding only Elements and Statements. An Administrator MUST be able to save a Canvas as a Template (active Elements and Statements only), edit a Template's structure and Statements in Invoeren without financial functions, rename it, and delete it by typing its name. Template names MUST be unique among Templates, ignoring case. The Template "Leeg canvas" MUST hold nothing, MUST NOT be shown as a card, and MUST NOT be editable, renamable or deletable. `[SD-10]`
- **FR-031**: The overview MUST show each Canvas card with a miniature drawn from its current Areas and Domains in their colours, and from DT from their stored Layout.

#### Element tree (D1)

- **FR-032**: The eight Areas of kickoff 6.1 MUST exist with key, screen label, kind (text, activities, list), order, level labels and whether they carry costs, and MUST NOT be changeable by any route (H2).
- **FR-033**: Element kinds and parents MUST follow kickoff 6.2: a Domain directly under an Area other than Direction; a Cluster under a Domain of an activity Area, never under a Cluster; an Activity under a Domain or a Cluster of that Domain (H3, H4).
- **FR-034**: Element fields MUST be name, description, Modality and Rationale (activity Areas only), icon (list Areas only, from a bundled set of icons each with a Dutch name), and status active or archived. Domains have no column width (ADR 0013).
- **FR-035**: Element names MUST NOT contain ">" and MUST be unique among the active Elements under the same parent, compared trimmed and ignoring case; for Domains the parent is the Area. `[SD-23]`
- **FR-036**: Each parent MUST keep an order of its children; a new Element goes last; reordering MUST be possible with "Omhoog" and "Omlaag" in Invoeren. No screen reorders by dragging. The order drives the Automatic layout and the reading order of the tree.
- **FR-037**: Moves MUST stay within Areas of the same kind: an Activity to another Domain or Cluster, a Cluster to another Domain of an activity Area with its Activities, a Domain to another Area with its subtree. A move MUST be refused when the name is taken at the destination. Allocation lines MUST stay on the moved Elements. Moves come from "Hangt onder" in Invoeren and, for Clusters, Activities and Items, from the confirmed Shift move in Tekenen (FR-101a); both go through these same rules. `[SD-11]`
- **FR-038**: An Element whose subtree carries no Allocation line MUST be deletable after confirmation naming the children that go with it; for a Cluster the user MUST choose between deleting its Activities and moving them under its Domain. An Element whose subtree carries an Allocation line MUST NOT be deleted; Redistribution (FR-069) applies instead. When a Cluster's Activities are moved under its Domain, they keep their lines, and only lines on the Cluster itself require Redistribution.
- **FR-039**: Archived Elements MUST NOT be Targets, MUST NOT carry Allocation lines, MUST be hidden in every mode unless "Toon gearchiveerd" is on in Invoeren, MUST NOT be copied into Canvases or Templates, and MUST be restorable under their former parent when that parent is active and the name is free. `[SD-17]`
- **FR-040**: Invoeren MUST list as warnings every Cluster without Activities and every Domain without Activities, counting Activities inside its Clusters, every Domain (Group in list Areas) lying partly or wholly beyond the Artboard ("Past niet op het tekenvlak"; these are the "blokken" counted in the notice of User Story 16 scenario 8), Direction text that does not fit the band ("Koerstekst past niet in de koersbalk"), and, from DT, overlapping Elements or Areas. `[GATE-C51]`
- **FR-040a**: An Element MUST be archivable directly from Invoeren, with its subtree, only while no Allocation line sits in that subtree; otherwise Redistribution (FR-069) applies.
- **FR-041**: Quick entry MUST parse lines per User Story 12: a tab or two spaces per indent level, at most two levels, a level-1 line becoming a Cluster only when level-2 lines follow it, no Clusters in list Areas, existing names reused, every line error reported with its line number, and the whole input saved at once or not at all.
- **FR-042**: A Canvas MUST hold at most one mission and one vision, and an ordered list of strategic goals of title and explanation.
- **FR-043a**: Every Canvas MUST hold exactly one Unknown allocation Domain ("Onbekende allocatie"), created by the system with the Canvas by every route (new, copy, import, full import) and always shown last in Supporting activities. It MUST NOT be renamed, edited, moved, reordered, archived or deleted, MUST NOT receive children or moved-in Elements, MUST NOT be a Target or a Redistribution target, MUST NOT appear in Templates, MUST NOT raise a warning, and carries no Modality; every route doing any of these MUST be refused (ADR 0008).
- **FR-043**: The four Modalities MUST be fixed: Common (C, green), Adaptive (A, blue), Specialized (S, orange), Distinct (D, purple). A Rationale MUST only be stored with a Modality; clearing the Modality clears the Rationale.

#### Canvas layout and colours (D1)

- **FR-044**: In D1 the canvas MUST be drawn in Automatic layout on the Artboard per kickoff 6.5, FR-107 and User Story 16, with zoom from 50% to 200% in 10% steps and "Passend maken", which may set any zoom from 10% to 200%. From DT an Area or Element MAY have a Manual layout set in Tekenen (ADR 0013). No layers, guides, free shapes, free texts or images exist beyond FR-100 to FR-116.
- **FR-045**: Keyboard operation MUST follow User Story 11 in Invoeren (Tab through rows, named arrow buttons) and User Story 16 in Bekijken (Tab between Areas and toolbar, arrow keys through the tree, Enter for the detail panel); Tekenen follows User Story 35.
- **FR-046**: The application MUST ship Default colours per Area and per level (Domain, Cluster, Activity) as listed in `design-contract.md`; no route changes them and no theme screen exists (ADR 0018). Text without a colour of its own MUST be ink `#171B21` or white, whichever contrasts more with its background. In D1 colours cannot be changed; a no-go on WP-E0 leads to a separate decision. `[GATE-C49]` `[GATE-C50]`

#### Tekenen (DT)

- **FR-100**: A Canvas MUST offer the modes Invoeren, Tekenen and Bekijken in that order in one switch, with the mode in the address. A Reader, an archived Canvas and a Snapshot MUST get Bekijken only, and a Template Invoeren only; the server MUST refuse every Tekenen and Invoeren write from them. A Finalised year MUST NOT block Tekenen, its Shift move included. In D1 the switch holds Invoeren and Bekijken (ADR 0014).
- **FR-101**: Tekenen MUST change only Layout, with the one exception of FR-101a: it MUST NOT create, delete or rename an Area, Element, Statement or text, nor change any other parent, by any control, shortcut or request (H2, H12 as amended). `[GATE-C34]` `[GATE-C35]` The Direction band (the Area Direction) and the logo MUST NOT be movable or resizable, and the server MUST refuse any Layout for them.
- **FR-101a**: In Tekenen an Administrator MUST be able to move one Cluster (with its Activities), Activity or Item to another parent by dragging it with Shift held, or through "Verplaatsen naar" in the panel `[SD-42]`; without Shift a block stays inside its parent. Valid parents: for a Cluster, a Domain; for an Activity, a Domain or a Cluster, also within its own Domain; for an Item, a Group; always in an Area of the same kind (activity Areas among themselves, list Areas among themselves), never the current parent, never the Unknown allocation Domain. A Domain is never moved with Shift and several blocks are never moved at once. Every move MUST first be confirmed in the dialog "Verplaatsen naar <doel>?", also through the panel, and for undo and redo (QS-9, decided by the product owner 2026-11-19); cancelling stores nothing and returns the block to its former place. The confirmed move MUST be sent as its own request in the order of the automatic saves, carrying the structure version, the Layout version and the block's Layout relative to the new parent (none on an undo back to Automatic layout), and the server MUST apply the move rules of FR-037 and FR-043a, place it at the end of the new parent's order, keep its Allocation lines on the same identifiers, store the block's Layout with its size and style unchanged (the subtree's Layout relative to the block stays), except that a block wider than its target is made as wide as the target with its Manual children pushed left and narrowed to fit, which the user is told (QS-8, decided by the product owner 2026-11-19), check that Layout like any Layout (FR-103: inside the new parent's width, minimum sizes, style), raise the structure version and the Layout version, write everything in one transaction, write an Audit log entry with old and new "Hangt onder" and the Layout entries, and return both versions. A Finalised year MUST NOT block it. Undo and redo of a move MUST move it back to its former parent, place in the order and Layout, or again, through the same rules. The block keeping its place deviates from addendum 6.2 and SD-29, which keep holding for "Hangt onder". `[GATE-C53]` (ADR 0036)
- **FR-102**: Layout MUST be stored per Area other than Direction and per Element as the application's own data: place and size in whole pixels relative to the parent (the Artboard for an Area), the order in front or behind among siblings, and the Element style; a stored height is a minimum (FR-106a), and growth caused by names or children is never written to it. The database MUST be the only source, and a drawing-library document MUST NOT be stored as truth; the drawing is built from model plus Layout when Tekenen opens and read back when it saves (H13, ADR 0016). Layout data MUST carry a format version, upgraded by small functions when read.
- **FR-103**: Tekenen MUST save automatically after every finished action, sending the whole Layout of the Canvas with the structure version and the Layout version it last loaded or last saved successfully (each save returns the new Layout version); saves MUST go one at a time, and actions that finish while a save is under way MUST be sent together in the next save with the returned version; the server MUST write it in one transaction after checking: every Area other than Direction and every Element exists, is active and belongs to the Canvas; none is missing or extra; each parent equals the structure (a Layout save never changes a parent; FR-101a does); each child lies inside its parent's width (x at least 0, x plus width at most the parent's width, y at least 0; a child below its parent makes the parent grow); each Area lies inside the Artboard and below the Direction band on its stored place and size (x at least 0, y at least 180, x plus width at most 1680, y plus stored height at most 1188); growth caused by names or children is drawn only and never written to a stored height; content that grows beyond the Artboard through its names or children is never a reason to refuse (FR-106a); widths and heights meet User Story 35 scenario 5; only the style of FR-104. Any failure MUST refuse the whole save and return the drawing to the last stored Layout. The structure version MUST rise with every create, delete, archive, restore, move (also a Shift move) and reorder of an Element, and the Layout version with every write or removal of stored Layout; an Element placed through Invoeren is placed when drawn and writes no Layout (FR-108), so only its structure change raises a version. The server MUST check the structure version first: when newer, the user MUST choose between applying the Layout to what still exists (Tekenen then loads the current structure and Layout version, re-applies its own changes to the Elements that still exist, and saves again) and reloading; when equal, a missing or extra Element MUST refuse the save and rebuild the drawing, and a newer Layout version MUST refuse the save and reload the drawing (ADR 0021). Children sticking out MUST be pushed back before sending and named to the user. No intermediate state is kept in the browser. `[GATE-C32]` `[GATE-C34]` `[GATE-C36]` `[SD-35]`
- **FR-104**: Element style MUST hold only: fill colour `#RRGGBB` with opacity 0 to 100; border colour `#RRGGBB`, width 0 to 8 pixels and style solid or dashed; corner radius 0 to 64 pixels; and for titles and Activity text: font Inter or Space Grotesk, size 10 to 48 pixels, weight normal or bold, italic yes or no, colour `#RRGGBB`, horizontal alignment left, centre or right, vertical alignment top, middle or bottom. The server MUST refuse any other key or value (addendum chapter 5). `[SD-37]`
- **FR-105**: The server MUST refuse saving a text colour whose contrast with the Element's effective fill (opacity composited over what lies behind it) is below 4.5:1, and name the Element and the ratio. When a fill change would make an Element's own text colour fall below 4.5:1, that text colour MUST return to automatic (ink or white) in the same action, and the user is told. `[SD-27]`
- **FR-106**: Bekijken MUST draw every Area and Element at its stored place and size within 1 pixel of Tekenen at 100% zoom, with the same text lines (H11, ADR 0015).
- **FR-106a**: Names MUST always wrap within their Element's width, breaking a word longer than the width; an Element MUST grow in height to hold its text and children, also with a Manual layout, and its parents MUST grow with it. Children in Automatic layout MUST be laid out inside their parent's current width, the parent growing in height to hold them. In Automatic layout growth re-flows the siblings; growth MUST NOT move any Element that has a Manual layout. In a parent with a child in Manual layout, its children in Automatic layout MUST stay automatic and be placed in their order under the lowest Manual child, and they may jump when a sibling becomes Manual `[GATE-C54]`. Overlapping Elements or Areas MUST be listed as Warnings. The Artboard MUST NOT grow or scale: content beyond its bottom edge MUST be drawn beyond the edge, marked, and listed as a Warning, and MUST NOT be refused, scaled or made smaller (ADR 0020). `[GATE-C51]`
- **FR-107**: The Automatic layout MUST be computed from structure, order and the font files alone, on the Artboard of 1680 by 1188 pixels in the arrangement of kickoff 6.5, and be the same every time. "Terug naar automatische plek" MUST reset place, size and style of the selected Area (not Direction) or Element; "Domein terug naar automatisch" those of a Domain and everything in it; "Alles automatisch opmaken" those of every Area other than Direction and every Element, giving exactly the Layout of a new Canvas with the same content. Each MUST ask for confirmation. `[SD-36]`
- **FR-108**: Structure changes MUST place Elements per User Story 38, when drawn and without writing Layout: automatic in a parent without Manual layout; under the lowest Manual child at the size Automatic layout gives that kind of Element in a parent with a Manual child, the parent growing in height with the message "Opmaak aangepast" when needed; an Element reparented through "Hangt onder" and its subtree back to automatic (a Shift move keeps the block's Layout, FR-101a); Layout removed with a deleted or archived Element and restored by undo. An Element added in Invoeren MUST show "nieuw" in "Lagen" until it is selected or changed in Tekenen. `[SD-26]` `[SD-29]`
- **FR-109**: Layout MUST go with a Canvas copy, a Snapshot, a Canvas JSON export and import (mapped to the new identifiers), and a full export and import; Templates MUST NOT hold Layout. A Canvas JSON file without Layout MUST import in Automatic layout. `[SD-25]`
- **FR-110**: The drawing library MUST be reached only through one adapter in `resources/js/edit-engine/` with the interface of addendum chapter 7; files outside it MUST NOT import `@open-pencil/*`; versions MUST be pinned exactly; the Tekenen code MUST load as its own bundle; the property panel MUST be the application's own, built on the headless SDK (H14, ADR 0016).
- **FR-111**: Tekenen MUST make no request to any host other than the application's own server: telemetry, AI, collaboration, cloud and outside icon sources not built in or switched off; CanvasKit and fonts served by the application (H1, H15).
- **FR-112**: Canvas text MUST use only Inter and Space Grotesk, shipped with the application and seeded read-only, the same files for the HTML view and Tekenen (ADR 0017).
- **FR-113**: Saving Layout MUST write one Audit log entry per changed Area or Element with its old and new place, size and style; changes to the same Area or Element by the same user within 60 seconds MUST be merged into one entry with the oldest old value and the newest new value, never across a change of its parent. `[SD-28]`
- **FR-114**: Tekenen MUST be usable within 3 seconds the first time on an ordinary laptop, and within 1 second afterwards for every action on a Canvas of 500 Elements, saving included, whether or not they fit on the Artboard.
- **FR-115**: No Tekenen work beyond WP-E0 MAY start before WP-E0 ends in go; a no-go MUST stop DT and be reported to the product owner with the WP-E0 report, without choosing another library and without preparing a replacement.
- **FR-116**: DT MUST have: unit tests on the Layout data and every check of FR-103 to FR-105; a round-trip test of the adapter (Layout in, drawing, Layout out, no difference); a picture comparison between Bekijken and Tekenen for three reference Canvases (small, medium, and full with content beyond the Artboard) with 1 pixel tolerance on places; browser tests of User Stories 35 to 38, and of User Story 39 once D3 exists; a browser test that fails on any request outside the own host; a test that saving Layout never changes an Element, parent, name or Modality, and (from D2) an Allocation set; a test that a Shift move changes only the parent, order and Layout of the moved block, its subtree moving with it, and never a name, Modality or Allocation line; server tests of every valid and refused target of FR-101a, across Areas of the same kind and with a Finalised year; a test that the drawing library never reparents a node by itself; browser tests of the Shift move by pointer and by keyboard, its confirmation and cancel with the keyboard, its push-back and its narrowing to the target's width, its refusals and its undo; a static check that no file outside the adapter imports `@open-pencil/*`; and an upgrade script that runs the adapter tests against newer library versions (addendum 11, v1.2 16.8).

#### Fiscal years, Cost records and lists (D2)

- **FR-047**: Fiscal years MUST belong to the Organisation: calendar years 2000 to 2100, unique per Organisation, deletable only without Yearly figures. Whether a Fiscal year is Finalised MUST be held per Canvas (ADR 0007, ADR 0011). `[SD-2]`
- **FR-048**: A Cost record MUST belong to one Organisation and hold code (optional, unique within the Organisation ignoring case when filled), name (required), Cost kind (required: Leverancierscontract, Personeel, Project, Overig), Supplier (optional free text, ADR 0009), Owner (required, from the Organisation's list), cost centre (optional), Application (optional, from the Organisation's list), description (optional) and archive status. `[SD-7]` `[GATE-U3]` The Kostenposten form MUST show all of them. `[GATE-C42]`
- **FR-049**: A Cost record MUST have at most one set of Yearly figures per Fiscal year of its Organisation: Budget (required, € 0,00 to € 999.999.999,99), Forecast (optional, same range), Run and Change (optional basis points, each 0 to 10000, together at most 10000, absent counts as 0), FTE (optional, 0,00 to 9.999,99), reference date (optional) and remark (optional). `[SD-4]` `[GATE-U3]`
- **FR-050**: Deleting Yearly figures MUST delete the Allocation sets of that Cost record and year in every Canvas; deleting a Cost record MUST delete all its Yearly figures and Allocation sets.
- **FR-051**: While a Fiscal year is Finalised in any Canvas of the Organisation, the server MUST refuse creating, changing or deleting Yearly figures of that year and deleting a Cost record that has Yearly figures in it (ADR 0007). `[SD-3]`
- **FR-052**: Each Organisation MUST keep a list of Owners (name) and a list of Applications (name, optional Supplier), names unique per list ignoring case. An entry in use MUST only be deleted through "Vervangen door", which reassigns every Cost record to the chosen entry (or, for Applications, to none) and deletes the entry in one step.
- **FR-053**: "Kostenposten", opened from a Canvas or for an Organisation from "Overzicht", MUST show the Organisation's Cost records in the table per Fiscal year of User Story 18 for the year of the top bar, with, when opened from a Canvas, the allocation status for that Canvas, the summary cards and the Finalise card, and always the panel "Boekjaren" to add, delete and reopen Fiscal years. `[GATE-C43]` `[SD-14]`

#### Allocation (D2)

- **FR-054**: An Allocation set MUST belong to one Canvas, Cost record and Fiscal year and MUST be either absent (the Cost record sits in Unknown allocation) or a list of lines whose whole basis points of 1 to 10000 add up to exactly 10000, without a repeated Target; the order of the lines MUST be stored (H5, ADR 0005, ADR 0008).
- **FR-055**: A Target MUST be an active Domain, Cluster or Activity in an activity Area of the same Canvas, other than the Unknown allocation Domain (H6, ADR 0008).
- **FR-056**: The server MUST refuse a set for a Cost record without Yearly figures in that year, for an archived Canvas or Organisation, and for a year Finalised in that Canvas. An archived Cost record MUST remain allocatable (FR-018); carrying a year over (FR-070) is not allocating and skips it.
- **FR-057**: A set MUST only be written by replacing the whole set, or removing it, in one transaction; no route changes a single line.
- **FR-058**: The allocation screen MUST show "Nog te verdelen", offer "Rest naar deze regel", keep "Opslaan" disabled unless the remainder is 0,00%, and offer "Gelijk verdelen" (10000 divided by the number of lines, the remainder in basis points one each to the first lines in set order) and "Verdelen over activiteiten van domein" (the screen's lines replaced by the Domain's active Activities, including those in its Clusters, divided the same way).

#### Calculation and closing check (D2)

- **FR-059**: The figures MUST be calculated by a module that reads no database and performs no other input or output, given the Elements, the included Cost records with Yearly figures and the Allocation sets, and MUST return the same result for the same input.
- **FR-060**: For a Canvas, Fiscal year and filter, the included Cost records MUST be all Cost records of the Organisation, archived ones included, with Yearly figures in that year that match the filter on Cost kind, Owner and Application.
- **FR-061**: A Cost record without a set MUST count as one line of 10000 basis points on the Unknown allocation Domain, which is that Domain's Direct amount.
- **FR-062**: Line amounts MUST be Budget times basis points divided by 10000, rounded down to whole cents, after which the remaining cents go one each to the lines with the largest remainder, ties going to the earlier line in set order, so the lines add up to the Budget exactly. Forecast MUST be split the same way; a missing Forecast MUST count as equal to the Budget. `[SD-5]` `[SD-6]`
- **FR-063**: Each line amount MUST be split into Run, Change and Undetermined by the same largest-remainder method using the Cost record's Run, Change and remaining basis points, ties in the order Run, Change, Undetermined.
- **FR-064**: Each Element MUST get a Direct amount (its own lines) and a Total amount (Direct plus the Totals of its children); an Area Total MUST be the sum of its Domain Totals; the Canvas total MUST be the sum of Area Totals, Unknown allocation included through Supporting activities. This MUST hold for Budget, Forecast, Run, Change and Undetermined.
- **FR-065**: Share MUST be an Element's Total divided by the Canvas total, shown rounded half up with two decimals in the detail panel and with one decimal on the canvas and in the overlay (`[GATE-C40]`); with a Canvas total of 0 it MUST show "-".
- **FR-066**: Breakdowns MUST be: per Cost kind; per Modality as the sum of Direct amounts of Elements with that Modality, plus "Geen modaliteit" for other Elements and "Onbekende allocatie" for the Unknown allocation Domain (`[GATE-C41]`); Run, Change and Undetermined; and per Element the contribution of each Cost record (amount and percentage). `[SD-18]`
- **FR-067**: FTE per Element MUST be the sum, over the lines in its subtree, of the Cost record's FTE times the line's basis points divided by 10000, shown with two decimals rounded half up, outside the closing check (A6). `[SD-19]`
- **FR-068**: After every calculation a closing check MUST verify, to the cent: the sum of included Budgets equals the sum of all line amounts on Targets plus the Direct amount of the Unknown allocation Domain (H7 as restated in ADR 0008); the same for Forecast; every Total equals its Direct amount plus its children's Totals; the Canvas total equals the sum of the Area Totals; each breakdown adds up to the Canvas total; Run plus Change plus Undetermined equals the Canvas total. On any failure no figure MUST be shown and a System notice MUST be recorded.

#### Redistribution, Carry over, Finalise (D2)

- **FR-069**: Removing an Element whose subtree carries Allocation lines MUST open Redistribution listing each Fiscal year with affected sets; per year the user MUST choose either moving every affected line to one chosen Target (adding to any line that Target already has in the set) or removing every affected set whole. The chosen Target MUST be a Target outside the removed subtree. Redistribution MUST be refused when an affected year is Finalised in the Canvas. On confirmation the sets MUST change and the Element with its subtree MUST be archived, in one step.
- **FR-070**: Carry over MUST follow User Story 23 for one Canvas, a source and a target Fiscal year and the option "Ook jaarwaarden overnemen", copying per Cost record only into an empty target, skipping an archived Cost record entirely (User Story 23 scenario 3), listing sets with a line on a non-Target under "Handmatig toewijzen", reporting counts and reasons, writing everything in one step, and refusing the run as User Story 23 scenario 5 states.
- **FR-071**: Finalising a Fiscal year in a Canvas MUST be refused while any Cost record of the Organisation with Yearly figures in that year has no set in that Canvas, or while the closing check fails; the refusal MUST name the Cost records. `[GATE-U1]`
- **FR-072**: A Finalised year MUST freeze, in its Canvas, the Allocation sets of that year and, in the whole Organisation, the Yearly figures of that year (FR-051). Structure, Modality and Statements MUST stay editable. `[SD-3]`
- **FR-073**: Reopening MUST be an Administrator action with confirmation and an Audit log entry; the record of who Finalised and when MUST be shown while Finalised.

#### Overlays (D2)

- **FR-074**: Bekijken MUST offer the overlays "Geen", "Modaliteit" and "Financieel"; the financial overlay MUST use the Fiscal year of the top-bar selector (default: the current calendar year when it is a Fiscal year of the Organisation, otherwise its latest) and offer filters on Cost kind, Owner and Application, each a single choice including "Alle".
- **FR-075**: The financial overlay MUST show per Domain card in activity Areas its Total amount, Share and a Forecast against Budget bar with both amounts as text, a colour intensity rising with Share, per activity Area its Total, on hover or focus the per Cost record breakdown, and in the side panel the figures of User Story 25 scenario 5.
- **FR-076**: In the financial overlay the Unknown allocation Domain MUST show its amount and Share like any Domain card, count in the Supporting activities Area Total, and appear as its own line in the side panel and breakdowns (ADR 0008).
- **FR-077**: The modality overlay MUST show per Element its Modality mark with colour and letter, a legend, and per Domain card the count of its Clusters and Activities per Modality and without one.

#### Snapshots (D3)

- **FR-078**: A Snapshot MUST hold its name, Fiscal year, remark, time of creation and creator, and an immutable content of Canvas name, Statements, active Elements with all fields and, from DT, their Layout and the Artboard, the Cost records and sets of that year and the calculated figures, with a schema version and a checksum.
- **FR-079**: A Snapshot MUST only be created when the closing check passes, MUST have its checksum verified every time it is read, and MUST NOT be shown when the check fails.
- **FR-080**: Only name, remark and archive status of a Snapshot MUST be changeable; deletion MUST require confirmation and offers no undo.

#### Export and import (D3)

- **FR-081**: Canvas JSON export MUST hold what User Story 27 scenario 1 lists, with a format version.
- **FR-082**: Canvas JSON import MUST create a new Canvas with new identifiers in an existing or new Organisation, reuse a Cost record whose identifier exists in the target Organisation only when equal, give every other imported Cost record a new identifier and map its sets to it, create missing Owners and Applications by name, and keep the line order of every set. `[SD-13]`
- **FR-083**: Full export MUST hold what User Story 28 scenario 1 lists and nothing more. Full import MUST be refused while any Organisation exists, and MUST refuse a file holding a Snapshot whose checksum does not match its content; imported Snapshots are marked "Geïmporteerd" in "Versies". `[SD-12]`
- **FR-084**: CSV files MUST use semicolons, decimal commas without thousands separators on export, UTF-8 with an optional byte order mark on import, double quotes around fields containing a semicolon, quote or line break, and the exact headers of kickoff 12.3 to 12.5. On export, a field starting with "=", "+", "-", "@", a tab or a carriage return MUST be prefixed with an apostrophe, and on import exactly one leading apostrophe before such a character MUST be removed.
- **FR-085**: Every import MUST first produce a dry run report per line or per record, MUST only be committable without errors, MUST apply every field rule of this spec (FR-014, FR-015, FR-035, FR-043, FR-049, and from DT FR-102 to FR-105) as well as the structure, reference and 100% rules, MUST validate again when committed, and MUST write everything in one step or nothing. A code MUST be required on every Cost record line of a CSV import. `[SD-8]`
- **FR-086**: Uploads MUST be limited to PNG or JPEG logos up to 1 MB, CSV up to 5 MB and JSON up to 50 MB, with the type verified on content.
- **FR-087**: PNG export MUST render the whole Canvas from the HTML view of Bekijken (ADR 0015), independent of zoom, with or without the active overlay, holding the Artboard and any content beyond it; printing MUST map the Artboard one to one onto one landscape A3 page without shell, print content beyond the Artboard on the following A3 pages, and warn first when there is such content. The smallest canvas text (10 pixels) MUST print at 6 pt or more. `[SD-24]`
- **FR-088**: Every file name the application produces MUST be built from neutral words, slugs and dates only (H8).

#### Start, seed and operations

- **FR-089**: A clean start MUST take two commands, `cp env.example .env` and `docker compose up -d`; the first start MUST create the database, the eight Areas, the four Modalities, the Template "Leeg canvas" and one Administrator whose e-mail address and one-time password are written to the container log once (D1), and the sample data of User Story 32 (D3). The application MUST listen on `http://localhost:8080` for the machine itself only (A1); colleagues reach it through an SSH tunnel to that machine, which the README documents, so the browser still sees `localhost`.
- **FR-090**: The application MUST make no outgoing connection: no CDN, outside font, telemetry or update check; every font and icon MUST be bundled; the content security policy MUST allow only the application's own origin, plus `data:` and `blob:` as image sources for the PNG export, which reach no host (ADR 0039); responses MUST carry nosniff, a same-origin referrer policy (ADR 0046, which replaced no-referrer on 2026-11-20 by decision of the product owner) and refuse framing (H1). The only relaxation allowed is WebAssembly compilation (`wasm-unsafe-eval`) for the Tekenen bundle; any further relaxation the drawing library needs is a WP-E0 finding for the product owner, not a change made in code. The design file in `specs/design/` MUST NOT be referenced by the build or end up in the built assets.
- **FR-091**: Error screens MUST show a plain Dutch message and a link to "Overzicht" only, never a stack trace, query or file path.
- **FR-092**: `make backup` and `make restore` MUST copy the database file and uploads to and from a dated folder (D3).

#### Verification obligations

- **FR-093**: The calculation module MUST have unit tests for largest-remainder rounding, roll up with mixed levels (a Domain and an Activity in the same Domain), Unknown allocation, Run and Change, Modality without double counting, empty input, and one Cost record at 100% on one Target (kickoff 16.1).
- **FR-094**: There MUST be a server test for every refusal named in this spec, including each one of kickoff 16.3, a test per kind of record of kickoff 8A for create, change, delete, every block, undo, archive and restore, and a test that a Reader is refused on every write route outside FR-009 (kickoff 16.3, 16.3a).
- **FR-095**: A property test MUST generate at least 1000 cases per run of random trees, random Budgets and Forecasts across the full range, and random valid sets of 1 to 20 lines, and MUST find the closing check of FR-068 passing in every case (kickoff 16.2).
- **FR-096**: An end-to-end browser test MUST create a Canvas, fill its structure, enter a Cost record, allocate it, check the overlay figures and export (kickoff 16.4), and every screen MUST pass an automated WCAG 2.2 AA scan without violations in it.
- **FR-097**: Import tests with faulty files MUST prove the report is right and nothing was written (kickoff 16.5).
- **FR-098**: A check MUST fail when the built front-end assets contain an outside URL (kickoff 16.6). The check covers the Tekenen bundle. Per the product owner's WP-E0 decision of 2026-11-19, URL strings that sit as text in the vendored drawing-library code and are never requested are allowed; any URL in application code, and any request to an outside address, still fails. The network test (no request outside the own server, FR-111) remains the hard requirement.
- **FR-099**: A check MUST search every file under version control (code, tests, seed, documentation) for each name in a forbidden-names file, ignoring case, and MUST fail on any hit. The file MUST live outside version control at a configured path, hold one name per line, start with the first client's name and be extendable later; when it is absent the check MUST report a visible skip, not a pass (kickoff 16.7). Client data exists only as data in the database, never in seed, fixtures or tests (kickoff 13.5). `[GATE-U4]`

### Key Entities *(include if feature involves data)*

- **User**: a person with name, e-mail address, password hash, role, optional second factor with recovery codes, and the flag that the password must be replaced.
- **Organisation**: name, slug, optional logo, archive status; owns Canvases, Cost records, Fiscal years, Owners and Applications.
- **Canvas**: name, status (active, archived, template), optional Organisation (absent for a Template), a structure version, and from DT a Layout version; drawn on the fixed Artboard; holds Statements, Elements, Allocation sets, Finalised years and Snapshots.
- **Area**: one of eight fixed records with key, label, kind, order, level labels and whether it carries costs.
- **Statement**: of a Canvas; mission, vision or strategic goal; title, text and order.
- **Element**: of a Canvas and an Area; kind Domain, Cluster or Activity; parent; name, description, Modality, Rationale, icon, order, archive status; whether it is the system-managed Unknown allocation Domain; stable identifier that survives moves.
- **Element layout** (DT): of one Area or Element; Automatic or Manual, place and size relative to its parent (height a minimum), order among siblings, Element style, time of last change.
- **Font**: one of a fixed, seeded list; key, screen name, files per weight.
- **Modality**: one of four fixed records with key, name, letter, colour and order.
- **Owner**, **Application**: list entries of an Organisation; an Application has an optional Supplier.
- **Cost record**: of an Organisation; stable identifier, code, name, Cost kind, Supplier, Owner, cost centre, Application, description, archive status.
- **Fiscal year**: a calendar year of an Organisation.
- **Yearly figures**: of a Cost record and Fiscal year; Budget, Forecast, Run, Change, FTE, reference date, remark.
- **Allocation set** and **Allocation line**: of a Canvas, Cost record and Fiscal year; ordered lines of Target and basis points.
- **Finalised year**: of a Canvas and Fiscal year; when and by whom.
- **Snapshot**: of a Canvas; name, Fiscal year, remark, archive status, creator, time, immutable content, schema version, checksum.
- **Audit log entry**: time, actor, action, kind and identifier of the record, context, old and new values.
- **System notice**: time, kind of failure, Canvas, year, filter or Snapshot, details without personal data.

## Success Criteria *(mandatory)*

### Measurable Outcomes

Each criterion maps to a kickoff acceptance criterion where one exists and is demonstrated with test output or a screenshot (kickoff 17).

- **SC-001** (D1, acc. 1): On a clean machine with Docker, two commands bring the application up and the Administrator signs in within 3 minutes of the second command, once the image has been built on that machine; the one-time build needs an internet connection for the base images and packages, and the README states this and the build time (ADR 0035).
- **SC-002** (D1, acc. 2): Every new Canvas shows the eight Areas in the fixed layout immediately.
- **SC-003** (D1, acc. 3): Adding a Domain, Activities and a Cluster in Invoeren and moving an Activity into the Cluster through "Hangt onder" succeeds without leaving the page, with the pointer and with the keyboard alone.
- **SC-004** (D1, acc. 4): 100% of attempts to create an Activity outside a Domain are refused with a readable message, through the screens and directly.
- **SC-005** (D1, acc. 5): A practised user puts 3 Domains with 15 Activities on the Canvas through quick entry within 1 minute.
- **SC-006** (D1, acc. 6): No screen or request can add, change or remove an Area.
- **SC-007** (D1 to D3, acc. 6a): Every kind of record of kickoff 8A in a delivery can be created, changed and deleted through the screens, each with confirmation and an Audit log entry.
- **SC-008** (D1, acc. 6b): The last Administrator and an Organisation with Canvases cannot be deleted, also by two Administrators acting at once, and the message says why.
- **SC-009** (D2, acc. 6c): Deleting a Cost record removes its Allocation sets and the closing check passes afterwards; in a Finalised year it is refused.
- **SC-010** (D2, acc. 6d): An Owner or Application in use can only be deleted after "Vervangen door".
- **SC-011** (D2, acc. 7): Budget € 230,00 at 33,33 / 33,33 / 33,34 gives amounts adding up to exactly € 230,00.
- **SC-012** (D2, acc. 8): Sets of 99,99% and 100,01% are refused by the server, also on a direct request.
- **SC-013** (D2, acc. 9): The overlay shows each Domain's Total including its Clusters and Activities, and the Canvas total equals the sum of all Cost records of the year.
- **SC-014** (D2, acc. 10): Cost records without a set appear in the Unknown allocation Domain of Supporting activities and as its own line in the side panel, and count in the Area and Canvas totals.
- **SC-015** (D2, acc. 11): A year with a Cost record in Unknown allocation cannot be Finalised. `[GATE-U1]`
- **SC-016** (D2, acc. 12): Deleting an allocated Activity opens Redistribution, after which every affected Cost record is at 0% or 100%.
- **SC-017** (D3, acc. 13): Full export, wiping all Organisations and full import give identical figures to the cent for every Canvas, year and filter.
- **SC-018** (D3, acc. 14): A Snapshot shows the old structure and amounts after later changes.
- **SC-019** (D3, acc. 15): PNG and PDF hold the whole Canvas with every text at least 6 pt; the Artboard fills one A3 page and content beyond it, if any, follows on further A3 pages.
- **SC-020** (D1 to D3): With 500 Elements and 1000 Cost records in a Canvas, every screen responds within 1 second on an ordinary laptop (4 cores, 8 GB memory); the end-to-end specs check it as the 95th percentile of twenty samples, alone in their own CI job (ADR 0044).
- **SC-021** (D1 to D3): Every screen passes the automated WCAG 2.2 AA scan without violations, and every story's keyboard-only scenario passes.
- **SC-022** (D1 to D3): During a full end-to-end run, no request leaves the machine.
- **SC-023** (D2): The property test of FR-095 passes on every run; no defect of the kind "figures do not close" or "rule can be bypassed" is open at delivery (kickoff 17.3).
- **SC-024** (D2): In an acceptance session, the product owner, without help, builds a Canvas with Domains in all four activity Areas and allocates ten Cost records within 30 minutes.
- **SC-025** (DT, v1.2 acc. 3a, addendum 10.2): A Domain dragged, resized and coloured in Tekenen is, after reloading, at the same place and size in Tekenen and Bekijken within 1 pixel, and structure and (from D2) figures are unchanged.
- **SC-026** (DT, v1.2 acc. 3b): An Element added in Invoeren appears in the drawing without handwork.
- **SC-027** (DT, addendum 10.11): During a full Tekenen session no request goes to any host other than the application's own server.
- **SC-028** (DT, addendum 10.10): "Alles automatisch opmaken" gives exactly the places, sizes and styles of a new Canvas with the same content.
- **SC-029** (DT, P9): Tekenen is usable within 3 seconds the first time on an ordinary laptop.

## Assumptions

- Kickoff assumptions A1 to A6 stand (grilling Q10): the application runs on the product owner's laptop or development server through Docker and is not reachable from the internet; users are the product owner, a few colleagues and Reader accounts; euro only; the Fiscal year is the calendar year; Area labels as in kickoff 6.1, changeable in a later release; FTE shows as a sum per Domain.
- Screen texts are plain Dutch for a non-developer; there is no English user interface.
- The application is reached on the machine itself; other users come in through an SSH tunnel (A1, A2).
- Concurrent editing in the same screen is out of scope (kickoff 4); the last save wins and both saves are audited, except Layout (SD-35). `[SD-22]`
- No password-forgotten flow exists, because the application sends no e-mail (H1); an Administrator resets passwords, and the console command covers the last Administrator. `[SD-21]`
- The PII inventory is `specs/001-omc-studio-v1/pii-inventory.md`.
- Out of version 1 (kickoff v1.2 chapter 4, addendum H15): free shapes, free texts and images in Tekenen; the drawing library's AI, collaboration, cloud and file exchange (.fig, .pen); intermediate cost layers; links with source systems; simultaneous editing; per-Canvas permissions; a theme screen (ADR 0018).
- Tekenen needs a browser the drawing library supports: Chrome or Edge 111, Firefox 128, Safari 16.4 or newer (addendum chapter 1); the other modes need the same.
- At an incident the product owner is warned and starts the ISO notification policy of their organisation, which sets windows and channels. The application only warns the Administrators visibly (FR-023, User Story 34); it names no window or contact. `[GATE-U2]`
- Future wish, explicitly not in version 1 and not prepared with extra abstractions (kickoff 20.5): after the picture of the whole Organisation, zooming into separate locations or units in a deeper layer. `[GATE-U3]`

## Phase 2 gate

The product owner answered the gate items on 2026-11-18 and approved the gate the same day; the source decisions below came after the approval.

### Answers to the open unknowns

| Marker | Unknown | Product owner's answer | What it means in this spec | Status |
|---|---|---|---|---|
| `[GATE-U1]` | Unknown allocation (ADR 0008) | Verbatim: "ja, tot ik hem indeel mag hij onder support activiteiten vallen" | The reading holds, but Unknown allocation is not outside the Areas: it is a fixed, system-managed Domain in Supporting activities that cannot be renamed, moved or deleted and is not a Target (FR-043a, FR-055, FR-061, FR-064, FR-068, FR-076). Its amount counts in the Supporting activities Area Total. ADR 0008 accepted | Decided. The rule that a year can only be Finalised when Unknown allocation is empty (FR-071, User Story 24, SC-015) was confirmed by the product owner on 2026-11-18 |
| `[GATE-U2]` | Incident arrangement (grilling Q9) | At an incident the product owner is warned and starts the ISO notification policy of their organisation, which governs windows and channels | The application warns Administrators visibly through System notices and a top-menu warning (FR-023, User Story 34); no window or contact is invented | Decided |
| `[GATE-U3]` | Shape of the first Organisation's real data | Size unknown; the organisation chart shows a large landscape with two collective labour agreements and a governance layer. Wish for later, not version 1: zoom from the whole Organisation into separate locations or units in a deeper layer | Performance requirement of kickoff 18.3 stays (SC-020). The deeper layer is recorded as a future wish outside scope, not prepared (kickoff 20.5). SD-4, SD-5 and SD-7 stand until real data says otherwise | Answered |
| `[GATE-U4]` | Forbidden names for the H8 check | No list exists yet. Only rule: the client's name appears nowhere in code, tests, seed or documentation of the repository; client data exists only as data in the database | FR-099: a list file outside version control, starting with the client's name, extendable later. The name was removed from every document in the repository | Decided |
| `[GATE-D1]` | Visual design | A: the product owner supplies a design; "sober own style" lapses | The design in `specs/design/` with five boards; the contract derived from it is `design-contract.md`, its navigation mapped in ADR 0019; colour is set in Tekenen over shipped Default colours (ADR 0018) | Decided; every difference with the other sources is decided in `source-conflicts.md` |

### Sources

After the gate answers, the product owner supplied kickoff v1.2, addendum A1 and the design, and decided how they rank (Reading guide, Precedence). Points decided by the product owner were applied to this spec, the ADRs and `docs/CONTEXT.md`; among them the three modes, Vue 3, Bekijken as HTML from the stored Layout, Layout as own data per Element, no theme screen, Invoeren as a tree with arrows, and Tekenen as its own delivery with WP-E0 right after the project setup. After the gate the product owner decided points 32 to 50 of `source-conflicts.md` one by one (five of them against the advice: automatic saving in Tekenen, free Areas, a fixed A3 Artboard, whole euros on the canvas and in the overlay, and names that always wrap). They are applied here, in ADR 0016 to 0021, `docs/CONTEXT.md` and `design-contract.md`. The two questions that followed were answered on 2026-11-18: `[GATE-C52]` every amount is rounded on its own and the line "Totalen sluiten" proves the cents; `[GATE-C51]` names break within the block's width and the block grows in height, never in width, and, as the orchestrator's recorded reading of the product owner's words, the Artboard stays a fixed landscape A3 that neither grows nor scales, the Administrator resolves a full Artboard in Tekenen, and the system refuses and shrinks nothing but shows what does not fit and lists it as a Warning. Nothing from the sources is open. After the phase 4 plan the product owner decided two more points (`source-conflicts.md` 53 and 54): a Cluster or Activity can be moved to another Domain in Tekenen with Shift, which amends H12 against kickoff v1.2 6.5.7 and addendum H12 and 6.3, and automatic siblings stay automatic under the lowest Manual child. The product owner then answered QS-1 to QS-7 on the scope of the Shift move: into and out of Clusters, to Areas of the same kind, Items between Groups, allowed in a Finalised year, the block stays where it is released (against addendum 6.2 and SD-29), only single Clusters, Activities or Items, and always a confirmation first (against the advice).

### Decisions this spec took under kickoff 20.4

Kickoff 20.4 lets the orchestrator resolve gaps within the H rules and K choices and report each in one line. These are those lines. SD-1 to SD-24 were open for reversal at the gate; SD-25 to SD-40 were added after the gate was approved and SD-41 in phase 3 (`docs/constraints.md`); the product owner confirmed SD-25 to SD-41 on 2026-11-18. SD-42 was added in phase 4 and is reported to the product owner, who can reverse it.

| Marker | Decision | Gap it closes |
|---|---|---|
| `[SD-1]` | One spec for all of version 1, every story tagged D1, D2 or D3 | Three deliveries, one phase graph |
| `[SD-2]` | Fiscal years belong to the Organisation; the Finalised state is per Canvas (ADR 0011, accepted) | Kickoff 9 puts `fiscal_years` on the Canvas while Yearly figures belong to the Organisation (ADR 0006) |
| `[SD-3]` | A Finalised year freezes that Canvas's sets and the Organisation's Yearly figures of that year (create, change, delete); structure, Modality and Statements stay editable, a Snapshot is the frozen picture | ADR 0007 names only deletion; kickoff 7.2.7 says "alleen-lezen" without saying of what |
| `[SD-4]` | Budget is required in Yearly figures and never negative; maximum € 999.999.999,99 | Kickoff 7.1 makes Budget required only "om te kunnen toewijzen"; no range given |
| `[SD-5]` | A missing Forecast counts as equal to the Budget | Kickoff 7.3.1 splits the Forecast but says nothing of its absence |
| `[SD-6]` | Rounding ties go to the earlier line in set order; Run, Change and Undetermined are split per line | Kickoff 7.3.1 and 7.3.2 leave ties and per-line rounding of Run and Change open; set order survives export and import, identifiers may not |
| `[SD-7]` | Owner is required on a Cost record | Kickoff 7.1 lists Owner without "optioneel" |
| `[SD-8]` | Every import is all or nothing after an error-free dry run; CSV Cost record lines need a code; unknown Owners and Applications are created | Kickoff 12.2 says all or nothing for JSON only; 12.3 updates "by code" |
| `[SD-9]` | An archived Cost record keeps counting in every figure; its own fields and Yearly figures are read-only, its Allocation sets stay writable | Kickoff 8A offers archiving as the alternative to deletion without saying what it means for totals |
| `[SD-10]` | Templates have no Organisation; "Leeg canvas" is protected; Templates hold Elements with all fields | Grilling Q6 settles what a Template holds, not where it lives |
| `[SD-11]` | Elements move only between Areas of the same kind | Kickoff 6.2.2 and 6.2.3 overlap; same kind keeps H4 and the Target rule intact |
| `[SD-12]` | Full export holds no Users, second-factor data, Audit log or System notices; full import only into an application without Organisations | Kickoff S12 names "volledige export en import" without content; data minimisation |
| `[SD-13]` | Canvas JSON import always creates a new Canvas and reuses Cost records by identifier only when equal | Kickoff 12.2 does not say how Organisation-level Cost records meet an existing Organisation |
| `[SD-14]` | "Kostenposten" holds the Organisation's Cost records; opened from a Canvas it shows that Canvas's status (design); Finalise sits on its card, Reopen in its "Boekjaren" panel | S7 shows "toegewezen ja of nee", which is per Canvas since ADR 0006 |
| `[SD-15]` | Owners and Applications ship in D2, with Cost records | Kickoff WP2 and WP5 both name "lijsten"; acceptance 6d is in delivery 2 |
| `[SD-16]` | Bekijken without overlays ships in D1 so the Reader role has a use; sample data ships in D3 | Kickoff WP4 names "canvasweergave"; H9 sample data is WP10 |
| `[SD-17]` | Archived Elements can be shown and restored in Invoeren | H10 and kickoff 8A point 6 for a kind that has no list |
| `[SD-18]` | The Modality breakdown includes "Geen modaliteit" and "Onbekende allocatie" so it adds up to the Canvas total (confirmed by the product owner, `[GATE-C41]`) | Kickoff 7.3.8 names only "Geen modaliteit" |
| `[SD-19]` | FTE per Element is FTE times allocated percentage, summed, two decimals | A6 says "som per domein, zonder aparte rekenregels" for a partly allocated Cost record |
| `[SD-20]` | No network address or browser identification is stored; the security log keeps 90 days | NIS2 logging against data minimisation |
| `[SD-21]` | Password policy of FR-002; no password-forgotten flow; console reset command | Kickoff 18.1 says "gehasht" only; H1 rules out e-mail |
| `[SD-22]` | Concurrent saves: last one wins, both audited; not for Layout, which follows SD-35 | Kickoff 4 rules out simultaneous editing, not two people saving |
| `[SD-23]` | Element names may not contain ">" | `doel_pad` of kickoff 12.4 uses ">" as separator |
| `[SD-24]` | The Artboard prints one to one on A3; content beyond it follows on further A3 pages, with a warning first; PNG holds everything | Acceptance 15 asks the whole Canvas, readable on A3, while the Artboard is a fixed A3 (decision 36) that, in the recorded reading of decision 51, neither scales nor grows |
| `[SD-25]` | Templates hold no Layout; a Canvas from a Template starts in Automatic layout | Addendum 6.1 point 5 names copies, Snapshots and export, not Templates |
| `[SD-26]` | The pill "nieuw" shows in "Lagen" until that Element is selected or changed in Tekenen | Kickoff v1.2 6.5.7 marks new Elements without saying until when |
| `[SD-27]` | A text colour below 4.5:1 on its fill is refused at save | WB3 against a user-chosen text colour (design field "Tekst") |
| `[SD-28]` | One Audit log entry per changed Area or Element on a Layout save, merged per Element and user over 60 seconds | FR-019 per record; kickoff 18.6 does not name Layout |
| `[SD-29]` | An Element reparented through "Hangt onder" loses the Manual layout of its whole subtree; a Shift move keeps the block's Layout instead (product owner, `[GATE-C53]`) | Addendum 6.2 names only the Element itself |
| `[SD-30]` | The Unknown allocation Domain has an editable Layout but a fixed structure | ADR 0008 fixes its structure; Tekenen changes no structure except the Shift move (H12 as amended), which can never target it |
| `[SD-31]` | Without an open Canvas the top bar holds "Overzicht" and, for Administrators, "Beheer" | The design shows tabs only inside a Canvas; Beheer must be reachable before any Canvas exists |
| `[SD-32]` | Withdrawn: rounding of whole euros in lists was decided by the product owner, `[GATE-C52]` | |
| `[SD-33]` | Overlay figures that do not fit a card are left out in a fixed order and shown in full on hover, focus and in the detail panel | Decision 39 keeps card size; cards can be as small as 40 by 24 |
| `[SD-34]` | Areas stay inside the Artboard below the Direction band; growth never moves other Elements; overlaps are listed as Warnings | Decisions 34, 35 and 48 together |
| `[SD-35]` | A Layout save on an older Layout version is refused and the drawing reloads | Automatic whole-Layout saves (decision 32) would otherwise overwrite another Administrator silently |
| `[SD-36]` | The Domain level of decision 47 is the action "Domein terug naar automatisch" | The design names only the Element and Canvas actions |
| `[SD-37]` | Style ranges: border width 0 to 8, corner radius 0 to 64, font size 10 to 48 pixels, weights normal and bold | Addendum chapter 5 gives no ranges |
| `[SD-38]` | The Automatic layout uses the fixed Artboard already in D1, so it does not change when DT arrives | Decision 36 with H11 |
| `[SD-39]` | Content beyond the Artboard is drawn below a line "Rand A3" on a hatched background with a notice for every role | The recorded reading of decision 51 says to show it, not how |
| `[SD-40]` | Direction band text that does not fit its fixed height is cut off with "…", shown in full on hover, focus and in the detail panel, and warned | The band is locked (decision 35) and the Artboard fixed (decision 36); the band height of 180 pixels follows design board 2 scaled to the Artboard |
| `[SD-41]` | Repeated lockouts (10 within 60 minutes, application-wide) become a System notice without personal data | Phase 3: the product owner asked for lockouts as an incident signal; without it a guessing attack shows only in the security log nobody reads unprompted |
| `[SD-42]` | The keyboard and single-pointer alternative of the Shift move is the panel field "Verplaatsen naar" with "Verplaatsen", followed by the same confirmation; the block lands under the target's lowest child at its left edge, keeping size and style; while Shift-dragging, the target is named in text ("Naar <doel>", "Kan hier niet: <reden>") | WB11 and WB6 for the Shift move of `[GATE-C53]`, which the product owner described for the pointer only |
