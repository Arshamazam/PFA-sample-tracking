# API Reference — Phase 2 (Field Side)

Base URL: `/(host)/api/v1`
All requests should send `Accept: application/json`. Authenticated requests send
`Authorization: Bearer <token>`.

## Conventions

- **Success** responses use an envelope: `{ "data": ..., "meta": ... }`. Paginated
  lists add Laravel's standard `links` and `meta.pagination` keys.
- **Failure** responses use `{ "message": ..., "errors": { field: [..] } }`
  (Laravel's validation shape). Validation and illegal state transitions return
  **422**; auth failures **401**; role/ownership failures **403**; unknown resources
  **404**.
- All datetimes are ISO 8601 UTC.
- Uploaded files (photos, signatures) are sent as `multipart/form-data` and stored
  privately; they are served only through the authenticated `GET /files/{path}`
  route.

## Roles & abilities

Tokens carry abilities derived from the user's role. Phase 2 endpoints are gated by
role middleware:

| Area | Allowed roles |
|------|---------------|
| Rapid tests | FSO |
| Sampling events | FSO (owner only) |
| Custody scan | FSO, TRANSPORT |
| Files, QR, part timeline | any authenticated active user |

---

## Auth

### POST /auth/login
Throttled to 5 requests/minute/IP.

Request:
```json
{ "email": "fso@pfa.test", "password": "password", "device_name": "pixel-7" }
```
Response `200`:
```json
{
  "data": {
    "token": "12|abcdef...",
    "user": { "id": "01hx...", "name": "Food Safety Officer (TEST)", "email": "fso@pfa.test", "role": "FSO", "role_label": "Food Safety Officer", "phone": null, "is_active": true },
    "abilities": ["rapid-tests:create", "rapid-tests:read", "sampling-events:create", "sampling-events:read", "sampling-events:update", "custody:scan", "files:read"]
  },
  "meta": { "token_type": "Bearer" }
}
```
Invalid credentials or a deactivated account return `422`.

### GET /auth/me
Response `200`: `{ "data": <user>, "meta": { "abilities": [...] } }`

### POST /auth/logout
Revokes the current token. Response `200`.

---

## Rapid tests (role: FSO)

### POST /rapid-tests
`multipart/form-data`:

| Field | Type | Notes |
|-------|------|-------|
| `premises_license` | string | required; auto-creates a MANUAL premises if unknown |
| `premises_name`, `premises_address`, `premises_city` | string | optional; used only when auto-creating |
| `device` | string | required; `LACTOSCAN` \| `OIL_TESTOMETER` \| `OTHER` |
| `reading` | string | required |
| `passed` | boolean | required |
| `photo` | file (image) | optional, ≤ 5 MB |
| `tested_at` | datetime | required |

Response `201`:
```json
{ "data": {
  "id": "01hx...", "sampling_event_id": null, "premises_id": "01hx...",
  "device": "LACTOSCAN", "device_label": "Lactoscan (Milk Analyzer)",
  "reading": "SNF 7.1%", "passed": false,
  "photo_path": "rapid-tests/abc.jpg",
  "photo_url": "http://localhost/api/v1/files/rapid-tests/abc.jpg",
  "tested_at": "2026-07-15T08:30:00+00:00", "created_at": "2026-07-15T08:31:00+00:00"
} }
```

### GET /rapid-tests
Query: `premises_license`, `from`, `to`, `per_page`. Paginated collection of rapid
tests. (District scoping is a TODO — district is not yet modelled.)

---

## Sampling events (role: FSO, owner only)

### POST /sampling-events
Creates a DRAFT event and generates the `event_code` (`PFA-LHR-YYYY-NNNNNN`;
district hardcoded `LHR` for now).
```json
{
  "premises_license": "PFA-LHR-2025-10001",
  "food_item": "Loose Milk",
  "brand_name": null,
  "is_perishable": true,
  "collected_at": "2026-07-15T09:00:00Z",
  "witness_name": "Shop Assistant",
  "witness_cnic": "35201-1234567-1",
  "rapid_test_id": null
}
```
Response `201`: the event resource with `status: "DRAFT"`.

### PATCH /sampling-events/{id}
Corrections + witness fields, **draft only** (finalized events return `422`).
Accepts `witness_signature` (image upload), `witness_name`, `witness_cnic`,
`food_item`, `brand_name`, `is_perishable`, `collected_at`.

### POST /sampling-events/{id}/parts
Attach ONE part (`multipart/form-data`). Duplicate roles are rejected with `422`
before hitting the DB constraint.

| Field | Type | Notes |
|-------|------|-------|
| `role` | string | `LAB` \| `REFERENCE` \| `FBO_COPY` |
| `seal_number` | string | required |
| `seal_photo` | file (image) | required |

Creates the part at `COLLECTED` with its opening custody event and a random
32-char `qr_token`. Response `201`: the part resource (includes `qr_svg_url`,
`tracking_url`).

### POST /sampling-events/{id}/finalize
Enforces the **Rule of Three**: exactly `LAB` + `REFERENCE` + `FBO_COPY`, each with a
seal number and seal photo, plus a witness name and uploaded signature. On success it
sets `finalized_at` and transitions all three parts `COLLECTED → SEALED` in one
transaction. Response `200`: the full event with `parts[]` and their QR payloads for
printing. Missing requirements return `422` with per-field errors.

### GET /sampling-events/{id}
Owner-only. Returns the event with `premises`, `parts[]`, and each part's
`custody_events[]`. Non-owners get `403`.

### GET /sampling-events
Owner-scoped, paginated. Query: `premises_license`, `status` (`DRAFT`|`FINALIZED`),
`from`, `to`, `per_page`.

---

## QR & files

### GET /sample-parts/{id}/qr.svg
Returns `image/svg+xml` — a QR encoding the part's public tracking URL
(`{APP_URL}/track/p/{qr_token}`; the public page itself is Phase 6).

### GET /files/{path}
The single, authenticated file-download route for the whole system. Streams a file
from the private disk. Path traversal is rejected with `404`.

---

## Custody

### POST /custody/scan (roles: FSO, TRANSPORT)
Resolves a part by `qr_token` and applies a custody transition via the state
machine. This one endpoint drives all scan-based moves (Phase 3 reuses it).

| Field | Type | Notes |
|-------|------|-------|
| `qr_token` | string | required |
| `to_status` | string | required; a `PartStatus` value |
| `latitude`, `longitude` | number | optional |
| `location_note` | string | optional |
| `temperature_c` | number | **required** when moving a perishable sample into `IN_TRANSIT`/`RECEIVED_REGISTRATION` |
| `notes` | string | **required** when `to_status` = `REJECTED` |
| `photo` | file (image) | optional |

Illegal transitions and unmet SOP guards return `422`. Response `200`: the updated
part with its custody trail.

**Transitions encoded in Phase 2** (each non-terminal state may also go to
`REJECTED` with notes):

```
LAB:       COLLECTED → SEALED → IN_TRANSIT → RECEIVED_REGISTRATION
REFERENCE: COLLECTED → SEALED → IN_TRANSIT → RECEIVED_REGISTRATION → IN_RETENTION
FBO_COPY:  COLLECTED → SEALED → RELEASED_TO_FBO   (terminal)
```
`IN_TRANSIT` requires an FSO/TRANSPORT actor; `RECEIVED_REGISTRATION` requires a
REGISTRATION_OFFICER (Phase 3 wires the registration endpoints).

### GET /custody/parts/{qr_token}
Authenticated internal view: the part, its full custody timeline, a summary of the
parent sampling event, and `meta.allowed_transitions`. (The public version is
Phase 6.)

---

---

# Phase 3 — Technical Wing (registration, lab, verification, reports)

## Registration Section (role: REGISTRATION_OFFICER)

Registration works from the **physical QR** on the sample, so these endpoints take
`qr_token`. The blind wall starts at the lab workbench.

### POST /registration/receive
`multipart/form-data`. Records arrival at the Technical Wing.

| Field | Type | Notes |
|-------|------|-------|
| `qr_token` | string | required |
| `seal_intact` | boolean | required |
| `seal_number_confirmed` | boolean | required — officer confirms the seal number matches the record |
| `seal_photo` | file (image) | **required** — the receiving-side photo, kept even on rejection |
| `temperature_c` | number | required for perishables (enforced by the state machine) |
| `notes` | string | **required when rejecting** |

Behaviour:
- `seal_intact=false` **or** `seal_number_confirmed=false` → part moves to **REJECTED**
  (notes mandatory; 422 without them).
- Otherwise → **RECEIVED_REGISTRATION**.
- **Late arrival** (after the collection date, or past `same_day_transfer_deadline`
  on the collection date) → still accepted, but a `SAME_DAY_TRANSFER` SOP violation
  is recorded.
- **Temperature outside `cold_chain_min_c`..`cold_chain_max_c`** → still accepted,
  but a `COLD_CHAIN_BREACH` violation is recorded.

Response `200`: the full part resource, including `sop_violations[]`.

### POST /registration/retain
`{ "qr_token": "...", "storage_location": "Cabinet B/3", "notes": null }`
REFERENCE part only (422 otherwise) → **IN_RETENTION**.

### POST /registration/blind-code
`{ "qr_token": "..." }` → assigns the next `BC-{YYYY}-{6-digit}` code (transaction-safe
counter) and moves the part to **BLIND_CODED**. LAB parts only — the transition map
rejects a REFERENCE part with 422.

### POST /registration/assign-section
`{ "qr_token": "...", "lab_section": "CHEMICAL" }` → records the section on the
lab result and moves the part to **ASSIGNED_TO_SECTION**.

### GET /registration/suggest-section?qr_token=…
Suggests the section from `test_catalog` using the event's `food_category`.
```json
{ "data": {
    "food_category": "MILK",
    "suggested_lab_section": "CHEMICAL",
    "suggested_test_name": "Milk Composition & Adulteration",
    "parameters_template": [ { "name": "Fat", "unit": "%", "permissible_limit": "min 3.5" } ],
    "available": [ { "lab_section": "CHEMICAL", "test_name": "…", "tat_hours": 48 },
                   { "lab_section": "MICROBIOLOGY", "test_name": "…", "tat_hours": 72 } ]
  }, "meta": { "matched": 2 } }
```

> **Flutter/API note:** `food_category` is set by the FSO at collection
> (`POST /sampling-events`, optional `food_category`, e.g. `MILK`, `OIL_GHEE`,
> `WATER`, `SPICES`, `MEAT`). Without it, section suggestion returns nulls and lab
> parameter validation is skipped — so the app should always send it.

## The blind wall (role: LAB_ANALYST)

Analysts address samples **only by `blind_code`** and receive a deliberately minimal
payload. Every `/lab/*` response contains exactly these fields:

```json
{ "data": {
    "blind_code": "BC-2026-000001",
    "food_category": "MILK",
    "food_item": "Loose Milk",
    "is_perishable": true,
    "lab_section": "CHEMICAL",
    "lab_section_label": "Chemical",
    "status": "TESTING",
    "status_label": "Testing",
    "received_at": "2026-07-16T16:24:38+00:00",
    "assigned_at": "2026-07-16T16:24:38+00:00",
    "parameters_template": [ … ],
    "parameters": [ … ]
  } }
```

Never exposed to analysts: `qr_token`, seal number/photos, **any** premises data
(name, address, `license_no`), `brand_name`, witness details, the FSO's identity,
`event_code`, `sampling_event_id`, the part id, the custody trail, or the verdict.
Analysts are also blocked from `/verification/*`, `/sampling-events`, `/rapid-tests`,
`/custody/parts/{qr_token}` and the report PDF (all `403`).
`tests/Feature/BlindWallTest.php` enforces this by recursively scanning every
analyst payload for forbidden keys **and** identifying values.

### GET /lab/queue?section=CHEMICAL
Blind resources for parts in `ASSIGNED_TO_SECTION` or `TESTING` in that section,
oldest first. Paginated.

### POST /lab/{blind_code}/start
`ASSIGNED_TO_SECTION → TESTING`, claiming the sample for the analyst.

### POST /lab/{blind_code}/results
`multipart/form-data`:

| Field | Type | Notes |
|-------|------|-------|
| `parameters[]` | array | `{name, value, unit, permissible_limit, within_limit, is_additional?}` |
| `report_photo` | file (image) | required — the physical bench report |

- Parameter names are validated against the `test_catalog` template for the sample's
  `food_category`; anything outside it must set `is_additional: true` (422 otherwise).
- First submission: `TESTING → RESULT_ENTERED`. While `RESULT_ENTERED` the analyst may
  re-submit; the previous parameters are archived to `lab_result_revisions` and the
  state does not advance.
- Sending `verdict` (or `verdict_at`) returns **422** — the verdict is not the
  analyst's to set.

## Verification / maker-checker (role: VERIFYING_OFFICER)

### GET /verification/queue
Parts at `RESULT_ENTERED` with the **full de-blinded** record — `sampling_event`,
`premises`, `license_no`, `brand_name`, plus `lab_result` and `sop_violations`.
A verdict is a legal determination about a named business, so this role sees everything.

### POST /verification/{blind_code}/verdict
`{ "verdict": "FIT" | "UNFIT", "notes": "…" }`
Sets `verdict`, `verdict_at`, `verified_by_id`, moves the part to **VERIFIED**, and
dispatches the report job.
**Maker-checker:** if the verifier is the analyst who produced the result → `422`.

### POST /verification/{blind_code}/return
`{ "notes": "required reason" }` → `RESULT_ENTERED → TESTING` so the analyst can redo
the work.

## Reports

The PDF is rendered by the queued `GenerateReportPdf` job (queue driver `database`;
run a worker: `php artisan queue:work`). It writes to private storage at
`reports/{event_code}/{part_id}.pdf`, sets `report_pdf_path`, and moves the part to
**REPORT_ISSUED** as a system actor. If the job fails the part stays at `VERIFIED`;
`php artisan reports:retry-failed` re-queues it.

### GET /reports/{blind_code}.pdf
Returns `application/pdf`. Allowed: `VERIFYING_OFFICER`, `REGISTRATION_OFFICER`,
`ADMIN`, and the **owning FSO**. Analysts get `403` (the report names the business).
`404` if the report has not been generated yet.

## Admin (role: ADMIN)

| Endpoint | Notes |
|----------|-------|
| `GET/POST /admin/users`, `GET/PATCH /admin/users/{user}` | Create with a role; deactivate/reactivate via `is_active`. Accounts are never deleted (the custody trail references them). Deactivating **your own** account → 422. |
| `GET/POST /admin/test-catalog`, `GET/PATCH/DELETE /admin/test-catalog/{testCatalog}` | Filter by `food_category`, `lab_section`. |
| `GET /admin/sop-violations` | Filter `type` (`SAME_DAY_TRANSFER`\|`COLD_CHAIN_BREACH`\|`OTHER`), `resolved` (bool), `from`, `to`. |
| `PATCH /admin/sop-violations/{sopViolation}` | `{ "resolved": true, "resolution_notes": "…" }` — notes required when resolving. |

---

## Happy-path walkthrough (curl)

Assumes the app is served at `http://127.0.0.1:8000` and seeded
(`php artisan migrate:fresh --seed`). A small placeholder image is used for uploads.

```bash
BASE=http://127.0.0.1:8000/api/v1
ACC='-H Accept:application/json'
printf '\x89PNG\r\n\x1a\n' > /tmp/x.png   # any real image works; use a photo in practice

# 1. Login as the FSO and capture the token
TOKEN=$(curl -s $ACC -H 'Content-Type: application/json' \
  -d '{"email":"fso@pfa.test","password":"password","device_name":"cli"}' \
  $BASE/auth/login | php -r 'echo json_decode(file_get_contents("php://stdin"),true)["data"]["token"];')
AUTH="-H Authorization:Bearer $TOKEN"

# 2. Create a DRAFT sampling event (perishable)
EVID=$(curl -s $ACC $AUTH -H 'Content-Type: application/json' -d '{
  "premises_license":"PFA-LHR-2025-10001","food_item":"Loose Milk",
  "is_perishable":true,"collected_at":"2026-07-15T09:00:00Z","witness_name":"Shop Assistant"
}' $BASE/sampling-events | php -r 'echo json_decode(file_get_contents("php://stdin"),true)["data"]["id"];')

# 3. Add the three parts
for ROLE in LAB REFERENCE FBO_COPY; do
  curl -s $ACC $AUTH -F "role=$ROLE" -F "seal_number=SEAL-$ROLE" -F "seal_photo=@/tmp/x.png" \
    $BASE/sampling-events/$EVID/parts >/dev/null
done

# 4. Upload the witness signature, then finalize (seals all three parts)
curl -s $ACC $AUTH -X PATCH -F "witness_signature=@/tmp/x.png" $BASE/sampling-events/$EVID >/dev/null
LAB_TOKEN=$(curl -s $ACC $AUTH -X POST $BASE/sampling-events/$EVID/finalize \
  | php -r '$d=json_decode(file_get_contents("php://stdin"),true)["data"]["parts"];
            foreach($d as $p){ if($p["role"]==="LAB") echo $p["qr_token"]; }')

# 5. Scan the LAB part into transit WITH a cold-chain temperature
curl -s $ACC $AUTH -H 'Content-Type: application/json' -d "{
  \"qr_token\":\"$LAB_TOKEN\",\"to_status\":\"IN_TRANSIT\",
  \"latitude\":31.5204,\"longitude\":74.3587,\"temperature_c\":4.5
}" $BASE/custody/scan

# 6. Inspect the custody timeline (COLLECTED → SEALED → IN_TRANSIT)
curl -s $ACC $AUTH $BASE/custody/parts/$LAB_TOKEN
```

## Technical-wing walkthrough (curl) — receive → report

Continues from the field walkthrough above (`$LAB_TOKEN` is the LAB part's QR token).
Note the login limiter is **5/min per IP** — fetch each token once and reuse it.

```bash
BASE=http://127.0.0.1:8000/api/v1
ACC='Accept: application/json'
login() { curl -s -H "$ACC" -H 'Content-Type: application/json' \
  -d "{\"email\":\"$1@pfa.test\",\"password\":\"password\",\"device_name\":\"cli\"}" \
  $BASE/auth/login | php -r 'echo json_decode(file_get_contents("php://stdin"),true)["data"]["token"];'; }

REG=$(login registration_officer); LAB=$(login lab_analyst); VER=$(login verifying_officer)

# 1. Receive the LAB part (intact seal, cold-chain reading).
#    A 12 C reading here is accepted but records a COLD_CHAIN_BREACH violation.
curl -s -H "$ACC" -H "Authorization: Bearer $REG" \
  -F "qr_token=$LAB_TOKEN" -F 'seal_intact=1' -F 'seal_number_confirmed=1' \
  -F 'temperature_c=4' -F "seal_photo=@/tmp/x.png" $BASE/registration/receive

# 2. Suggest a section, assign a blind code, route it.
curl -s -H "$ACC" -H "Authorization: Bearer $REG" \
  "$BASE/registration/suggest-section?qr_token=$LAB_TOKEN"

BC=$(curl -s -H "$ACC" -H "Authorization: Bearer $REG" -H 'Content-Type: application/json' \
  -d "{\"qr_token\":\"$LAB_TOKEN\"}" $BASE/registration/blind-code \
  | php -r 'echo json_decode(file_get_contents("php://stdin"),true)["data"]["blind_code"];')
echo "blind code: $BC"

curl -s -H "$ACC" -H "Authorization: Bearer $REG" -H 'Content-Type: application/json' \
  -d "{\"qr_token\":\"$LAB_TOKEN\",\"lab_section\":\"CHEMICAL\"}" $BASE/registration/assign-section

# 3. Analyst: queue (blind — no premises/licence/brand anywhere), start, enter results.
curl -s -H "$ACC" -H "Authorization: Bearer $LAB" "$BASE/lab/queue?section=CHEMICAL"
curl -s -H "$ACC" -H "Authorization: Bearer $LAB" -X POST $BASE/lab/$BC/start
curl -s -H "$ACC" -H "Authorization: Bearer $LAB" -X POST $BASE/lab/$BC/results \
  -F 'parameters[0][name]=Fat' -F 'parameters[0][value]=2.9' -F 'parameters[0][unit]=%' \
  -F 'parameters[0][permissible_limit]=min 3.5' -F 'parameters[0][within_limit]=0' \
  -F "report_photo=@/tmp/x.png"

# 4. Verifier: full de-blinded queue, then the verdict (a different user than the analyst).
curl -s -H "$ACC" -H "Authorization: Bearer $VER" $BASE/verification/queue
curl -s -H "$ACC" -H "Authorization: Bearer $VER" -H 'Content-Type: application/json' \
  -d '{"verdict":"UNFIT","notes":"Fat below permissible limit."}' \
  $BASE/verification/$BC/verdict

# 5. Run the queued PDF job, then download the report.
php artisan queue:work --once --stop-when-empty
curl -s -o report.pdf -H "Authorization: Bearer $VER" "$BASE/reports/$BC.pdf" && head -c 5 report.pdf  # %PDF-
```

Verified output of the run above: part reaches `REPORT_ISSUED`, the PDF is written to
`reports/PFA-LHR-2026-000001/{part}.pdf`, the verifier and owning FSO download it
(`200`), and the analyst is refused (`403`).
