# Admin Panel (Phase 5)

A server-rendered internal panel (Blade + Alpine.js + Tailwind) for the five office
roles. The field roles (FSO / TRANSPORT) get an **interim web fallback** until the
Flutter app ships. The Sanctum API is untouched — the panel authenticates with the
**web session guard** against the same `users` table, and web controllers call the
**same services** the API uses (no duplicated business logic).

## Stack & build

- Blade views, Alpine.js for small interactivity (modals, confirm dialogs, scan
  focus, dynamic parameter rows), Tailwind via Vite.
- Local dev: `npm run dev` (Vite dev server) + `php artisan serve`.
- Production: `npm run build` locally, then upload `public/build/` to the host
  (shared hosting has no Node). See the README deploy note.

## Auth & access

- `GET /login` — session login, throttled 5/min per IP.
- Seeded accounts have `must_change_password = true`; first login is intercepted by
  `GET /password/change` before any page is reachable.
- Deactivated (`is_active = false`) users are locked out even mid-session.
- Each section is guarded by the same `role:` / `active` middleware as the API.
  Direct URL access to another role's screen renders a friendly **403** with a
  "switch account" action. (Locked by `tests/Feature/Panel/RouteRoleMatrixTest.php`.)

## Shared Blade components

`x-status-badge`, `x-custody-timeline`, `x-scan-input` (autofocus for hardware QR
scanners + manual fallback), `x-photo` (protected-route image + lightbox),
`x-sop-violation-banner`, `x-confirm-action` (restates the SOP consequence before
any destructive/legal action), `x-params-table`.

## Screens by role

### Registration Officer (`/registration/*`, `/registration/retention`)
| Screen | Route | Notes |
|--------|-------|-------|
| Receiving desk | `registration.receiving.create` → `.show` → `.store` | scan → summary → seal-intact + photo + temp (perishable) → accept/reject; violations surface via banner after submit |
| Blind coding | `registration.blind.create` → `.show` → `.store` → `.label` | one-click assign, then a **printable label** (blind code + QR + section, `@media print`) |
| Section assignment | `registration.section.create` → `.show` → `.store` | suggested section preselected from the test catalog |
| Retention shelf | `registration.retention.index` / `.destroy` | retained references with storage, days held, eligibility; destroy (photo + notes, eligibility-guarded) |
| File dispute | `registration.disputes.create` / `.store` | officer files for a walk-in FBO |

### Lab Analyst (`/lab/*`) — behind the blind wall
| Screen | Route | Notes |
|--------|-------|-------|
| My queue | `lab.queue` | oldest-first, aging indicator, section filter |
| Sample detail | `lab.show` / `lab.start` / `lab.results` | dynamic parameter rows (Alpine), within-limit auto-computed client-side **and** server-verified, report-photo upload |

Every analyst value is passed through `BlindSamplePartResource` (the same allow-list
as the API), and an **activated retest is indistinguishable** from a first-time
sample (`ACTIVATED_FOR_RETEST` is shown as `ASSIGNED_TO_SECTION`). Locked by
`tests/Feature/Panel/PanelBlindWallTest.php`.

### Verifying Officer (`/verification/*`, `/disputes/*`)
| Screen | Route | Notes |
|--------|-------|-------|
| Verification queue | `verification.queue` | RESULT_ENTERED, full de-blinded |
| Review + verdict | `verification.show` / `.verdict` / `.return` | full record, both seal photos, custody timeline, limit-highlighted params; verdict (FIT/UNFIT + notes) with maker-checker errors surfaced; return-to-analyst |
| Disputes | `disputes.index` / `.show` / `.decide` | list, detail with window countdown + original vs retest side-by-side + final verdict/source, accept/reject |

### Admin (`/admin/*`)
Users (create/edit/deactivate, no self-deactivate, reset must-change-password),
test-catalog CRUD (dynamic parameters editor), SOP violations (filter + resolve),
settings editor (typed: window days, temps, deadline time), and a read-only
sampling-events explorer (search by code/license/date → full event story).

### FSO / TRANSPORT web fallback (`/field/*`)
Marked with an "Interim web version — mobile app pending" banner. Create draft
event → add parts with camera capture (`<input capture="environment">`) → witness
fields → finalize (Rule of Three) → **print the 3 QR labels**; rapid-test entry;
my-events list; and a custody-scan screen (allowed transitions come from the state
machine). Unblocks real UAT with PFA staff before the Flutter build.

## Shared services (single source of truth)

Web and API both call: `RegistrationService`, `LabService`, `VerificationService`,
`SamplingEventService`, `DisputeService`, `CustodyStateMachine`, `EventCodeGenerator`,
`QrService`. The API controllers were refactored to delegate to these in this phase,
so there is exactly one implementation of every rule.

## Screenshots

_Add screenshots here (login, receiving desk, blind label print view, lab parameter
entry, verification detail, dispute side-by-side) once captured during UAT._

## Manual click-through (verified)

Login → forced password change → receive → blind-code (print label) → assign →
analyst entry → verify UNFIT → file dispute → accept → retest → close, all in the
browser. Cross-role URLs return 403.
