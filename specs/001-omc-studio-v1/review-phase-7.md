# Phase 7 review report

## Voor de producteigenaar bij poort 7

1. Je keurt goed: de review van de hele code op release (tip f8a778e) is gedaan en alles wat is gevonden is opgelost of bewust vastgelegd.
2. Opgelost: alle 6 blokkerende en alle grote bevindingen (beveiliging, privacy, rekenregels, toegankelijkheid, bewijs, prestaties), elk met een test die eerst faalde.
3. Gemeten: elk scherm antwoordt binnen 1 seconde bij 500 elementen en 1000 kostenposten, en Tekenen start binnen 3 seconden, op de trage CI-machine.
4. Schone start op de Mac met Docker (door de orchestrator): bouwen 60 seconden, eerste antwoord na 2 seconden (grens 180).
5. Bewust niet gedaan, geaccepteerd of jouw besluit: wachtwoorden langer dan 72 tekens (bcrypt kapt af), cookienaam zonder voorvoegsel, vastgezette image-versies, wat het logboek na het wissen van een gebruiker bewaart, de "nieuw"-markering in Lagen, de naam van een organisatie na hernoemen, de overlaybalk onder 3:1 (D7).
6. Nog open: T312, de aantallen per domein in de modaliteit-overlay zijn onzichtbaar; wacht op jouw besluit.
7. Af en toe vertragen twee tests op WebKit en slagen bij herhaling; dat is vastgelegd en geen fout in het product.
8. Na jouw goedkeuring en de besluiten P1 tot en met P5 hieronder draait de orchestrator `complete-phase.py 7`; dit document met de eindoordelen in sectie 10 is het bewijs.

## Besluiten voor de producteigenaar bij poort 7

Elk punt hieronder vraagt jouw besluit; het advies staat erbij. Zonder besluit blijft het open en staat het zo in dit rapport.

| # | Besluit | Mogelijkheden | Advies |
|---|---|---|---|
| P1 | **D7, de balk "boven budget" in de financiële overlay** haalt vanaf een aandeel van 31,6% geen 3:1 meer tegen de getinte kaart (WCAG 1.4.11); op korte kaarten valt de prognosetekst eerder weg dan de balk. | a) de balk op een witte strook tekenen (dan altijd minstens 4,1:1); b) de volgorde van wegvallen omdraaien zodat de prognosetekst blijft staan; c) zo laten. | a, de enige keuze die het criterium haalt zonder de kaart anders in te delen. |
| P2 | **Wissen voorbij FR-011**: bij het wissen van een gebruiker vervangt de applicatie zijn naam en e-mailadres in álle logboekregels, ook in regels over andere onderdelen die toevallig dezelfde tekst bevatten. FR-011 noemt alleen de regels over die gebruiker. | a) het vervangen beperken tot FR-011 (regels over de gebruiker en de actor); b) FR-011 aanpassen zodat het bredere vervangen de regel is. | a, het logboek van andere onderdelen hoort onveranderd te blijven (FR-021); een naam die toevallig gelijk is aan een element mag die geschiedenis niet herschrijven. **Besloten 2026-11-22: a**, gebouwd in 96bf2f9. |
| P3 | **Verloren actorlink**: na het wissen worden alle acties van die gebruiker "verwijderde gebruiker"; acties van twee gewiste gebruikers zijn niet meer uit elkaar te houden. | a) een vaste schuilnaam per gewiste gebruiker bewaren (bijvoorbeeld "verwijderde gebruiker 3"), zonder naam of e-mailadres; b) zo laten en de grens vastleggen (staat nu in docs/constraints.md, sectie 8). | a, het blijft binnen de privacyregel (geen naam, geen adres) en houdt een incident te reconstrueren. **Besloten 2026-11-22: a**, gebouwd in 943ddb0. |
| P4 | **Sessiecorrelatie**: twee gelijktijdige sessies van één account waren in het beveiligingslog niet te scheiden. De review stelde een willekeurige sessiereferentie voor; die is gebouwd (zie sectie 11) en is een nieuw pseudoniem gegeven, 90 dagen bewaard met het log. | a) de sessiereferentie accepteren; b) weer weghalen. | a, SD-20 verbiedt netwerkadressen en browserherkenning, geen willekeurige referentie; het sluit een gat bij een incident. **Besloten 2026-11-22: a**, gebouwd in c5eac39. |
| P5 | **Verwijzingen naar het ontwikkelgereedschap buiten de uitzondering van 2026-11-21** (artikel 4): `.specify/`, `.workflow/`, `scripts/vendored-urls.txt`, `proof/wp-e0/asset-report.mjs` en één zin in `docs/adr/README.md`. | a) de uitzondering bij naam uitbreiden met deze gereedschapsbestanden en de zin in `docs/adr/README.md` herschrijven; b) de bestanden aanpassen. | a, het zijn gereedschap en strings uit bibliotheken van derden, geen productcode; alleen de ADR-zin hoort bij het product en wordt herschreven. **Besloten 2026-11-22: a**, gebouwd in 5ad607a. |

Reviewed: the tip of `integration/dt`, commit 6ec1d33 (deliveries 1 to 3 and Tekenen). Method: `docs/process/07-review-validate.md`, review as a fan-out. Nine reviewers ran blind to each other, each read-only and each without a run of the suites: correctness, design, OWASP Top 10, WCAG 2.2 AA, AVG/GDPR with NIS2, test-coverage honesty, `ponytail:ponytail-audit` over the repository and `impeccable:impeccable` in audit mode over the interface. Every blocking finding and every major finding that two or more reviewers did not report independently was checked against the code by the orchestrating session; findings that did not survive are not listed. Items marked "decision" are not code defects but need the product owner or the article owner.

**Gate verdict: not passed.** Six blocking findings (B1 to B6), all small to medium and all fixable before the gate. Nothing in this report has been fixed except the seed route (B-seed, see section 6).

Not done here, by instruction: `complete-phase.py` was not run, no gate was approved, and `routing.py record-review` was not run (60 tasks still show no recorded reviewer; `python3 .workflow/routing.py check 7` lists them). The reviewers ran on the phase model, and the builder ledger has to be checked per task before reviews are recorded.

## 1. Blocking

| ID | Finding | Files | Proposal |
|---|---|---|---|
| B1 | Two tables ship without the index their access path needs (house article 2, data-model.md:42 and :50): `two_factor_recovery_codes.user_id` and `undo_records.user_id` | database/migrations/2026_11_18_000011_create_two_factor_recovery_codes_table.php:13; 2026_11_18_000012_create_undo_records_table.php:13; app/Services/Accounts/RecoveryCodes.php:47,58,69 | Add `->index()` (the project is undelivered, so edit in place) and name it in data-model.md |
| B2 | Code comments in tracked source (article 3): scaffold leftovers, stock framework blocks in every config file, a false `.dockerignore` header, dev-container comments | app/Http/Controllers/Controller.php:7; database/seeders/DatabaseSeeder.php:13-18; tests/Feature/ExampleTest.php:5,9-12; config/*.php (10 files, 141 comment lines in session.php); .dockerignore:3-6; .devcontainer/Dockerfile:3-34 | Delete ExampleTest and DatabaseSeeder (the seeder creates a known-password account and nothing calls it); strip the blocks; correct or drop the header and the "React frontend" remark |
| B3 | AI references in the repository (article 4), decision needed | .devcontainer/Dockerfile:16,28; .devcontainer/README.md:37-38; .devcontainer/docker-compose.yml:17-21; tracked workflow tooling under `.claude/`, CLAUDE.md, docs/adr/README.md:8; commits d66a78c and d0fc304 carry a `Co-Authored-By` trailer and a session URL and are already on `release` and origin | Article owner rules whether the workflow tooling directory is exempt and writes the exemption into the constitution; rename or move the dev-container wiring; for the two commits either rewrite history (blocked by the branch policy hook, owner decision) or record a dated exemption and add a commit-msg hook that refuses such trailers |
| B4 | Dutch identifiers in code (article 1, ADR 0031): the CSV import uses Dutch header words as internal array keys and cost-kind keys; a Dutch data attribute and message keys in the panel | app/Services/Exchange/CostRecordImport.php:21; CostRecordRowValidator.php:11-18,34-70; StructureCsv.php:22,165,255,274; AllocationCsv.php:12; AllocationCsvImport.php; resources/js/pages/Drawing/PropertiesPanel.vue:104 (`data-invoeren-note`); lang/nl.json:687-688 (`drawing.invoeren_note_*`) | English keys in code; the Dutch header texts and cost-kind labels read from lang/nl.json under `exchange.*` as ADR 0031 and contracts/exchange-formats.md say; rename to `entry_note` |
| B5 | The sign-in and second-factor throttle can be bypassed with parallel requests (FR-004 MUST): the lock check precedes the slow verification and the file-cache `increment` is a read-then-write without a lock | app/Services/Accounts/Throttle.php:30-50; SignInService.php:30-56; Auth/SecondFactorChallengeController.php:41-60; Auth/ReauthenticateController.php:40-55; AccountController.php:52-72; config/cache.php:20 | One guarded method that wraps check, attempt and count in a cache lock (or count before verifying and clear on success); a concurrency feature test. Found by reading by two reviewers and confirmed by reading, not by a run |
| B6 | The Cost record CSV import ignores the archive rules (FR-018, FR-085): an archived Organisation accepts imports and archived Cost records and their Yearly figures are overwritten | app/Services/Exchange/CostRecordImport.php:44,226-249; CostRecordImportController.php | `assertWritable` at the start of the commit; an error line in the scan for archived records; the two refusal tests FR-094 asks for |

## 2. Major

| ID | Finding | Files | Proposal |
|---|---|---|---|
| M1 | Security and error log retention is by file count, not by age (FR-022 90 days, R-5 14 days) | config/logging.php:73,82; tests/Unit/Audit/SecurityLogTest.php:87 | Delete by file date; test with a 91-day-old file |
| M2 | Every Reader page view writes a false `forbidden` security entry (`Gate::after` on a display check) and a real refusal is logged twice | app/Providers/AuthServiceProvider.php:17-21,39-43; Http/Middleware/SharePendingNotices.php:15; SnapshotController.php:39 | Role check for display decisions or log from the 403 path; test that a Reader opening the overview writes nothing |
| M3 | Failed and throttled sign-ins carry no user identifier although the account is known (FR-022) | app/Services/Accounts/SignInService.php:32,44-52,103 | Pass the subject when the address resolves; keep unknown addresses anonymous |
| M4 | Yearly figure Audit entries get no organisation or canvas, so they are never purged (FR-021) and the Organisation filter hides them | app/Services/Audit/AuditTrail.php:27; Exchange/CostRecordBatch.php:57; Finance/CostRecords.php:157 | Resolve the organisation from the Cost record; test that deleting an Organisation leaves none |
| M5 | `system_notices.seen_by` stores a user id that the inventory and FR-023 say is not there and nothing reads | database/migrations/2026_11_19_000009_create_system_notices_table.php:21; Http/Controllers/SystemNoticeController.php:68 | Drop the column |
| M6 | Common-password check is effectively 10 entries long (ASVS 6.2.4, no exemption in ADR 0025) | app/Rules/Password.php:10,22,33; resources/data/common-passwords.txt | List filtered to the policy length with at least 3,000 entries; unit test on the count |
| M7 | Undo of a Cost record deletion can restore Allocation lines onto archived Elements (FR-017, FR-039); the set undo checks this, the record undo does not | app/Services/Undo/CostRecordUndo.php:64-91 against AllocationSetUndo.php:39,86 | Reuse the checks; refusal test |
| M8 | Carry over copies the Allocation set of an archived Cost record without its Yearly figures and reports it both copied and skipped; the test locks the behaviour in. Spec conflict (FR-056 against US 23 scenario 3) settled in code. Decision | app/Services/Allocation/CarryOver.php:66-79; tests/Feature/Allocation/CarryOverTest.php:69-80,128-141 | Product owner decides, spec records it; report each record once |
| M9 | Audit log shows raw keys for every D2, D3 and DT entry and cannot filter them (FR-013, FR-019); Finalise, Redistribution and Carry over are not distinguishable | app/Http/Controllers/Admin/AuditLogController.php:24; resources/js/pages/Admin/AuditLog.vue:72; app/Services/Allocation/Finalisation.php:52, AllocationSets.php:56 | Labels in nl.json for every subject, action and field; extend the kinds; wrap the three actions in `AuditContext::during` |
| M10 | Undo records are not removed at sign-out and expired ones stay until the next deletion (ADR 0033, plan.md:184), reported by one reviewer | app/Http/Controllers/Auth/SignOutController.php; app/Services/Undo/UndoStore.php:50-53,170-173 | Delete the user's records at sign-out; prune on restore; test |
| M11 | Invoeren does not fit at 320 px (WCAG 1.4.10, WB10): a fixed 216 px list beside the work area | resources/js/pages/Canvas/Entry.vue:151-152 | Breakpoint; a 320 px spec for Invoeren |
| M12 | Selected row in Tekenen "Lagen" is only a 1.20:1 tint (1.4.11, WB3); changes board 1b, product owner to be told | resources/js/pages/Drawing/LayerItem.vue:23 | The 2 px ink ring used for every other selected state |
| M13 | Tekenen has no keyboard or click zoom or pan (2.5.7, 2.1.1, WB11, US35 scenario 1) | resources/js/pages/Drawing/Toolbar.vue:31; edit-engine/openPencilEngine.ts:252 | Zoom in, out, 100% and pan controls |
| M14 | Focus ring on the Bekijken canvas shrinks with the zoom (WB2 requires 2 CSS px); the test reads the width before scaling | resources/js/canvas/CanvasView.vue:179 | Counter-scale the ring; test the rendered size |
| M15 | Page titles are not unique per mode and two sign-in pages have none (2.4.2, WB9) | Invoeren, Tekenen and Bekijken pages; Auth pages | One title per mode, `<Head>` on both auth pages |
| M16 | SC-029 "first use within 3 s" is asserted on warm reloads: only sample 1 is cold and the p95 of 20 drops it. This was reported "met" in dt-results.md:13, review-evidence.md:39,129 and d2-d3-dt-acceptance.md:54 | tests/e2e/drawing-performance.spec.ts:13-21; tests/e2e/support/timing.ts:23 | Assert the first sample of a fresh context against 3 s, or record "warm reload met, cold not asserted" |
| M17 | SC-019 reported met, but no test makes a PDF, counts pages or checks PNG text size; the 6 pt check passes on an empty list | tests/e2e/print.spec.ts:93-120; export-png.spec.ts:20-21; performance-d3.spec.ts:165 | PDF on the overflowing seed with page count and A3 size; guard the check; downgrade to partly met until then |
| M18 | Evidence documents contradict the code and each other: SC-026 "not built" although the journey exists, the new-Domain overlap listed open although fixed, the drag credited to the wrong spec, SC-001 "met" in one and "not run" in another | specs/001-omc-studio-v1/dt-results.md:8,10; docs/review-evidence.md:11,36,150; d2-d3-dt-acceptance.md:58; d1-acceptance.md:197 | Refresh the documents; one status for SC-001 |
| M19 | SC-020 "every screen" claims do not match what is measured; the D2 timings are not recorded and the Invoeren sample stops at the shell | tests/e2e/d2-flow.spec.ts:119,126-151; docs/review-evidence.md:30,127-133 | Cite and record them; wait for the rows; list the untimed screens |
| M20 | Tekenen page is a god component at the size cap with very long lines (395 lines, 21.4 KB, longest line 639 characters) | resources/js/pages/Drawing/Index.vue | Composables for engine lifecycle, reset and style editing; a line-length lint rule |
| M21 | Rules implemented several times with no shared fixture or with the wrong twin tested: move rules (three copies), automatic widths (the TS twin is not used by the engine, so the parity test proves a dead copy), three percentage parsers, geometry and style limits, field limits and name normalisation at about 20 sites | app/Services/Structure/ElementTree.php:79-92; resources/js/edit-engine/shiftMove.ts:94-98; pages/Drawing/moveTargets.ts:27-41; layout/automaticWidths.ts:16 and flowDomains.ts:23-30; format/parseAmount.ts, allocation/helpers.ts:43-46; app/Services/Layout/LayoutValidator.php:12-16; edit-engine/constraints.ts:16-20 | One rule per concern plus a JSON fixture read by PHP and vitest; `App\Support\FieldLimits`; call `NormalisesName::normalise` in Exchange |
| M22 | The canvas layout-input presenter is copied in five places and has already drifted; password rules live in controllers although the architecture lists a `PasswordService` | app/Services/Canvas/ViewData.php:25-50 and four controllers; AccountController.php:44-90, NewPasswordController.php:30-45, ReauthenticateController.php:31-51 | Extend `ViewData`; add `PasswordService` |
| M23 | The three canvas modes build three page headers, and the mode switch shows with no active segment on Toewijzen, Kostenposten and Versies (no board and no user story shows it there; the product owner can say if intended) | resources/js/pages/Canvas/View.vue:51; Entry.vue:141; Drawing/Index.vue:369; shell/AppShell.vue:53 | One shared canvas header; the switch only on mode routes |
| M24 | The Direction band differs between Tekenen and Bekijken (no dashed Logo box in Tekenen, goals all weight 600); the contract says one arrangement in every mode | resources/js/edit-engine/scene.ts:76-79 against canvas/CanvasView.vue:133-134 | Both from the same `splitDirectionLine` segments; a Tekenen comparison row |
| M25 | Shared components built in T386 are bypassed: InlineField (Toewijzen copy loses the invalid and disabled states), ReportTable (four copied tables), the field error edge depends on the field type; Toewijzen lists and the Overview have no empty state | resources/js/pages/Finance/AllocationEditor.vue:58-69; components/ImportReport.vue:19,37; CountsReport.vue:16,26; components/fields/TextField.vue:10, TextArea.vue:10, SelectField.vue:13; Finance/CostRecordStatusList.vue:30, TargetTree.vue:35; Overview.vue:70 | Use the shared components; one invalid-edge utility; empty sentences |

## 3. Minor and nit (grouped; every item cites its file in the reviewer notes)

- **Security hardening:** bcrypt truncates passwords over 72 bytes (app/Rules/Password.php:12); re-authentication keeps the session id (Auth/ReauthenticateController.php:59-61); one-time passwords and recovery codes pass through the session in plain text (env.example:29, Admin/UserController.php:51, SecondFactorController.php:30,38; set `SESSION_ENCRYPT=true`); `.dockerignore` leaves `.worktrees`, `.scratch`, `.forbidden-names`, `storage/app` and the dev SQLite in the build context (a clean clone is unaffected); no production `php.ini` and `X-Powered-By` present (Dockerfile:10,27); static files without nosniff (docker/apache.conf); session cookie without a prefix (config/session.php:130,172); database errors are logged without their origin (bootstrap/app.php:31); undo route lacks `auth` (routes/web.php:58-60); tags instead of digests in the Dockerfile and CI.
- **Privacy:** the PII inventory misses undo records, the user id in the error log, the first Administrator e-mail in the container log, per-user throttle keys, account state columns, user ids inside Audit values and the real session payload (pii-inventory.md:11-23; mirror in docs/constraints.md section 7); an abandoned second-factor enrolment keeps the secret (SecondFactorService.php:42); throttle files outlive 60 seconds (Throttle.php:38,45); dormant outbound log and mail channels remain configured (config/logging.php:94-111, config/services.php:17-31); log files have no permission setting and inconsistent key names. Decisions: user deletion scrubs matching text in unrelated Audit entries beyond FR-011 (UserDeletion.php:40-58,179-188) and removes actor linkability (:177); no session correlation in the security log (SecurityLog.php:16-21; against SD-20).
- **Correctness:** share with one decimal is rounded twice (resources/js/format/index.ts:60-63); Finalise with a failing closing check records no notice (Finalisation.php:46-49); "nieuw" depends on the last save (DrawingController.php:32-36); layout reset checks no versions (LayoutStore.php:50); a Shift move stores client size and style (DrawingMove.php:170-195); the full export silently omits damaged Snapshots (FullExport.php:55-57); Organisation rename keeps the old slug; default Fiscal year uses UTC (ResolveFiscalYear.php:37).
- **WCAG minor:** over-budget bar under 3:1 on dark tints, the organisation switch and year selector navigate on change, ARIA tree roles without tree keyboard support, an empty X, Y, B or H reverts silently, the Toewijzen line error does not name the line, axe gaps for error pages and two Tekenen dialogs, an ineffective "200% text" test. Deviations D1 to D11 with proposed owners and dates are in section 4.
- **Test honesty minor:** SC-001 browser test does not use the logged password; SC-005 assertion cannot fail; SC-002 cites a constant; functional Playwright gets two CI retries (playwright.config.ts:11, ci.yml:152); tautology and a WebKit skip in drawing-restrictions.spec.ts:15,25; the "repaint" spec measures two frames; the referrer spec's second half is racy; SC-009 and the Organisation half of SC-008 lack proof; 16 requirements have no task citation (traceability only); drawing-two-admins.spec.ts has no outside-request guard.
- **Design and lean:** dead code (app/Rules/HexColour.php, Lists/BulkResult.php, Support/CanvasFont.php, format/parseAmount.ts, shell registries, unused dependencies `@lucide/vue`, `concurrently`, `laravel/pail`); `laravel/tinker` is a production dependency; the WP-E0 prototype is still tracked and never run; two toolkits for import errors; seven files within 30 lines of the 400 cap; PHP test helpers copied; `APP_TIMEZONE` in env.example does nothing; a second flag for page-to-shell options.
- **Visual:** no type or elevation tokens (about 330 hand-typed sizes), page titles at 20 and 22 px, Panel bypassed about 25 times, menus and callouts hand-built, raw form controls, three copies of the discard confirmation, hard-coded `#C9CED6` and `#454C57`, empty sentences in three colours, the wordmark drawn two ways, badge and arrow copies.

## 4. Verdicts

**OWASP Top 10 (2021, ASVS 5.0 level 2):** A01 met. A02 met (plain-text secrets in the session, minor). A03 met. A04 met. A05 partly met (build context, php.ini, nosniff on static files, cookie prefix). A06 met (`composer audit --locked` and `scripts/check-audit.mjs` clean on the review day). A07 partly met (B5, M6, bcrypt truncation, re-authentication). A08 met. A09 partly met (M1, M2, M3). A10 met (no outgoing HTTP anywhere).

**WCAG 2.2 AA (static):** Perceivable does not conform yet (1.4.10 for Invoeren, 1.4.11 for the selected layer row). Operable does not conform yet (2.5.7 and 2.1.1 for the Tekenen zoom and pan, 2.4.2, the focus ring under zoom). Understandable conforms with minor gaps. Robust conforms with minor gaps. Text tokens in use reach 4.5:1 and input and button borders reach 3:1. Recorded deviations with proposed owner and date for the orchestrator to confirm: D1 `data-invoeren-note` (builder, 2026-11-23); D2 Invoeren at 320 px (builder, 2026-11-24); D3 selected layer row (builder, product owner told, 2026-11-24); D4 Tekenen zoom and pan (builder, 2026-11-27); D5 canvas focus ring under zoom (builder, 2026-11-25); D6 page titles (builder, 2026-11-23); D7 over-budget bar on dark tints (product owner decides, then builder, 2026-11-26); D8 selectors that navigate on change (builder, 2026-11-25); D9 ARIA tree roles (builder, 2026-11-25); D10 silent revert and unnamed line error (builder, 2026-11-25); D11 axe and 200% text test gaps (builder, 2026-11-25).

**AVG/GDPR:** reconciliation code to inventory fails (items in section 3); inventory to code holds with two exceptions (the 90-day retention that is not enforced, and "only when turned on" for the second-factor secret). Deletion and retention: user deletion passes; canvas and Organisation deletion fails for Yearly figure entries (M4). No personal data leaves the own origin.

**NIS2:** conditional pass. Who, what and when can be reconstructed for successful actions; "from where", the account targeted by failed attempts and session separation cannot. M1, M2 and M3 must be fixed before the gate. The reconstruction walked through (a guessed Administrator password, changed Allocations, a deleted user and a deleted Canvas) found the gaps above and no step without any log.

**Scope note (`specs/PROFILE.md`) validation:** the reviewers found nothing built outside the scope note: no hosted service, no outgoing connection, Tekenen changes layout of existing Elements only and draws no free shapes, texts or images. Drift recorded: the Direction band differs between Tekenen and Bekijken (M24); the mode switch appears on screens the design does not show it on (M23).

**Evidence honesty:** D1 partly honest, D2 honest with gaps, D3 partly honest (SC-019), DT partly honest (SC-029, stale documents). Honest and proven: the shared contrast and money fixtures, the FR-095 property test and the one-cent tamper test, the finalised-year guards on every write path, the race tests and the drag-and-colour proof.

**Correctness:** D1 partly met, D2 partly met (M7, M8, B6, share rounding), D3 met, DT met with minor deviations.

## 5. Not covered

No reviewer ran a browser or the suites; the throttle race, the false refusal entries and the Invoeren reflow come from reading code and CSS, not from a run. Not read in depth: the drawing-library adapter internals, the PNG and print path, backup and restore, sample data, `model $fillable` against every service whitelist. Per-task review recording (`routing.py record-review`) was not done.

## 6. Seed route (finding, fixed on its own branch)

Recurring `php artisan tinker` seed failures in Playwright (observed on Chromium and WebKit, never reproducible alone) made full runs red for reasons unrelated to the code. Fixed by one seed route, `tests/e2e/support/seed.php` with `tests/e2e/support/seed.ts`: no interactive shell, one copy of the database environment instead of 23, a bounded retry and the PHP error text in the thrown message. The design reviewer's remaining proposal (named seed scenarios) is open.

## 7. Status of the fixes (2026-11-21)

Each finding below was fixed on its own branch cut from 6ec1d33, with a test that fails without the fix, and was checked by an independent reviewer that ran the new test against the parent code. The branches are not merged anywhere yet; they become pull requests to `release` once Tekenen is there, each on a branch cut from `origin/release`, merged on a green CI run. Merge order and stacks: B1 first (it also carries this report and the seed route), B4 before B6 and before M21 (both are stacked on B4), B5 before M3 and before M22 (M3 is stacked on B5, M22 on M3), M18 before M19 before M16 before M17 (stacked, they share the rows of docs/review-evidence.md).

| ID | Status | Branch | Commit | Test that fails without the fix |
|---|---|---|---|---|
| B1 | opgelost | review/b1-indexes | d8a8d02 | tests/Feature/Architecture/IndexesTest (user_id indexes) |
| B2 | opgelost | review/b2-comments | c05d2da | tests/Feature/Architecture/NoCodeCommentsTest (every tracked PHP file; .devcontainer left to B3) |
| B3 | opgelost (product owner, option A, 2026-11-21) | review/b3-tooling-exception | 278507f, merged as c6951ff (pull request 415) | tests/Feature/Architecture/CommitMessageHookTest (9 tests, red before the hook existed); dated exceptions for the tooling files and for the commits d66a78c and d0fc304 in docs/process/00-profile-and-constitution.md; `.githooks/commit-msg` refuses Co-Authored-By trailers and session links, activated by `make hooks`; history not rewritten |
| B4 | opgelost | review/b4-english-keys | 29e64b4 | tests/Feature/Exchange/EnglishExchangeKeysTest |
| B5 | opgelost | review/b5-throttle | a05961d | tests/Feature/Concurrency/ThrottleRaceTest (8 real processes), tests/Feature/Accounts/ThrottleTest |
| B6 | opgelost | review/b6-csv-archive (stacked on B4) | bb55ee2 | CostRecordImportCommitTest and CostRecordImportPreviewTest (archived Organisation, archived record, record archived between scan and write) |
| M1 | opgelost | review/m1-log-retention | c037a8b | tests/Feature/LogRetentionTest |
| M2 | opgelost | review/m2-false-forbidden | b9866c3 | tests/Feature/Security/ForbiddenLogTest |
| M3 | opgelost | review/m3-signin-subject (stacked on B5) | 8af831a | tests/Feature/Accounts/SignInServiceTest |
| M4 | opgelost | review/m4-yearly-audit | 78bb4fa | tests/Feature/Audit/YearlyFigureAuditTest |
| M5 | opgelost | review/m5-seen-by | 30e343f | tests/Feature/SystemNoticePageTest |
| M6 | opgelost | review/m6-common-passwords | a2c5fd3 | tests/Unit/Rules/PasswordRuleTest (at least 3,000 entries at the policy length, a listed password refused whatever its case); independent review passed; docs/constraints.md V6 names the source; the list is 10,000 entries of 12 or more characters filtered from the SecLists xato-net top 1,000,000, with the MIT licence and the source in resources/data/common-passwords.LICENSE and common-passwords.SOURCE |
| M7 | opgelost | review/m7-undo-archived | d1384f2 | tests/Feature/Finance/CostRecordDeleteTest |
| M8 | opgelost (product owner, option A, 2026-11-21) | review/m8-carry-over-archived | 0fe7211, merged as f036102 (pull request 416) | tests/Feature/Allocation/CarryOverTest (an archived Cost record is skipped and reported once; red before); spec.md User Story 23 scenarios 3 and 6, FR-056 and FR-070 agree |
| M9 | opgelost | review/m9-audit-labels | 6a4c097 | AuditLabelsTest, AllocationCsvImportTest |
| M10 | opgelost | review/m10-undo-signout | 54d34d9 | undo tests at sign-out and restore |
| M16 | opgelost | review/m16-sc029-cold (stacked on M19) | bc56548 | tests/e2e/drawing-performance.spec.ts, cold first use in five fresh browser contexts, slowest against 3000 ms (slowest 1967 ms Chromium, 1401 ms Firefox, 1046 ms WebKit; browser cache empty except for what the sign-in pages loaded, server caches warm); warm and cold are two rows in dt-results.md and review-evidence.md |
| M17 | opgelost | review/m17-sc019-print (stacked on M16) | 5d63c2c | tests/e2e/print.spec.ts (Chromium-only PDF: 4 A3 landscape pages for the overflowing seed, 1 page for a canvas that fits; red with A4 portrait) and tests/e2e/export-png.spec.ts (PNG is twice the Artboard width, smallest text on the exported DOM at least 6 pt per spec.md:741; red with an 8 px text); SC-019 met on the named seeds |
| M18 | opgelost | review/m18-evidence-docs | ffb052e | tests/Unit/EvidenceDocumentsTest (one status for SC-001, the SC-025 and SC-026 rows name the right specs; red on the old documents) |
| M19 | opgelost | review/m19-sc020-evidence (stacked on M18) | 7c4677d | tests/e2e/d2-flow.spec.ts (the Invoeren sample waits for the 499 rows of Primaire activiteiten; the old sample measured an empty default Area); figures recorded in review-evidence.md; SC-020 partly met with the untimed screens listed; three runs under load missed the 1000 ms budget with normal medians (budget unchanged) |
| M21 | opgelost | review/m21-shared-rules (stacked on B4) | 751af83, 677408d, a9782c4, abbb552, c68d1b2 | MoveRulesFixtureTest with moveRules.test.ts, drawnWidths.test.ts, the percentage fixture, LayoutLimitsTest, FieldLimitsTest; each red before its commit |
| M22 | opgelost | review/m22-presenter-password (stacked on M3) | 8fefdf4 | ViewDataPresenterTest, PasswordServiceTest; the overview looks the modalities up once whatever the number of canvases |
| M12 | open, decision of the product owner | none | none | none |
| M23 | open, decision of the product owner | none | none | none |
| Seed route (section 6) | opgelost | review/b1-indexes | 4c55733 | no tinker failure in the full Chromium and WebKit runs (it was four per run before) |

Not in these rounds, still open: M11, M13 to M15, M20, M24, M25 (with session 2). Every minor and nit of section 3 has a row in section 8.

Remarks the reviewers left on the fixed items: B5's "five wrong attempts count one lockout" test guards against a future double count and does not prove the fix; `clear()` is not under the lock, so parallel requests can count one extra lockout; M1 prunes when the channel first logs, so an idle installation keeps old files until the next write; M10 does not delete undo records at the forced sign-outs (absolute session expiry, forced password change), because ADR 0033 says only "at sign-out"; M4 does not backfill existing entries without an organisation; M7 copies the element check from the set undo instead of sharing it; the seed route run on WebKit still showed four failures that are timeouts of the page server under machine load and two content failures, which have not yet been rerun on a quiet machine.

Second round (2026-11-21): the seed route was rerun on a quiet machine: the three Chromium specs that timed out under load passed 10 of 10 and the four WebKit specs 12 of 12, so those failures came from load and not from the seed route. Each branch of M6, M16 to M19, M21 and M22 was checked by an independent reviewer; findings of that review were reworked and the reworked commits are the ids above. Open remarks of the reviewers: M16 to M19 evidence rows were measured on an arm64 container with eight gigabytes, the D2 timings of M19 are noisy under load; the B5 five-failures test only guards against double counting; SecondFactorChallengeTest::test_a_reused_code_counts_towards_the_waiting_time showed a one-second boundary flake (60 s expected, 59 s seen) in one run on the M22 branch that predates the change.

Hand-off notes to the M25 owner (session 2): D9 (ARIA tree and treeitem roles in TargetTree.vue, TargetNode.vue and RedistributionDialog.vue, 4.1.2) belongs with M25; the `[role="treeitem"]` wait in tests/e2e/sc020-screens.spec.ts must change with it; the Toewijzen percentage line error that does not name its line (AllocationEditor.vue) and the one-line `useMeasurer` in Overview.vue:38 (it keeps its own `createBrowserMeasurer` without a catch) are in the same files.

## 8. Section 3 points: commit or motivated 'niet doen' (2026-11-21)

Each fixed item was checked by an independent reviewer; all bundles passed after rework. Branches: SH = review/s3-security-hardening (on M22), PL = review/s3-privacy-logging (on SH), CM = review/s3-correctness-minors, LT = review/s3-lean-tests-backend (on B1), WC = review/s3-wcag-minor, TH = review/s3-test-honesty-e2e, FL = review/s3-frontend-lean, VM = review/s3-visual-minor, S20 = review/sc020-all-screens (on M17).

**SC-020 on every screen (S20, 611210e on the stack M18, M19, M16, M17):** tests/e2e/sc020-screens.spec.ts times Overview, Beheer, Account, Kostenposten importeren, Toewijzen with a selected record, Logboek, Gebruikers, Eigenaren, Applicaties, Organisaties, Volledige export, Voorbeeldgegevens and the sign-in, new password and second factor screens, 20 samples, p95 under 1000 ms, no code fix needed. Status: met on this machine; red only in runs under heavy load (WebKit Gebruikers p95 1179 ms at load 5 to 8, Applicaties 1007 ms), budget unchanged; figures in docs/review-evidence.md section 7.

| Point | Status | Commit | Branch |
|---|---|---|---|
| Security: bcrypt truncates over 72 bytes | niet doen: spec decision (spec and an existing test name bcrypt and allow 128 characters; argon2id or a cap changes FR-002) | none | none |
| Security: re-authentication keeps the session id | opgelost | 3eabbc1 | SH |
| Security: one-time passwords and recovery codes in the sessions table | opgelost (SESSION_ENCRYPT true, reauth e2e helper moved to the session store) | 70e8f30 | SH |
| Security: .dockerignore leaves secrets in the build context | opgelost (allowlist plus storage/*) | 30cf5aa | SH |
| Security: php.ini and X-Powered-By; static files without nosniff | opgelost (variables_order restored to EGPCS) | bab3cb1 | SH |
| Security: session cookie prefix | niet doen: loopback without TLS cannot carry Secure (ADR 0024); accepted gap recorded in constraints.md V3 and V7 | 5183716 | SH |
| Security: database errors logged without origin | opgelost | 4dd9367 | SH |
| Security: undo route lacks auth | opgelost | 89bca2c | SH |
| Security: images and actions pinned by tag | niet doen: digests cannot be resolved offline; Dependabot proposal recorded | 5183716 | SH |
| Privacy: PII inventory gaps; throttle files outlive 60 s | opgelost (documents, guarded) | 24df229 | PL |
| Privacy: abandoned second-factor enrolment keeps the secret | opgelost | b0f3dc7 | PL |
| Privacy: dormant outbound log and mail channels | opgelost (pruned at boot, ADR 0027 sentence) | a77cd0b | PL |
| Privacy: log file permission and key names | opgelost | a5fb48e | PL |
| Privacy: user deletion scrub beyond FR-011, lost actor linkability, session correlation | niet doen: product decisions (FR-011, FR-021, SD-20) | none | none |
| Correctness: share rounded twice | opgelost | d537d5e | CM |
| Correctness: Finalise records no notice | opgelost | 27b1aba | CM |
| Correctness: "nieuw" marker depends on the last save | niet doen: a faithful marker needs a baseline for Elements that existed before it, a migration and a client change; product decision | none | none |
| Correctness: layout reset ignores versions | opgelost | f71a7d3 | CM |
| Correctness: Shift move stores client size and style | opgelost | 355a955 | CM |
| Correctness: full export omits damaged Snapshots silently | opgelost (header and a line on the Exchange page; the control stays a button with the old look) | 23988f6 | CM |
| Correctness: Organisation rename keeps the slug | niet doen: FR-024 derives the slug at creation and an existing test pins it; changing it renames export files | none | none |
| Correctness: default Fiscal year in UTC | opgelost | d1757e9 | CM |
| WCAG: over-budget bar under 3:1 on dark tints (D7) | niet doen: product owner decides | none | none |
| WCAG: Organisation switch navigates on change (D8) | opgelost (explicit "Tonen" button) | 897cb3f | WC |
| WCAG: year selector navigates on change (D8) | niet doen: documented reason and residual risk in review-evidence.md section 8 (it reloads like a filter); the apply-button pattern is a product choice | 7072666 | WC |
| WCAG: ARIA tree roles (D9) and the Toewijzen line error naming | niet doen: files of M25 (see the hand-off notes above) | none | none |
| WCAG: empty X, Y, B or H reverts silently | opgelost | 0ece242 | WC |
| WCAG: axe gaps (error pages, two dialogs) | opgelost | b624325 | WC |
| WCAG: 200% text test enlarges nothing | opgelost (computed sizes doubled, plus a text-spacing test; true text-only zoom is not possible in Playwright, said so) | 5cb1fd3 | WC |
| Tests: SC-001 browser test ignores the logged password | opgelost | 5b4fb91 | TH |
| Tests: SC-005 assertion cannot fail | opgelost | 3ef21c1 | TH |
| Tests: SC-002 cites a constant | opgelost | 154dc9f | LT |
| Tests: functional Playwright retried in CI | aangepast: the functional job keeps its CI retries, motivated in docs/review-evidence.md section 8; the performance and secure-cookie runs stay at --retries=0 (guarded by CiWorkflowTest) | ac47e6e | LT |
| Tests: tautology and WebKit skip in drawing-restrictions | opgelost (gap documented) | 55d02a4 | TH |
| Tests: style "repaint" spec measures two frames | opgelost (retitled to what it measures) | bca98d0 | TH |
| Tests: referrer second half racy | opgelost | 05ff99d | TH |
| Tests: SC-009 closing check after deletion | opgelost | 154dc9f | LT |
| Tests: SC-008 Organisation race | opgelost | fee9377 | LT |
| Tests: no requirement-level trace for 16 FRs | opgelost (table with tests; FR-044 and FR-053 gaps stated) | 3ab715f | LT |
| Tests: drawing-two-admins and other specs without the guards fixture | opgelost (secure-cookie left as is on purpose) | 7e5930e | TH |
| Tests nit: refusal coverage is a text search; route enumeration wording; FR-095 generator narrow; block 500 of 508 wait | niet doen: documented heuristics or harmless, no behaviour at stake | none | none |
| Design: dead backend code, `laravel/pail`, `laravel/tinker` in require | opgelost | 71c34f4 | LT |
| Design: dead JS, `@lucide/vue`, `concurrently` | opgelost | 08f1d54 | FL |
| Design: two import error toolkits | opgelost | a50a99b | LT |
| Design: admin filter controllers and the notices N+1 | opgelost | bafdb3f | LT |
| Design: layout engine has no narrow interface | niet doen: seam refactor in the files of the Tekenen engine owner (M13 to M15, M20, M24); no behaviour at stake | none | none |
| Design: WP-E0 prototype still tracked | niet doen: owner decision after ADR 0040 | none | none |
| Design: second page-to-shell flag; `useWideMain` | niet doen: part of the mode switch placement (M23) | none | none |
| Design: measurer bootstrap repeated | opgelost for Invoeren, Bekijken and Snapshot view; Overview.vue is an M25 file (hand-off above) | f18f113 | FL |
| Design: routes.ts parity unproven | opgelost | 1ef8e3a | LT |
| Design: seven files near the 400-line cap | niet doen: none is over the cap; LayoutValidator is split with the next layout rule | none | none |
| Design: PHP test helpers copied | opgelost (5 tests keep a cached admin() on purpose) | d328408 | LT |
| Design: APP_TIMEZONE does nothing | opgelost | 9ff4cb8 | LT |
| Nit: XSRF cookie reader in 8 files | opgelost | 0f2954a | TH |
| Nit: AI provider endpoints in scripts/vendored-urls.txt | niet doen: article owner rules with B3 | none | none |
| Visual: typography tokens; off-scale sizes; seven page titles | opgelost for the tokens, the off-scale sizes and the seven titles; the other ~330 hand-typed sizes are staged by screen (niet doen now: no logic, each needs its own screen run) | 43ae724 | VM |
| Visual: page titles of View.vue and SnapshotView.vue | niet doen: M23 | none | none |
| Visual: elevation token | opgelost; the toolbar shadow stays (M13 file) | 43ae724 | VM |
| Visual: hard-coded #C9CED6 and #454C57; empty sentences; row Verwijderen; wordmark | opgelost | 43ae724 | VM |
| Visual: Modality badge outside ModalityMark; interface name | opgelost (class constant in Block.vue to avoid a component per block) | e1274ae | VM |
| Visual: CardMenu and UserMenu re-implement ActionMenu | opgelost | 99b2495 | VM |
| Visual: hand-built notices | opgelost | 9fd917b | VM |
| Visual: raw form controls | opgelost | 8cb96d8 | VM |
| Visual: three discard confirmations | opgelost | f71f62c | VM |
| Visual: Panel bypassed | opgelost | 4353544 | VM |
| Visual: disabled Toewijzen actions without explanation | opgelost | 8460455 | VM |
| Visual: reorder arrows copy IconButton | niet doen: an existing guard forbids a component instance per row (500-row Invoeren performance, commit 94691cbb) | none | none |

Remarks that remain for the merge: Docker cannot be built here, so `scripts/prove-clean-start.sh` must run once before the first merge that carries the image changes (SH); the branches conflict as the reviewers listed (bundle PL with M1 and M10, SH and PL with B2 on config files and .dockerignore, LT with M5 on SystemNoticePageTest, the reauth helper of B1 with SH); the first-start browser spec skips when a second engine reuses the same install.

## 9. Final status on release (2026-11-21, tip f8a778e)

Every finding of this report is either on release or listed as open below. The commit is the merge commit on release of the pull request that carries the fix; the fix commits are in sections 7 and 8.

| Findings | Status | Pull request, merge commit on release |
|---|---|---|
| B1, B2, B4, B5, B6, M1 to M7, M9, M10, M16 to M19, M21, M22, D8 (Organisation switch), every small point marked opgelost in section 8 | opgelost | 411, f8a778e |
| B3 (tooling exception, hook) | opgelost | 415, c6951ff |
| M8 (archived Cost records at carry over) | opgelost | 416, f036102 |
| M11, M13, M14, M15 (Invoeren at 320 px, Tekenen zoom and pan, focus ring under zoom, page titles) | opgelost | 410, d1f9131 |
| M12 (selected layer row) | opgelost | 418, cad6454 |
| M20, M24, M25 (Tekenen page split, Direction band, shared components) | opgelost | 409, a16adce |
| M23 (mode switch only on the three modes) | opgelost | 417, c2f8e4e |
| D9 (ARIA tree roles removed) | opgelost | 414, 4eea87c |
| Evidence fixes of the second reviewer round and the ledger records | opgelost | 417, c2f8e4e; 421, 18ddb9a; 412, 1839559; 420, 9409871 |

Open on release, with the reason:

- **T312** (modality overlay counts strip of a Domain is drawn under the Domain fill and is invisible, assumption T312-1 in tasks.md): reopened by the second review round; waits for the decision of the product owner.
- **D7** (the over-budget bar reaches only 2.53:1 on the darkest overlay tints, the forecast text carries the meaning): product owner decides.
- **Recorded gaps, accepted by decision or by constraint:** no `__Host-` cookie prefix (loopback without TLS, ADR 0024); images and actions pinned by tag, not digest (cannot be resolved offline, Dependabot proposed); passwords longer than 72 bytes are cut by bcrypt (spec decision: argon2id or a cap); the user deletion scrub beyond FR-011, the lost actor link after deletion and the missing session correlation in the security log (decisions against FR-011, FR-021, SD-20); the "nieuw" marker needs a baseline and a migration; the Organisation slug is fixed at creation (FR-024); the year selector navigates on change (documented residual risk); about 330 hand-typed font sizes migrate screen by screen (tokens exist); `LayoutValidator` is split with the next layout rule; the WP-E0 prototype stays tracked until the owner decides after ADR 0040; the lazy per-row form data of Kostenposten (a possible later optimisation).
- **Evidence limits:** the clean start was proven on the host (macOS with Docker Desktop, by the orchestrator, see wp1-evidence.md) and not in the container; SC-020 and SC-029 are measured on the CI runner and on the 11 core arm64 development container, with the budgets unchanged; two WebKit tests (drawing-text-style, view/hover) and one Firefox test (redistribution) showed as flaky on single runs and passed on retry.

## 10. Final verdicts after the fixes (2026-11-22, tip 8f946b8)

Three reviewers on the phase model re-checked the code of the release tip against the first findings, read-only and blind to each other; their new findings are fixed in section 11 or listed as decisions P1 to P5 at the top.

**OWASP Top 10 (2021, ASVS 5.0 level 2):** A01 to A10 all met. O1 to O11 closed: nine fixed in code with guard tests, O4 (bcrypt cuts passwords over 72 bytes) and O10 (no `__Host-` cookie prefix) kept as recorded gaps. No regression from the fixes: the deferred props (Invoeren costs and targets, Kostenposten moreRows) stay behind the same route and controller checks, `X-Omitted-Snapshots` carries a count only on an Administrator route, `QueryTrace` logs SQL without bound values. `composer audit --locked` and `scripts/check-audit.mjs` clean on 2026-11-22; the four npm high findings are the chain the product owner accepted in ADR 0040. Advisories left open: A1, record the bcrypt gap in docs/constraints.md V6 (it is only in this report); A2, the cookie exemption wording in V3 and the exemption count in section 6; A3, Inertia devtools depend on APP_ENV alone (`INERTIA_DEVTOOLS_ENABLED=false` in env.example). New blocking finding: the tooling references outside the exception of 2026-11-21, decision P5.

**WCAG 2.2 AA:** after section 11, Perceivable partially conforms, the only remaining failure is 1.4.11 for the over-budget bar on dark tints from a 31.6% share (D7, decision P1); Operable, Understandable and Robust conform. D1 to D6, D9 and D11 resolved; D8 resolved for the Organisation switch, the year selector's recorded reason holds for 3.2.2; D10 resolved for the Tekenen fields, the Toewijzen line error naming is fixed in section 11. New findings N1 to N5 of this axis in section 11. A browser pass with a screen reader remains the product owner's acceptance step.

**AVG/GDPR:** code to inventory passes; inventory to code conditional on the deleted-user labels of FR-011 and three inventory rows (section 11); retention and deletion conditional on the same fix and on decisions P2 and P3; the deferred props, sessions, full export and throttle files expose no personal data to a wrong role.

**NIS2:** conditional pass. The first review's conditions M1 (retention by age), M2 (no false refusal entries) and M3 (failed sign-ins carry the user) are met. The limits of reconstruction are now written in docs/constraints.md section 8 and the security log carries a session reference (section 11, decision P4).

## 11. Status of the final findings (2026-11-22)

Built on two branches by builders on another model than the reviewers, reviewed on the phase model before the merge, each item with a test that failed first unless it is a document fix. Pull request 426 (privacy and security) merged as 565b80c, pull request 427 (accessibility and conventions) merged as 810ef27, both green on all eight CI jobs.

| ID | Finding | Status | Commit on release | Test |
|---|---|---|---|---|
| P-N1 | After deleting a user, "Definitief door" and "Gemaakt door" showed an empty name or "geen" instead of "verwijderde gebruiker" (FR-011) | opgelost; imported finalised years are marked by a new `imported` column and show no finaliser | 411041b, 2b7596d, 6057324 (426) and this report's commit | tests/Feature/Accounts/DeletedUserLabelsTest; CanvasImportTest::test_an_imported_finalised_year_is_marked_imported_and_the_source_year_is_not |
| S-REF | The security log could not separate concurrent sessions of one account | opgelost; every entry carries a random `session_ref` kept in the session (decision P4) | 2de2452, 9bc0123, f292b35 (426) | tests/Feature/Audit/SecurityLogSessionReferenceTest |
| R-LIM | The reconstruction paragraph claimed the Audit log answers who changed what | opgelost; docs/constraints.md section 8 lists the limits (deleted users, hard deletions, from where, slow guessing) | 4fd0e11 (426) | document |
| P-N2 | Inventory said user ids inside Audit values are pseudonymised | opgelost; the numeric id stays and no longer resolves to a person | 73e579b (426) | document |
| P-N3 | Undo records were said to go after 10 minutes, but stayed until the next deletion or undo | opgelost; also pruned at every sign-in, documents exact | 6b55c4f (426) | tests/Feature/Accounts/SignInUndoPruneTest |
| P-N4 | Second-factor secret "only when turned on" | opgelost; also while an enrolment is pending, discarded at the next sign-in or sign-out | 3f52d13 (426) | document |
| P-N5 | Throttle retention inconsistent; challenge throttle missing from the inventory | opgelost | b4bb949 (426) | document |
| W-N1 | Dutch identifiers in test code (house convention) | opgelost; guard scans app, tests and resources/js | 3c37afc (427) | tests/Feature/Architecture/EnglishIdentifiersTest |
| W-N2 | Focus lost after "Verder bewerken" (2.4.3) | opgelost in DiscardConfirm for the dock, dialogs and the detail panel | 5375e53 (427) | e2e reauth, overlays, design-kostenposten on three engines |
| W-N3 | Two screens titled "Kostenposten" (WB9) | opgelost; the Canvas variant is "<canvas> - Kostenposten" | 95fb21f (427) | tests/e2e/review-a11y-titles.spec.ts |
| W-N4 | Deferred loading not announced, disabled controls without a reason, a failed deferred load stayed loading for ever | opgelost; status regions, aria-describedby, a failure text with retry | 93c0f1b, 5cfd2e5 (427) | tests/js/components/list.test.ts, tests/e2e/element-costs-line.spec.ts |
| W-N5 | Zoom readout flooding announcements, stale field error, nav name "Areas" against the visible "Area's", aria-label on a span, dangling aria-describedby | opgelost; the field error now clears at the next input so the refusal stays visible | 346fe2a, 31e7419, 1a6360c (427) | drawing-zoom-pan, drawing-box-empty, allocation-editor specs |
| D10b | The Toewijzen line error did not name its line (WB5) | opgelost | 1a6360c (427) | tests/e2e/allocation-editor.spec.ts |
| D8r | No proof that focus stays on the year selector | holds, now asserted | 1ced561 (427) | tests/e2e/year-selector.spec.ts |
| D5r | Ring width unmeasured after a fit below 50% | holds, now measured on three engines | 86cf415 (427) | tests/e2e/view/focus-ring.spec.ts |
| D11r | 200% text and text spacing tested on two screens only | opgelost; extended to Invoeren, Bekijken and Kostenposten, which overflowed by 112 px at 200% text and is fixed | c239f73 (427) | tests/e2e/shell/reflow.spec.ts |
| P1 | Decision for the product owner (D7) | open, waiting for the product owner | none | none |
| P2 to P5 | Decisions of the product owner, all option a (2026-11-22) | opgelost | P2 96bf2f9, P3 943ddb0, P4 c5eac39, P5 5ad607a | tests/Feature/Accounts/UserDeletionTest.php, DeletedUserLabelsTest.php, Audit/AuditLogPageTest.php |
| T312 | Modality overlay counts strip invisible | open, product owner | none | none |

Advisories left open on purpose (no gate effect): OWASP A1 (bcrypt gap only recorded here, not in docs/constraints.md V6), A2 (cookie exemption wording and the exemption count), A3 (Inertia devtools depend on APP_ENV alone); privacy N7 (README invites keeping security logs beyond 90 days), N8 (no file permission on the error log), N9 (pruning needs a write, files can live one day past the limit), N10 (refusal entries name the route template, not the model); the WebKit Tekenen style panel that sometimes does not appear after a layer click (drawing-apply-style, drawing-text-style), retried green in CI, handed to the second session.

