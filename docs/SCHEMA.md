# PFA Sample Testing & Tracking — Schema (Phase 1)

Database schema and domain model for the Punjab Food Authority sample testing and
chain-of-custody tracking system, implementing the legal sampling SOP under the
Punjab Food Authority Act 2011.

## Conventions

- **Primary keys:** ULIDs (`char(26)`) on every domain table; foreign keys use
  `foreignUlid`. The `users` table also uses a ULID PK so all `*_id` FKs align.
- **Enums:** stored as plain `string` columns; the allowed values are defined by
  PHP 8.2 backed enums in [`app/Enums/`](../app/Enums). They are intentionally
  **not** MySQL `ENUM` columns so new states can be added without migrations.
- **Timestamps:** every table has `created_at` / `updated_at`.
- **Soft deletes:** only `sampling_events`.
- **Portability:** targets MariaDB / MySQL on shared hosting (Hostinger). No
  PostgreSQL-only features, no long-running processes.

## Core Domain Rules

- **Rule of Three** — every `sampling_event` produces exactly three `sample_parts`
  (`LAB`, `REFERENCE`, `FBO_COPY`), enforced by a unique `(sampling_event_id, role)`
  index. `finalized_at` is set only once all three exist.
- **Blind testing** — lab analysts work against `sample_parts.blind_code`, never the
  business identity. Only the `LAB` part (and an activated `REFERENCE`) has a blind code.
- **Append-only custody** — every movement/status change is an immutable
  `custody_events` row. The current status is denormalized onto `sample_parts.status`
  for fast queries. The `CustodyEvent` model throws on update/delete.
- **Dispute / resampling** — on an `UNFIT` verdict the FBO may file a `dispute` within
  `dispute_window_days`; the `REFERENCE` part is then activated for retest.

## Tables

| Table | Purpose | Notable columns |
|-------|---------|-----------------|
| `users` | Staff accounts (interim, pending PFA staff DB) | `role`*, `phone`, `cnic` (unique), `is_active` |
| `premises` | Food businesses (local cache of PFA registered-business DB) | `license_no`* (unique), `city`, `source` (`MANUAL`/`PFA_DB`) |
| `sampling_events` | A legal sampling event | `event_code`* (unique), `premises_id`, `fso_id`, `is_perishable`, `witness_*`, `collected_at`, `finalized_at`; soft deletes |
| `rapid_tests` | On-site rapid screening | `sampling_event_id` (nullable), `premises_id`, `device`, `reading`, `passed` |
| `sample_parts` | One of the three physical parts | `role`, `qr_token`* (unique), `blind_code`* (nullable, unique), `seal_number`, `status`*; unique `(sampling_event_id, role)` |
| `custody_events` | **Append-only** chain of custody | `sample_part_id`, `status`, `actor_id` (nullable), `lat`/`lng`, `temperature_c`, `notes`; indexed `created_at` |
| `lab_results` | Analytical result per part | `sample_part_id` (unique), `lab_section`, `analyst_id`, `verified_by_id`, `parameters` (json), `verdict`, `verdict_at` |
| `disputes` | FBO dispute against a verdict | `sampling_event_id`, `filed_by_*`, `status`*, `filed_at`, `decided_by_id`, `retest_lab_result_id` |
| `test_catalog` | Test templates per food category | `food_category`*, `lab_section`, `test_name`, `parameters` (json), `tat_hours` |
| `settings` | Key-value app settings | `key` (unique), `value` |
| `sequence_counters` | Transaction-safe named counters | `key` (PK), `value` — backs the event-code generator |
| `personal_access_tokens` | Sanctum API tokens (Phase 2) | ULID `tokenable` morph to `users`; `abilities` |
| `sop_violations` | SOP deviations (Phase 3) | `sample_part_id`, `type`*, `details` (json), `detected_at`, `resolved_at`, `resolved_by_id`, `resolution_notes` |

`*` = indexed.

> **Phase 2 additions.** `sampling_events` gained a nullable `stale_flagged_at`
> timestamp (set by `sampling:prune-drafts` to flag abandoned drafts, never
> delete), and `personal_access_tokens` was added for Sanctum with a ULID
> `tokenable` key to match the ULID `users` PK.

> **Phase 3 additions.** `sampling_events.food_category` (set by the FSO; drives
> lab-section routing via `test_catalog`), `lab_results.lab_result_revisions` (json
> archive of superseded analyst submissions), and the `sop_violations` table.

## SOP Violations

Deviations are **recorded, not blocking** — the sample still moves so the lab work is
not lost, and an admin resolves the flag later.

- **`SAME_DAY_TRANSFER`** — the sample reached registration after its collection date,
  or after `same_day_transfer_deadline` on the collection date.
- **`COLD_CHAIN_BREACH`** — a perishable sample was scanned into `IN_TRANSIT` or
  `RECEIVED_REGISTRATION` with a temperature outside
  `cold_chain_min_c`..`cold_chain_max_c`. (A *missing* temperature is still a hard
  block — see the cold-chain guard.)
- **`OTHER`**

## The Blind Wall

From `BLIND_CODED` onwards a LAB part is addressed by its `blind_code`
(`BC-{YYYY}-{6-digit}`, allocated from the same `sequence_counters` mechanism as
event codes). Lab analysts are served exclusively by
`App\Http\Resources\BlindSamplePartResource`, an allow-list that never reveals the
premises, licence, brand, witness, FSO, event code, QR token, or part id. Analyst
roles are additionally barred from every de-blinded endpoint (including
`custody/parts/{qr_token}` and the report PDF). See `tests/Feature/BlindWallTest.php`.

## Event Codes

`sampling_events.event_code` has the form **`PFA-{DISTRICT}-{YYYY}-{6-digit sequence}`**
(e.g. `PFA-LHR-2026-000123`). Codes are allocated by
[`App\Services\EventCodeGenerator`](../app/Services/EventCodeGenerator.php) using a
row-locked counter in `sequence_counters` (scoped per district + year) inside a DB
transaction — never `MAX(id)+1`.

## Enums

All in [`app/Enums/`](../app/Enums), stored as strings:

- **PartRole:** `LAB`, `REFERENCE`, `FBO_COPY`
- **PartStatus:** `COLLECTED`, `SEALED`, `IN_TRANSIT`, `RECEIVED_REGISTRATION`,
  `BLIND_CODED`, `ASSIGNED_TO_SECTION`, `TESTING`, `RESULT_ENTERED`, `VERIFIED`,
  `REPORT_ISSUED`, `IN_RETENTION`, `RELEASED_TO_FBO`, `ACTIVATED_FOR_RETEST`,
  `REJECTED`, `DESTROYED`
- **Verdict:** `FIT`, `UNFIT`
- **DisputeStatus:** `FILED`, `ACCEPTED`, `REJECTED`, `RETEST_IN_PROGRESS`, `CLOSED`
- **LabSection:** `FAT_OIL`, `MICROBIOLOGY`, `CHEMICAL`, `GENERAL`
- **RapidTestDevice:** `LACTOSCAN`, `OIL_TESTOMETER`, `OTHER`
- **UserRole:** `FSO`, `TRANSPORT`, `REGISTRATION_OFFICER`, `LAB_ANALYST`,
  `VERIFYING_OFFICER`, `ADMIN`

> The Phase-1 schema defines the *vocabulary* of `PartStatus`; the transition rules
> (state machine) are enforced in Phase 2, not the database.

## Part Lifecycle (as encoded through Phase 3)

The transition map is keyed by role, so each part type has its own path:

```
LAB:       COLLECTED → SEALED → IN_TRANSIT → RECEIVED_REGISTRATION → BLIND_CODED
             → ASSIGNED_TO_SECTION → TESTING → RESULT_ENTERED → VERIFIED
             → REPORT_ISSUED (terminal)
           RESULT_ENTERED → TESTING is also allowed (verifier returns work)

REFERENCE: COLLECTED → SEALED → IN_TRANSIT → RECEIVED_REGISTRATION → IN_RETENTION
           (ACTIVATED_FOR_RETEST path arrives with disputes, Phase 5)

FBO_COPY:  COLLECTED → SEALED → RELEASED_TO_FBO (terminal)
```

Any non-terminal state may also move to `REJECTED` (notes mandatory) — e.g. a broken
seal at intake. Terminal states: `REPORT_ISSUED`, `RELEASED_TO_FBO`, `REJECTED`,
`DESTROYED`.

**Role guards** (who may move a part *into* a state):

| Target | Required role |
|--------|---------------|
| `IN_TRANSIT` | FSO, TRANSPORT |
| `RECEIVED_REGISTRATION`, `BLIND_CODED`, `ASSIGNED_TO_SECTION`, `IN_RETENTION` | REGISTRATION_OFFICER |
| `TESTING`, `RESULT_ENTERED` | LAB_ANALYST |
| `VERIFIED`, `REPORT_ISSUED` | VERIFYING_OFFICER |

`RESULT_ENTERED → TESTING` is a transition-specific override requiring
VERIFYING_OFFICER (the same target, `TESTING`, is otherwise the analyst's). A null
actor means a system-generated event (e.g. the PDF job issuing the report) and
bypasses role checks.

## Seeded Data (development)

- **RoleUsersSeeder** — one **TEMPORARY** test user per role, `{role}@pfa.test` /
  `password`. Interim accounts pending PFA staff DB integration; do not ship to prod.
- **PremisesSeeder** — 10 sample Lahore food businesses.
- **TestCatalogSeeder** — MILK, OIL_GHEE, WATER, SPICES. ⚠️ Permissible limits are
  plausible placeholders and **must be confirmed against official PFA standards**
  before production.
- **SettingsSeeder** — `dispute_window_days = 7`, `same_day_transfer_deadline = 20:00`.

## Reset / Seed

```bash
php artisan migrate:fresh --seed
```
