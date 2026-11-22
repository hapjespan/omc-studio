# PII inventory: OMC Studio version 1

Date: 2026-11-18. Risk class `standard`. Source: `spec.md` in this directory, grilling Q8, kickoff 18.2.

The application holds Organisation and cost data, not data about children, parents or staff (kickoff 18.2). Owner and Supplier are organisation names or roles; the Owner field carries the hint "Afdeling of rol, geen persoonsnaam". The only intended personal data is about the people who use the application.

There are no processors: the application runs on one machine, makes no outgoing connection (H1) and shares data with no third party. The drawing library of Tekenen runs inside the browser and sends nothing out (H15, FR-111), so it is no processor either.

## Elements

| Element | Where it lives | Why it is needed | Lawful basis | Retention |
|---|---|---|---|---|
| User name | User account | Shown in the Users list and as the actor in the Audit log | Legitimate interest: access control and accountability for financial changes (Q8) | Until an Administrator deletes the account; then replaced by "verwijderde gebruiker" everywhere it was shown (FR-011) |
| User e-mail address | User account | Sign-in identifier | Legitimate interest: access control | Until the account is deleted; removed from Audit log values at deletion (FR-011) |
| Password hash | User account | Sign-in | Legitimate interest: access control | Until the password changes or the account is deleted; never in the Audit log, security log or any export |
| Second-factor key | User account, only when turned on | Second factor | Legitimate interest: access control | Until the factor is turned off or reset, or the account is deleted |
| Recovery codes (hashed) | User account, only with a second factor | Sign-in without the authenticator app | Legitimate interest: access control | Until used, regenerated, the factor is turned off or reset, or the account is deleted |
| Role | User account | Decides what the user may do | Legitimate interest: access control | Until the account is deleted |
| Actor reference in Audit log entries | Audit log | Who changed which financial record (kickoff 18.6) | Legitimate interest: accountability for financial changes (Q8) | As long as the Canvas or Organisation the entry is about; pseudonymised to "verwijderde gebruiker" when the user is deleted (Q8) |
| Actor reference in "Definitief door" and Snapshot "Gemaakt door" | Finalised years, Snapshots | Who finalised a year or recorded a Snapshot | Legitimate interest: accountability | As long as the record; pseudonymised when the user is deleted |
| Session record | Session store | Keeps a user signed in | Legitimate interest: access control | Until sign-out or 120 minutes without activity; holds the user reference and last activity only, no network address or browser identification (SD-20) |
| Throttle key | File cache, one entry per hashed e-mail address (sign-in) and per user identifier (password change, re-authentication, second-factor password) | Limits guessing passwords (FR-004) | Legitimate interest: security | Entries expire after 60 seconds; the file is removed on the next read or on a cache clear, so it can outlive 60 seconds on disk |
| Undo record | Database table undo_records | Undo of a deletion for ten minutes (ADR 0033) | Legitimate interest: correcting a mistake | The user identifier and the deleted rows (no user records) for at most 10 minutes; removed at restore, at sign-out of that user and when it expires |
| User identifier in the application error log | Application error log file | Trace an unhandled exception to a session | Legitimate interest: security and fault finding | 14 days, then removed automatically; no e-mail address or name |
| First Administrator e-mail address in the container log | Container log | The operator reads the first address and the one-time password once (R-25), and the console reset command prints its password | Legitimate interest: first access | As long as the operator keeps the container log; the operator clears it |
| Account state columns | User account | One-time password expiry, must-change-password flag, second-factor confirmation time, last second-factor time step | Legitimate interest: access control | Until the account is deleted |
| User identifiers inside Audit values | Audit log values (for example created_by, finalised_by) | Who made a change | Legitimate interest: accountability | As long as the Audit entry; pseudonymised at user deletion with the actor reference |
| Session payload | Session store (encrypted at rest) | Keeps a user signed in: user reference, sign-in time, pending second-factor state, and flashed one-time passwords or recovery codes until the next request | Legitimate interest: access control | Until sign-out or 120 minutes without activity |
| Session reference in the security log | Application security log, one random value of 12 hexadecimal characters per browser session, kept in the session | Correlate the security events of one session, such as sign-in attempts and sign-out, and tell concurrent sessions of one account apart | Legitimate interest: security | 90 days with the security log (FR-022); pseudonymous: not derived from a network address, browser identification or the session identifier, appears in no other table, and cannot be linked to a person without the log itself; entries written outside a session (console, queue) carry none (SD-20) |
| User identifier in the security log | Application security log | Detect and reconstruct an incident (NIS2) | Legitimate interest: security | 90 days, then removed automatically (FR-022); holds no e-mail address, name, typed input or network address |

## Not collected, by design

- Network addresses and browser identification: not stored in sessions or logs (SD-20).
- E-mail addresses typed at a failed sign-in: not logged (FR-022).
- System notices, the repeated-lockout notice included: no user identifier, name or e-mail address (FR-023).
- Users, second-factor data and the Audit log: not in any export, full export included (SD-12).
- Element layout (DT): place, size and style of Elements; no personal data. Its Audit log entries name the actor, covered by the Audit log row above.
- No intermediate state is kept in the browser: Tekenen saves automatically (ADR 0021).

## Delivery 3 and Tekenen: checked for new personal fields (T369)

No new personal field. Checked per addition against the elements above:

- Snapshots hold the structure, the amounts and the Layout of one Canvas and year; the only person reference is "Gemaakt door", already listed above and pseudonymised at user deletion.
- Exports (JSON of a Canvas, the full export, the CSV files, PNG and print) hold Organisation, structure and cost data and Layout, never users, second-factor data or the Audit log (SD-12); `tests/Feature/Exchange/FullExportTest.php` proves it.
- Sample data is synthetic: an Organisation with the neutral name "Voorbeeld BV" and invented names, removable from the beheerscherm.
- Layout (place, size, colour, font) and the Artboard carry no personal data; their Audit log entries name the actor, covered by the Audit log row above.
- The drawing library runs in the browser, stores nothing there and sends nothing out (ADR 0016, SC-027).
- Imports read free text from a file; the residual risk below about free-text fields covers it.

## Residual risk

- Free-text fields (Supplier, Owner, descriptions, remarks, Rationales, Statements) could receive a person's name by mistake, or name a sole trader as Supplier. Mitigation: the Owner hint, and the Audit log shows who entered it so it can be corrected. No automatic detection.
- `make backup` copies the whole database file, user data included, to a folder on the same machine. Retention of backups is the product owner's; the README says to delete backups that are no longer needed and to keep the copy safe.
- Exports (JSON, CSV, PNG, PDF) hold Organisation and cost data but no user data; where they go after download is the product owner's responsibility.

## Change rule

A change that adds a personal data element updates this inventory in the same commit (constitution, AVG/GDPR article).
