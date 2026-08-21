# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Visa Application System (VAS) — a government/embassy platform for visa intake, document verification, fee
payment, appointment booking, officer review, and decisions, with full audit and legal defensibility as
first-class requirements. Applicant, agent, officer, and admin portals; public tracking; Stripe payments.

**Current state: Stage 1 complete and deployed, Stage 2 (Foundation) in progress — S2.1–S2.10 done.** S2.10 built
the Blade/Livewire design-system component library (Content_guidelines.md §5): `icon` (self-hosted Heroicons
2.x via blade-heroicons — a transitive dependency of `rappasoft/laravel-livewire-tables`, not a project-level
install; its own generic `<x-icon>` tag had to be disabled in `config/blade-icons.php` to free that exact name
for this project's wrapper), `button`, `badge` (StatusBadge), `alert`, `card`, `field-group` + `input`/
`textarea`/`select`/`checkbox`/`radio-group`/`date-input`, `error-summary`, `empty-state`, `progress-bar`,
`skeleton`, `modal`, and Livewire `Shared\Toast`/`Shared\SessionWarning`. Tokens, high-contrast mode, and print
stylesheet were already complete from Stage 1 scaffolding — S2.10's real gap there was extending `base.css`'s
forced-colors/`prefers-contrast` rules to cover `.progress-bar`/`.modal`/`.toast`/`.skeleton`, which weren't in
the original 5-selector placeholder set. Two real token gaps found and fixed properly rather than worked
around: Modal's exact sizes (28/36/48rem) and its 85vh body-scroll cap aren't expressible via the §2.4 spacing
scale, so `tailwind.config.js` gained named `maxWidth`/`maxHeight` tokens (`modal-sm/md/lg`, `modal`) rather
than an arbitrary-value bracket. `SessionWarning` is a self-contained shell (events: `session-expiring`,
`autosave-completed`, `autosave-failed`, `session-extended`, `retry-autosave`) — the actual autosave feature it
integrates with doesn't exist yet (Stage 3+), and its full trigger-timing spec lives in the still-missing App
Flow §3.6 (see "Naming trap" below), so its 120-second warning lead time is this component's own reasonable
default, not doc-mandated. A local-only `/dev/components` preview route (registered only under
`app()->environment('local')`) exercises every component together — rendering it end-to-end (not just the 102
unit-style Blade/Livewire tests) caught a real bug TDD alone missed: `<x-error-summary>` checked
`$errors instanceof MessageBag`, but Blade's real `$errors` is a `ViewErrorBag` *wrapping* a MessageBag, not one
itself — the mismatch silently produced garbage output only visible once actually rendered in a real request
context, not through a hand-constructed `MessageBag` in a test. S2.9 audited
every append-only trigger and `CHECK` constraint Backend_schema.md specifies for tables built so far (§4.x,
§7, §8.1) against what S2.1–S2.8 had actually shipped — each table added its own constraints as it was built,
so the audit found every one already in place (`audit_logs`' pair from S2.8 is the only append-only trigger
that currently applies; the other four append-only tables in §8.1 don't exist until Stage 3+). The one real gap
was the MySQL-version migration guard §7 calls for (`SELECT VERSION()`, abort below 8.0.16 — below that,
`CHECK` parses and is silently ignored, the worst possible failure mode). Built as
`App\Support\Concerns\EnsuresCheckConstraintSupport` (MariaDB always passes, matching
`tests/Pest.php`'s `databaseEnforcesCheckConstraints()` logic) and retrofitted into all nine existing
`CHECK`-adding migrations. Also added `database/migrations` to `phpstan.neon`'s analysed paths — it had been
`app`-only, which is why Larastan couldn't see the trait was used and flagged it as dead code; broadening the
scope was the real fix, not a suppression, and now gives migrations the same static analysis coverage as the
rest of the app. S2.8 added
append-only audit logging: `audit_logs` (Backend_schema.md §4.12) with `trg_audit_logs_no_update`/
`trg_audit_logs_no_delete` as the real enforcement (a raw `DB::table()` update/delete throws `QueryException`;
Eloquent-layer immutability would not be a real guarantee). `App\Support\AuditLogger` writes rows — actor
resolved across all three guards (or passed explicitly, e.g. at registration before login completes),
`on_behalf_of` for agent actions (FR-AG-05), IP/user-agent from the current request, before/after via
`old_values`/`new_values`. **Deliberately placed outside `app/Services/`**, not as a seventh approved service —
Implementation_plan.md's S2.8 text calls it "the `AuditLogger` service," but the six-service cap in this file is
closed by design (see below); asked the user, who chose `app/Support/AuditLogger.php` over expanding the cap,
consistent with how S2.7 kept MFA logic out of `app/Services/` for the identical reason. Wired into the three
auth completion points (`user.registered`, `user.email_verified`, `auth.login` — logged from both
`AuthenticatedSessionController` and `MfaChallengeController`, since staff logins complete at the MFA step, not
the password step) and, via a new `App\Models\Concerns\Auditable` trait's `created`/`updated`/`deleted` hooks,
into all nine reference-data models — so fee/config changes get a compliance trail automatically once an admin
CRUD screen exists to make one, without that screen needing to remember to call `AuditLogger` itself. S2.6 added
three session guards (web/agent/staff) each with their own cookie name and domain-based routing, registration +
email verification, rate-limited login with full `login_attempts` recording, and password reset — all
non-enumerating (PUB-05-style) where applicable. S2.7 added mandatory TOTP-based MFA for staff (enrolment with
a QR code and 10 bcrypt-hashed recovery codes, a login-time challenge step, `EnsureMfaEnrolled` gating every
non-enrolment staff route in non-local environments) using `pragmarx/google2fa-laravel`/`google2fa-qrcode`. See
"`Applicant`/`Agent`/`Staff` subclasses — a recurring pitfall" below before touching anything related to
guards, relations on `User`, or notifications sent to a user (`docs/Implementation_plan.md` §4–§5).
**`visa-agent.geninnovations.net` and `visa-staff.geninnovations.net` are live** as of 2026-08-21 (split-directory
index.php + standard front-controller `.htaccess`, since they can't use the primary domain's
rewrite-to-`/public` trick — see Deployment_runbook.md's "Agent and staff subdomains" section), each verified
serving the correct guard with the correct session cookie name. That same check caught production sitting 3
commits behind (the deploy step had only been run once, at the initial redeploy) — now caught up.

All 9 reference-data tables exist with tested models (currencies,
countries, visa_types, visa_fees, document_types, rejection_reasons, service_locations, holidays,
visa_type_document_requirements), seeded via `ReferenceDataSeeder`. `FeeResolver` — the first of the six
approved services — is built and tested: specificity precedence (nationality-specific beats general),
inclusive `valid_from`/exclusive `valid_until` boundaries, and `AmbiguousFeeRuleException` /
`FeeRuleNotFoundException` rather than ever silently picking a rule. `users` (status/type/suspension CHECK
constraints, soft deletes, self-referencing `suspendedBy`) and `applicant_profiles` (passport number stored via
Laravel's `encrypted` cast, paired with a peppered SHA-256 blind index for duplicate detection per
Backend_schema.md §13.2) are both built and tested. Spatie `laravel-permission` tables exist matching
Backend_schema.md §4.2 exactly (custom migration, not the vendor-published one); `RolePermissionSeeder` seeds
the nine PRD §3.3 roles and translates the full capability matrix into 16 permissions with `refund.initiate`/
`refund.approve` deliberately split across roles to make the separation-of-duties rule structural, not just
policed. Five policies exist (`User`, `ApplicantProfile`, `VisaType`, `VisaFee`, `ServiceLocation`) and the
first of the five permanently-green tests — policy coverage — is live at
`tests/Feature/Authorization/PolicyCoverageTest.php`: it enumerates every model under `app/Models`, exempts
only pure reference/config tables, and fails if any other model lacks a registered policy, so every future
sensitive model added in later stages is caught automatically if its policy is forgotten. 221 tests as of S2.7,
TDD throughout (every test written and watched fail before the code that makes it pass existed). Laravel 12 is scaffolded with the full locked dependency set (adjusted for Hostinger single-host — see
Architecture below), Pest 4/Pint/Larastan level 6 all installed and passing, Tailwind 3.4 wired to the Content
Guidelines tokens, Sentry installed and wired into exception handling. **Live at
`https://visa.geninnovations.net`**, deployed to Hostinger and verified against the real production database —
see `docs/Deployment_runbook.md` for the full setup. **Redeployed from scratch 2026-08-20** after a WordPress
reinstall for the main domain turned out to reset the entire hosting account (SSH keys, the old app checkout,
cron jobs, and the database all wiped or recreated — see runbook §0). The topology changed in the process: the
app root and web root are now the **same directory**
(`~/domains/geninnovations.net/public_html/visa`), protected by a root `.htaccess` that rewrites every request
into `public/` — replacing the earlier split-directory-plus-hand-written-`index.php` approach (Hostinger still
can't redirect a subdomain's document root to `public/` directly; the `.htaccess` rewrite gets the same
no-sensitive-file-exposure property a different way, verified empirically with curl post-deploy, not assumed).

**The production database is MariaDB 11.8.8, not literally MySQL 8.0.x.** Discovered while wiring up local
dev/testing (2026-08-16), not previously known. Verified directly against this instance and confirmed
wire-compatible for everything `Backend_schema.md` relies on: `CHECK` constraints (enforced), the generated-
column partial-unique-index emulation pattern, `SIGNAL SQLSTATE` triggers, and both the `ascii_bin` and
`utf8mb4_0900_ai_ci` collations the schema specifies. **One real difference**: MariaDB stores `JSON` columns as
`LONGTEXT` with a `CHECK(json_valid())` rather than MySQL 8's native binary JSON type — functionally
compatible (JSON_EXTRACT etc. all work), just without MySQL 8's binary storage optimization underneath. Treat
this as the authoritative engine going forward, not an aspiration to reconcile back to literal MySQL 8.

**Settled 2026-08-20 (after real back-and-forth — this is the final state, not a waypoint): local dev and the
Pest test suite point at the real Hostinger MariaDB database**, the same one the production server uses. This
machine cannot run a local engine that enforces `CHECK` constraints: MAMP Pro's bundled MySQL 8.0 refuses to
start on this specific Mac (MacBook Pro Retina 15", Mid 2015, Haswell i7 — the CPU comfortably meets MySQL 8's
requirements, so this is almost certainly the same class of problem as Homebrew and Herd's PHP 8.3 breaking
here: macOS 12.5.1 is behind what current prebuilt binaries assume, not a hardware limit), and Docker Desktop
was judged likely to hit the identical wall, so it wasn't attempted. MAMP's MySQL 5.7 remains installed but
unused for this project — it doesn't enforce `CHECK` constraints (need 8.0.16+), which silently invalidated a
real chunk of test coverage when tried (see the `databaseEnforcesCheckConstraints()` mechanism this produced,
below — now moot for local runs but left in place since it's harmless and self-disables automatically).
**Consequence to hold onto:** local dev/test and production are no longer isolated — `migrate:fresh` locally
now wipes the live server's data too, since there's only one database. Treat this database with production
care from local sessions, not test-database carelessness. Suite runtime is ~11 minutes (network round-trips
to Hostinger) — slow, but was the deliberate trade for full `CHECK`-constraint fidelity without fighting this
machine's binary-compatibility issues further. `.env`'s `DB_HOST` has changed hostnames multiple times across
this project (`srv683` → `srv1331` → `srv1130`) — if connections start failing, verify the current hostname in
hPanel → Databases → Remote MySQL rather than assuming the value in `.env` still holds.
- The `databaseEnforcesCheckConstraints()` Pest helper (`tests/Pest.php`) and the `->skip(...)` guards on tests
  that assert a `CHECK` violation throws `QueryException` were built for the MAMP MySQL 5.7 phase and are
  **no-ops now** (MariaDB enforces `CHECK`, so the guard's condition is always false and nothing skips) — left
  in place rather than stripped out, since they cost nothing and would matter again if local ever moves to a
  non-enforcing engine. **Real bug this surfaced once MariaDB actually started enforcing these checks**:
  `login_attempts`' `chk_login_failure` CHECK, copied verbatim from Backend_schema.md, had a three-valued-logic
  gap — `NULL IN (...)` evaluates to `NULL` not `FALSE` in SQL, so a failed attempt with a null
  `failure_reason` silently passed the constraint as originally worded. Fixed by adding an explicit
  `failure_reason IS NOT NULL` clause. Worth checking Backend_schema.md's other multi-branch CHECK constraints
  for the same pattern if any of them ever get copied into a migration and start throwing surprising results.
- **Hard resource ceiling discovered 2026-08-20**: this DB user (`u508116592_visatest`) has a Hostinger-enforced
  cap of **500 connections/hour**. A long working session running the full suite repeatedly (each run opens a
  fresh connection per test under `RefreshDatabase`) exhausted it, producing a wall of
  `SQLSTATE[HY000] [1226] User ... has exceeded the 'max_connections_per_hour' resource` failures partway
  through a run — every test after the quota was hit fails this way regardless of whether the code is correct.
  **This is a hard quota, not flakiness** — recognize it by that exact error text and stop re-running the full
  suite; it won't recover until the rolling hourly window clears. Prefer targeted `--filter=` runs on the
  specific file(s) you're actively changing over routine full-suite runs, and reserve full-suite passes for
  real checkpoints, not every micro-change — each full run costs a meaningful fraction of the hourly budget.
- This machine's Herd-managed PHP 8.3 (and 8.2) binaries are broken (`dyld` symbol error) — only the default
  PHP 8.4 works. Composer is pinned to resolve as if PHP 8.3 (`config.platform.php` in `composer.json`) so
  package versions are correct, but `artisan`/`composer` commands actually execute under 8.4 locally until
  Herd's PHP 8.3 build is repaired (Herd → PHP → 8.3 → Reinstall, on the user's side, not touched here). The
  Hostinger server's PHP is genuinely 8.3.30, so this only affects local development fidelity, not production.
- `SENTRY_LARAVEL_DSN`, `STRIPE_KEY`/`STRIPE_SECRET`/`STRIPE_WEBHOOK_SECRET`, and `AWS_ACCESS_KEY_ID`/
  `AWS_SECRET_ACCESS_KEY`/`AWS_BUCKET` are all blank in both local and production `.env` — no Sentry project,
  Stripe test keys, or AWS account exist yet. `FILESYSTEM_DISK` stays `local` until AWS S3 is wired in.
- Fonts (Public Sans, Source Serif 4, IBM Plex Mono) are referenced in `tokens.css` with system-font fallbacks
  but not yet self-hosted as actual font files — deferred to when real UI components get built (Stage 2/3).
- Remote MySQL access on the production database is currently open to any host (`%`) — a deliberate, temporary
  choice since it's explicitly the test database with no real applicant data, and local dev's public IP is
  dynamic (observed changing mid-session). **Must be scoped to specific IPs before anything production-like
  touches it.** Note: this wildcard entry was believed set from earlier in the project but had in fact never
  been saved in hPanel — confirmed and actually created 2026-08-20 (hPanel → Databases → Remote MySQL →
  "Any Host" checkbox) after several access-denied failures traced it back. **Later the same day**, a full
  account reset (Deployment_runbook.md §0) recreated the database and its user from scratch — new password,
  new physical host (`srv1130.hstgr.io`, having earlier been `srv683`/`srv1331`) — while the `%` entry itself
  survived unchanged. So "access denied" from this account has now had at least three different real causes
  across this project (allowlist not actually saved; dynamic client IP; credentials silently reset) that all
  produce the identical MySQL 1045 error. **Don't assume which one it is** — check the DB user's `Created at`
  date in hPanel (recent = credentials were reset) and the hostname shown on the Remote MySQL page before
  re-diagnosing from scratch.
- `storage:link` is not run in production — `exec()` is disabled on the Hostinger PHP environment, and this
  project doesn't need it anyway (FR-DM-07/BR-09: documents are never served via the public-disk symlink
  pattern, only authorized controller streams with signed URLs).

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

### `Applicant`/`Agent`/`Staff` subclasses — a recurring pitfall, watch for it

`App\Models\Applicant`, `Agent`, and `Staff` (S2.6) are thin `User` subclasses — same `users` table, each with a
permanent global scope on `user_type` — that exist purely so each auth guard's provider only ever resolves its
own account type (Backend_schema.md §11.1's actual security boundary). They are **not independent entities**,
but PHP/Eloquent doesn't know that, and by 2026-08-21 this exact pattern had broken working code **four
separate times**, always the same shape: some mechanism infers an identity/key from the *runtime* class
(`Applicant`/`Agent`/`Staff`) instead of the *conceptual* one (`User`), so a lookup made through a different
class than the one that wrote the row silently finds nothing:

1. **Spatie's guard-guessing** matches a model class to a `config('auth.providers')` entry by exact string —
   broke for a plain `User` instance once providers pointed at the subclasses. Fixed with Spatie's own
   `protected string $guard_name = 'web';` property on `User`.
2. **`getMorphClass()`** defaults to `static::class` — would have silently split Spatie's `model_type` and
   notifications' `notifiable_type` depending on which subclass touched a row last. Fixed by overriding
   `getMorphClass()` on `User` to return `self::class` (not `static::class` — the distinction is the whole
   fix), so every subclass reports `User::class` regardless of which one is actually calling it.
3. **Default foreign-key inference** on `hasOne`/`hasMany`/`belongsTo` uses `Str::snake(class_basename($this))`
   — also the runtime class. `User::mfaMethod()`/`mfaRecoveryCodes()` silently looked for `staff_id`/
   `applicant_id` instead of `user_id` when called through a subclass, until both relations were given an
   explicit foreign key.
4. **Test assertions** (`assertAuthenticatedAs`, `Notification::fake()`'s `assertSentTo`) compare by exact
   class too — a test creating a plain `User` fixture but asserting against what a guard-scoped controller
   actually logged in/notified (a `Staff`/`Applicant`/`Agent` instance) fails not because the code is wrong,
   but because the assertion is comparing the wrong concrete class. Fix is in the test: re-fetch via the
   subclass (e.g. `Staff::query()->find($user->id)`) before asserting.

**The pattern to apply going forward**: any new relation, cast, or third-party integration touching `User`
should be checked against this same failure mode before it ships, not discovered by a cascading test failure
after the fact. When something can't be made subclass-safe generically (case 4), fix it locally in the test/
call site and leave a comment explaining why — don't be surprised if it recurs in S2.8+ (audit logging,
notifications, anything polymorphic or key-inferring touching `users`).

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
