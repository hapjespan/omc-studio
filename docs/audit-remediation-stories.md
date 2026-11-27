# OMC Studio audit remediation backlog

Each numbered section is a separate Jira **Story**. Use its heading as the Summary and copy its user story, problem, approach, acceptance criteria and test criteria into the Description. The numbers match the audit findings. Priorities and components are proposed values. Set the actual project, assignee and any required custom fields in the destination Jira project.

## Definition of Done for every story

A story can move to **Done** only when all of the following are true.

- [ ] Every acceptance criterion passes through the affected user or operator workflow.
- [ ] A regression test demonstrates the original defect on the previous implementation and passes with the fix. The story’s failure cases and unaffected behaviours have test evidence.
- [ ] The applicable PHP, frontend, database and browser CI checks pass for the revision being released.
- [ ] A reviewer has checked the fix and its failure handling. The Jira issue links the reviewed change, test results and any manual verification.
- [ ] There are **zero known unresolved bugs in the changed behaviour**, including regressions identified during review, testing or acceptance. Reopen the story if any criterion fails.
- [ ] The responsible owner accepts the result. Any changed operator steps or credential migration steps are documented and verified.

## 1. Restore a backup without losing live uploads

**Issue type** Story · **Priority** Highest · **Component** Recovery

**User story**
As the operator, I want restore to verify the complete backup before replacing live data, so that a damaged copy cannot take my application offline or erase its logos.

**Problem**
Restore accepts a database and key even when referenced logos are missing. It removes live files before their replacements have been copied successfully.

**Fix approach**
Validate the staged database and key, then check each logo path referenced by that database against the staged uploads. Keep the safety copy and make replacement recoverable across the database, key and uploads. Surface copy failures through the restore command.

**Acceptance criteria**

- [ ] Given a backup whose database references a missing logo, when I run restore, then the command fails before replacement and my live records, key and uploads are intact.
- [ ] Given a valid backup with no referenced uploads, restore succeeds even if its uploads directory is absent. Given a failure during replacement, the command reports failure and the complete previous installation can be recovered using the reported recovery location.

**Test criteria**

- [ ] Extend the restore feature tests with a missing directory, one missing referenced file, an empty installation, and an injected failure after the first replacement. Compare database records, key and upload bytes before and after recovery. Exercise the documented Makefile entry point on a disposable volume.

**Definition of Done**
Apply the shared Definition of Done above.

## 2. Sign out successfully from the drawing screen

**Issue type** Story · **Priority** Highest · **Component** Drawing and authentication

**User story**
As a signed-in user, I want Sign out to end my session from the drawing screen, so that leaving a shared computer protects my account.

**Problem**
The drawing navigation handler turns the sign-out POST into a GET, leaving the session active.

**Fix approach**
Limit full-page URL navigation to GET visits. When a pending drawing save delays sign-out, retain the original sign-out action and execute it once the user can leave.

**Acceptance criteria**

- [ ] Given a saved drawing, when I choose Sign out, then I reach sign-in and a fresh request to a protected page requires authentication.
- [ ] Given a save in progress, sign-out waits for it. If saving fails, staying preserves the session and edit, while explicitly discarding and leaving completes sign-out.

**Test criteria**

- [ ] Add a browser regression through the user menu. Cover saved, delayed-save and failed-save states. Verify the request method, one sign-out request, the protected-page response, and ordinary GET navigation to and from Drawing.

**Definition of Done**
Apply the shared Definition of Done above.

## 3. Authenticate the full supported passphrase

**Issue type** Story · **Priority** High · **Component** Authentication

**User story**
As an account holder, I want every character of my passphrase to count, so that changing its ending changes the credential required for sign-in.

**Problem**
Validation accepts up to 128 characters, but the deployed bcrypt configuration only protects the first 72 bytes.

**Fix approach**
Use a supported full-input password hasher such as Argon2id for new credentials. Identify legacy hashes explicitly during verification and define a safe upgrade path. For ambiguous long legacy credentials, establish the replacement through a password change or reset. Review sign-in, reauthentication, recovery-code checks and the dummy hash used for unknown users together.

**Acceptance criteria**

- [ ] Given a newly set 128-character passphrase, when I sign in or reauthenticate, then the complete correct value succeeds and a change beyond byte 72 fails. The documented 12–128 character policy still applies.
- [ ] Existing accounts can sign in or use the documented reset path during rollout. Existing one-time passwords and recovery codes continue to work under their original expiry and single-use rules.

**Test criteria**

- [ ] Test ASCII and multibyte passphrases around 72 bytes and 128 characters through first-password setup, change, sign-in and reauthentication. Test legacy hashes, unknown accounts, reset and recovery codes on the delivered PHP image.

**Definition of Done**
Apply the shared Definition of Done above.

## 4. Keep the working logo when a replacement upload fails

**Issue type** Story · **Priority** High · **Component** Organisation branding

**User story**
As an administrator, I want a failed upload to preserve the current logo, so that an ordinary storage error cannot remove it.

**Problem**
The storage disk can return false. The upload service commits the new path and deletes the old file without checking that result.

**Fix approach**
Check the storage write before saving the new path. Remove the staged file if the database update fails, and delete the old file only after commit.

**Acceptance criteria**

- [ ] Given an existing logo, when storage refuses its replacement, then the upload reports an error and the original logo still loads with the same bytes.
- [ ] A database failure preserves the original path and image. A successful replacement serves the new image and removes the superseded file after commit.

**Test criteria**

- [ ] Exercise the upload and logo-serving routes. Inject both a false return from storage and a database failure after the write. Assert the path, file contents, response and cleanup in each case.

**Definition of Done**
Apply the shared Definition of Done above.

## 5. Back up logos consistently while people are editing

**Issue type** Story · **Priority** High · **Component** Recovery

**User story**
As the operator, I want a live backup to contain every logo referenced by its database, so that the backup is usable when I need it.

**Problem**
The database is copied before the upload directory. A concurrent logo replacement can delete a file still referenced by the database copy.

**Fix approach**
Check the copied uploads against references in the copied SQLite database before reporting success. If a referenced file disappeared during copying, retry from a fresh database copy or fail clearly and clean up the incomplete attempt.

**Acceptance criteria**

- [ ] Given a logo replacement during backup, every backup reported as successful contains the exact file referenced by its database.
- [ ] If a consistent copy cannot be made, the command exits unsuccessfully and leaves previously completed backups usable.

**Test criteria**

- [ ] Coordinate a logo replacement between the database and upload copy in a disposable test. Restore each successful result and fetch the referenced logo. Inject a persistent copy failure and verify cleanup and the exit status.

**Definition of Done**
Apply the shared Definition of Done above.

## 6. Enforce the archived cost-record rule on save and undo

**Issue type** Story · **Priority** High · **Component** Finance

**User story**
As a finance administrator, I want archiving a cost record to freeze its financial data, so that another editor or an undo action cannot change it afterward.

**Problem**
A waiting save can check an outdated cost record. Undoing a yearly-figure deletion checks that the parent record exists but can restore the figure after the parent was archived.

**Fix approach**
Reload the affected record after acquiring the existing organisation write lock. Apply the active-record check to edits and to existing parent records during undo, before restoring any dependent rows.

**Acceptance criteria**

- [ ] Given that archive commits before a pending edit acquires the lock, the edit is refused with the archived-record message and financial data stays intact.
- [ ] Given a deleted yearly figure whose parent is subsequently archived, undo is refused and restores zero rows. Editing and undo on an active record still succeed when other finance rules allow them.

**Test criteria**

- [ ] Use separate database connections or coordinated transactions for the archive-and-edit case. Separately test delete, archive, undo through the normal endpoints. Assert figures, allocations and audit entries, including absence of partial writes.

**Definition of Done**
Apply the shared Definition of Done above.

## 7. Detect duplicate fiscal years before committing a CSV

**Issue type** Story · **Priority** High · **Component** Cost import

**User story**
As a finance administrator, I want preview to identify duplicate yearly figures, so that I can correct my file before import.

**Problem**
The validator accepts both 2026 and 02026. Duplicate detection uses the input text, but both values resolve to the same fiscal-year row.

**Fix approach**
Build the duplicate key from the normalised cost code and resolved fiscal-year ID. Use the same scan for preview and commit, preserving the original line numbers for feedback.

**Acceptance criteria**

- [ ] Given rows for the same cost code with years 2026 and 02026, preview flags the duplicate and identifies both lines.
- [ ] Commit refuses that file without writing any rows. Different costs in the same year and the same cost in different years remain valid.

**Test criteria**

- [ ] Cover identical and differently formatted years for new and existing cost records. Check preview feedback, direct commit refusal, unchanged database contents and both valid combinations.

**Definition of Done**
Apply the shared Definition of Done above.

## 8. Preserve a literal zero in imported cost fields

**Issue type** Story · **Priority** High · **Component** Cost import

**User story**
As a finance administrator, I want the text code 0 to survive import, so that my cost-centre and supplier records remain accurate.

**Problem**
PHP treats the string 0 as false. CSV validation therefore converts a valid cost centre or supplier of 0 into an empty database value.

**Fix approach**
Convert only the trimmed empty string to null in both optional text fields. Keep the resulting text consistent across preview, comparison and commit.

**Acceptance criteria**

- [ ] Given a cost centre or supplier of 0, importing creates or updates that field with the literal string 0. A blank field retains the existing blank-field behaviour.
- [ ] Exporting and re-importing a record containing 0 preserves both fields and preview counts it as unchanged.

**Test criteria**

- [ ] Test each field independently with a new record, an update, a blank cell and an export/import round trip. Assert persisted strings and preview status.

**Definition of Done**
Apply the shared Definition of Done above.

## 9. Export a consistent financial snapshot during concurrent edits

**Issue type** Story · **Priority** Medium · **Component** JSON exchange

**User story**
As an administrator, I want every section of a JSON download to describe the same point in time, so that the file can be imported with its references and totals intact.

**Problem**
Separate queries can read the fiscal years before an edit commits and the figures or allocations after it commits.

**Fix approach**
Build the full export or single-canvas export from one consistent database snapshot. Use a transaction isolation level that guarantees repeatable reads on each supported engine. Keep encoding and download outside the transaction once the data is materialised.

**Acceptance criteria**

- [ ] Given a concurrent edit that adds a fiscal year and its first figure, an export contains either the complete edit or the complete earlier state.
- [ ] A concurrent allocation change produces a file whose targets exist. Single-canvas and full downloads still import successfully with the exported totals.

**Test criteria**

- [ ] Use two real database connections and synchronise an edit between export reads. Check SQLite and PostgreSQL, including engines that delay the writer. Import the result into an eligible database and compare references and totals.

**Definition of Done**
Apply the shared Definition of Done above.

## 10. Create each account with one usable initial credential

**Issue type** Story · **Priority** Medium · **Component** Account administration

**User story**
As an administrator, I want account creation to issue the initial password in one write, so that a failure cannot leave an account with an undisclosed password.

**Problem**
The creation path saves one random password, logs success, then generates and saves a second password to display.

**Fix approach**
Pass the new, unsaved user to the existing one-time-password issuer, as the first-administrator installer does. Retain the returned password and log successful creation after persistence.

**Acceptance criteria**

- [ ] On success the administrator receives one initial password, the account is created with its expiry and mandatory-change flag, and that password opens the first-password flow.
- [ ] If initial credential persistence fails, the account is absent and there is no successful user-created security event.

**Test criteria**

- [ ] Exercise admin user creation, first sign-in and mandatory change. Inject failure during the initial save and check the database and security log. Verify there is a single user insert and no second credential update.

**Definition of Done**
Apply the shared Definition of Done above.

## 11. Bound selection history by the number of drawing elements

**Issue type** Story · **Priority** Low · **Component** Drawing performance

**User story**
As a canvas editor, I want repeated selections to remain responsive throughout a long editing session, so that revisiting elements does not progressively slow the drawing screen.

**Problem**
Every selection appends IDs and copies the entire history, including IDs that were already seen.

**Fix approach**
Track seen element IDs in a reactive Set and use membership checks to calculate which new elements still need highlighting.

**Acceptance criteria**

- [ ] Selecting an element clears its new-element highlight. Other unseen elements keep their highlights, including after multi-select and deselect.
- [ ] After repeatedly selecting the same elements, the stored count equals the number of distinct IDs selected during the session.

**Test criteria**

- [ ] Add a drawing-state regression that repeatedly selects the same IDs, includes a multi-selection, and checks both the distinct count and the visible new-element list on a 500-element canvas.

**Definition of Done**
Apply the shared Definition of Done above.

## Release verification for stories 1, 5 and 9

- **Story 1.** Inject a failed upload copy after creating the safety backup and verify the live logo bytes. Fail both a swap and its rollback. Verify maintenance mode stays active, the recovery location is reported and Make leaves the service stopped. Check the Make target with restore exit codes 0, 3, 1 and 137.
- **Story 5.** Write a prefix of a logo and make the filesystem report a failed copy. Require the backup command to fail and remove the incomplete backup directory.
- **Story 9.** Bind a canvas, rename it before export and change the organisation slug after export. The exported name, contents and download filename must agree with the same database snapshot.

## Open and edit a large canvas within one second

**Issue type** Story · **Priority** High · **Component** Canvas entry

**User story**

As a canvas editor, I want to open Invoeren and add an element on a 500-element canvas within one second, so I can continue entering activities without losing my place.

**Problem and fix approach**

Firefox exceeded the opening budget on release commit `a70eb9ee` (p95 1,236 ms). Read only the row fields required by Invoeren, skip loading elements and layouts on deferred detail requests, and allow the browser to defer painting offscreen rows.

**Acceptance criteria**

- [ ] Opening and adding an element each have a p95 below 1,000 ms across 20 samples on the release benchmark.
- [ ] All 500 elements remain available, including the last row. An editor can reach it, open its details and operate the entry form with the keyboard.

**Test criteria**

- [ ] Run the existing opening and addition benchmarks on Chromium, Firefox and WebKit with the release thresholds. Check row count and keyboard activation after the opening measurement.
- [ ] Run the keyboard-only entry workflow and its accessibility assertions. Attach the timing distribution and engine to the ticket.

**Definition of Done**

Apply the shared Definition of Done above.
