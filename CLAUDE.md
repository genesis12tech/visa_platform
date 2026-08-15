# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Visa Application System (VAS) — a government/embassy platform for visa intake, document verification, fee
payment, appointment booking, officer review, and decisions, with full audit and legal defensibility as
first-class requirements. Applicant, agent, officer, and admin portals; public tracking; Stripe payments.

**Current state: Stage 1 S1.1 complete** (`docs/Implementation_plan.md` §4). Laravel 12 is scaffolded with the
full locked dependency set (adjusted for Hostinger single-host — see Architecture below), Pest 4/Pint/Larastan
level 6 all installed and passing, Tailwind 3.4 wired to the Content Guidelines tokens, Sentry installed and
wired into exception handling. Pushed to `genesis12tech/visa_platform` on `main`. No domain models, migrations,
policies, or the six approved services exist yet — that's Stage 2 (Foundation).

**Known follow-ups from the scaffold, not yet resolved:**
- Local dev database is **SQLite**, a temporary stand-in only. `Backend_schema.md`'s triggers, generated
  columns, and `CHECK` constraints are MySQL-8-specific and won't work correctly against SQLite — this must
  move to real MySQL 8 before Stage 2 migration work starts (either the Hostinger DB, if remote access is
  enabled for the dev machine's IP, or a local MySQL 8 install).
- This machine's Herd-managed PHP 8.3 (and 8.2) binaries are broken (`dyld` symbol error) — only the default
  PHP 8.4 works. Composer is pinned to resolve as if PHP 8.3 (`config.platform.php` in `composer.json`) so
  package versions are correct, but `artisan`/`composer` commands actually execute under 8.4 locally until
  Herd's PHP 8.3 build is repaired (Herd → PHP → 8.3 → Reinstall, on the user's side, not touched here).
- `SENTRY_LARAVEL_DSN`, `STRIPE_KEY`/`STRIPE_SECRET`/`STRIPE_WEBHOOK_SECRET`, and `AWS_ACCESS_KEY_ID`/
  `AWS_SECRET_ACCESS_KEY`/`AWS_BUCKET` are all blank in `.env` — no Sentry project, Stripe test keys, or AWS
  account exist yet. `FILESYSTEM_DISK` stays `local` until AWS S3 is wired in.
- Fonts (Public Sans, Source Serif 4, IBM Plex Mono) are referenced in `tokens.css` with system-font fallbacks
  but not yet self-hosted as actual font files — deferred to when real UI components get built (Stage 2/3).

## Read the docs before any task — and don't contradict them

`docs/` is the authoritative, prescriptive specification for this system. **Read the relevant document(s)
before starting any task.** Do not make architectural, schema, stack, or design decisions that contradict
them. If a task genuinely requires deviating from what they specify, **stop and flag the conflict before
proceeding** — do not silently reinterpret or pick a different approach.

### Document map

| Document | Governs | Authority note |
|---|---|---|
| `docs/Tech_stack.md` | Locked dependency versions, hosting topology, strict-MVC structure | **Supersedes stack assumptions in every other document** (Postgres→MySQL, Filament→Livewire, DDD→MVC) |
| `docs/Backend_schema.md` | Full MySQL 8 schema: tables, triggers, constraints, indexes | Reverses the PK strategy other docs assume (see below); authoritative column-level catalogue |
| `docs/Visa_application_system_prd.md` | Requirements, personas, status model, business rules (BR-*), open decisions (OD-*) | Base requirements; defers to Tech Stack/Backend Schema where they've since resolved an open decision |
| `docs/Content_guidelines.md` | Design tokens, component library, copy, accessibility | **Prescriptive, not advisory** — don't invent colours, spacing, copy, or component structure |
| `docs/AppFlow_specs.md` | Screen-level UI specs for the 11 highest-complexity screens | See naming note below |
| `docs/Implementation_plan.md` | The 12-stage build sequence, gate criteria, the "five permanently-green tests" | Supersedes any earlier "Implementation Prompts" document |

**Naming trap:** despite its filename, `docs/AppFlow_specs.md` contains the *Screen-Level UI Specifications*
document (11 screens: APP-05, APP-06, APP-07, APP-09, APP-13, APP-17, APP-21, OFF-03, OFF-05, ADM-09, AGT-10) —
not an "App Flow" document. Several other docs cite a separate "App Flow v1.0" (sections like §2.1, §3.1, §3.6,
§7.2, §11, and open items AF-4/AF-5) that **does not exist anywhere in `docs/`**. Treat App Flow citations as
pointing at a missing document, not at `AppFlow_specs.md` — don't assume the two are the same file, and don't
invent App Flow content to fill the gap. Flag it if a task depends on it.

## Architecture

### Hosting — project-specific deviation from Tech_stack.md, confirmed 2026-08-14

**This project does not use the Vercel + separate-worker split described in `Tech_stack.md` §5.** That
design was replaced, deliberately, with a single Hostinger shared-hosting plan (SSH access confirmed
available). This removes Tech Stack's top two risks outright (R-1: the community-maintained `vercel-php`
runtime; R-2: code skew between two deploy tiers) at the cost of losing Redis-backed Horizon and a resident
virus-scanning daemon. Concretely:

| Tech_stack.md says | This project actually does |
|---|---|
| Vercel serverless HTTP tier + separate worker VPS | **One Hostinger host, deployed via SSH.** No `vercel.json`, no `vercel-php` pin, no `trustProxies(at: '*')` Vercel workaround, no SHA-divergence deploy gate |
| `QUEUE_CONNECTION=redis` + `laravel/horizon` | **`QUEUE_CONNECTION=database`.** Jobs drained by `php artisan queue:work --stop-when-empty` on a **cron trigger every minute** — there's no persistent process to run Horizon against. Drop `laravel/horizon` from `composer.json` (it requires Redis) |
| `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `predis/predis` | **`database`** for both cache and sessions. `Backend_schema.md` §4.1 already keeps the `sessions` table specifically "for local development and for... when the Redis driver is swapped" — this was anticipated, no schema change needed |
| Resident ClamAV daemon on the worker host | **`clamscan` invoked from a cron-triggered script** against newly-uploaded files — not a live `clamd`, but keeps scanning entirely on-server (see data residency below). Confirmed by SSH access being available |
| Private S3-compatible bucket, no specific provider | **AWS S3, `ap-south-1` (Mumbai)** — confirmed 2026-08-14. Uses the already-locked `league/flysystem-aws-s3-v3`, no adapter change |

**What is unaffected by this change:** strict MVC, the six-service cap, guard parity, the full MySQL 8 schema
(triggers, `CHECK` constraints, generated columns, `BIGINT`+ULID keys), money-as-minor-units, the status
machine, Stripe integration, and the Content Guidelines design system. This is an infrastructure-layer
substitution only.

Data residency: **applicant data and documents must stay inside India** — this is why S3 is pinned to Mumbai
and virus scanning stays on-server rather than going to an external API.

### Strict MVC — no `app/Domain/`, no Action classes

Controllers are thin (validate via Form Request → call a model method or an approved service → respond).
Business logic lives on Eloquent models as methods/scopes. Livewire components are view controllers only — no
business logic there. Every sensitive model has a Policy; every controller method authorises explicitly.

**`app/Services/` is capped at exactly six classes** — this list is closed by design; a seventh is a signal
that logic is leaking out of models and must be raised in review, not added silently:

1. `SubmissionGuard` — aggregates section/document/payment state; sole source of submission blockers
2. `ApplicationSubmitter` — snapshot + fee freeze + status transitions, one transaction
3. `StripeWebhookProcessor` — signature verify, idempotency, ledger write, transition, invoice/PDF dispatch
4. `ApprovalGuard` — aggregates payment/document/appointment state; sole source of approval blockers
5. `AppointmentBooker` — row-lock, capacity re-check, insert, increment, one transaction
6. `FeeResolver` — dated-rule precedence with explicit ambiguity detection

### Guard parity — the rule that gets flagged hardest across every doc

`SubmissionGuard` and `ApprovalGuard` must be the **single source of truth**, consumed identically by both the
controller/domain action and the Livewire view that shows blockers/disabled reasons. A second implementation
of a guard anywhere (even a UI-only re-check) is the specific failure mode the docs call out repeatedly: an
applicant or officer sees an enabled control that then fails. Every slice that extends a guard must extend the
existing service, not add a new one.

### MySQL 8, not PostgreSQL — deltas that matter

- `json`, not `jsonb`; no partial indexes. Where the docs assumed a partial unique index (e.g. "one active
  form template per visa type"), the schema uses a generated column that is `NULL` when the row is inactive,
  with a unique index on that column — replicate this pattern rather than trying a partial index.
- Primary keys are `BIGINT UNSIGNED AUTO_INCREMENT` (internal only) plus a separate `CHAR(26)` ULID (or, for
  `visa_applications`, a public `tracking_number`) for anything externally addressable. This **reverses**
  ULID-as-PK guidance that appears in the PRD and Tech Stack — Backend Schema §1 D-2 is the current word.
  `getRouteKeyName()` routes on the ULID/tracking number, never the raw `id`.
- Enums are `VARCHAR` + `CHECK`, never MySQL `ENUM` (altering `ENUM` rewrites the table).
- `CHECK` constraints only enforce from MySQL 8.0.16+ — migrations must guard against running on an older
  patch, where they silently parse and do nothing.
- The database enforces invariants (append-only tables, non-negative money, capacity ceilings, separation of
  duties); the application enforces workflow (the status transition matrix). Don't move one into the other.

### Money

Always integer minor units (`*_minor BIGINT`) paired with a `currency CHAR(3)`, using `bcmath`/a `Money` value
object — never floats, never `DECIMAL`. This resolves PRD OD-2 and is assumed throughout every other document.

### Application status model

14 internal statuses map to 8 simplified public statuses (`docs/Visa_application_system_prd.md` §7). Every
transition is atomic, database-transaction-wrapped, and rejected by the domain layer if not in the permitted
table — never enforced only in the UI. Status history and audit logs are append-only, enforced by DB triggers
(`SIGNAL` on `UPDATE`/`DELETE`), not just application discipline.

### Payments (Stripe)

Direct `stripe/stripe-php` SDK, not Cashier (one-off consular fees, not subscriptions). The webhook is
**always authoritative** — the browser return from Stripe Checkout carries no trustworthy outcome, and
"absence of confirmation is never evidence of failure." Idempotency is anchored on a
`(provider, provider_event_id)` unique index; replaying the same event any number of times must produce
exactly one ledger entry and one transition.

### Documents

Private storage only, MIME-validated by content inspection (not extension), SHA-256 checksummed, queued for
virus scanning, never previewable/downloadable until scan is clean. Image dimension validation is deferred to
the worker host (Vercel has no `gd`/`imagick`) — a file can be accepted at upload and rejected moments later;
this deferred state is first-class (`image_check_status`), not an edge case to special-case later.

### Appointments

Capacity is enforced atomically (row-lock, re-check, insert, increment, one transaction) — no soft locks, they
create phantom unavailability. `CHECK (booked_count <= capacity)` is the schema-level backstop behind the
application logic, not a replacement for it.

### Agents

Access is governed by `agent_applicant_links` (consent-recorded, expiring). Linkage is verified **on every
request**, never cached in session — revocation must take effect on the agent's very next request. An
unlinked agent gets a 404 (not 403): existence must not be confirmed.

## The five permanently-green tests

Once code exists, these must never go red without work stopping to fix them first
(`docs/Implementation_plan.md` §16.2):

1. Policy coverage — every sensitive model has a registered policy
2. Transition matrix — exactly the permitted application-status transitions succeed
3. Webhook replay — 100 replays of one event produce exactly one ledger entry
4. Booking concurrency — 50 concurrent bookings against a capacity-1 slot yield exactly 1 success
5. Guard parity — UI blockers equal domain guard output, across a state matrix

## Commands

```bash
composer install && npm install         # install locked dependencies (composer.json/package.json)
./vendor/bin/pest                       # test suite (Pest 4.x — NOT 5.x, see below)
./vendor/bin/pest --filter=SomeTest     # run a single test
./vendor/bin/pint                       # code style — auto-fixes; use --test to check without fixing
./vendor/bin/phpstan analyse            # Larastan, level 6 (config: phpstan.neon)
npm run build                           # compile Tailwind/Vite assets
npm run dev                             # Vite dev server with HMR
php artisan queue:work --stop-when-empty  # drain the database queue — cron-triggered every minute in
                                           # production (this project has no Horizon/Redis; see Architecture)
php artisan schedule:run                # scheduler — cron-triggered every minute in production
```

No `php artisan serve` alias is documented here deliberately — this project has no Docker/Sail setup (see
Architecture), so use `php artisan serve` directly or Herd's own site serving once a PHP 8.3 build is repaired
(see "Known follow-ups" above).

**Pest is pinned to 4.x, never 5.x** — Pest 5 requires PHP `^8.4` and Laravel `^13.23`, both excluded by the
locked PHP 8.3 / Laravel 12 stack (Tech Stack §3.3). Don't "helpfully" upgrade it.

## Decisions already made — don't relitigate

- **Laravel 12 + PHP 8.3**, not 13/8.4 (deliberate; resolves PRD OD-1)
- **No Filament** — hand-built Blade + Livewire 3.8 admin/officer screens using
  `rappasoft/laravel-livewire-tables`; budget ~1.5–2× more effort on those screens than a Filament estimate
- **Money as integer minor units** (resolves PRD OD-2)
- **BIGINT PK + ULID public column**, not ULID-as-PK (Backend Schema D-2, reverses earlier docs)
- Tailwind **3.4.x**, not 4.x; dark mode and RTL are explicitly **not supported** in v1 (Content Guidelines
  §3.4). **Launch language set confirmed 2026-08-14: English, Hindi, French** — none are RTL, so this no
  longer conflicts with PRD NFR-U-06 in practice (resolves OD-4 / Content Guidelines CG-1). Logical CSS
  properties (`ms-*`/`me-*`) are still used throughout per the existing hedge
- **Hosting**: single Hostinger shared-hosting host via SSH, not the Vercel/worker split — see Architecture
  above. **Data residency: India** (resolves OD-13). **Object storage: AWS S3, `ap-south-1`**. **Virus
  scanning: self-hosted `clamscan` via cron**, not an external API (resolves TS-5)

## Open decisions still blocking downstream work

The PRD (§18), Tech Stack (§15), Backend Schema (§15), and Implementation Plan (§18) each carry their own
open-items table. Resolved as of 2026-08-14: data residency (India), launch language set (English, Hindi,
French), virus-scanning provider (self-hosted).

**Resolved 2026-08-15 with a working default, pending real sign-off** — proceed on these, but do not treat
them as final without the confirmation named against each. Full detail and rationale in `DataCredential.txt`:

- **Payment-record retention**: 7 years from financial year end (documents remain 2 years, already confirmed).
  Needs an accountant/compliance advisor's confirmation before launch, not before Stage 2 code.
- **GST**: 18% on the service-fee component, base visa fee treatment still genuinely open (may be
  GST-exempt as a statutory government charge). Stored as a per-fee-rule config value, not hardcoded — needs
  a tax consultant's confirmation before S5.1 real fee configuration.
- **Refund status boundaries**: 100% while `submitted`/`payment_pending`/`paid`; 50% while `under_review`
  through `decision_pending`; non-refundable once `approved`/`rejected`. Needs confirmation this matches
  intended policy before S5.6.
- **Duplicate-passport blind-index detection**: proceeding — build it (Backend Schema §13.2). Still worth a
  five-minute nod from whoever owns privacy/security policy before Stage 2.4 ships it, per the source
  document's own framing.

**Still genuinely open, no default proposed:**

- Whether an appointment reschedule resets the SLA clock (App Flow AF-5 — the missing document referenced
  above)
- Visa catalogue detail (fees, processing times, form questions, required documents per visa type) — the visa
  *types* are confirmed (Tourist, Student, Business, Employment, Entry, Journalist, Conference, Medical,
  Research); per-type detail is still to be researched against Indian High Commission sources
- AWS account for S3 not yet created — doesn't block Stage 1 scaffolding, but blocks actually wiring document
  storage (S1/S2)
