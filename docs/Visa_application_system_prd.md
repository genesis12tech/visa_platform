# Product Requirements Document
## Visa Application System (VAS)

| Field | Value |
|---|---|
| **Document version** | 1.0 (Draft for review) |
| **Date** | 12 August 2026 |
| **Status** | Awaiting stakeholder sign-off |
| **Product owner** | *[To be assigned]* |
| **Engineering lead** | Terry |
| **Source material** | *Secure Laravel Architecture Blueprint — Visa Application System, v1.0 (May 2026)* |
| **Operating model** | Government / embassy — issuing authority staff operate the officer and admin surfaces |
| **Target market** | Global applicants; Stripe as the payment provider |
| **v1 scope additions** | Appointments & biometrics scheduling; agent / representative accounts |

### Revision history

| Version | Date | Author | Change |
|---|---|---|---|
| 1.0 | 2026-08-12 | Product | Initial PRD derived from the architecture blueprint |

---

## 1. Executive Summary

### 1.1 Purpose

This document defines **what** the Visa Application System must do, for whom, and to what standard. It translates the existing architecture blueprint (which defines *how* the system is built) into product requirements that can be reviewed by consular leadership, legal/compliance, finance, and operations before engineering commits to a build.

The architecture blueprint remains the authoritative technical reference. Where this PRD and the blueprint conflict, the conflict is listed in [§18 Open Decisions](#18-open-decisions) rather than silently resolved.

### 1.2 Problem statement

Visa issuance at most missions is a paper-and-email process supported by disconnected tools: applicants email forms and scans, fees are collected through ad-hoc channels, officers track cases in spreadsheets, and there is no reliable audit trail linking a decision to the exact evidence it was based on. This produces four recurring failures:

1. **No defensible audit trail.** When a decision is challenged — administratively or judicially — the mission cannot reliably reproduce the application exactly as it stood at the moment of decision.
2. **Unbounded turnaround times.** Without queue visibility, SLA breaches are discovered from applicant complaints rather than dashboards.
3. **Document handling risk.** Passport scans, bank statements, and employment records circulate through email and shared drives with no access logging.
4. **Reconciliation gaps.** Fees collected cannot be tied line-by-line to applications, refunds, or the gateway ledger.

### 1.3 Product vision

A single, secure, auditable system through which an applicant (or their authorised representative) can complete an application, submit evidence, pay the correct fee, book a biometrics or interview appointment, and track progress — and through which consular officers can review, request corrections, and decide, with every action recorded immutably.

### 1.4 What makes this system different from a generic workflow tool

| Constraint | Consequence for the product |
|---|---|
| Decisions have legal effect | Every decision must be reproducible from an immutable snapshot, not from live data |
| Applicant data is high-sensitivity PII | Private storage, policy-gated access, and logging of every read — not just every write |
| Money is collected on behalf of the state | Ledger-oriented payments; no boolean `paid` flag; daily reconciliation |
| Officers exercise delegated authority | Actor, timestamp, reason, and before/after state recorded on every override |
| Applicants are global and non-expert | Plain-language public statuses, accessibility compliance, and multi-language support |

---

## 2. Goals, Success Metrics, and Non-Goals

### 2.1 Business goals

| ID | Goal | Rationale |
|---|---|---|
| G-1 | Move 100% of in-scope visa categories to digital intake | Eliminates paper handling and email-based document exchange |
| G-2 | Produce a complete, immutable audit trail for every decision | Legal defensibility and compliance audit readiness |
| G-3 | Reduce median application turnaround time | Directly measurable service improvement |
| G-4 | Achieve full fee reconciliation against the gateway ledger | Financial control and revenue assurance |
| G-5 | Reduce applicant-initiated status enquiries | Self-service tracking displaces phone and email load |
| G-6 | Give operations real-time visibility of backlog and SLA risk | Enables proactive staffing rather than reactive escalation |

### 2.2 Success metrics

These are the measures the product is judged on post-launch. Baselines must be captured during the pilot; targets are proposals for stakeholder confirmation.

| ID | Metric | Definition | Proposed target |
|---|---|---|---|
| M-1 | Digital intake rate | Applications submitted through the portal ÷ all applications | ≥ 95% within 2 quarters of go-live |
| M-2 | Median turnaround | `decided_at − submitted_at`, business hours, by visa type | ≥ 30% reduction vs. baseline |
| M-3 | SLA breach rate | Applications exceeding the configured SLA per visa type | < 5% of decided applications |
| M-4 | First-pass document acceptance | Documents accepted without resubmission ÷ all documents | ≥ 80% |
| M-5 | Payment reconciliation variance | Unmatched gateway transactions at daily close | 0 unexplained items |
| M-6 | Webhook processing success | Payment webhooks processed without manual intervention | ≥ 99.9% |
| M-7 | Draft-to-submission conversion | Drafts submitted ÷ drafts created | ≥ 70% |
| M-8 | Appointment no-show rate | Missed appointments ÷ scheduled appointments | Tracked; reduction target set after baseline |
| M-9 | Audit completeness | Sampled sensitive actions with a complete audit record | 100% |
| M-10 | Applicant enquiry volume | Support contacts per 100 applications | ≥ 40% reduction vs. baseline |

### 2.3 Non-goals for v1

Explicitly **not** built in v1. Listing these protects the delivery timeline; each may be revisited post-launch.

| Non-goal | Reasoning |
|---|---|
| Visual drag-and-drop form builder | Admin-edited versioned JSON schema is sufficient; a builder is a product in itself |
| Public partner API and native mobile apps | Web surfaces must stabilise first; Sanctum-based API deferred to Phase 2 |
| Biometric *capture* hardware integration | v1 schedules and records biometric appointments; capture remains at the visa application centre |
| Automated risk scoring or eligibility decisioning | Decisions remain human; automation requires a separate policy and legal mandate |
| Passport/e-visa printing and chip personalisation | Downstream of decision; separate procurement |
| Interfaces to national watchlist / immigration databases | Requires inter-agency agreements outside this project's control |
| Complex multi-step refund workflows | v1 supports approval-gated single refunds only |
| SMS and WhatsApp notification channels | Email + in-portal only in v1; channel abstraction leaves the door open |
| Meilisearch/Algolia, read replicas, Octane | Performance measures introduced only when load data justifies them |
| Multi-tenancy across multiple issuing authorities | Single-authority deployment; tenancy would change the data model materially |

---

## 3. Personas and Roles

### 3.1 External personas

**Priya — Applicant (primary persona).**
Applying for a tourist visa from a country where she has never used a consular digital service. Uses a mid-range Android phone on intermittent mobile data. Anxious about her passport scan and about whether her payment registered. Needs: unambiguous next steps, confidence that documents uploaded successfully, and a status she can check without logging in.

**Daniel — Agent / Representative (v1 scope).**
Runs a travel agency submitting 20–40 applications a month on behalf of clients. Needs: a consolidated dashboard across clients, the ability to act on a client's behalf with recorded consent, and the ability to pay for several applications without re-entering details. Must **not** retain access after a client revokes authorisation.

**Amara — Corporate/institutional sponsor.** *(Post-v1 consideration.)* Files on behalf of employees. Not a distinct role in v1; uses the agent model if needed.

### 3.2 Internal personas

**Chen — Case Officer.**
Reviews 25–40 cases a day against policy. Needs a queue that surfaces the next-most-urgent case, all evidence on one screen, and the ability to request corrections without leaving the case. Frustrated by anything that requires a second system or a spreadsheet.

**Fatima — Senior Officer / Supervisor.**
Owns SLA performance and handles escalations and overrides. Needs reassignment tools, workload balance across officers, and visibility of anything ageing past threshold. Every override she makes must be defensible.

**Ravi — Document Verifier.**
Validates document quality and authenticity. Needs to preview documents safely, compare against requirements, and reject with a reason that reaches the applicant in plain language.

**Elena — Finance Officer.**
Reconciles fees daily and processes approved refunds. Needs payment records tied to applications and gateway IDs, and must be able to see payment data **without** the ability to influence application outcomes.

**Sam — Support Staff.**
Answers applicant enquiries. Needs enough metadata to help — status, dates, outstanding items — with **no** access to document contents or sensitive profile fields.

**Marcus — Administrator / Super Admin.**
Configures visa types, fees, forms, document requirements, roles, and service locations. Super Admin additionally reviews audit logs and compliance reports.

### 3.3 Role capability matrix

| Capability | Applicant | Agent | Case Officer | Senior Officer | Doc Verifier | Finance | Support | Admin | Super Admin |
|---|---|---|---|---|---|---|---|---|---|
| Create/edit own draft | ✅ | ✅ (linked clients) | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| View submitted application | Own | Linked clients | Assigned | All | Assigned | Payment data only | Metadata only | ✅ | ✅ |
| Upload documents | ✅ | ✅ (linked) | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Preview/download documents | Own | Linked | Assigned | All | Assigned | ❌ | ❌ | ✅ | ✅ |
| Accept/reject documents | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ |
| Request additional information | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ |
| Assign / reassign cases | ❌ | ❌ | Self-assign only | ✅ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Schedule / reschedule appointment | Request only | Request only | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Approve / reject application | ❌ | ❌ | ✅ (assigned) | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Override workflow rules | ❌ | ❌ | ❌ | ✅ (reason required) | ❌ | ❌ | ❌ | ❌ | ✅ |
| Initiate / approve refund | ❌ | ❌ | ❌ | ❌ | ❌ | Initiate | ❌ | Approve | ✅ |
| Configure visa types, fees, forms | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Manage users and roles | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Run reports / exports | ❌ | Own volume only | Team | Team | ❌ | Finance | ❌ | ✅ | ✅ |
| View audit logs | ❌ | ❌ | Own actions | Team | ❌ | Finance-scoped | ❌ | Limited | ✅ |

> **Separation of duties rule.** No single role may both approve a refund and alter the application it relates to. No officer may approve an application on which they are the sole document verifier where policy requires four-eyes review (configurable per visa type).

---

## 4. Scope of Release 1

### 4.1 In scope

**Core lifecycle (the vertical slice).**
Registration and identity verification → applicant profile → visa type selection → dynamic form completion → document upload → submission → fee payment via Stripe → officer review → document verification → decision → notification and decision letter → public tracking.

**Appointments and biometrics.**
Service location management, capacity-based slot configuration, officer-initiated and applicant-selected booking, rescheduling within policy, no-show and completion recording, appointment letter PDF, and blocking of decision progression until a required appointment is completed.

**Agent / representative accounts.**
Agent registration and vetting, explicit applicant-to-agent linkage with recorded consent and expiry, agent dashboard across linked clients, agent-initiated applications and payments, applicant-side visibility of who acts for them, and immediate revocation.

**Administration and reporting.**
Visa type, fee rule, form template, document requirement, and service location configuration; role and permission management; pre-aggregated dashboards; queued exports; audit log review.

### 4.2 Out of scope for v1

See [§2.3 Non-goals](#23-non-goals-for-v1). Additionally out of scope: applicant-facing live chat, offline/counter payment capture, appeal and administrative-review case types, and visa sticker/label production.

### 4.3 Supported surfaces

| Surface | Users | Technology direction | v1 |
|---|---|---|---|
| Applicant portal | Applicants, agents | Blade + Livewire, session auth | ✅ |
| Officer portal | Case officers, senior officers, verifiers | Filament panel | ✅ |
| Admin portal | Admin, super admin, finance | Filament panel | ✅ |
| Public tracking | Anyone with tracking number + second factor | Plain Laravel route, rate-limited | ✅ |
| Partner / mobile API | Third parties, native apps | Sanctum | ❌ Phase 2 |

---

## 5. End-to-End User Journeys

### 5.1 Applicant happy path

1. **Discover and register.** Priya selects a destination and visa type from a public catalogue, sees the fee, processing window, and required documents *before* creating an account. She registers and verifies her email.
2. **Complete profile.** She enters legal name, date of birth, nationality, and passport details. Passport number is encrypted at rest. The profile is reusable across future applications.
3. **Create a draft.** She selects a visa type; the system binds the draft to the currently active form template version and generates a tracking number.
4. **Fill the dynamic form.** Sections save independently with per-section validation. She can leave and return; progress is visible as a completion indicator.
5. **Upload documents.** The system lists exactly which documents are required for her visa type and nationality, including conditional requirements. Each upload is validated server-side, checksummed, and queued for virus scanning.
6. **Submit.** Submission is blocked until all mandatory fields and required documents are present and clean. On submission the system writes an **immutable snapshot** and moves the application to `payment_pending`.
7. **Pay.** She is redirected to Stripe Checkout. On webhook confirmation, the application moves to `paid`, a receipt PDF is generated, and she is notified.
8. **Book an appointment** (where the visa type requires biometrics or interview). She selects from available slots at a service location; an appointment letter PDF is issued.
9. **Track.** She checks status either in-portal or via public tracking with her tracking number plus a second factor.
10. **Respond to requests.** If information is requested, she receives a notification with a plain-language reason, corrects the specific items, and resubmits.
11. **Receive the decision.** She is notified, and can download a decision letter generated from the immutable snapshot.

### 5.2 Officer happy path

1. Chen opens his queue, sorted by SLA risk. Filters: status, visa type, nationality, submitted date, appointment status.
2. He self-assigns (or is assigned by Fatima) and opens the case: form answers, documents, payment ledger, notes, and status history on a single screen.
3. He previews documents via short-lived signed URLs. Preview is blocked if the scan status is not clean. Every preview is logged.
4. He accepts or rejects each document with a reason; rejection notifies the applicant and returns the application to `info_requested`.
5. Where required, he schedules a biometrics or interview appointment.
6. Once evidence is complete, payment confirmed, and any required appointment completed, `Approve` becomes available. `Reject` requires a mandatory reason at all times.
7. The decision writes actor, timestamp, from/to status, and reason to the append-only history, and queues the decision letter.

### 5.3 Agent journey

1. Daniel registers as an agent and is vetted by an administrator before activation.
2. He invites a client, or a client links to him by accepting a request. The linkage records who consented, when, through what mechanism, and when authorisation expires.
3. He acts on linked applications only. The application permanently records that it was filed by an agent, and the audit log names the human who acted — never just "agent".
4. He pays for one or more applications. Each application retains its own payment record and invoice.
5. The applicant sees the agent's identity and every action taken on their behalf, and may revoke authorisation at any time. Revocation takes effect immediately, including for in-flight sessions.

### 5.4 Edge and unhappy paths (must be designed, not discovered)

| Scenario | Required behaviour |
|---|---|
| Payment succeeds but the applicant closes the browser | Webhook is authoritative; state advances regardless of return-URL traffic |
| Duplicate webhook delivery | Idempotent; no duplicate ledger entries or repeated transitions |
| Webhook arrives before the checkout redirect | Handled; the applicant's return page reflects already-paid state |
| Payment fails | Application stays in `payment_pending`; retry allowed; failure reason recorded |
| Upload passes client validation but fails scan | Document marked infected; applicant notified; access blocked; incident logged |
| Applicant edits profile after submission | Snapshot is unaffected; decision letters use snapshot data |
| Form template is republished mid-draft | Draft remains bound to its original template version |
| Officer opens a case another officer is editing | Concurrency guard; stale writes rejected with a clear message |
| Applicant misses an appointment | Recorded as `missed`; policy-driven rebooking window; SLA clock behaviour configurable |
| Agent authorisation revoked mid-application | Access removed immediately; application remains with the applicant |
| Applicant requests account deletion | Retention policy applies; statutory records preserved, non-statutory PII erased |
| Fee rule changes after submission | Application uses the fee resolved and persisted at submission time |

---

## 6. Functional Requirements

Priority uses MoSCoW: **M** = Must (v1 blocker), **S** = Should, **C** = Could, **W** = Won't (v1).

### 6.1 Identity and access management

| ID | Requirement | Priority |
|---|---|---|
| FR-ID-01 | Applicants can self-register with email and password; email verification is mandatory before submission or payment | M |
| FR-ID-02 | Passwords are hashed with bcrypt/Argon2; plaintext is never stored or logged | M |
| FR-ID-03 | MFA is enforceable for all internal roles (officer, verifier, finance, admin, super admin) and optional for applicants | M |
| FR-ID-04 | Sessions regenerate on login; secure cookies and HTTPS are enforced in production | M |
| FR-ID-05 | Roles and permissions are database-backed and manageable by administrators without deployment | M |
| FR-ID-06 | Every sensitive model has an explicit authorisation policy; no model relies on role checks alone | M |
| FR-ID-07 | Applicants maintain a reusable profile with encrypted passport number and other sensitive fields | M |
| FR-ID-08 | Failed login attempts are rate-limited and logged; repeated failures trigger a lockout and alert | M |
| FR-ID-09 | Administrators can suspend an account, immediately terminating active sessions | M |
| FR-ID-10 | Internal user accounts can be deactivated on staff departure with full retention of their audit history | M |
| FR-ID-11 | Password reset uses expiring single-use tokens and notifies the account owner | M |
| FR-ID-12 | Applicants can view their own login history | S |

**Acceptance criteria**
- An unverified account cannot submit or pay, and receives a clear explanation of why.
- An officer without MFA configured cannot access the officer panel in a production environment.
- Policy tests demonstrate that no applicant can read another applicant's application, document, payment, or notification.
- Suspending a user invalidates their sessions within one request cycle.

### 6.2 Agent and representative management

| ID | Requirement | Priority |
|---|---|---|
| FR-AG-01 | Agents register through a distinct flow and require administrator activation before acting | M |
| FR-AG-02 | An agent-to-applicant linkage records consenting party, timestamp, method, scope, and expiry | M |
| FR-AG-03 | Agents can act only on applications belonging to actively linked applicants | M |
| FR-AG-04 | Applicants can view all linked agents and revoke any linkage instantly | M |
| FR-AG-05 | Every agent action is audited against the individual human user, not the agency | M |
| FR-AG-06 | Applications permanently record whether they were filed by an agent and which one | M |
| FR-AG-07 | Agents have a dashboard listing all linked applications with status and outstanding actions | M |
| FR-AG-08 | Agents can pay for applications on behalf of linked applicants; each application retains a discrete payment and invoice | M |
| FR-AG-09 | Agent authorisation expires automatically at a configurable interval and on decision | S |
| FR-AG-10 | Administrators can suspend an agency, blocking all its users while preserving records | S |
| FR-AG-11 | Notifications are delivered to both agent and applicant, with per-event configuration | S |
| FR-AG-12 | Bulk creation of applications from a structured upload | C |

**Acceptance criteria**
- Revoking a linkage removes access to the application on the agent's very next request.
- An agent cannot enumerate or access any applicant to whom they are not linked.
- The audit log for an agent-submitted application names the specific agent user.

### 6.3 Visa catalogue and configuration

| ID | Requirement | Priority |
|---|---|---|
| FR-CF-01 | Administrators manage destination countries, visa types, processing windows, and active status | M |
| FR-CF-02 | Fee rules are dated and resolvable by visa type, nationality, priority option, currency, and tax | M |
| FR-CF-03 | Exactly one form template version is active per visa type at any time | M |
| FR-CF-04 | Published form template versions are immutable; changes create a new version | M |
| FR-CF-05 | Document requirements are configurable per visa type, including conditional rules (e.g. required if applicant is a minor) | M |
| FR-CF-06 | Document types define permitted MIME types, extensions, and maximum size | M |
| FR-CF-07 | Service locations and appointment capacity are configurable per location and appointment type | M |
| FR-CF-08 | SLA targets are configurable per visa type and drive breach reporting | M |
| FR-CF-09 | Configuration changes are audited with actor, timestamp, and before/after values | M |
| FR-CF-10 | Administrators can preview a form template before publishing | S |
| FR-CF-11 | Deactivating a visa type prevents new applications but does not affect in-flight ones | M |

**Acceptance criteria**
- Publishing a new template version does not alter any existing draft or submitted application.
- Fee resolution is deterministic and returns a single rule for any valid combination; ambiguity is a configuration error surfaced to the administrator.
- A visa type cannot be activated without an active form template and at least one valid fee rule.

### 6.4 Dynamic application forms

| ID | Requirement | Priority |
|---|---|---|
| FR-FM-01 | Forms are rendered from a versioned JSON schema defining sections, fields, validation, help text, and conditional visibility | M |
| FR-FM-02 | Applications bind to a specific template version at draft creation and remain bound for their lifetime | M |
| FR-FM-03 | Sections save independently; a validation failure in one section does not discard others | M |
| FR-FM-04 | All validation is enforced server-side; client-side validation is convenience only | M |
| FR-FM-05 | Answers marked sensitive in the schema are encrypted at rest | M |
| FR-FM-06 | Applicants see per-section completion status and outstanding mandatory items | M |
| FR-FM-07 | Core searchable fields (nationality, destination, visa type, dates) are stored relationally, not only in JSON | M |
| FR-FM-08 | Drafts auto-save and survive session expiry | S |
| FR-FM-09 | Forms support multiple languages driven by schema-level translation keys | S |
| FR-FM-10 | Applicants can copy answers from a previous application into a new draft | C |

**Acceptance criteria**
- One answer row exists per (application, section, field); duplicates are impossible at the database level.
- A conditional field that is not visible is never required and never persisted as an empty answer.
- Submitting with a mandatory field missing returns a field-level error identifying the exact section and field.

### 6.5 Application lifecycle

| ID | Requirement | Priority |
|---|---|---|
| FR-AP-01 | Applicants can create, edit, and delete their own drafts | M |
| FR-AP-02 | Every application receives a non-sequential public tracking number, distinct from its primary key | M |
| FR-AP-03 | Submission is blocked unless all mandatory answers and required documents are present and clean | M |
| FR-AP-04 | Submission writes an immutable snapshot of the complete application payload | M |
| FR-AP-05 | Submitted applications cannot be edited by the applicant unless status explicitly permits correction | M |
| FR-AP-06 | Every status transition writes an append-only history record with from/to status, actor, timestamp, and reason where required | M |
| FR-AP-07 | Internal statuses map to a simplified applicant-facing public status | M |
| FR-AP-08 | Status transitions are atomic and wrapped in database transactions | M |
| FR-AP-09 | Invalid transitions are rejected by the domain layer, not merely hidden in the UI | M |
| FR-AP-10 | Applicants can withdraw an application before a decision, subject to refund policy | S |
| FR-AP-11 | The system computes and exposes time-in-status and SLA risk per application | M |
| FR-AP-12 | Raw database identifiers never appear in URLs, emails, or PDFs | M |

**Acceptance criteria**
- Two applications submitted in the same second receive tracking numbers with no inferable relationship.
- Attempting a transition not permitted by the state machine fails with a domain exception and writes no partial state.
- The snapshot taken at submission is byte-identical when re-read after any subsequent profile edit.

### 6.6 Document management

| ID | Requirement | Priority |
|---|---|---|
| FR-DM-01 | Documents are stored on private object storage; no public bucket or public disk is ever used | M |
| FR-DM-02 | Storage paths are generated identifiers; original filenames are retained as metadata only | M |
| FR-DM-03 | Server-side validation covers MIME type, extension, file size, and page/image limits | M |
| FR-DM-04 | A SHA-256 checksum is computed and stored for every uploaded file | M |
| FR-DM-05 | Every upload is queued for virus scanning; preview and download are blocked until the scan is clean | M |
| FR-DM-06 | Replacing a document creates a new version record; prior versions are retained | M |
| FR-DM-07 | Documents are served only via short-lived signed URLs or authorised controller streams | M |
| FR-DM-08 | Verifiers can accept, reject, or require resubmission, with a mandatory reason on anything but acceptance | M |
| FR-DM-09 | Rejection notifies the applicant with a plain-language reason and returns the application to an action-required state | M |
| FR-DM-10 | Every upload, preview, download, and review action is written to the audit log | M |
| FR-DM-11 | An infected file is quarantined, never served, and raises an operational alert | M |
| FR-DM-12 | Officer downloads can be watermarked where policy requires | S |
| FR-DM-13 | Applicants see per-document status and outstanding requirements at a glance | M |

**Acceptance criteria**
- A file whose declared MIME type contradicts its actual content is rejected before storage.
- A signed URL is unusable after expiry and unusable by any user other than the one it was issued to.
- Downloading a document produces an audit record containing actor, document, timestamp, IP, and user agent.

### 6.7 Payments and invoicing (Stripe)

| ID | Requirement | Priority |
|---|---|---|
| FR-PY-01 | Fees are resolved from dated rules and persisted on the application at submission | M |
| FR-PY-02 | Payment records are ledger-oriented: attempts, provider identifiers, line items, failure reasons, and status are all retained | M |
| FR-PY-03 | Card data is never transmitted to or stored by this system; hosted Stripe Checkout or tokenised PaymentIntents only | M |
| FR-PY-04 | Webhook signatures are verified using Stripe's official library before any processing | M |
| FR-PY-05 | Provider event IDs are persisted and processing is idempotent; duplicate events cause no state change | M |
| FR-PY-06 | Payment success, application transition, and ledger write occur within a single transaction | M |
| FR-PY-07 | Successful payment generates an invoice with a unique public receipt number and a queued receipt PDF | M |
| FR-PY-08 | Failed payments record the failure reason, hold the application in `payment_pending`, and permit retry | M |
| FR-PY-09 | Multi-currency is supported; a single application is charged in exactly one currency | M |
| FR-PY-10 | Refunds require finance initiation plus administrator approval, with a mandatory reason and full audit | M |
| FR-PY-11 | A daily reconciliation report compares gateway transactions against internal ledger records | M |
| FR-PY-12 | Finance users can view payment data without any ability to alter application status | M |
| FR-PY-13 | Webhook processing failures are alerted and manually replayable | M |
| FR-PY-14 | Applicants can download receipts for all their payments at any time | M |

**Acceptance criteria**
- Replaying an identical webhook event 100 times produces exactly one ledger entry and one transition.
- A webhook with an invalid signature is rejected with 4xx, logged, and never processed.
- A refund cannot be completed by the same user who initiated it.
- Fee amounts displayed at checkout match the amount persisted at submission, to the minor unit.

### 6.8 Appointments and biometrics

| ID | Requirement | Priority |
|---|---|---|
| FR-AB-01 | Administrators define service locations with address, timezone, operating hours, and holidays | M |
| FR-AB-02 | Appointment capacity is configurable per location, appointment type, and time slot | M |
| FR-AB-03 | Visa types declare whether biometrics, interview, or document drop-off is required | M |
| FR-AB-04 | Applicants select from genuinely available slots; capacity is enforced atomically to prevent double-booking | M |
| FR-AB-05 | Officers can schedule, reschedule, or cancel appointments on the applicant's behalf | M |
| FR-AB-06 | Scheduling generates an appointment letter PDF and notifies the applicant | M |
| FR-AB-07 | Applicants can reschedule within a configurable policy window and count limit | M |
| FR-AB-08 | Appointment outcomes are recorded as completed, missed, or cancelled | M |
| FR-AB-09 | An application cannot be approved while a required appointment is incomplete | M |
| FR-AB-10 | Missed appointments trigger notification and a policy-driven rebooking window | M |
| FR-AB-11 | Appointment reminders are sent at a configurable interval before the slot | S |
| FR-AB-12 | Biometric enrolment completion is recorded with an external reference identifier; no biometric data is stored in this system | M |
| FR-AB-13 | Staff can view a daily appointment roster per location | S |
| FR-AB-14 | Slots can be blocked for closures or maintenance without deleting bookings | S |

**Acceptance criteria**
- Two concurrent bookings for the last remaining slot result in exactly one success and one clear failure.
- Timezone handling is correct for a location and applicant in different zones; the letter shows local time at the location.
- Approval is blocked, with an explicit reason, where a required biometrics appointment shows any status other than completed.

> **Scope note.** v1 records that biometric enrolment occurred and stores the visa application centre's reference. It does not capture, transmit, or store fingerprints, facial images, or iris data. Any change to this materially alters the compliance profile and must be treated as a new project phase.

### 6.9 Officer review and decisions

| ID | Requirement | Priority |
|---|---|---|
| FR-OR-01 | Officers see a filterable, sortable queue with SLA risk indicated | M |
| FR-OR-02 | Case officers see only assigned applications; senior officers and admins see all | M |
| FR-OR-03 | Officers may self-assign unassigned cases; reassignment requires senior officer or admin | M |
| FR-OR-04 | A single case view presents answers, documents, payments, notes, appointments, and status history | M |
| FR-OR-05 | Officers can request additional information with a structured, applicant-visible reason | M |
| FR-OR-06 | Review notes support internal-only and applicant-visible visibility | M |
| FR-OR-07 | Approval is blocked unless payment is confirmed, all required documents are accepted, and required appointments are complete | M |
| FR-OR-08 | Rejection requires a mandatory reason selected from a configurable list plus free text | M |
| FR-OR-09 | Every decision records the deciding officer, timestamp, from/to status, and reason | M |
| FR-OR-10 | Overrides of workflow rules require senior authority and a mandatory reason, and generate a supervisor-visible audit entry | M |
| FR-OR-11 | Decision letters are generated from the immutable snapshot, never from live data | M |
| FR-OR-12 | Four-eyes review is enforceable per visa type, requiring a second officer to countersign | S |
| FR-OR-13 | Officers can add an internal case note at any status | M |
| FR-OR-14 | Concurrent edits by two officers are detected and the stale write rejected | S |

**Acceptance criteria**
- A case officer cannot approve an application assigned to a colleague.
- Approving an application with a rejected required document is impossible through the UI, the API, and the domain layer.
- A decision letter regenerated six months later is identical to the original.

### 6.10 Notifications and generated documents

| ID | Requirement | Priority |
|---|---|---|
| FR-NT-01 | Notifications are delivered by email and stored as in-portal database notifications | M |
| FR-NT-02 | All external notification delivery is queued; provider failure never blocks a user request | M |
| FR-NT-03 | Notifications cover: submission, payment success, payment failure, document rejection, information requested, appointment scheduled, appointment reminder, decision, and account security events | M |
| FR-NT-04 | Notification templates are administrator-editable and support multiple languages | S |
| FR-NT-05 | PDFs are generated asynchronously for application summary, receipt, appointment letter, and decision letter | M |
| FR-NT-06 | All PDFs are written to private storage and served through authorised, audited access | M |
| FR-NT-07 | PDF and email job failures are visible, alerted, and retryable | M |
| FR-NT-08 | Officers are notified of new assignments and SLA-threshold breaches | M |
| FR-NT-09 | Notifications never contain sensitive document content or raw identifiers | M |
| FR-NT-10 | Applicants can view all notification history in the portal | M |

**Acceptance criteria**
- Submission dispatches summary PDF, applicant notification, officer queue notification, audit log, and search index jobs — and returns an HTTP response without waiting for any of them.
- A permanently failing email job is visible in monitoring within the alerting threshold and does not block the application's progression.

### 6.11 Public tracking

| ID | Requirement | Priority |
|---|---|---|
| FR-PT-01 | Status can be checked with tracking number plus a second verification factor (date of birth, email OTP, or secure token) | M |
| FR-PT-02 | The endpoint is rate-limited per IP and per tracking number | M |
| FR-PT-03 | Only simplified public status and next-action guidance are exposed; no personal data, documents, or officer identity | M |
| FR-PT-04 | Failed verification attempts are logged; repeated failures trigger throttling and alerting | M |
| FR-PT-05 | Enumeration of tracking numbers is infeasible by construction and by rate limit | M |
| FR-PT-06 | The tracking page is available in all supported languages | S |

**Acceptance criteria**
- A correct tracking number with an incorrect second factor is indistinguishable in response and timing from an unknown tracking number.
- Automated sequential probing is throttled and alerted before any meaningful volume of guesses.

### 6.12 Reporting, dashboards, and exports

| ID | Requirement | Priority |
|---|---|---|
| FR-RP-01 | Dashboards read from pre-aggregated metrics tables, not live aggregation over transactional tables | M |
| FR-RP-02 | Daily metrics cover applications, processing performance, documents, payments, and visa type mix | M |
| FR-RP-03 | Officer performance metrics cover assigned volume, completions, average turnaround, and rework | M |
| FR-RP-04 | Compliance reporting covers login anomalies, admin overrides, sensitive downloads, and audit gaps | M |
| FR-RP-05 | Exports are queued, written to private storage, and expire after a configurable period | M |
| FR-RP-06 | Exports apply the requesting user's authorisation and field-level redaction | M |
| FR-RP-07 | Every export records who exported what, when, from which IP, and with which filters | M |
| FR-RP-08 | Encrypted values are never exported except through an approved compliance workflow | M |
| FR-RP-09 | Reporting snapshots are generated on a schedule and are re-runnable for a given date | M |
| FR-RP-10 | Finance can export a reconciliation report in CSV/XLSX | M |

**Acceptance criteria**
- A dashboard renders within its performance budget when the transactional tables hold at least one year of production-scale data.
- A support user attempting an export receives an authorisation failure that is logged.

### 6.13 Audit and compliance

| ID | Requirement | Priority |
|---|---|---|
| FR-AU-01 | Audit logs are append-only; no interface permits editing or deleting an entry | M |
| FR-AU-02 | Audited events include authentication, authorisation failures, all state transitions, document actions, payment actions, configuration changes, exports, and overrides | M |
| FR-AU-03 | Each entry records actor, action, target, timestamp, IP, user agent, and before/after values where applicable | M |
| FR-AU-04 | Audit entries never contain sensitive values in cleartext | M |
| FR-AU-05 | Super admins can search and filter audit logs by actor, action, target, and date range | M |
| FR-AU-06 | Audit retention meets the statutory retention period for consular records | M |
| FR-AU-07 | Data subject access requests can be fulfilled from a single consolidated view of an applicant's data | S |
| FR-AU-08 | Retention policies purge non-statutory personal data on schedule while preserving statutory records | M |

**Acceptance criteria**
- Deleting or updating an audit row is impossible through the application under any role.
- A sampled decision can be fully reconstructed — evidence, actor, reasoning, timing — from audit and snapshot data alone.

---

## 7. Application Status Model

### 7.1 Internal statuses

`draft` · `submitted` · `payment_pending` · `paid` · `under_review` · `info_requested` · `resubmitted` · `document_verification` · `interview_scheduled` · `decision_pending` · `approved` · `rejected` · `withdrawn` · `closed`

### 7.2 Public status mapping

| Internal status | Public status | Applicant action |
|---|---|---|
| `draft` | Draft | Complete sections and upload required documents |
| `submitted`, `payment_pending` | Submitted | Pay the fee or await confirmation |
| `paid`, `under_review`, `document_verification` | In Review | Monitor status; no edits unless requested |
| `info_requested` | Action Required | Correct application data or resubmit documents |
| `resubmitted` | In Review | No action needed |
| `interview_scheduled` | Appointment Scheduled | Attend, or reschedule if policy allows |
| `decision_pending` | In Review | No action needed |
| `approved`, `rejected` | Decision Made | Download the decision letter or follow instructions |
| `withdrawn` | Withdrawn | No further action |
| `closed` | Closed | No further action |

### 7.3 Transition rules

| From | Permitted to | Actor | Guard |
|---|---|---|---|
| `draft` | `submitted` | Applicant/Agent | All mandatory answers and required documents present and clean |
| `submitted` | `payment_pending` | System | Fee resolved and persisted |
| `payment_pending` | `paid` | System (webhook) | Verified, idempotent payment success |
| `payment_pending` | `payment_pending` | System | Failure recorded; retry permitted |
| `paid` | `under_review` | Officer | Assignment made |
| `under_review` | `document_verification` | Officer/Verifier | — |
| `under_review`, `document_verification` | `info_requested` | Officer/Verifier | Reason mandatory |
| `info_requested` | `resubmitted` | Applicant/Agent | Requested items addressed |
| `resubmitted` | `under_review` | System | — |
| `under_review`, `document_verification` | `interview_scheduled` | Officer | Appointment booked |
| `interview_scheduled` | `decision_pending` | System/Officer | Appointment marked completed |
| `under_review`, `document_verification`, `decision_pending` | `approved` | Officer/Senior | Payment confirmed **and** all required documents accepted **and** required appointments completed |
| `under_review`, `document_verification`, `decision_pending` | `rejected` | Officer/Senior | Reason mandatory |
| Any pre-decision | `withdrawn` | Applicant/Admin | Refund policy evaluated |
| `approved`, `rejected`, `withdrawn` | `closed` | System | Retention/closure schedule |

Any transition not listed is invalid and must be rejected by the domain layer.

---

## 8. Business Rules and Invariants

These are non-negotiable and must be enforced in the domain layer, not only in the UI.

| ID | Invariant |
|---|---|
| BR-01 | A submitted application's snapshot is immutable for the life of the record |
| BR-02 | Status history and audit logs are append-only; corrections are new entries, never edits |
| BR-03 | An application cannot be approved with a missing, pending, infected, or rejected required document |
| BR-04 | An application cannot be approved without confirmed payment |
| BR-05 | An application cannot be approved with an incomplete required appointment |
| BR-06 | A payment webhook is processed exactly once regardless of delivery count |
| BR-07 | One application is charged in exactly one currency |
| BR-08 | Monetary arithmetic uses integer minor units; floating point is never used for money |
| BR-09 | Documents are never stored on, or served from, public storage |
| BR-10 | Raw primary keys never appear in public URLs, emails, or generated documents |
| BR-11 | Decision letters and receipts derive from immutable snapshot and ledger data only |
| BR-12 | Every override records actor, reason, and before/after state |
| BR-13 | Refund initiation and refund approval are performed by different users |
| BR-14 | A draft is bound to the form template version active at its creation |
| BR-15 | The fee charged is the fee resolved and persisted at submission time |
| BR-16 | An agent may access only applications for which an active linkage exists |
| BR-17 | Appointment capacity cannot be exceeded under any concurrency condition |
| BR-18 | All workflow transitions are atomic |

---

## 9. Data Requirements

### 9.1 Core entities

Grouped by domain. The architecture blueprint holds the authoritative column-level catalogue.

| Domain | Entities |
|---|---|
| Identity | `users`, `applicant_profiles`, `roles`, `permissions`, `agent_profiles`, `agent_applicant_links` |
| Reference | `countries`, `visa_types`, `visa_fees`, `document_types`, `visa_type_document_requirements`, `service_locations` |
| Forms | `form_templates`, `application_answers`, `application_snapshots` |
| Applications | `visa_applications`, `application_status_histories`, `review_notes` |
| Documents | `application_documents`, `document_versions` |
| Payments | `payments`, `payment_items`, `payment_webhook_events`, `invoices`, `refunds` |
| Appointments | `appointments`, `appointment_slots` |
| Reporting | `daily_application_metrics`, `daily_payment_metrics`, `officer_performance_metrics`, `document_rejection_metrics` |
| Compliance | `audit_logs`, `export_logs` |

New relative to the blueprint: `agent_profiles`, `agent_applicant_links`, `appointment_slots`, `refunds`, `export_logs`.

### 9.2 Data classification and handling

| Classification | Examples | Handling |
|---|---|---|
| Restricted | Passport number, biometric reference, document contents | Encrypted at rest; private storage; access logged on every read; redacted from exports by default |
| Confidential | Name, date of birth, nationality, contact details, form answers | Policy-gated; sensitive fields encrypted; never in logs |
| Internal | Case notes, officer assignments, SLA metrics | Role-gated; not applicant-visible unless explicitly marked |
| Public | Visa types, fees, processing windows, public status | Openly available |

### 9.3 Integrity requirements

- Foreign keys enforced at the database level throughout.
- Unique constraints on: `tracking_number`; `(application_id, section_key, field_key)`; `(application_id, document_type_id)`; `(application_document_id, version_number)`; `(provider, provider_payment_id)`; `(provider, provider_event_id)`; `invoice_number`.
- Indexes supporting: applicant portal lists, officer queue filtering, backlog reporting, and appointment availability lookups.

### 9.4 Retention

| Data | Retention | Note |
|---|---|---|
| Application records and snapshots | Statutory consular retention period | *Exact period to be confirmed by legal — see §18* |
| Uploaded documents | Statutory period, then secure deletion | Deletion is itself audited |
| Audit logs | At least as long as the records they describe | Append-only, potentially partitioned by period |
| Payment and invoice records | Statutory financial retention period | Typically longer than application retention |
| Draft applications never submitted | Configurable (proposed: 12 months) | Applicant notified before purge |
| Notification records | 24 months | |
| Export files | 7 days on private storage | Then automatically removed |

---

## 10. Non-Functional Requirements

### 10.1 Performance

| ID | Requirement | Target |
|---|---|---|
| NFR-P-01 | Applicant portal page response (p95) | < 500 ms |
| NFR-P-02 | Officer queue load with 100k applications (p95) | < 1 s |
| NFR-P-03 | Document upload acknowledgement, 10 MB file | < 3 s |
| NFR-P-04 | Dashboard render from pre-aggregated tables | < 2 s |
| NFR-P-05 | Payment webhook acknowledgement | < 500 ms; processing deferred to queue |
| NFR-P-06 | Queue wait time under normal load | < 30 s for `high`, < 5 min for `reports` |
| NFR-P-07 | No synchronous PDF generation, email delivery, or virus scanning in any HTTP request | Absolute |

### 10.2 Scale

| Dimension | v1 target | Design headroom |
|---|---|---|
| Applications per year | 250,000 | 1,000,000 |
| Concurrent applicant sessions | 2,000 | 10,000 |
| Concurrent internal users | 300 | 1,000 |
| Documents per application | 5–15 | 30 |
| Document storage growth | ~2 TB/year | Lifecycle-policied |
| Peak submissions per hour | 2,000 | 8,000 |

*Volumes require confirmation against actual mission throughput — see §18.*

### 10.3 Availability and resilience

| ID | Requirement |
|---|---|
| NFR-A-01 | Target availability 99.5% for applicant surfaces, 99.9% for webhook endpoints |
| NFR-A-02 | RPO ≤ 15 minutes; RTO ≤ 4 hours |
| NFR-A-03 | Automated backups with periodic, evidenced restore tests |
| NFR-A-04 | Web tier is stateless and horizontally scalable behind a load balancer |
| NFR-A-05 | Scheduler runs on exactly one leader or under distributed lock |
| NFR-A-06 | Gateway or mail provider outage degrades gracefully without data loss; jobs retry with backoff |
| NFR-A-07 | Planned maintenance windows are announced in-portal in advance |

### 10.4 Security

| ID | Requirement |
|---|---|
| NFR-S-01 | HTTPS enforced everywhere; HSTS enabled; secure and HTTP-only cookies |
| NFR-S-02 | CSRF protection on all web routes; verified webhooks excluded and signature-checked instead |
| NFR-S-03 | Rate limiting on login, registration, tracking, OTP, upload, payment retry, and webhook endpoints |
| NFR-S-04 | Encryption in transit and at rest for all applicant data |
| NFR-S-05 | `APP_DEBUG=false` in production; no stack traces exposed to users |
| NFR-S-06 | Dependency scanning in CI; critical vulnerabilities block deployment |
| NFR-S-07 | Independent penetration test before go-live, with remediation of high and critical findings |
| NFR-S-08 | Secrets held in a managed secret store, never in source control |
| NFR-S-09 | Object-level authorisation verified by automated policy tests for every sensitive model |
| NFR-S-10 | Security event alerting for failed-login spikes, authorisation failures, and unusual download volume |

### 10.5 Accessibility, localisation, and compatibility

| ID | Requirement |
|---|---|
| NFR-U-01 | Applicant-facing surfaces meet WCAG 2.2 Level AA |
| NFR-U-02 | Full keyboard operability and screen-reader compatibility across the applicant journey |
| NFR-U-03 | Responsive from 320 px upward; mobile-first for the applicant portal |
| NFR-U-04 | Latest two versions of Chrome, Safari, Firefox, and Edge; Android Chrome and iOS Safari |
| NFR-U-05 | Interface, notifications, and PDFs localisable; language set confirmed per deployment |
| NFR-U-06 | Right-to-left layout support where the language set requires it |
| NFR-U-07 | All dates and times displayed with an explicit timezone |
| NFR-U-08 | The portal remains usable on constrained connections; uploads resume or fail clearly |

### 10.6 Observability and operations

| ID | Requirement |
|---|---|
| NFR-O-01 | Centralised structured logging with correlation identifiers |
| NFR-O-02 | Queue monitoring with alerting on depth, wait time, and failure rate |
| NFR-O-03 | External error tracking with alert routing |
| NFR-O-04 | Alerting on: webhook failures, PDF failures, mail bounce rate, slow queries, failed-login spikes, sensitive download anomalies, storage growth |
| NFR-O-05 | Runbooks for webhook replay, stuck jobs, quarantine handling, and reconciliation discrepancies |
| NFR-O-06 | Health-check endpoints for load balancer and uptime monitoring |

---

## 11. Integrations

| Integration | Purpose | Criticality | Failure behaviour |
|---|---|---|---|
| Stripe (Checkout / PaymentIntents / Webhooks) | Fee collection, refunds | Critical | Applications hold at `payment_pending`; retry permitted; webhooks replayable |
| Private S3-compatible object storage | Document and PDF storage | Critical | Uploads fail explicitly; no silent loss |
| Virus scanning service | Document safety | Critical | Documents remain unscanned and inaccessible; alert raised |
| Transactional email provider | Notifications | High | Jobs retry with backoff; in-portal notifications remain available |
| Error monitoring | Production diagnostics | Medium | Degrades to local logs |
| Identity verification service | Applicant verification | *Deferred* | Not integrated in v1 |
| Government/immigration databases | Watchlist and record checks | *Out of scope* | Manual process retained |

---

## 12. Analytics and Instrumentation

Events to capture for product measurement, distinct from audit logging:

- Registration started / completed / abandoned
- Draft created; per-section completion; draft abandoned (with last section reached)
- Document upload attempted / succeeded / failed (with reason category)
- Submission attempted / blocked (with blocking reason) / completed
- Checkout initiated / abandoned / succeeded / failed
- Appointment slot search / booked / rescheduled / missed
- Public tracking lookup attempted / succeeded / failed
- Officer queue actions: assign, request info, accept/reject document, decide (with duration in state)
- Notification opened; in-portal notification read

Analytics events must not capture personal data values — only identifiers, categories, and timings.

---

## 13. Release Plan

Milestones follow the established build sequence, extended for the two v1 scope additions.

| Milestone | Deliverable | Exit criteria |
|---|---|---|
| **M0 — Foundation** | Project scaffold, admin panel shell, queues, scheduler, CI, test harness, agent ruleset | Test suite green; panel loads; worker processes jobs |
| **M1 — Identity, roles, base admin** | Users, profiles, roles, permissions, countries, visa types, fees | Applicants cannot reach internal panels; every model has a policy with passing tests |
| **M2 — Application workflow and forms** | Versioned templates, drafts, section saves, submission, snapshots, status history, tracking numbers | Draft → submit works; snapshot immutable; history append-only; tracking non-sequential |
| **M3 — Documents** | Requirements, private uploads, versioning, scanning, verifier review, resubmission | Required documents enforced; no public storage; every action audited |
| **M4 — Payments** | Fee resolution, Stripe checkout, webhook handling, ledger, invoices, receipts | Duplicate webhook idempotent; transition occurs once; finance cannot alter applications |
| **M5 — Officer review and decisions** | Queue, assignment, notes, information requests, decisions, guards | Officer sees only assigned cases; approval blocked on incomplete evidence; decisions fully attributed |
| **M6 — Appointments and biometrics** | Locations, slots, booking, rescheduling, outcomes, appointment letters | No double-booking under concurrency; approval blocked on incomplete appointment |
| **M7 — Agents and representatives** | Agent registration, vetting, linkage and consent, dashboard, payments, revocation | Revocation immediate; agent cannot reach unlinked applicants; actions attributed to individuals |
| **M8 — Notifications, PDFs, queues** | All lifecycle notifications, queued PDF generation, named queues | Nothing slow runs in-request; failures visible and retryable |
| **M9 — Reporting, exports, hardening** | Metrics tables, dashboards, queued exports, rate limiting, security review, load tests, backup drill | Dashboards within budget at scale; exports authorised and audited; penetration test findings remediated |

### 13.1 Pilot before general availability

Recommended: launch one visa type at one service location with a limited applicant cohort. Pilot exit criteria — 200 applications through the full lifecycle, zero unexplained reconciliation variances, no critical security findings, and baselines captured for every metric in §2.2.

---

## 14. Dependencies

| Dependency | Owner | Needed by |
|---|---|---|
| Legal confirmation of retention periods and lawful basis for processing | Legal / DPO | M1 |
| Approved visa type catalogue, fee schedule, and processing SLAs | Consular policy | M1 |
| Approved form content per visa type | Consular policy | M2 |
| Document requirement matrix including conditional rules | Consular policy | M3 |
| Stripe account, entity verification, and settlement setup | Finance | M4 |
| Approved decision, rejection, and correction reason taxonomies | Consular policy | M5 |
| Service location list, hours, holidays, and capacity | Operations | M6 |
| Agent vetting criteria and terms of authorisation | Legal / Operations | M7 |
| Approved notification and letter templates, translated | Communications | M8 |
| Reporting requirements from finance and operations | Finance / Operations | M9 |
| Penetration test vendor engagement | Security | Pre-launch |
| Data protection impact assessment | DPO | Pre-launch |

---

## 15. Risks and Mitigations

| ID | Risk | Impact | Likelihood | Mitigation |
|---|---|---|---|---|
| R-1 | Data breach exposing applicant PII | Severe | Low | Private storage, encryption, policy tests, access logging, penetration testing, least privilege |
| R-2 | Payment webhook failure stalls applications and revenue | High | Medium | Idempotent processing, persisted events, alerting, manual replay, daily reconciliation |
| R-3 | Legal challenge to a decision the system cannot evidence | Severe | Low | Immutable snapshots, append-only history, mandatory reasons, full audit trail |
| R-4 | Form schema complexity exceeds the JSON approach | Medium | Medium | Start with the simplest schema that works; defer any builder; review after two visa types |
| R-5 | Appointment capacity model fails under contention | High | Medium | Atomic capacity enforcement; concurrency load tests as an M6 exit criterion |
| R-6 | Agent model becomes a lateral access path to applicant data | High | Medium | Explicit consent-recorded linkage, instant revocation, per-user audit, dedicated policy tests |
| R-7 | Officer adoption resisted in favour of existing processes | High | Medium | Early officer involvement, queue-first design, pilot feedback loop, training |
| R-8 | Document storage cost growth outpaces budget | Medium | Medium | Lifecycle policies, retention enforcement, storage monitoring |
| R-9 | Virus scanning becomes a throughput bottleneck | Medium | Medium | Dedicated queue, independent scaling, backlog alerting |
| R-10 | Accessibility non-compliance blocks a government launch | High | Medium | WCAG 2.2 AA as an acceptance criterion, not a post-launch fix; audit before pilot |
| R-11 | Scope creep from additional visa types during build | Medium | High | Vertical slice first; new types are configuration, not code |
| R-12 | Statutory retention conflicts with erasure requests | Medium | Medium | Legal determination before M1; retention engine designed around statutory override |
| R-13 | Multi-currency and tax handling proves more complex than modelled | Medium | Medium | Confirm currency and tax rules with finance before M4 |

---

## 16. Assumptions

1. A single issuing authority operates the system; multi-tenancy is not required.
2. Officers work within one organisation with a shared role taxonomy.
3. Stripe is available and permitted in all jurisdictions where fees are collected.
4. Biometric capture remains a physical process at service locations; this system records only completion and an external reference.
5. Applicants have email access; email is a valid primary notification channel.
6. Document authenticity checking remains a human judgement in v1.
7. Fee schedules change infrequently and are managed by administrators, not developers.
8. The mission accepts electronic decision letters; physical issuance is a downstream process.
9. Applicant volumes fall within the §10.2 envelope.
10. Infrastructure is cloud-hosted with private object storage available in the required jurisdiction.

---

## 17. Glossary

| Term | Meaning |
|---|---|
| **Application snapshot** | Immutable copy of the complete application payload captured at submission; the sole basis for decisions and generated letters |
| **Tracking number** | Non-sequential public reference (e.g. `VISA-IND-2026-8K3F2Q`) used in place of database identifiers |
| **Form template version** | A published, immutable JSON schema defining one visa type's form at a point in time |
| **Read model** | Pre-aggregated table populated on a schedule to serve dashboards without querying transactional tables |
| **Four-eyes review** | Requirement that two distinct officers participate in a decision |
| **Linkage** | Consent-recorded relationship permitting an agent to act for an applicant |
| **Idempotency** | Property whereby repeated processing of the same event produces the same result exactly once |
| **SLA breach** | Application exceeding the configured processing target for its visa type |

---

## 18. Open Decisions

These require a decision before the milestone indicated. Each is a genuine fork, not a formality.

| ID | Decision | Options | Needed by |
|---|---|---|---|
| OD-1 | **Framework version.** The blueprint specifies Laravel 13.x; the established project standard and existing skill definition target Laravel 12 + Filament 4. | (a) Laravel 12, matching existing standards and tooling; (b) Laravel 13, matching the blueprint | M0 — blocking |
| OD-2 | **Money representation.** The blueprint specifies `decimal(12,2)`. Other systems in this portfolio use integer minor units in `BIGINT` with arbitrary-precision arithmetic, which eliminates a class of rounding defects and is the stronger choice for a multi-currency ledger. | (a) Integer minor units *(recommended)*; (b) `decimal(12,2)` per blueprint | M0 — schema-blocking |
| OD-3 | **Statutory retention periods** for applications, documents, audit logs, and financial records | Legal determination | M1 |
| OD-4 | **Supported languages** at launch, and whether any require RTL layout | Policy determination | M2 |
| OD-5 | **Currency strategy.** Single settlement currency, or per-destination currency with applicant-facing conversion | (a) Single; (b) Per-destination | M4 |
| OD-6 | **Tax treatment.** Whether VAT/GST applies to visa fees, and per which jurisdiction | Finance determination | M4 |
| OD-7 | **Refund policy.** Which statuses permit refunds, at what percentage, within what window | Policy determination | M4 |
| OD-8 | **Four-eyes review.** Which visa types require countersigned decisions | Policy determination | M5 |
| OD-9 | **Appointment ownership.** Applicant self-service booking, officer-assigned slots, or both by visa type | (a) Self-service; (b) Officer-assigned; (c) Configurable *(recommended)* | M6 |
| OD-10 | **Agent vetting.** Manual administrator approval, accreditation-number verification, or external register lookup | Policy determination | M7 |
| OD-11 | **Agent payment model.** Per-application payment only, or a prepaid agency balance | (a) Per-application *(recommended for v1)*; (b) Balance model | M7 |
| OD-12 | **Virus scanning provider** and whether scanning happens in-infrastructure or via an external service | Technical + procurement | M3 |
| OD-13 | **Data residency.** Jurisdiction in which applicant data and documents must be stored | Legal + infrastructure | M0 |
| OD-14 | **Second factor for public tracking.** Date of birth, email OTP, or secure token — noting date of birth is the weakest option | (a) Date of birth; (b) Email OTP *(recommended)*; (c) Token | M2 |
| OD-15 | **Volume baselines.** Confirmation of actual annual application volume and peak submission patterns | Operations data | M9 |
| OD-16 | **Withdrawal policy.** Whether applicants may withdraw post-payment, and the refund consequence | Policy determination | M5 |

---

## 19. Appendix — Traceability to the Architecture Blueprint

| PRD section | Blueprint section |
|---|---|
| §3 Personas and roles | §3.1 User management and identity |
| §6.4 Dynamic forms | §3.2 Application forms |
| §7 Status model | §3.3 Application lifecycle |
| §6.11 Public tracking | §3.4 Application tracking |
| §6.7 Payments | §3.5 Payments, §6.4 Payment security |
| §6.6 Documents | §3.6 Document management, §6.3 Document security |
| §6.10 Notifications and PDFs | §3.7 Notifications and PDF generation |
| §6.12 Reporting | §3.8, §9.2 Reporting read models |
| §8 Business rules | §4.2 Design rules, §6.5 Workflow security |
| §9 Data requirements | §5 Database design |
| §10.4 Security NFRs | §6 Security and compliance design |
| §11 Integrations | §8 Recommended packages |
| §13 Release plan | §12 Implementation roadmap |

Requirements introduced by this PRD and **not** present in the blueprint — and therefore requiring architectural extension — are: appointment slot capacity management (§6.8), the agent linkage and consent model (§6.2), the refund approval workflow (FR-PY-10), export logging (FR-RP-07), and accessibility compliance (§10.5).
