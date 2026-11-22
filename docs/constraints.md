# Constraints: OMC Studio version 1

Date: 2026-11-18, phase 3. Input: `specs/001-omc-studio-v1/spec.md` (commit c79175d), `specs/001-omc-studio-v1/pii-inventory.md`, kickoff v1.2, addendum A1. Risk class `standard`.

Every entry carries a number or a verdict and its origin: **PO** (product owner decision), **orch** (orchestrator choice, confirmed by the product owner in phase 3, or in phase 4 where the row says so), or the spec item it restates. GATE-U1 (Finalise only with an empty Unknown allocation), ADR 0011 and SD-25 to SD-41 were confirmed by the product owner on 2026-11-18, after phase 3. The session rows of V7, the image sources of V3 and P-5 were settled in phase 4 (ADR 0037, ADR 0039, ADR 0035).

## 1. Load and performance

| Id | Constraint | Number | Origin |
|---|---|---|---|
| L-1 | Concurrent users | At most 5 signed-in users at once, of whom at most 2 Administrators writing | orch |
| L-2 | Throughput beyond L-1 | Unbounded, not designed for; accepted | orch |
| P-1 | Screen response | p95 at most 1000 ms from request to usable screen, with 500 Elements and 1000 Cost records in one Canvas, on 4 cores and 8 GB memory; measured by the end-to-end suite on that dataset | SC-020, orch (percentile) |
| P-2 | Tekenen | First use within 3 s; every action, saving included, within 1 s on a Canvas of 500 Elements | FR-114, SC-029 |
| P-3 | CSV import | Dry run and commit of a 5 MB file each within 10 s | orch |
| P-4 | JSON import (Canvas or full) | Dry run and commit of a 50 MB file each within 60 s | orch |
| P-5 | Clean start | Sign-in screen answers within 3 minutes of `docker compose up -d`, once the image has been built on that machine; the README states the one-time build time | SC-001, orch (phase 4, Q-12) |
| P-6 | PNG and print export | No separate bound; accepted unknown, measured once in D3 and reported | orch |
| P-7 | Tekenen change notice from the drawing library | Within 100 ms of the action | WP-E0 P4 |

## 2. Data volume

| Id | Constraint | Number | Origin |
|---|---|---|---|
| V-1 | Organisations | Up to 20 | orch |
| V-2 | Canvases | Up to 20 per Organisation | orch |
| V-3 | Fiscal years | Up to 10 per Organisation | orch |
| V-4 | Elements and Cost records | Up to 500 Elements per Canvas, up to 1000 Cost records per Organisation | SC-020 |
| V-5 | Snapshots | Up to 50 per Canvas | orch |
| V-6 | Database file | Up to 1 GB; accepted | orch |
| V-7 | Audit log | Grows without bound while its Canvas or Organisation exists; accepted | orch, grilling Q8 |
| V-8 | Uploads | Logo 1 MB, CSV 5 MB, JSON 50 MB | FR-024, FR-086 |
| V-9 | First Organisation's real size | Unknown; V-1 to V-5 stand until real data says otherwise | GATE-U3 |

## 3. Retention and deletion

| Id | Data | Retention | Deletion path | Origin |
|---|---|---|---|---|
| R-1 | Audit log entries | As long as their Canvas or Organisation | Removed with the hard deletion of that Canvas or Organisation, which leaves one deletion entry (FR-021) | grilling Q8 |
| R-2 | System notices | Until their Canvas is deleted; notices without a Canvas (lockouts, SD-41) unbounded; accepted | Removed with the hard deletion of their Canvas | PO |
| R-3 | Snapshots | Until an Administrator deletes them | Delete in "Versies"; removed with their Canvas | PO |
| R-4 | Security log | 90 days | Daily files; files older than 90 days are removed at rotation, without a scheduler (ADR 0023) | FR-022 |
| R-5 | Application error log | 14 days | Daily files, removed at rotation | orch |
| R-6 | Sessions | Until sign-out or 120 minutes idle | Expired rows removed by the session store's own clean-up during requests | FR-005 |
| R-7 | Throttle keys | Entries expire after 60 seconds; the file is removed on the next read or on a cache clear, so it can outlive 60 seconds on disk | Expire in the cache store | FR-004 |
| R-8 | Deleted user | Account row hard-deleted at once; every place that named them shows "verwijderde gebruiker" | Users screen (FR-011); console command for the last Administrator's password only (FR-008) | PO, FR-011 |
| R-9 | Backups | The product owner's; outside the application | Deleted by hand; README says so | PO, ADR 0026 |
| R-10 | Exports after download | The product owner's | Outside the application | pii-inventory |

The PII elements and their retention and deletion path are in section 7.

## 4. Availability and recovery

| Id | Constraint | Number or verdict | Origin |
|---|---|---|---|
| A-1 | Availability | No target: runs when the machine runs; accepted | PO, ADR 0026 |
| A-2 | Recovery point | Everything since the last `make backup`; README advises weekly and before every import | PO |
| A-3 | Recovery time | 15 minutes with `make restore` | PO |
| A-4 | Automatic backup | None | PO |

## 5. Project and operation

| Id | Constraint | Number or verdict | Origin |
|---|---|---|---|
| O-1 | Deadline | None, for any delivery | PO |
| O-2 | Team | The product owner plus the agent sessions of the workflow | PO |
| O-3 | Budget | The existing subscription; no other cost, no paid service (ADR 0027) | PO |
| O-4 | Operational ownership | The product owner operates the machine: backups, the console reset command, reading the security log when warned, dependency updates, SSH access for colleagues | PO |
| O-5 | Deployment | `deploy_target: none`; the product owner's laptop or development server via Docker Compose, two commands (H9) | PROFILE, FR-089 |
| O-6 | Process topology | One application container and one SQLite file on a volume; no Redis, mail, queue worker or scheduler (ADR 0023) | orch |
| O-7 | Database engine | SQLite in delivery, development and tests; CI runs the suite a second time on PostgreSQL (ADR 0022) | orch |
| O-8 | Network | Listens on `127.0.0.1:8080` only; colleagues use an SSH tunnel (ADR 0024); no outgoing connection (ADR 0027) | FR-089, FR-090, H1 |
| O-9 | Browsers | Chrome or Edge 111, Firefox 128, Safari 16.4 or newer, in every mode | spec Assumptions |
| O-10 | Reference machine | 4 cores, 8 GB memory | SC-020 |
| O-11 | CI | GitHub Actions on hosted runners; no client data in the repository or in CI (FR-099) | orch |

## 6. Security requirements (OWASP ASVS 5.0, level 2)

Target level 2 with two exemptions (ADR 0025). Each chapter has requirements or an exemption with its reason.

| ASVS chapter | Requirement | Spec |
|---|---|---|
| V1 Encoding and sanitization | All output escaped by the template layer; user data never rendered as raw HTML. All queries through parameter binding, no input concatenated into SQL. CSV export neutralises formula prefixes. | FR-084 |
| V2 Validation and business logic | Every field rule and every business rule (structure, 100%, Finalised year, last Administrator) checked on the server, also on direct requests and on import commit; concurrent requests on the last Administrator are serialised in a transaction. | FR-010, FR-014, FR-015, FR-085, FR-094 |
| V3 Web frontend security | Content security policy own origin only, plus `data:` and `blob:` image sources for the PNG export (ADR 0039), `wasm-unsafe-eval` for the Tekenen page only; nosniff, same-origin referrer policy (ADR 0046), framing refused; cookies HttpOnly and SameSite=Lax; **Exemption** 3.3.1 and 3.3.4 (`__Host-` cookie prefix, Secure): transport is loopback plus an SSH tunnel without TLS (ADR 0024), so the cookie cannot carry the Secure attribute that the prefix requires; the Secure attribute stays on by default and the README names the setting where a browser refuses it; no intermediate state or personal data in browser storage. | FR-005, FR-090, ADR 0021 |
| V4 API and web service | No public API; only the application's own Inertia routes. Every state-changing request needs a POST, PUT, PATCH or DELETE with an anti-forgery token. | FR-005 |
| V5 File handling | Size and type limits verified on content; logos re-encoded, stored under random names outside the public files, served with a fixed content type; generated file names from neutral words, slugs and dates only; no user-supplied path is used. | FR-024, FR-086, FR-088 |
| V6 Authentication | Local accounts only; passwords 12 to 128 characters, checked against a bundled list of 10,000 common passwords of 12 or more characters (filtered from the SecLists xato-net top 1,000,000, MIT licence and source in `resources/data/common-passwords.LICENSE` and `common-passwords.SOURCE`), slow salted hash; one-time passwords of 16 random characters, 24 hours; 5 failures per minute locks 60 s, same message for wrong e-mail and wrong password; optional TOTP with replay protection and 8 hashed recovery codes. **Exemption**: multi-factor authentication is optional per account, not required (ADR 0010). | FR-001 to FR-008 |
| V7 Session management | New session identifier at sign-in; 120 minutes idle timeout with a warning; 12 hours absolute lifetime (7.3.2); any number of sessions per account, documented (7.1.2); sessions end at sign-out, password change (other sessions), second factor on or off and new recovery codes (other sessions, 7.4.3), reset and deletion; an Administrator can end all sessions of another user (7.4.5); changing one's own e-mail address needs the current password (7.5.1). **Exemption** 7.1.3 (`__Host-` prefix, see V3): the session cookie carries no prefix for the same reason. **Exemption** 7.5.2: users cannot list and end their own sessions; few users, short idle timeout and the Administrator action cover it (ADR 0025, ADR 0037). | FR-005, FR-011, orch (phase 4, Q-1 to Q-5) |
| V8 Authorization | Two roles valid for every Organisation; every write route checks the Administrator role on the server; a test per write route proves the Reader is refused. Per-Canvas or per-Organisation permissions are out of version 1. | FR-009, FR-094 |
| V9 Self-contained tokens | Out of scope: no tokens are issued; sessions are server-side. | |
| V10 OAuth and OIDC | Out of scope: no outside identity provider (H1). | FR-001 |
| V11 Cryptography | Random values (one-time passwords, recovery codes, file names) from a cryptographically secure generator; TOTP secrets encrypted at rest with the application key; Snapshot checksum detects damage, not tampering by someone with file access. | FR-006, FR-079 |
| V12 Secure communication | **Exemption**: no TLS in the application; loopback and SSH tunnel carry transport security (ADR 0024). | FR-089 |
| V13 Configuration | Debug output off in the delivered configuration; application key generated at first start; `.env` never in git, `env.example` kept current; no default password (first Administrator gets a one-time password); error screens without stack trace, query or path; uploads and logs outside the public directory. | FR-089, FR-091 |
| V14 Data protection | Authenticated responses not cached by the browser; no user data in any export; data minimisation per section 7. | SD-12, pii-inventory |
| V15 Secure coding and architecture | Dependency policy below; mass assignment only through explicit field lists; the drawing library pinned and bundled with telemetry, AI, collaboration and cloud off. | K10, A8 |
| V16 Security logging and error handling | Section 8. | FR-019 to FR-023 |
| V17 WebRTC | Out of scope: collaboration over WebRTC is off (K10). | |

**Dependency policy.** Lockfiles committed; the drawing library pinned to an exact version. CI fails on `composer audit` or `npm audit --audit-level=high` findings of high or critical. Dependabot alerts are on and only report. Every update is a deliberate step with a full test run (A8). **Accepted gap**: container image tags and GitHub Action versions are not pinned by digest, because digests cannot be resolved offline; Dependabot proposals for the Dockerfile and the workflow files are the way to move to digests once a connected maintainer can review them.

**Secrets.** `.env` holds the application key; it stays out of git. The first Administrator's one-time password appears once in the container log and expires after 24 hours or at first sign-in (FR-003, FR-089).

**Verification point for WP1.** Whether Chrome, Edge, Firefox and Safari each send a Secure session cookie to `http://localhost:8080`; for a browser that does not, the README names the setting of FR-005 (ADR 0024).

## 7. Personal data: retention, deletion and processors

| Element | Retention | Deletion path |
|---|---|---|
| User name, e-mail address, role | Until the account is deleted | Administrator deletes the user (FR-011); name and e-mail replaced by "verwijderde gebruiker" everywhere |
| Password hash | Until changed or the account is deleted | Overwritten on change; row deleted with the account |
| Second-factor key, recovery code hashes | While turned on, or while an enrolment is pending; a pending secret is discarded at the next sign-in or sign-out. Until turned off, reset, used (codes), regenerated, or account deleted | Removed by the user, by an Administrator's reset, by the console command (FR-008), or with the account |
| Actor reference in Audit log, "Definitief door", "Gemaakt door" | As long as the record | Pseudonymised at user deletion; removed with its Canvas or Organisation |
| Session record and session payload (encrypted at rest; holds the user reference, sign-in time and flashed one-time passwords or recovery codes) | 120 minutes idle or sign-out | R-6 |
| Throttle key (per hashed e-mail address at sign-in; per user identifier for password change, re-authentication, second-factor password and the second-factor challenge) | Expires after 60 seconds; the file is removed on the next read or on a cache clear | R-7 |
| Undo record (user identifier and deleted rows) | Usable for 10 minutes; removed at the next deletion, undo or sign-in by any user, and the user's own records at restore and at sign-out | ADR 0033 |
| User identifier in the application error log | 14 days | R-5 |
| First Administrator e-mail address in the container log | As long as the operator keeps the container log | R-25 |
| Account state columns (one-time password expiry, must-change flag, second-factor times) | Until the account is deleted | Row deleted with the account |
| User identifiers inside Audit values | As long as the Audit entry | The numeric identifier stays and no longer resolves to a person in the application; the security log keeps it for 90 days |
| Session reference in the security log (random value, not derived from the browser, linkable to a person only through the log itself) | 90 days | R-4 |
| User identifier in the security log | 90 days | R-4 |
| Personal data in backups | The product owner's | R-9 |

**Processors: none.** The application runs on one machine and connects to nothing (ADR 0027). The framework's own default log channels and mailers (Slack, Papertrail, SMTP, SES, Postmark, Resend, sendmail) are removed from the configuration at boot, because the framework merges its defaults back into a config file that lacks them; an architecture test fails if any of them returns. The drawing library runs in the browser and sends nothing out. GitHub and its CI hold code and synthetic test data only, never client data or user accounts (FR-099). Full detail: `specs/001-omc-studio-v1/pii-inventory.md`.

## 8. Logging, monitoring and incident readiness (NIS2)

**What is logged**

| Log | Where | Content | Retention |
|---|---|---|---|
| Audit log | Database | Every change to records, with actor and old and new values (FR-019) | R-1 |
| Security log | File in application storage, readable only by the machine's operator | Sign-in success and failure, lockout, sign-out, password and second-factor events, role changes, user creation and deletion, refused access, imports, full exports; time, event, user identifier (FR-022) | 90 days |
| System notices | Database, read-only list for Administrators | Failed closing checks, damaged Snapshots, repeated lockouts (FR-023, SD-41) | R-2 |
| Application error log | File in application storage | Unhandled exceptions: class, message, stack trace, route name, user identifier | 14 days |

**What is deliberately not logged**: network addresses, browser identification, e-mail addresses (also those typed at a failed sign-in), names, typed input, request bodies, query bindings, passwords, password hashes, codes, second-factor keys, recovery codes, Cost record amounts outside the Audit log. No web server access log is written. Nothing leaves the machine.

**Detection**: an incident shows itself as a System notice, which puts a warning in the top bar of every Administrator on every screen until it is marked "Gezien":
- a closing check that fails (figures do not close to the cent);
- a Snapshot whose checksum fails;
- 10 sign-in or second-factor lockouts within 60 minutes across the application (SD-41); a lone forgotten password causes one or two, a guessing attack many more. One notice per occurrence, updated with count and last time while not "Gezien".

**Notification**: the warned Administrator informs the product owner; the product owner starts the ISO notification policy of their organisation, which sets the windows, the parties to notify and the channels (GATE-U2). The application names no window or contact of its own.

**Reconstruction**: the Audit log answers what changed and when, and who changed it as long as that person's account exists; the security log answers who signed in and was refused when; backups restore the state of a date (ADR 0026). The limits, in plain sentences:
- The actions of a deleted user can no longer be attributed to that person in the Audit log: `actor_id` becomes null, and several deleted users cannot be told apart. The security log keeps the numeric user identifier for 90 days.
- The hard deletion of a Canvas or an Organisation keeps only the deletion entry (FR-021, AuditRemoval); its earlier state and history are in backups only.
- Concurrent sessions of one account are separable through the session reference in the security log (SD-20 allows a random reference).
- "From where" a session came is not recorded by the application (SD-20); it exists only in the host's sshd log of the SSH tunnel (ADR 0024), which shows which connection was open at that time.
- Slow guessing below the lockout limit raises no notice; it is visible only as `sign_in_failed` lines in the security log.

## 9. ADRs that close off design options

| ADR | Closes off |
|---|---|
| 0012 | A separate database server in delivery; React |
| 0022 | Developing or testing on another engine than the one delivered |
| 0023 | Redis, mail, queue workers, schedulers, any second service |
| 0024 | TLS termination in the stack; listening beyond loopback |
| 0025 | Security requirements below ASVS level 2 without a recorded exemption |
| 0026 | Automatic backups; an availability target |
| 0027 | CDN, outside fonts, telemetry, hosted error tracking, e-mail, outside identity providers |
