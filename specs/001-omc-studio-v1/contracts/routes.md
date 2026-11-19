# Contract: HTTP routes

The application's only interface is its own Inertia routes (V4): no public API. Paths are English (house convention 1). Every state-changing route uses POST, PUT, PATCH or DELETE with the anti-forgery token (FR-005). Role column: **A** Administrator only (route group `can:administer`, refused with 403 for a Reader and logged in the security log), **R** any signed-in user, **G** guest. The Reader allowlist of FR-009 is exactly the routes marked **R** that change state. A test enumerates the router and fails when a state-changing route is neither in the **A** group nor on that allowlist (R-12).

Responses: Inertia pages for GET; for writes, a redirect back with flash data, or validation errors keyed by field with messages from `lang/nl.json` (WB5). A refused business rule is a validation error on the key `rule`, so Inertia shows it on the page; the Tekenen save, sent outside an Inertia visit, gets the same errors as JSON with status 422, and a stale version as status 409 (DT). Writes under an archived Organisation or Canvas, and writes a Template may not take, are refused by the service guard (R-27). Nested resources use scoped bindings: an Element, goal or set of another Canvas in the path is a 404. Links into and out of Tekenen are full page loads (R-19).

## Delivery 1

| Method and path | Role | Purpose | Spec |
|---|---|---|---|
| GET /sign-in, POST /sign-in | G | Sign in; throttled per e-mail hash | US1, FR-004 |
| GET /sign-in/second-factor, POST /sign-in/second-factor | G (pending) | Code or recovery code | US2 |
| POST /sign-out | R | End session | US1 |
| GET /password/new, PUT /password/new | R (must change) | Replace one-time password; middleware blocks every other route until done | FR-003 |
| GET /account | R | Mijn account | US1, US2 |
| PUT /account/password | R | Change own password; ends other sessions | US1.10 |
| POST /account/second-factor | R | Start enabling (password confirmed) | US2.1 |
| POST /account/second-factor/confirm | R | Confirm with a code, show recovery codes once | US2.2 |
| POST /account/second-factor/recovery-codes | R | Regenerate | US2.7 |
| DELETE /account/second-factor | R | Turn off | US2.8 |
| POST /session/keep-alive | R | "Sessie verlengen" | US1.9 |
| POST /session/reauthenticate | R | Confirm the password (and the code) within the last 10 minutes of the 12 hour lifetime, starting a new period | US1.9a, ADR 0037 |
| GET /overview | R | Organisations, Canvases, Templates (A only) | US8, US9 |
| GET /admin | A | Beheer index | US3.3 |
| GET /admin/users; POST; PATCH /admin/users/{user}; DELETE | A | Users list; on one's own row a changed e-mail address needs `current_password` | US5, US5.10 |
| POST /admin/users/{user}/password-reset | A | New one-time password | US5.3 |
| DELETE /admin/users/{user}/second-factor | A | Reset another user's factor | US2.9 |
| DELETE /admin/users/{user}/sessions | A | "Sessies beëindigen" for another user; refused on one's own row | US5.9 |
| GET /admin/organisations; POST; PATCH /{organisation}; DELETE | A | Organisations list | US6 |
| POST /admin/organisations/{organisation}/archive, /restore | A | | US6.6 |
| GET /organisations/{organisation}/logo | R | Logo with fixed content type | FR-024 |
| GET /admin/audit-log | A | Logboek with filters | US7 |
| POST /canvases | A | New from Template, copy, or save as Template (`source` field) | US8, US9 |
| PATCH /canvases/{canvas} | A | Rename | US8.5a |
| POST /canvases/{canvas}/archive, /restore | A | | US8.6 |
| DELETE /canvases/{canvas} | A | Requires the typed name | US8.7 |
| GET /canvases/{canvas} | R | Redirects to the default mode for the role | US3.5 |
| GET /canvases/{canvas}/entry | A | Invoeren; Reader or archived Canvas is redirected to view | US11 |
| GET /canvases/{canvas}/view | R | Bekijken | US16 |
| POST /canvases/{canvas}/elements | A | Create Domain, Cluster or Activity | US10, US11 |
| PATCH /canvases/{canvas}/elements/{element} | A | Fields and "Hangt onder" (move, through the same move service as the Shift move) | US11.6, US11.8 |
| POST /canvases/{canvas}/elements/{element}/reorder | A | `direction`: up or down | US11.5 |
| POST /canvases/{canvas}/elements/{element}/archive, /restore | A | | FR-040a, US10.15 |
| DELETE /canvases/{canvas}/elements/{element} | A | With `cluster_children`: delete or lift | US10.11 to 13 |
| POST /canvases/{canvas}/quick-entry/preview | A | Dry-run parse, no write | US12 |
| POST /canvases/{canvas}/quick-entry | A | Parse again and create all or nothing | US12 |
| PUT /canvases/{canvas}/statements/{kind} | A | Mission or vision | US13 |
| POST /canvases/{canvas}/goals; PATCH /{goal}; POST /{goal}/reorder; DELETE | A | Strategic goals | US13 |
| PATCH /templates/{canvas}; DELETE /templates/{canvas} | A | Rename and delete a Template; refused for "Leeg canvas" | US9 |
| POST /undo/{token} | A | Restore the deletion held under the token | FR-017 |

## Delivery 2

| Method and path | Role | Purpose | Spec |
|---|---|---|---|
| GET /organisations/{organisation}/cost-records | A | Kostenposten for an Organisation | US18, FR-053 |
| GET /canvases/{canvas}/cost-records | A | Kostenposten from a Canvas, with status and cards | US18.4 |
| POST /organisations/{organisation}/cost-records; PATCH /cost-records/{costRecord}; DELETE | A | Cost records | US18 |
| POST /organisations/{organisation}/cost-records/bulk-delete | A | `ids` of the selection; deletes what every rule allows, reports the skipped rows with their reason, one undo token for the whole action | US4.9, FR-017 |
| POST /cost-records/{costRecord}/archive, /restore | A | | US18.13 |
| PUT /cost-records/{costRecord}/years/{year}; DELETE | A | Yearly figures | US18.7 to 12 |
| POST /organisations/{organisation}/fiscal-years; DELETE /fiscal-years/{fiscalYear} | A | | US18.1, US18.2 |
| GET, POST, PATCH, DELETE /admin/organisations/{organisation}/owners[/{owner}] | A | With `replace_with` on delete (numeric id) | US17 |
| GET, POST, PATCH, DELETE /admin/organisations/{organisation}/applications[/{application}] | A | With `replace_with` on delete: Owners take a numeric id, Applications take a numeric id or the sentinel `none` | US17 |
| GET /organisations/{organisation}/cost-records/import | A | Import page | US19 |
| POST /organisations/{organisation}/cost-records/import/preview, /import | A | CSV dry run and commit | US19 |
| GET /admin/owners; GET /admin/applications | A | Owners and Applications lists with an Organisation chooser | US17 |
| GET /canvases/{canvas}/allocation | A | Toewijzen | US20 |
| PUT /canvases/{canvas}/years/{year}/cost-records/{costRecord}/allocation | A | Replace the whole set | FR-057 |
| DELETE /canvases/{canvas}/years/{year}/cost-records/{costRecord}/allocation | A | "Set leegmaken" | US20.9 |
| GET /canvases/{canvas}/elements/{element}/redistribution | A | Affected years | US22 |
| POST /canvases/{canvas}/elements/{element}/redistribution | A | Apply choices and archive, one step | FR-069 |
| POST /canvases/{canvas}/years/{year}/carry-over | A | Carry over with report | US23 |
| POST /canvases/{canvas}/years/{year}/finalise, /reopen | A | | US24 |
| GET /admin/system-notices; POST /admin/system-notices/{notice}/seen | A | | US34 |

Bekijken (`GET /canvases/{canvas}/view`) gains the query parameters `overlay`, `cost_kind`, `owner`, `application` in D2; every page takes `year` (R-14).

## Delivery DT

| Method and path | Role | Purpose | Spec |
|---|---|---|---|
| GET /canvases/{canvas}/drawing | A | Tekenen; Reader, archived Canvas or Snapshot redirected to view, Template to entry | US35.15 |
| PUT /canvases/{canvas}/layout | A | Save the whole Layout; see `layout-payload.md` | FR-103 |
| POST /canvases/{canvas}/layout/reset | A | Back to automatic: `scope` element, domain or canvas | FR-107 |
| POST /canvases/{canvas}/drawing/moves | A | Confirmed Shift move or "Verplaatsen naar": `element_id`, `target_id` (a Domain, or for an Activity also a Cluster; in a list Area a Domain, shown as Groep), `structure_version`, `layout_version`, `layout` (the block's place, size and style relative to the target; null on an undo back to Automatic layout), optional `position` (sent by undo); the move rules of the Element move plus the Layout checks; parent, order and Layout in one transaction; returns both versions | FR-101a, ADR 0036 |

## Delivery 3

| Method and path | Role | Purpose | Spec |
|---|---|---|---|
| GET /canvases/{canvas}/versions; POST | R list, A create | Versies, take a Snapshot | US31 |
| GET /snapshots/{snapshot}/view | R | Bekijken on a Snapshot | US31.4 |
| PATCH /snapshots/{snapshot}; POST /archive, /restore; DELETE | A | Name, remark, archive only | FR-080 |
| GET /canvases/{canvas}/export/json | R | Canvas JSON | US27 |
| POST /imports/canvas/preview, /imports/canvas | A | Canvas JSON dry run and commit | US27 |
| GET /admin/exchange; GET /admin/export/full; POST /admin/import/full/preview, /admin/import/full | A | Full export and import | US28 |
| GET /organisations/{organisation}/export/cost-records.csv | R | | US29.1 |
| GET /canvases/{canvas}/export/allocations.csv | R | | US29.2 |
| POST /canvases/{canvas}/import/allocations/preview, /import/allocations | A | | US29.3 |
| POST /canvases/{canvas}/import/structure/preview, /import/structure | A | | US29.4 |
| POST /admin/sample-data/remove | A | | US32.2 |

PNG and print run in the browser on the Bekijken view (R-20); they need no route.

## Console

| Command | Purpose | Spec |
|---|---|---|
| `php artisan app:install` | Idempotent first start | FR-089, R-25 |
| `php artisan app:reset-admin-password {email} {--second-factor}` | Last-resort reset | FR-008 |
| `php artisan app:backup`, `app:restore {folder}` (wrapped by `make backup`, `make restore`) | D3 | FR-092 |
