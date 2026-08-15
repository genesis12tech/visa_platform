# Implementation Plan
## Visa Application System — initialisation to production

| Field | Value |
|---|---|
| **Version** | 1.0 |
| **Date** | 13 August 2026 |
| **Supersedes** | *Phase-Gated Implementation Prompts v1.0* (stale on four counts — see §1.4) |
| **Shape** | Thin foundation, then six vertical slices |
| **Concurrency** | Strictly sequential, with two identified safe parallel branches |
| **Deployment** | Both tiers live from Stage 1; every stage deploys |
| **Stages** | 12 · **Steps** 74 · **Indicative duration** 24–30 weeks to pilot |

---

## 1. Plan Shape and Rationale

### 1.1 Why foundation-then-slices

| Approach | Why not chosen |
|---|---|
| Pure vertical slice from step one | Auth, RBAC, policies, money, audit, and the trigger set are prerequisites for *every* slice. Building them opportunistically inside slice one means retrofitting them across slices two to six. |
| Pure layer-by-layer | All integration risk lands at the end. On a split Vercel + worker deployment with a community-maintained PHP runtime, that is where projects die. |
| **Foundation, then slices** | Foundation is deliberately thin — only what two or more slices need. Each slice then ships end-to-end and is independently deployable and demonstrable. |

**The foundation admits only what two or more slices require.** Anything used by a single slice belongs to that slice. This rule is what keeps the foundation from swelling into a horizontal layer.

### 1.2 Why strictly sequential

Guard single-sourcing is the correctness backbone of this system: `SubmissionGuard` and `ApprovalGuard` are each consumed by both a controller and a Livewire view, and the Screen UI Specs require they never diverge. Parallel tracks generate merge conflicts precisely at those files.

Practically, one person reviewing Claude Code output cannot meaningfully review two streams. The bottleneck is review quality, not typing speed, and parallelism does not relieve it.

**Two safe parallel branches** if a second person joins — both touch nothing on the critical path:

| Branch | Can start after | Touches |
|---|---|---|
| **P-A · Reference-data admin CRUD** | Stage 2 | `countries`, `currencies`, `visa_types`, `document_types`, `service_locations`, `rejection_reasons` admin screens |
| **P-B · Reporting and exports** | Stage 7 | Metrics tables, aggregation jobs, dashboards, export pipeline |

### 1.3 Why day-one deployment changes step one

The first deployable artifact is **not** a login screen. It is a walking skeleton proving the split topology works: a request served by Vercel that queues a job processed by the worker host, with both tiers deployed from the same commit.

This front-loads the two highest-severity risks in the Tech Stack register:

- **R-1** — `vercel-php` is community-maintained, single-maintainer, with no published GitHub releases and no SLA
- **R-2** — code skew between tiers, where a job serialised on one tier fails to deserialise on the other

Both are trivially cheap to discover in week one and catastrophic to discover in week twenty, when six slices of domain code sit on top of an assumption that never held.

### 1.4 What changed since the prompts document

| Change | Source | Effect on this plan |
|---|---|---|
| PK reversal — `BIGINT` internal + `ULID` public | Backend Schema D-2 | Every migration step |
| No Filament — Blade + Livewire throughout | Tech Stack D-3 | Admin and officer screens hand-built; ~1.5–2× effort on those stages |
| Strict MVC — six approved services, no `app/Domain/` | Tech Stack D-4 | Session primer and every code step |
| MySQL 8 — no partial indexes, `json` not `jsonb` | Tech Stack D-1 | Generated-column patterns; trigger set |
| Money resolved — integer minor units | Backend Schema D-1 | Stage 2 |
| **Notification spine moved forward** | This plan | Slices dispatch real notifications immediately rather than leaving stubs to grep for later |

**On that last one.** The prompts document deferred all notifications to M8 and required finding every stub by grep. Stubs left across six slices reliably produce two or three that are never replaced. Building a minimal notification spine in Stage 2 means every slice wires real delivery from the start, and Stage 9 hardens rather than retrofits.

---

## 2. Stage Map

```
S0  Ground              ── decisions + provisioning        1 wk    no code
S1  Walking skeleton    ── both tiers live, CI/CD          1–2 wk  ◆ DEPLOY
S2  Foundation          ── schema, auth, RBAC, spines      3–4 wk  ◆ DEPLOY
    ├─────────────────────────────────── P-A may start here
S3  Slice A · Apply     ── draft → form → submit           3–4 wk  ◆ DEPLOY
S4  Slice B · Documents ── upload → scan → serve           2–3 wk  ◆ DEPLOY
S5  Slice C · Payment   ── fee → Stripe → webhook          2–3 wk  ◆ DEPLOY
S6  Slice D · Review    ── queue → case → decision         3–4 wk  ◆ DEPLOY
S7  Slice E · Appoint.  ── slots → booking → roster        2–3 wk  ◆ DEPLOY
    ├─────────────────────────────────── P-B may start here
S8  Slice F · Agents    ── vetting → linkage → portal      2–3 wk  ◆ DEPLOY
S9  Comms hardening     ── notifications, PDFs, retries    1–2 wk  ◆ DEPLOY
S10 Reporting           ── metrics, dashboards, exports    2 wk    ◆ DEPLOY
S11 Hardening           ── security, a11y, load, pen test  3–4 wk  ◆ DEPLOY
S12 Pilot → GA          ── one visa type, one location     4 wk    ◆ PROD
                                                    ────────────
                                              24–30 weeks to pilot
```

Estimates assume one developer working with Claude Code, reviewing every output. They are indicative and should be re-baselined after Stage 3, which is the first stage with enough real work to calibrate against.

### 2.1 Dependency graph

```
S0 ──▶ S1 ──▶ S2 ──┬──▶ S3 ──▶ S4 ──▶ S5 ──▶ S6 ──▶ S7 ──▶ S8 ──▶ S9 ──▶ S11 ──▶ S12
                   │                                  │
                   └──▶ [P-A admin CRUD] ─────────────┘
                                                      └──▶ [P-B / S10 reporting] ──▶ S11
```

Hard ordering constraints, each for a concrete reason:

| Must precede | Because |
|---|---|
| S4 Documents → S3 Apply | `SubmissionGuard` gains document blockers; building it twice is the failure mode |
| S5 Payment → S3 Apply | Fee is resolved and frozen at submission |
| S6 Review → S4, S5 | `ApprovalGuard` aggregates document *and* payment state |
| S7 Appointments → S6 | `ApprovalGuard` gains the appointment blocker |
| S8 Agents → S3–S7 | Agent portal mirrors the full applicant surface; building it earlier means rebuilding it |
| S11 Hardening → all slices | Penetration testing needs the complete attack surface |

---

## 3. Stage 0 — Ground

**Goal** Remove every decision that would otherwise block mid-build, and provision infrastructure.
**Duration** ~1 week · **Code written** none

### Steps

**S0.1 · Close the blocking decisions.** Six are genuinely blocking; the rest can run behind the build.

| ID | Decision | Blocks | Recommendation carried in the docs |
|---|---|---|---|
| OD-13 | Data residency jurisdiction | All provisioning | — |
| SCH-7 | `READ-COMMITTED` isolation confirmed with the provider | Schema creation | Adopt; §14.1 gives the reasoning |
| SCH-6 | Accept the blind-index equality leak, or drop duplicate detection | `applicant_profiles` | Accept, with the pepper in the secret store |
| TS-3 | Vercel plan tier — `maxDuration: 60` needs Pro | Deployment | — |
| TS-5 | Virus scanning: self-hosted ClamAV or external API | S4 | ClamAV on the worker; no third-party data egress |
| OD-4 | Launch language set — decides whether RTL is in scope | Content Guidelines CG-1 | — |

**S0.2 · Provision infrastructure.** Managed MySQL 8.0 and Redis 7.2 in the residency jurisdiction; private S3-compatible bucket with public access blocked at the account level; worker host (Ubuntu 24.04, PHP 8.3 with `gd`); Vercel project; Stripe account in test mode; error monitoring; transactional email provider.

**S0.3 · Secrets and access.** Managed secret store, `APP_KEY` generated, blind-index pepper generated, database credentials issued with least privilege, deploy keys created.

**S0.4 · Repository.** Single repository serving both tiers. Branch protection on `main`. `CLAUDE.md` seeded from Tech Stack §9.3 with the strict-MVC block.

**Exit criteria** — every row of the S0.1 table is closed; a `mysql` client connects from the worker host; a test object writes to the bucket and is *not* publicly retrievable.

---

## 4. Stage 1 — Walking Skeleton ◆ first deployment

**Goal** Prove the split topology end to end before any domain code exists.
**Duration** 1–2 weeks · **Entry** S0 complete

### Steps

**S1.1 · Laravel 12 scaffold.** Exact versions from Tech Stack §4.1. Pest 4 (not 5 — it requires PHP 8.4). Pint, Larastan level 6. Tailwind 3.4 with the *replaced* theme from Content Guidelines §2.8, so off-token values cannot compile.

**S1.2 · Vercel deployment.** `api/index.php` forwarder, `vercel.json` pinned to `vercel-php@0.7.4` — the PHP 8.3 build. Cache paths redirected to `/tmp`. `trustProxies(at: '*')` in `bootstrap/app.php`, without which rate limiting keys on the proxy IP and signed URLs break.

**S1.3 · Worker host deployment.** Same commit, Supervisor running Horizon, cron running the scheduler under a distributed lock.

**S1.4 · The skeleton test — the point of this stage.**

```
A route on Vercel dispatches a job to the `default` queue.
The job runs on the worker host and writes a row to MySQL.
A second route on Vercel reads that row back.

This must pass in the deployed environment, not locally.
```

If this fails, stop and resolve it. Every later stage assumes it works.

**S1.5 · CI/CD.** One pipeline, both targets, same commit, with a deploy gate that blocks when the two tiers' SHAs diverge (Tech Stack R-2). Pipeline: `composer install` → Pint → Larastan → Pest → migrate → deploy Vercel → deploy worker → skeleton smoke test.

**S1.6 · Version lock enforcement.** The drift check from Tech Stack §12.2, including the `grep` asserting `vercel-php@0.7.4` in `vercel.json`. That string has no dependency resolver behind it; nothing else prevents a silent jump to PHP 8.5.

**S1.7 · Observability baseline.** Error monitoring on both tiers, structured logging with correlation IDs, health-check endpoints, Horizon dashboard restricted to authenticated super admins.

### Exit criteria — the deployment gate

- [ ] Skeleton test passes **in the deployed environment**
- [ ] CI deploys both tiers from one commit; SHA divergence blocks
- [ ] Version drift check fails the build when a version moves
- [ ] Cold-start p95 measured and recorded as the baseline
- [ ] `APP_DEBUG=false`; no stack traces reachable

> **The R-1 rehearsal.** Before leaving this stage, serve HTTP from the worker host once and confirm the application runs there unmodified. That is the fallback if the community runtime breaks, and it is far better rehearsed now — when it takes an hour — than during an incident.

---

## 5. Stage 2 — Foundation ◆ deploy

**Goal** Everything two or more slices depend on. Nothing else.
**Duration** 3–4 weeks

### Steps

**S2.1 · Schema conventions and the Money value object.** `BIGINT` PK + `ULID` public column pattern; a base migration helper; `Money` immutable value object with bcmath, `allocate()` distributing remainder minor units deterministically, and a `MoneyCast` for `(amount_minor, currency)` pairs. Tests must cover JPY — a zero-decimal currency is where naive money objects break.

**S2.2 · Reference data.** `currencies`, `countries`, `visa_types`, `visa_fees`, `document_types`, `rejection_reasons`, `service_locations`, `holidays`, `visa_type_document_requirements`. Seed 20 countries, 4 visa types, 8 fee rules including one nationality-specific and one expired.

**S2.3 · `FeeResolver` — the first approved service.** Deterministic resolution with specificity precedence, inclusive `valid_from` / exclusive `valid_until` boundaries, and explicit `AmbiguousFeeRuleException`. Ambiguity is a configuration error surfaced to an administrator, never a silent pick.

**S2.4 · Users, profiles, and encryption.** `users`, `applicant_profiles`. Passport number encrypted via Laravel cast plus the peppered `passport_number_hash` blind index. Assert in a test that a raw SQL read returns ciphertext.

**S2.5 · Roles, permissions, policies.** Spatie tables, nine roles seeded with the PRD capability matrix. Policies on every sensitive model. **Ship the policy-coverage test here** — it enumerates models and asserts each sensitive one has a registered policy. It is one of the five permanently-green tests.

**S2.6 · Authentication.** Three guards on three hosts with three cookie names. Registration, verification, login, password reset. `login_attempts` recording unknown emails with `NULL` `user_id` — that is what makes credential-stuffing visible.

**S2.7 · MFA for staff.** TOTP, recovery codes, `EnsureMfaEnrolled` middleware admitting staff without MFA to enrolment routes only.

**S2.8 · Audit logging.** `audit_logs` with append-only triggers. `AuditLogger` service capturing actor, on-behalf-of, IP, user agent, before/after. Wire into auth events and reference-data changes.

**S2.9 · Database constraints and triggers, part one.** The append-only trigger pairs, `CHECK` constraints on tables that exist so far, and a migration guard aborting below MySQL 8.0.16 — below that version `CHECK` parses and is silently ignored, which is the worst possible failure mode.

**S2.10 · Design system components.** The Blade component library from Content Guidelines §5: `button` (with `disabledReason`), `badge`, `alert`, `card`, `field-group`, inputs, `error-summary`, `empty-state`, `modal`, `progress-bar`, `skeleton`. Livewire `Toast` and `SessionWarning`. Tokens, high-contrast mode, print stylesheet.

**S2.11 · Notification spine.** Base notification classes, mail and database channels, `notification_templates`, queue routing to `emails`. Two real notifications (email verification, password reset) proving the path. **Every later slice dispatches real notifications from the start.**

**S2.12 · PDF spine.** Dompdf on the worker, a base PDF job writing to private storage, an authorised and audited download route. One real PDF proving the path.

**S2.13 · Applicant portal shell.** Layout, four-tab bottom bar, top nav, skip link, session-warning modal with autosave firing *before* the modal renders.

### Exit criteria

- [ ] Policy-coverage test green
- [ ] Money tests pass including JPY and allocation remainders
- [ ] Fee resolution deterministic across the boundary matrix
- [ ] Passport ciphertext at rest verified by raw SQL
- [ ] Staff cannot reach any panel route without MFA
- [ ] Audit rows cannot be updated or deleted (trigger test)
- [ ] Components verified at 320px, keyboard-only, and in forced colours
- [ ] Deployed; skeleton test still green

---

## 6. Stage 3 — Slice A · Apply ◆ deploy

**Goal** An applicant registers, completes a form, and submits. No documents, no payment.
**Duration** 3–4 weeks

### Steps

**S3.1 · Form templates.** `form_templates` with the `active_visa_type_id` generated column enforcing one active template per visa type — MySQL has no partial indexes. `FormSchemaValidator` returning errors by JSON path. The published-immutability trigger.

**S3.2 · Applications and tracking numbers.** `visa_applications` with all seven secondary indexes. `ApplicationStatus` enum with the 14 cases and public mapping. Tracking number generator using Crockford base32 excluding I, L, O, U. Verify 100,000 generated values for uniqueness and absence of inferable ordering.

**S3.3 · Answers and section status.** `application_answers` with its natural unique key. Section status derivation including `needs_attention` — the state where a later answer reveals a mandatory field in an earlier section. It is the most-missed case in form engines and needs an explicit test.

**S3.4 · State machine and history.** Transition table, `application_status_histories` with append-only triggers, `public_status` sync trigger. **Ship the transition-matrix test here** — the second permanently-green test.

**S3.5 · `SubmissionGuard` — the second approved service.** Returns typed blockers. Sections only for now, with a documented extension point for documents (S4) and scan state.

> **This is the single most important architectural moment in the build.** `SubmissionGuard` must be the sole source of submission blockers. The hub view consumes it; the submit controller consumes it. A second implementation anywhere means the applicant eventually sees an enabled Submit button that fails.

**S3.6 · `ApplicationSubmitter` — the third approved service.** One transaction: guard, resolve and freeze the fee, write the immutable snapshot, transition to `submitted` then `payment_pending`, audit, dispatch events.

**S3.7 · Application hub (APP-05).** Screen UI Specs §2. One Livewire component; section cards are Blade partials. Disabled Submit lists every blocker with working links. Locked sections name their blocker.

**S3.8 · Section editor (APP-06).** Autosave on blur and 30s idle, conditional visibility with value retention, save-failure escalation to "Copy my answers" on third consecutive failure.

**S3.9 · Review and declaration (APP-09).** Late re-validation. Declaration never pre-ticked, never persisted across sessions.

**S3.10 · Admin form template editor (ADM-09).** Three panes, JSON-path validation, structural diff, preview rendering through the *same* components as APP-06.

**S3.11 · Applications list, home, profile.** APP-01, APP-02, APP-25 through APP-27.

### Exit criteria

- [ ] Register → draft → complete → submit works in the deployed environment
- [ ] Transition-matrix test green
- [ ] Snapshot unchanged after mutating the live profile
- [ ] Fee frozen at submission; later fee-rule changes do not affect it
- [ ] Hub blocker list **equals** `SubmissionGuard` output across a state matrix
- [ ] No input lost by session expiry, navigation, back button, or failed save
- [ ] Double submission produces exactly one submission
- [ ] Hub renders in a fixed query count regardless of section count

---

## 7. Stage 4 — Slice B · Documents ◆ deploy

**Goal** Upload, scan, serve securely, review.
**Duration** 2–3 weeks

**S4.1 · Requirements resolution.** Conditional rules evaluated against answers; unresolvable conditions return the blocking section rather than guessing.

**S4.2 · Storage and versioning.** `application_documents`, `document_versions`. MIME validated by **content inspection**, not extension — a `.exe` renamed `.pdf` must be rejected before storage. SHA-256 checksum. Generated storage paths containing no user-supplied string.

**S4.3 · Virus scanning.** `DocumentScanner` interface with a fake for tests. Quarantine on infection, alert, block all access.

**S4.4 · Deferred image validation.** Vercel's runtime has **no `gd` and no `imagick`** (Tech Stack §5.3), so dimension checks run on the worker. `image_check_status` makes the deferred outcome first-class. The applicant-facing consequence — a file accepted at upload and rejected moments later — needs its own copy in the document checklist.

**S4.5 · Secure serving.** Policy check, refuse unless scan is clean, **write the audit entry before generating the signed URL**, 5-minute expiry scoped to the requesting user.

**S4.6 · Extend `SubmissionGuard`.** Document blockers added to the existing service. Not a new one.

**S4.7 · Document checklist and detail (APP-07, APP-08).** Nine states. Camera-first on mobile. Error copy interpolating actual sizes and formats. **`infected` renders as "couldn't be processed"** — never a malware disclosure.

### Exit criteria

- [ ] Content/extension mismatch rejected before storage
- [ ] No document served while scanning, infected, or failed
- [ ] Audit written before signed URL generation (ordering asserted)
- [ ] Signed URL unusable after expiry and by any other user
- [ ] Hub blockers still equal `SubmissionGuard` output, now including documents
- [ ] Uploader fully keyboard-operable

---

## 8. Stage 5 — Slice C · Payment ◆ deploy

**Goal** Fee to confirmed payment, idempotently.
**Duration** 2–3 weeks

**S5.1 · Ledger schema.** `payments`, `payment_items`, `payment_webhook_events`, `invoices`, `refunds`. All money in minor units. `chk_payments_refund_bound` makes over-refunding structurally impossible.

**S5.2 · Checkout.** Stripe Checkout Session, idempotency key derived from the payment ULID, application reference in metadata. No card data touches the application.

**S5.3 · `StripeWebhookProcessor` — the fourth approved service.** Signature verified before parsing. Event persisted by `(provider, provider_event_id)`. Already-processed returns 200 immediately. Otherwise dispatched to the `high` queue. Endpoint acknowledges under 500 ms.

> **Highest-risk code in the system.** Review the tests personally. Replay the same event 100 times and assert exactly one ledger entry and one transition.

**S5.4 · Receipts.** Generated from the ledger and invoice, never from live application data.

**S5.5 · Payment screens (APP-11, 13, 14, 15).** APP-13 is the critical one: the state machine from Screen UI Specs §6.2 exactly. Reassurance state after 15s. **`APP-15` only on a definitive gateway failure — never on timeout.** No retry control on the reassurance state; that is how double charges happen.

**S5.6 · Refunds and reconciliation.** Separation of duties enforced at the domain layer *and* by `chk_refunds_four_eyes`. Daily reconciliation with unmatched items as the primary output.

### Exit criteria

- [ ] Webhook replayed 100× → one ledger entry, one transition *(third permanently-green test)*
- [ ] Invalid signature → 4xx, logged, never processed
- [ ] Webhook-first, redirect-first, webhook-never, duplicate — all four orderings correct
- [ ] Delayed webhook produces reassurance, never failure
- [ ] No path from APP-13 initiates a second payment
- [ ] Self-approval of a refund blocked at the domain layer *and* by constraint

---

## 9. Stage 6 — Slice D · Review and Decision ◆ deploy

**Goal** Officers work a queue and decide. Largest slice — no Filament.
**Duration** 3–4 weeks

**S6.1 · SLA as persisted state.** `sla_due_at` and `sla_state` maintained by transition-triggered and nightly jobs. Business-hours arithmetic over the mission calendar. **No elapsed-time computation in the queue query.** Time in `info_requested` excluded from the officer clock — pending AF-5.

**S6.2 · Officer queue (OFF-03).** `rappasoft/laravel-livewire-tables`. Real table semantics, never card collapse on mobile. SLA shown as tone **and** text. Saved views. **Test with 100,000 seeded rows before proceeding.**

**S6.3 · Case record (OFF-05).** Seven tabs. Application tab renders from the **immutable snapshot** with a compare-to-live toggle. History tab has no edit affordance anywhere.

**S6.4 · Document review actions.** Accept, reject with mandatory reason, request resubmission. Preview blocked unless scan clean.

**S6.5 · Information requests (OFF-08, APP-21).** Itemised checklist. An already-complete section does **not** auto-satisfy a request to change it — completion requires modification after the request timestamp.

**S6.6 · `ApprovalGuard` — the fifth approved service.** Aggregates payment, documents, four-eyes. Appointment blocker stubbed with a documented extension point for S7.

**S6.7 · Decisions.** Approve and Reject in one transaction each. **Approve is always visible, disabled with a specific reason** — hiding it leaves officers guessing and generates supervisor escalations.

**S6.8 · Assignment and concurrency.** Self-assign, reassign with reason, optimistic concurrency preserving the officer's typed content on conflict. Losing a half-written rejection reason to a colleague's unrelated edit is the fastest way to lose officer trust.

### Exit criteria

- [ ] Queue loads under 1s p95 at 100k applications
- [ ] Case officer sees only assigned cases (policy test)
- [ ] No path — UI, route, or service — approves with unmet guards
- [ ] Disabled reason **equals** `ApprovalGuard` output
- [ ] Application tab unchanged after mutating the live profile
- [ ] Stale write rejected with typed content preserved
- [ ] **MVP vertical slice now complete end to end** — walk it manually

---

## 10. Stage 7 — Slice E · Appointments ◆ deploy

**Goal** Book, reschedule, record outcomes, without ever overbooking.
**Duration** 2–3 weeks

**S7.1 · Slots and capacity.** Generated from local wall-clock operating hours — never by adding fixed UTC offsets. DST transitions and holidays handled. `chk_slots_booked` as the constraint backstop.

**S7.2 · `AppointmentBooker` — the sixth and final approved service.** Optimistic selection, atomic confirmation: row-lock, re-check, insert, increment, in one transaction. No soft locks — they create phantom unavailability and fail on abandonment.

> **Run the 50-way concurrency test at least 20 times.** Intermittent overbooking is worse than consistent overbooking because it hides.

**S7.3 · Booking screens (APP-16 to APP-19).** Location timezone always labelled; applicant-local shown only when zones differ. **`SlotUnavailableException` handled inline** — keep the selected date, refresh times, warn, move focus. A full-page error here is a specification failure; a slot being taken mid-selection is normal under load.

**S7.4 · Officer scheduling and roster (OFF-09, OFF-13).**

**S7.5 · Extend `ApprovalGuard`** with the real appointment blocker.

**S7.6 · Location and capacity admin (ADM-12, ADM-13).** Capacity reduction below existing bookings blocked by trigger and explained in the UI.

### Exit criteria

- [ ] 50 concurrent bookings on a capacity-1 slot → exactly 1 success, 0 overbooking *(fourth permanently-green test)*
- [ ] Slot taken mid-selection never produces a full-page error
- [ ] DST-transition day generates the correct slot count
- [ ] Approval blocked with a specific reason while a required appointment is incomplete

---

## 11. Stage 8 — Slice F · Agents ◆ deploy

**Goal** Vetted agencies act for consenting applicants, revocably.
**Duration** 2–3 weeks

**S8.1 · Agencies and vetting.** Pending agencies reach only AGT-03 — a real screen with status and timeframe, not a greyed-out dashboard.

**S8.2 · Linkage and consent.** `agent_applicant_links` with the `active_pair_hash` generated column. `chk_links_consent` makes an active link without recorded consent impossible.

**S8.3 · Per-request linkage verification.** Checked in the guard on **every request**, never cached in the session. Revocation takes effect on the agent's very next request. Unlinked access returns 404, not 403 — existence must not be confirmed.

**S8.4 · Agent portal.** Own host, own guard, own cookie. Dashboard, clients, acting-as hub with the persistent non-dismissible banner. **Withdrawal controls absent entirely, not disabled.**

**S8.5 · Multi-application payment.** Each application retains its own payment and invoice. Partial success reports per-application outcomes — never a blanket failure when some succeeded.

### Exit criteria

- [ ] Revocation effective on the next request, no cache window
- [ ] Agent cannot reach an unlinked applicant; response indistinguishable from not-found
- [ ] Agent and applicant sessions fully separate
- [ ] Every agent action audited to the individual user plus the agency
- [ ] Acting-as banner present on every application-context screen, non-dismissible

---

## 12. Stage 9 — Communications Hardening ◆ deploy

**Goal** Complete and harden what the spine started.
**Duration** 1–2 weeks

**S9.1 · Complete the notification set.** Every event in App Flow §11. Because the spine landed in S2, this is completion rather than stub replacement.

**S9.2 · Deep links.** Every notification lands on login with a return-to when the session has ended, then completes to the intended screen. No content exposed to an unauthenticated request.

**S9.3 · Complete the PDF set.** Summary, receipt, appointment letter, decision letter. Decision letter and summary from the **immutable snapshot**; receipt from the ledger.

**S9.4 · Notification centre (APP-23, APP-24, AGT-15).**

**S9.5 · Delivery hardening.** Retry with backoff, bounce handling, failure alerting.

### Exit criteria

- [ ] Decision letter regenerated after mutating the live profile is byte-identical *(legal defensibility evidence — verify directly)*
- [ ] No notification body contains a raw ID or sensitive value
- [ ] A permanently failing email is alerted and does not block application progression
- [ ] Every App Flow §11 deep link resolves correctly

---

## 13. Stage 10 — Reporting and Exports ◆ deploy

**Goal** Dashboards that do not touch transactional tables.
**Duration** 2 weeks · *Parallel branch P-B may cover this*

**S10.1 · Metrics read models.** Four tables, idempotent jobs, re-runnable per date via natural-key upsert.

**S10.2 · Dashboards.** Officer and admin widgets, all reading read models. Every widget has an empty state, including the positive "Your queue is clear".

**S10.3 · Exports.** Queued, authorisation-scoped, field-redacted, private storage with 7-day expiry, `export_logs` recording who, what, when, from which IP, with which filters.

### Exit criteria

- [ ] Re-running a date produces identical rows, not duplicates
- [ ] Metrics match direct aggregation over the same seeded data
- [ ] No dashboard query touches `visa_applications` directly
- [ ] Dashboards render under 2s with a year of production-scale data
- [ ] Export contents differ correctly by requesting role

---

## 14. Stage 11 — Hardening ◆ deploy

**Goal** Meet the non-functional requirements as acceptance criteria, not aspirations.
**Duration** 3–4 weeks

**S11.1 · Rate limiting.** Every endpoint in PRD NFR-S-03. Public tracking throttled by IP **and** tracking hash independently, with a **fixed minimum response time** — without it, a lookup that skips the email send returns measurably faster and timing alone reveals which tracking numbers are real.

**S11.2 · Security headers and response parity.** HSTS, CSP without inline-script exemptions, and 403/404 indistinguishable for forbidden resources.

**S11.3 · Penetration test.** External vendor against the complete surface. Remediate all high and critical findings.

**S11.4 · Accessibility audit.** WCAG 2.2 AA across applicant and agent surfaces. Automated contrast and label checks in CI. Manual keyboard-only and screen-reader walkthrough of the full journey. **Pay attention to the 2.2 additions** — *Dragging Movements* and *Accessible Authentication* both bite this application directly, at the file uploader and the tracking OTP.

**S11.5 · Load testing.** Tracking, queue, upload, submission, exports at v1 volumes then at headroom.

**S11.6 · Backup and restore.** Perform and time an actual restore. A documented intention is not a tested backup.

**S11.7 · Runbooks.** Webhook replay, stuck jobs, quarantined documents, reconciliation discrepancies, restore, and the **R-1 fallback to worker-host HTTP** — rehearsed once, not just written.

**S11.8 · Retention jobs.** Per Backend Schema §14.4, once OD-3 is closed.

### Exit criteria

- [ ] Zero WCAG 2.2 AA violations on applicant and agent surfaces
- [ ] Wrong tracking number and wrong OTP identical in response **and timing**
- [ ] All high and critical penetration findings remediated
- [ ] p95 targets met at v1 volume
- [ ] A restore has actually been performed and timed
- [ ] Every runbook walked through once

---

## 15. Stage 12 — Pilot and General Availability

**Duration** ~4 weeks

**S12.1 · Pilot scope.** One visa type, one service location, limited applicant cohort.

**S12.2 · Baseline capture.** Every PRD §2.2 metric has a baseline before targets mean anything.

**S12.3 · Pilot exit criteria.** 200 applications through the full lifecycle · zero unexplained reconciliation variances · no critical security findings · every metric baselined.

**S12.4 · Broaden.** Additional visa types are **configuration, not code** — if a new visa type requires code, that is a design defect to fix before GA.

**S12.5 · GA readiness.** Checklist mapping every PRD §10 non-functional requirement to evidence rather than assertion.

---

## 16. Working Rules

### 16.1 Per-step definition of done

```
[ ] Feature works in the DEPLOYED environment, not only locally
[ ] Pest tests written and passing
[ ] Pint and Larastan clean
[ ] Every new model has a registered policy
[ ] Every controller method authorises
[ ] All money through Money; no floats anywhere
[ ] All state transitions transactional
[ ] Audit entries written for sensitive actions
[ ] UI uses only design-system components and tokens
[ ] Copy from Content Guidelines §7 or a translation key
[ ] Verified at 320px, keyboard-only, forced colours
[ ] The five permanently-green tests still green
```

### 16.2 The five permanently-green tests

| # | Test | Lands |
|---|---|---|
| 1 | Policy coverage — every sensitive model has a policy | S2.5 |
| 2 | Transition matrix — exactly the permitted transitions succeed | S3.4 |
| 3 | Webhook replay — 100 replays, one ledger entry | S5.3 |
| 4 | Booking concurrency — 50 concurrent, one success, zero overbooking | S7.2 |
| 5 | Guard parity — UI blockers equal domain guard output | S3.5, extended S4.6, S6.6, S7.5 |

**If any goes red, stop feature work until it is green.** Each one guards a failure class that is silent in production and expensive to discover from a user report.

### 16.3 Gate discipline

Every stage boundary is a gate. Do not begin the next stage until every exit criterion passes.

| When a gate fails | Do |
|---|---|
| A test fails | Fix within the current stage. Never carry a red test forward. |
| A criterion is unmeetable as specified | Stop; amend the specification document; then proceed. Do not silently reinterpret. |
| An estimate overruns | Continue; re-baseline the remaining stages. Do not descope exit criteria to hit a date. |
| A blocking decision surfaces mid-stage | Halt that step, resolve it, resume. Assumed answers to blocking questions are how rework is created. |

### 16.4 Deployment cadence

Every stage ends with a deployment. Within a stage, deploy at least weekly. A branch that has not been deployed in a week is accumulating undiscovered integration risk — which is exactly the risk day-one deployment was chosen to eliminate.

---

## 17. Risks Against the Sequence

| # | Risk | Stage | Mitigation in the plan |
|---|---|---|---|
| 1 | `vercel-php` breaks or is abandoned | Any | R-1 fallback rehearsed at S1; worker host serves HTTP unmodified |
| 2 | Code skew between tiers | Any | Single pipeline, SHA-divergence deploy gate (S1.5) |
| 3 | Filament removal underestimated | S6, S10 | 1.5–2× multiplier already in the estimates; re-baseline after S6.2 |
| 4 | Guard logic duplicated between UI and domain | S3–S7 | Guard-parity test extended at every slice |
| 5 | Overbooking under real concurrency | S7 | 50-way test run 20× before the gate |
| 6 | Officers reject the hand-built queue | S6 | Demo OFF-03 to a real officer at the S6 gate, before S7 |
| 7 | Accessibility failures found at S11 | S11 | Verified per component at S2.10 and per screen at every slice gate |
| 8 | Strict MVC drifts to fat controllers | S3+ | Six-service cap; a seventh triggers an architecture conversation |
| 9 | Blocking decisions answered by assumption | Any | S0.1 closes six; §16.3 halts on new ones |
| 10 | Estimates prove optimistic | All | Re-baseline after S3, the first stage large enough to calibrate |

---

## 18. Open Items

| ID | Item | Needed by |
|---|---|---|
| IP-1 | Six S0.1 decisions closed | S0 exit |
| IP-2 | OD-3 statutory retention | S11.8 |
| IP-3 | OD-6 tax treatment | S5.1 |
| IP-4 | OD-7 refund policy percentages | S5.6, Content Guidelines §7.5 |
| IP-5 | AF-5 — does a reschedule reset the SLA clock? | S6.1, S7.2 |
| IP-6 | Mission working calendar and holidays seeded | S6.1 |
| IP-7 | Whether a second person joins, activating P-A / P-B | S2 exit |
| IP-8 | Penetration test vendor engaged | Before S11.3 |
| IP-9 | Pilot cohort identified and consented | Before S12.1 |
