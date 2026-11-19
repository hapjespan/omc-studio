# Data model: OMC Studio version 1

Date: 2026-11-18, phase 4. Engine: SQLite, with the same migrations run on PostgreSQL in CI (ADR 0022). Amounts are whole cents, percentages whole basis points (ADR 0005). Every table carries the index its access pattern needs (house convention 2); the "Serves" column names the query.

Conventions: `*_normalised` holds the trimmed, lower-cased value used for "unique ignoring case and surrounding spaces"; the visible value keeps the user's casing. `archived_at` null means active. Foreign keys are named per column; "cascade", "restrict" and "no action" are the delete rules; "no action" is used where a cascade removes both sides in one statement. Rules that need a lock are serialised per ADR 0032.

Each table is created in the delivery that first needs it; no table or column exists ahead of its delivery (constitution V).

## Delivery 1

### users

| Column | Type | Rule |
|---|---|---|
| id | integer pk | |
| name | string(150) | required |
| email | string(254) | stored lower-cased, unique |
| password | string | bcrypt hash |
| role | string | `administrator` or `reader` |
| must_change_password | boolean | true after a one-time password |
| one_time_password_expires_at | datetime null | 24 hours after creation or reset (FR-003) |
| two_factor_secret | text null | encrypted with the application key (V11) |
| two_factor_confirmed_at | datetime null | factor active when set |
| two_factor_last_time_step | integer null | replay protection (FR-006) |
| created_at, updated_at | datetime | |

| Index | Serves |
|---|---|
| unique(email) | sign-in lookup, uniqueness |
| index(role) | count and lock Administrators for the last-Administrator rule |

### two_factor_recovery_codes

| Column | Type | Rule |
|---|---|---|
| id | integer pk | |
| user_id | fk users cascade | |
| code_hash | string | bcrypt; row deleted when used |

| Index | Serves |
|---|---|
| index(user_id) | check a code, count remaining ("Nog 7 herstelcodes") |

### sessions, cache, cache_locks (Laravel)

Laravel's `sessions` table without the `ip_address` and `user_agent` columns (R-28): `id` primary key, `user_id` (index, to end a user's sessions), `payload` (holds the sign-in time for the 12-hour limit, ADR 0037), `last_activity` (index, for expiry). Throttle keys live in the file cache (R-7, R-9), so the skeleton's `cache` and `cache_locks` tables are removed together with `jobs`, `job_batches`, `failed_jobs` and `password_reset_tokens`: no queue (ADR 0023) and no e-mail reset (SD-21).

### undo_records

`token` (string pk, random), `user_id` (fk users cascade), `kind`, `payload` (json: the deleted rows with identifiers and dependents), `expires_at`. Index: (user_id), (expires_at) for clean-up during requests (ADR 0033).

### areas (reference data)

| Column | Type | Rule |
|---|---|---|
| id | integer pk | |
| key | string | `direction`, `steering`, `governing`, `actors`, `channels`, `value_propositions`, `primary_activities`, `supporting_activities` |
| kind | string | `text`, `activities` or `list` |
| position | integer | fixed order |
| carries_costs | boolean | true for the four activity Areas |

Screen and level labels live in `lang/nl.json` under `areas.<key>.*` (ADR 0031). No route writes this table (FR-032).

| Index | Serves |
|---|---|
| unique(key) | lookup by key (CSV structure import, seeding) |
| index(position) | ordered listing |

### modalities (reference data)

`id`, `key` (`common`, `adaptive`, `specialized`, `distinct`), `letter`, `colour` (`#RRGGBB`), `position`. Index: unique(key), index(position). No route writes it (FR-043).

### organisations

| Column | Type | Rule |
|---|---|---|
| id | integer pk | |
| name, name_normalised | string(150) | unique on normalised |
| slug | string | lowercase letters, digits, hyphens; unique (FR-024) |
| logo_path | string null | random name under `storage/app/private/logos` |
| archived_at | datetime null | |
| created_at, updated_at | datetime | |

| Index | Serves |
|---|---|
| unique(name_normalised) | uniqueness |
| unique(slug) | file names, uniqueness |
| index(archived_at) | overview hides archived |

### canvases

A Template is a canvas row with `kind = template` and no Organisation (FR-030).

| Column | Type | Rule |
|---|---|---|
| id | integer pk | |
| organisation_id | fk organisations restrict, null | null for a Template |
| kind | string | `canvas` or `template` |
| is_blank_template | boolean | true only for "Leeg canvas"; protected |
| name, name_normalised | string(150) | |
| scope_key | string | `organisation:<id>` or `template`; unique with the name |
| archived_at | datetime null | |
| created_at, updated_at | datetime | updated_at shows on the card |

| Index | Serves |
|---|---|
| unique(scope_key, name_normalised) | canvas names unique per Organisation, Template names unique among Templates (NULL-safe on both engines) |
| index(organisation_id, archived_at) | overview per Organisation |
| index(kind) | Templates section |

### statements

`id`, `canvas_id` (fk cascade), `kind` (`mission`, `vision`, `goal`), `title` (string 150, null except goals), `body` (text up to 2000), `position`. At most one mission and one vision per Canvas, checked in the service (FR-042). Index: (canvas_id, kind, position) for the Direction band and goal order.

### elements

| Column | Type | Rule |
|---|---|---|
| id | uuid pk | stable across moves and export (R-24) |
| canvas_id | fk canvases cascade | |
| area_id | fk areas restrict | |
| parent_id | fk elements no action, null | null for a Domain; the structure service deletes a subtree model by model, children first, and only without lines in it (FR-038), so every Element gets its Audit log entry (R-11) |
| kind | string | `domain`, `cluster`, `activity` |
| name, name_normalised | string(150) | no `>` (FR-035) |
| description | text null | up to 2000 |
| modality_id | fk modalities restrict, null | activity Areas only |
| rationale | text null | only with a Modality |
| icon | string null | list Areas only, from the fixed icon list |
| position | integer | order among siblings of the same kind |
| is_unknown_allocation | boolean | one per Canvas (FR-043a) |
| archived_at | datetime null | |
| created_at, updated_at | datetime | every change touches the Canvas's `updated_at`, which the card shows (US8.1) |

| Index | Serves |
|---|---|
| index(canvas_id, parent_id, position) | tree per parent, reorder (kickoff 9) |
| index(canvas_id, area_id, parent_id, name_normalised) | name uniqueness under a parent, Domains by Area |
| index(canvas_id, is_unknown_allocation) | find the Unknown allocation Domain |
| index(canvas_id, archived_at) | "Toon gearchiveerd", active tree |

Uniqueness of active names under one parent and the single Unknown allocation Domain are checked in the structure service inside a serialised transaction, because a partial unique index over a nullable parent is not portable between the two engines (R-9).

### audit_entries

| Column | Type | Rule |
|---|---|---|
| id | integer pk | |
| occurred_at | datetime | |
| actor_type | string | `user` or `system` (first start, install) |
| actor_id | fk users set null, null | with `actor_type = user`, null shows "verwijderde gebruiker" (FR-011) |
| action | string | `created`, `changed`, `deleted`, `archived`, `restored`, `undone`, `finalised`, `reopened`, `redistributed`, `carried_over`, `imported`, event names for secrets |
| subject_type | string | English kind name |
| subject_id | string | |
| subject_label | string(150) | name at the time |
| organisation_id | integer null | no foreign key: entries outlive nothing but are removed by the deletion service (FR-021) |
| canvas_id | integer null | same |
| changes | json | `{field: [old, new]}`; never secrets (FR-020) |

| Index | Serves |
|---|---|
| index(occurred_at) | Logboek newest first |
| index(canvas_id, occurred_at) | filter by Canvas; removal with the Canvas |
| index(organisation_id, occurred_at) | filter by Organisation; removal with the Organisation |
| index(actor_id) | filter by user; pseudonymisation at user deletion |
| index(subject_type, occurred_at) | filter by kind |
| index(subject_type, subject_id) | rewrite entries about a deleted user (FR-011); merge Layout entries within 60 seconds (FR-113) |

## Delivery 2

### fiscal_years (ADR 0011)

`id`, `organisation_id` (fk cascade), `year` (integer 2000 to 2100). Index: unique(organisation_id, year). Deletion is checked explicitly by the service ("Dit boekjaar heeft nog jaarwaarden"); the `restrict` keys from `yearly_figures`, `allocation_sets` and `finalised_years` are a safety net only.

### owners, applications

`id`, `organisation_id` (fk cascade), `name`, `name_normalised`; `applications` adds `supplier` (string 150, null). Index: unique(organisation_id, name_normalised) on each.

### cost_records

| Column | Type | Rule |
|---|---|---|
| id | uuid pk | |
| organisation_id | fk organisations restrict | |
| code, code_normalised | string(50) null | unique per Organisation when filled |
| name | string(150) | required |
| cost_kind | string | `supplier_contract`, `personnel`, `project`, `other` |
| supplier | string(150) null | free text (ADR 0009) |
| owner_id | fk owners restrict | required (SD-7) |
| cost_centre | string(50) null | |
| application_id | fk applications restrict, null | |
| description | text null | |
| archived_at | datetime null | |
| created_at, updated_at | datetime | |

| Index | Serves |
|---|---|
| unique(organisation_id, code_normalised) | code uniqueness; NULLs are distinct on both engines, so empty codes repeat freely |
| index(organisation_id, archived_at) | table per Organisation |
| index(owner_id), index(application_id) | "Vervangen door", filters |

### yearly_figures

| Column | Type | Rule |
|---|---|---|
| id | integer pk | |
| cost_record_id | fk cost_records no action | the service deletes Yearly figures model by model before the Cost record (R-11) |
| fiscal_year_id | fk fiscal_years restrict | |
| budget_cents | bigint | 0 to 99,999,999,999 |
| forecast_cents | bigint null | same range; null counts as Budget (SD-5) |
| run_bp, change_bp | integer null | 0 to 10000 each, sum at most 10000 |
| fte_hundredths | integer null | 0 to 999,999 |
| reference_date | date null | |
| remark | text null | |

| Index | Serves |
|---|---|
| unique(cost_record_id, fiscal_year_id) | one set of figures per year |
| index(fiscal_year_id) | included Cost records of a year (FR-060); Fiscal year deletion check |

### allocation_sets, allocation_lines

| allocation_sets | Rule |
|---|---|
| id | integer pk |
| canvas_id | fk canvases cascade |
| cost_record_id | fk cost_records no action, deleted by the service first (R-11) |
| fiscal_year_id | fk fiscal_years restrict |
| created_at, updated_at | |

| allocation_lines | Rule |
|---|---|
| id | integer pk |
| allocation_set_id | fk allocation_sets cascade |
| element_id | fk elements no action: deleting an Element that still carries a line fails on both engines; a Canvas deletion removes lines first, model by model (ADR 0041), because PostgreSQL fires the Canvas cascades in constraint creation order |
| basis_points | integer 1 to 10000 |
| position | integer, set order (SD-6) |

| Index | Serves |
|---|---|
| unique(canvas_id, fiscal_year_id, cost_record_id) on sets | one set per Canvas, year and Cost record; loading all sets of a Canvas and year |
| index(cost_record_id) on sets | deleting a Cost record or its Yearly figures |
| unique(allocation_set_id, element_id) on lines | no Target twice (FR-054) |
| index(element_id) on lines | "subtree carries lines" (FR-038), Redistribution |

The sum of 10000 is enforced by the allocation service, which replaces the whole set in one transaction (FR-057); no route writes a single line.

### finalised_years

`id`, `canvas_id` (fk cascade), `fiscal_year_id` (fk restrict), `finalised_at`, `finalised_by` (fk users set null). Index: unique(canvas_id, fiscal_year_id); index(fiscal_year_id) for "Finalised in any Canvas of the Organisation" (FR-051).

### system_notices

| Column | Type | Rule |
|---|---|---|
| id | integer pk | |
| kind | string | `closing_check_failed`, `snapshot_damaged`, `repeated_lockouts` |
| fingerprint | string | the fact: kind plus Canvas, year, filter and check, or Snapshot; `repeated_lockouts` for lockouts |
| canvas_id | fk canvases cascade, null | removed with the Canvas (R-2) |
| details | json | year, filter, check, difference in cents, Snapshot name, or count; no personal data |
| occurrences | integer | |
| first_occurred_at, last_occurred_at | datetime | |
| seen_at | datetime null | |
| seen_by | fk users set null, null | |

| Index | Serves |
|---|---|
| index(fingerprint, seen_at) | one notice per fact while not seen (FR-023) |
| index(seen_at) | top-bar warning on every screen |
| index(kind, last_occurred_at) | list and filters |
| index(canvas_id) | filter by Canvas, removal with the Canvas |

Lockout counting for SD-41 (still a proposal) uses the file cache (R-9): a list of lockout times kept for 60 minutes under one key, no user identifier.

## Delivery DT (Tekenen)

### layouts

| Column | Type | Rule |
|---|---|---|
| id | integer pk | |
| canvas_id | fk canvases cascade | |
| area_id | fk areas restrict, null | set for an Area's Layout (not Direction) |
| element_id | fk elements cascade, null | set for an Element's Layout; exactly one of the two |
| x, y, width, height | integer | whole pixels relative to the parent; height is a minimum (FR-102) |
| z | integer | order in front or behind among siblings |
| style | json | only the keys of FR-104 |
| format_version | integer | upgraded by small functions when read |
| updated_at | datetime | |

| Index | Serves |
|---|---|
| unique(element_id) | one Layout per Element |
| unique(canvas_id, area_id) | one Layout per Area and Canvas; NULL area for Element rows is distinct on both engines |
| index(canvas_id) | load and replace a Canvas's whole Layout |

A row exists only for a Manual layout; no row means Automatic layout (R-6).

### canvases (columns added in DT)

`structure_version` (integer, default 0, raised by every create, delete, archive, restore, move and reorder of an Element), `layout_version` (integer, default 0, raised by every write or removal of `layouts` rows) and `layout_saved_at` (datetime null, the last successful Tekenen save, for the "nieuw" pill). The versions are read and raised inside the serialised transaction that changes them (FR-103).

Invariant checked by the server on every save: an Element's stored x plus width lies within its parent's width, where the parent's width is its stored width when it has a row and its automatic width otherwise (`AutomaticWidths`, R-5).

## Delivery 3

### snapshots

| Column | Type | Rule |
|---|---|---|
| id | integer pk | |
| canvas_id | fk canvases cascade | |
| name | string(150) | editable |
| fiscal_year | integer | |
| remark | text null | editable |
| archived_at | datetime null | editable |
| created_by | fk users set null, null | "Gemaakt door" |
| created_at | datetime | |
| imported | boolean | marked "Geïmporteerd" (FR-083) |
| schema_version | integer | |
| content | text (json) | immutable; no route updates it |
| checksum | char(64) | SHA-256 of `content`, verified on every read (FR-079) |

Index: (canvas_id, archived_at, created_at) for "Versies".

### organisations (column added in D3)

`is_sample` boolean, default false: the "Voorbeelddata" badge and its removal (US3.1, US32).

### application_state (D3)

`key` (string pk), `value` (string). One row `sample_data_removed` so a restart never brings the sample data back (US32). The primary key is its index.

## State transitions

| Record | States | Transitions and guards |
|---|---|---|
| User | must change password, active | one-time password → active after a new password; reset returns to "must change" and ends sessions |
| Second factor | off, pending, on | on only after a valid code; off by the user (password), by an Administrator reset, or by the console command |
| Organisation, Canvas, Cost record, Element, Snapshot | active, archived | archive and restore; Element archive only without lines in its subtree (FR-040a), else Redistribution |
| Allocation set | absent (Unknown allocation), complete (10000) | replaced whole or removed; refused while its year is Finalised in that Canvas |
| Fiscal year in a Canvas | open, Finalised | Finalise when no Cost record of the year lacks a set in the Canvas (GATE-U1, confirmed) and the closing check passes; Reopen by an Administrator |
| System notice | new, seen | "Gezien" only |
| Element Layout (DT) | automatic (no row), manual (row) | manual on any Tekenen change; back to automatic per Element, Domain or Canvas; removed on a move through "Hangt onder", archive and delete; after a Shift move the block keeps its Layout at the released place (ADR 0036); restored by undo |
| Element parent | under one Domain or Cluster | changed by "Hangt onder" (Invoeren) or a confirmed Shift move of a Cluster, Activity or Item (Tekenen, ADR 0036), both through `ElementTree::move` with the same rules, same kind of Area only |

## Calculation input and output (FR-059)

Input, all plain value objects built by the controller from plain queries: the Canvas's active Elements (identifier, parent, Area, kind, Modality, Unknown allocation flag; archived Elements carry no lines, FR-039, and a line on an Element missing from the input counts nowhere, so the closing check fails and reports it); the included Cost records with Yearly figures (FR-060); the sets of the Canvas and year with ordered lines. Output: per Element Direct and Total amounts for Budget, Forecast, Run, Change and Undetermined; Area Totals; Canvas total; Shares as basis points of the Canvas total; breakdowns per Cost kind, Modality (with "none" and Unknown allocation) and per Element per Cost record; FTE numerators; and the closing check result with each failed check and its difference in cents. See `contracts/calculation.md`.
