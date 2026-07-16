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
