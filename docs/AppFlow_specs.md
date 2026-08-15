# Screen-Level UI Specifications
## Visa Application System — the eleven screens carrying real complexity

| Field | Value |
|---|---|
| **Version** | 1.0 |
| **Date** | 13 August 2026 |
| **Companion documents** | PRD v1.0; App Flow v1.0 |
| **Scope** | Detailed specification for 11 of 96 screens — those with non-trivial state, concurrency, or guard logic |
| **Stack assumption** | Laravel 12 + Blade/Livewire (applicant, agent), Filament 4 (officer, admin) — pending PRD OD-1 |

### Screens specified here

| ID | Screen | Why it's complex |
|---|---|---|
| APP-05 | Application hub | 14 status variants, guard aggregation, dependency locking |
| APP-06 | Form section editor | Autosave, conditional visibility, schema-driven rendering |
| APP-07 | Document checklist | 9 document states, async scan, conditional requirements |
| APP-09 | Review & declaration | Re-validation at the last moment, irreversibility |
| APP-13 | Payment return | Race between webhook and redirect; must never assert false failure |
| APP-17 | Appointment slot picker | Concurrency, capacity, dual-timezone rendering |
| APP-21 | Action-required workspace | Itemised remediation with per-item completion |
| OFF-03 | Officer queue | SLA computation at scale, saved views |
| OFF-05 | Case record | 7 tabs, guard-driven actions, concurrent edit |
| ADM-09 | Form template editor | Schema validation, diff, preview, immutable publish |
| AGT-10 | Agent acting-as hub | Delegated-authority delta over APP-05 |

---

## 1. Design Position

### 1.1 What this interface is not trying to be

A consular service is not a brand surface. The applicant using it is frequently anxious, often on a mid-range phone over intermittent mobile data, frequently operating in a second or third language, and occasionally facing a decision that determines whether they attend a funeral or start a job. The design goal is **predictability, legibility, and the elimination of doubt** — not memorability.

Concretely, this means the interface deliberately forgoes: scroll-triggered animation, asymmetric or grid-breaking layout, decorative motion, custom cursors, texture and grain overlays, and any effect that competes with the content. Where the house frontend guidance calls for bold aesthetic commitment, the commitment here is to **restraint executed precisely** — which that guidance explicitly permits, and which is the correct reading for this context.

What is carried over: a real token system rather than ad-hoc values, a deliberate type scale, deliberate typography rather than defaults, and refusal to ship components that look like untouched framework defaults.

### 1.2 Design tokens

```css
:root {
  /* Type — Source Serif 4 for headings, Public Sans for UI.
     Public Sans is a US government typeface designed for exactly this
     context: high legibility at small sizes, wide language coverage,
     open licence. Source Serif 4 gives headings authority without
     stiffness and pairs cleanly with it. */
  --font-heading: "Source Serif 4", Georgia, serif;
  --font-body: "Public Sans", "Helvetica Neue", sans-serif;
  --font-mono: "IBM Plex Mono", ui-monospace, monospace;  /* tracking numbers */

  /* Scale — 1.200 minor third, base 16px, capped for mobile density */
  --text-xs:   0.75rem;   /* 12px — metadata only, never body */
  --text-sm:   0.875rem;  /* 14px — secondary */
  --text-base: 1rem;      /* 16px — body; never smaller for form input */
  --text-lg:   1.125rem;
  --text-xl:   1.375rem;
  --text-2xl:  1.75rem;
  --text-3xl:  2.25rem;

  /* Spacing — 4px base */
  --space-1: 0.25rem;  --space-2: 0.5rem;  --space-3: 0.75rem;
  --space-4: 1rem;     --space-6: 1.5rem;  --space-8: 2rem;
  --space-12: 3rem;    --space-16: 4rem;

  /* Colour — all pairings verified ≥ 4.5:1 on their stated background */
  --ink:            #14181F;  /* body text on --surface: 15.8:1 */
  --ink-muted:      #4A5462;  /* secondary text on --surface: 8.1:1 */
  --ink-subtle:     #6B7684;  /* metadata on --surface: 4.9:1 */
  --surface:        #FFFFFF;
  --surface-sunken: #F4F6F8;
  --surface-raised: #FFFFFF;
  --border:         #D3D9E0;
  --border-strong:  #9AA5B1;

  --brand:          #0B4F71;  /* deep consular blue; on white 8.9:1 */
  --brand-hover:    #073B55;
  --brand-subtle:   #E8F1F6;

  --success:        #1D6F42;  /* on white 5.6:1 */
  --success-subtle: #E7F3EC;
  --warning:        #8A5A00;  /* on white 5.4:1 — NOT amber-on-white */
  --warning-subtle: #FDF3E2;
  --danger:         #A0212B;  /* on white 6.9:1 */
  --danger-subtle:  #FBEAEB;
  --info:           #0B4F71;
  --info-subtle:    #E8F1F6;

  --focus-ring:     #B85C00;  /* deliberately non-brand so it is never
                                 confused with a selected state */

  --radius-sm: 3px;  --radius-md: 6px;  --radius-lg: 10px;
  --shadow-1: 0 1px 2px rgb(20 24 31 / 0.06);
  --shadow-2: 0 2px 8px rgb(20 24 31 / 0.08);
  --shadow-modal: 0 16px 48px rgb(20 24 31 / 0.18);

  --tap-min: 44px;
  --content-max: 44rem;      /* applicant single-column reading measure */
  --panel-max: 90rem;        /* officer/admin dense layouts */
}
```

**Status colour rule.** Every status is conveyed by **icon + text + colour**, never colour alone. The warning token is a dark amber-brown rather than the conventional light amber precisely because light amber cannot reach 4.5:1 on white and is the single most common accessibility failure in status UI.

### 1.3 Component primitives referenced throughout

`Button` (primary / secondary / ghost / danger; loading and disabled-with-reason variants) · `StatusBadge` (icon + label + tone) · `Card` · `Alert` (info / success / warning / danger; inline and page-level) · `FieldGroup` (label, hint, input, error, described-by wiring) · `ProgressBar` · `Skeleton` · `EmptyState` · `Modal` · `Toast` · `Tabs` · `DataTable` · `Timeline` · `FileDrop`.

**Disabled-with-reason** is a first-class variant, not an afterthought: a disabled button that carries an `aria-describedby` pointing at a visible reason. It is used wherever a guard blocks an action, because hiding a blocked control leaves the user guessing.

---

## 2. APP-05 · Application Hub

### 2.1 Purpose and success criteria

The single screen from which an applicant understands and drives an application through its entire life. Success: an applicant returning after two weeks knows within one screenful what state their application is in and whether they must do anything.

### 2.2 Layout — draft state, mobile (320–767 px)

```
┌─────────────────────────────────────┐
│ ‹ Applications                      │  back, 44px target
├─────────────────────────────────────┤
│ VISA-IND-2026-8K3F2Q         [copy] │  mono, --text-sm
│ Tourist Visa · India                │  h1, --text-2xl
│ ┌────────┐                          │
│ │ Draft  │  Fee: $160.00            │  StatusBadge + fee
│ └────────┘                          │
├─────────────────────────────────────┤
│ 4 of 7 sections complete            │
│ ████████████░░░░░░░░░               │  ProgressBar
│ 6 of 8 documents uploaded           │
├─────────────────────────────────────┤
│ ┌─────────────────────────────────┐ │
│ │ Continue with                   │ │  primary CTA card
│ │ Travel details              →   │ │  --brand background
│ └─────────────────────────────────┘ │
├─────────────────────────────────────┤
│ SECTIONS                            │  h2, --text-sm caps
│ ┌─────────────────────────────────┐ │
│ │ ✓ Personal details    Complete  │ │  each card:
│ │   Name, date of birth, contact  │ │  44px+ tap target
│ │                        Review › │ │  whole card tappable
│ └─────────────────────────────────┘ │
│ ┌─────────────────────────────────┐ │
│ │ ● Travel details   In progress  │ │
│ │   Dates, purpose, itinerary     │ │
│ │                      Continue › │ │
│ └─────────────────────────────────┘ │
│ ┌─────────────────────────────────┐ │
│ │ 🔒 Sponsor details      Locked  │ │
│ │   Complete Personal details     │ │  reason is the description
│ │   first                         │ │
│ │              Go to that section›│ │
│ └─────────────────────────────────┘ │
├─────────────────────────────────────┤
│ DOCUMENTS                           │
│ ┌─────────────────────────────────┐ │
│ │ 6 of 8 required uploaded        │ │
│ │ ✓ 5 accepted  ● 1 checking      │ │
│ │ ○ 2 not uploaded                │ │
│ │              Manage documents › │ │
│ └─────────────────────────────────┘ │
├─────────────────────────────────────┤
│ ┌─────────────────────────────────┐ │
│ │ Before you can submit:          │ │  --warning-subtle
│ │ • Complete Travel details    ›  │ │  each item a link
│ │ • Complete Sponsor details   ›  │ │
│ │ • Upload Bank statement      ›  │ │
│ │ • Upload Travel insurance    ›  │ │
│ │                                 │ │
│ │ [ Submit application ]          │ │  disabled + described-by
│ └─────────────────────────────────┘ │
├─────────────────────────────────────┤
│ Delete this draft                   │  --danger, ghost
└─────────────────────────────────────┘
```

Desktop (≥ 1024 px): two columns — sections at 2fr left, a sticky sidebar at 1fr right holding progress, documents summary, and the submit block. Max width `--panel-max`; the section column never exceeds `--content-max`.

### 2.3 Data contract

```php
// Single read model assembled by one query object.
// Rendering the hub must not trigger N+1 across sections or documents.
ApplicationHubView {
  reference: string            // tracking number
  visaType: { name, country, code }
  status: ApplicationStatus
  publicStatus: string
  feeMinor: int, currency: string
  createdAt, submittedAt, paidAt, decidedAt: ?Carbon

  progress: {
    sectionsComplete: int, sectionsTotal: int,
    documentsUploaded: int, documentsRequired: int
  }

  sections: array<{
    key, label, description: string
    status: not_started|in_progress|complete|needs_attention|locked
    lockedBy: ?{ key, label }     // present iff locked
    fieldsComplete, fieldsRequired: int
  }>

  documents: { accepted, checking, rejected, missing: int }

  submitBlockers: array<{
    type: section|document|payment|scan
    label: string
    route: string
  }>                              // empty ⇒ submit enabled

  appointment: ?AppointmentSummary
  payment: ?PaymentSummary
  timeline: array<{ label, occurredAt, isCurrent }>
  permissions: { canEdit, canSubmit, canDelete, canWithdraw: bool }
}
```

`submitBlockers` is computed by the **same domain service** the `SubmitApplication` action calls. The UI must never re-implement the guard, or the two will drift and the applicant will see an enabled button that fails.

### 2.4 Section status derivation

| Status | Condition |
|---|---|
| `locked` | A declared schema dependency is not `complete` |
| `needs_attention` | Was complete; a later answer changed conditional visibility so a now-visible mandatory field is empty |
| `complete` | Every currently-visible mandatory field is valid |
| `in_progress` | At least one field has a value; not complete |
| `not_started` | No field has a value |

`needs_attention` is the state most often missed in implementations. It arises when, for example, the applicant changes marital status in a later section and thereby reveals a mandatory spouse field in an earlier one. It must be surfaced on the earlier card in red, and it must appear in `submitBlockers`.

### 2.5 Locking policy

Locking is permitted **only** where a genuine data dependency exists — the dependent section's schema, validation, or conditional visibility cannot be resolved until the blocking section is complete. Locking to enforce a preferred reading order is prohibited; the guided hub's whole premise is that order is a recommendation, not a constraint.

Every locked card names its blocker in the description and links to it. A lock with no stated reason is a specification defect.

### 2.6 States

| State | Rendering |
|---|---|
| Loading | Skeleton mirroring the final layout: header block, progress bar, five section-card skeletons. No spinner on blank. |
| Template load failure | Full hub renders from cached view data with a page-level Alert: "We're having trouble loading your form. Your answers are safe. [Retry]". Sections read-only until recovery. |
| Post-submission | Sections collapse into a read-only summary; submit block replaced by status-specific content (§2.7). |
| Permission denied | SYS-04, identical to not-found. |
| Deleted draft | SYS-04. |

### 2.7 Status variants — content switch

| Status | Replaces the submit block with | Sections |
|---|---|---|
| `draft` | Blockers + Submit | Editable cards |
| `submitted` / `payment_pending` | Fee due Alert + **Pay now** | Read-only summary |
| *payment processing* | Info Alert: "We're confirming your payment" + poll | Read-only |
| `paid` | Confirmation + **Book appointment** (if required) | Read-only |
| `under_review` / `document_verification` / `decision_pending` | Timeline + "No action needed" + expected window | Read-only |
| `info_requested` | **Danger-tone** Alert with request summary + **Resolve now** → APP-21 | Read-only except requested items |
| `interview_scheduled` | Appointment block pinned above everything | Read-only |
| `approved` / `rejected` | Outcome block + decision letter download | Read-only |
| `withdrawn` / `closed` | Archive summary + record download | Read-only |

### 2.8 Accessibility

- `h1` = visa type + destination; tracking number is `<p>` above it, not a heading.
- Sections list is a `<ul>`; each card an `<li>` containing one `<a>` covering the card, with the status announced inside the link text ("Travel details, in progress, continue").
- Progress bars carry `role="progressbar"` with `aria-valuenow/min/max` and a visible text equivalent.
- Submit button when disabled: `aria-describedby` pointing at the blocker list `id`, so a screen reader user learns why on focus without hunting.
- Copy-tracking-number control announces "Copied" via a polite live region.

### 2.9 Livewire notes

- One parent component `ApplicationHub`; section cards are stateless Blade partials, not nested Livewire components — nesting 7+ components here costs more than it saves.
- No polling in `draft`. Poll every 5 s **only** in *payment processing*, and every 30 s in `under_review` (to catch an information request arriving while the tab is open); stop polling when the tab is hidden.
- `wire:navigate` between hub and sections to preserve scroll and avoid full reloads.

### 2.10 Acceptance criteria

1. A disabled Submit always lists every blocker, and each blocker links to the screen that resolves it.
2. `submitBlockers` and the `SubmitApplication` guard are computed from one shared service; a test asserts they agree across a matrix of application states.
3. A locked section always renders a named blocker and a working link.
4. Changing an answer that reveals a mandatory field in an earlier section flips that section to `needs_attention` and adds a blocker, within the same request.
5. Hub renders in under 500 ms p95 with 7 sections and 12 documents, in a fixed number of queries independent of section or document count.
6. Every one of the 14 status variants renders without a dead end — each has either an action or an explicit "no action needed".

---

## 3. APP-06 · Form Section Editor

### 3.1 Layout (mobile)

```
┌─────────────────────────────────────┐
│ ‹ Back to application               │
│ Travel details          Section 3/7 │  h1 + position
│ ░░░░░░░░████████░░░░░░              │
├─────────────────────────────────────┤
│ ⓘ Saved at 14:32                    │  live region, polite
├─────────────────────────────────────┤
│ Purpose of travel *                 │
│ ┌─────────────────────────────────┐ │
│ │ Tourism                      ▾  │ │
│ └─────────────────────────────────┘ │
│                                     │
│ Intended arrival date *             │
│ ┌─────────────────────────────────┐ │
│ │ DD / MM / YYYY                  │ │  format hint always visible
│ └─────────────────────────────────┘ │
│ Must be at least 15 days from today │  hint below, --ink-muted
│                                     │
│ ⚠ Enter a date in DD/MM/YYYY format │  error replaces nothing;
│                                     │  sits between input and hint
│ Do you have a sponsor? *            │
│  ( ) Yes   (•) No                   │
│  ↓ conditional block, announced     │
│ ┌─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ┐ │
│   (hidden — No selected)            │
│ └─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ┘ │
├─────────────────────────────────────┤
│ [ Save and continue → ]             │
│ [ Save and return to application ]  │
└─────────────────────────────────────┘
```

### 3.2 Autosave specification

| Trigger | Behaviour |
|---|---|
| Field blur | Debounced 400 ms; persists that field only |
| Idle 30 s while dirty | Persists all dirty fields |
| Navigation away | Synchronous final save; navigation waits with inline indicator |
| Session warning (SYS-01) | Save fires **before** the modal renders |
| Reconnect after offline | Queued saves flush oldest-first |

Indicator states: `Saving…` → `Saved at HH:MM` → (on failure) `Not saved — [Retry]`. The indicator is a polite live region; it must not announce on every keystroke.

**Failure handling.** A save failure never blocks input and never discards content. First failure: inline non-blocking Alert with Retry. Third consecutive failure: escalate to a modal offering **Copy my answers** (puts the section's content on the clipboard as plain text) alongside Retry. This is the only genuine protection against losing twenty minutes of typing to a backend outage, and it is cheap to build.

### 3.3 Conditional visibility

- Evaluated server-side on save; evaluated client-side for immediate feedback. Server is authoritative.
- A field that becomes hidden is **not** deleted — its value is retained but excluded from validation and from the snapshot. This matters: an applicant who toggles "Do you have a sponsor?" to No and back to Yes must not lose what they already typed.
- Revealing a block moves focus to its first field and announces via `aria-live="polite"`.
- A hidden field is never required and never contributes a blocker.

### 3.4 Validation

| Layer | Runs | On failure |
|---|---|---|
| Client | On blur | Inline error; does not block save |
| Server field | On save | Inline error, value retained |
| Server section | On "Save and continue" | Error summary at top with anchor links to each field |
| Cross-section | At APP-09 | Blocks submission, names the section |

Error summary is an `<h2>` + ordered list of links; focus moves to the summary heading on failed submit, per WCAG.

### 3.5 Acceptance criteria

1. No input is lost by session expiry, navigation, browser back, connection loss, or a failed save.
2. Toggling a conditional field off and back on restores the previously entered value.
3. All validation is enforced server-side; a client bypass cannot persist an invalid answer.
4. Section renders and saves in a fixed query count regardless of field count.
5. Full keyboard operability, including date entry and any composite field.

---

## 4. APP-07 · Document Checklist

### 4.1 Document row — nine states

| State | Badge | Row content | Controls |
|---|---|---|---|
| `not_uploaded` | ○ grey "Required" | Accepted formats, max size, why it's needed | **Upload** |
| `uploading` | ● progress | Filename, progress bar, byte count | **Cancel** |
| `scanning` | ● "Checking file" | "Usually under a minute" | — (auto-refresh) |
| `uploaded` | ✓ "Uploaded" | Filename, size, date | **Replace**, **Remove** |
| `under_review` | ● "With our team" | Submitted date | — |
| `accepted` | ✓ green "Accepted" | Reviewed date | View |
| `rejected` | ✕ red "Rejected" | **Reason in plain language, prominent** | **Upload replacement** |
| `resubmission_required` | ⚠ amber "Replace this" | Reason | **Upload replacement** |
| `scan_failed` | ✕ red "Couldn't be processed" | Guidance; support code | **Upload a different file** |

`infected` is never shown as such to the applicant. It renders as `scan_failed` with neutral guidance. Telling an applicant their file contains malware is unhelpful, frequently wrong from their perspective (an infected shared machine, not intent), and leaks scanner behaviour.

### 4.2 Upload interaction

- Three entry paths on mobile, in this order of prominence: **Take a photo** · **Choose from library** · **Choose a file**. Camera-first, because most applicants photograph documents rather than scan them.
- Client-side pre-checks (size, extension) run before any byte is sent, so an oversized file fails in under a second rather than after a two-minute upload on 3G. Server-side validation still runs and is authoritative.
- Chunked upload with resume for files over 5 MB.
- On completion the row transitions to `scanning` immediately; no intermediate blank.

### 4.3 Error copy — exact strings

| Failure | Copy |
|---|---|
| Too large | "This file is 14.2 MB. The limit for a bank statement is 10 MB. Try photographing fewer pages, or reducing the image quality." |
| Wrong format | "We can accept PDF, JPG, or PNG files. This file is a .docx." |
| Interrupted | "The upload didn't finish — probably a connection problem. Your file is still selected. [Try again]" |
| Storage unavailable | "We couldn't save your file. This is a problem on our side, not with your file. Please try again in a few minutes. (Reference: 7K2M)" |
| Scan failed | "We couldn't process this file. Try uploading a different copy — a clear photo or a fresh PDF usually works." |

Note what these do: state the actual numbers, name the actual formats, assign fault correctly, and give a concrete next action.

### 4.4 Conditional requirements

When requirements cannot yet be resolved because a determining section is incomplete, the screen renders a dependency empty state rather than a partial or guessed list:

> **We'll show your full document list once you complete *Personal details***
> Some documents depend on your age and whether you have a sponsor.
> [Go to Personal details →]

Optional documents are listed below required ones under a separate heading, collapsed by default.

### 4.5 Acceptance criteria

1. A file whose extension and actual content type disagree is rejected server-side before storage.
2. No document is previewable or downloadable while scan status is anything other than clean.
3. Replacing a document creates a new version row; the prior version remains retrievable by staff.
4. Every upload, replace, remove, and preview writes an audit entry.
5. Scan completion updates the row without a manual refresh, within 10 s of the job finishing.
6. The uploader is fully keyboard-operable.

---

## 5. APP-09 · Review & Declaration

### 5.1 Purpose

The last point at which an applicant can change anything, and the point at which immutability must be understood rather than merely disclosed.

### 5.2 Structure

```
Review your application                       h1
VISA-IND-2026-8K3F2Q

┌───────────────────────────────────────────┐
│ ⓘ After you submit, you won't be able to  │  Alert, info tone
│   change this application unless we ask   │  placed BEFORE the
│   you for corrections.                    │  review content
└───────────────────────────────────────────┘

Personal details                    [Edit ›]  h2 per section
  Full name          Priya Anand Sharma
  Date of birth      14 March 1991
  Nationality        India
  Passport number    •••••••34            [Show]

Travel details                      [Edit ›]
  ...

Documents                     [Manage docs ›]
  ✓ Passport               accepted
  ✓ Photograph             accepted
  ● Bank statement         checking

Fee
  Visa fee                 $140.00
  Service fee              $20.00
  ─────────────────────────────────
  Total                    $160.00 USD

┌───────────────────────────────────────────┐
│ Declaration                               │
│ [long-form declaration text, scrollable,  │
│  never truncated behind a "read more"]    │
│                                           │
│ [ ] I confirm the information I have      │
│     given is true and complete.           │
│                                           │
│ [ Submit application ]                    │
└───────────────────────────────────────────┘
```

Edit links return to APP-06 for that section and return **here** on save, not to the hub.

### 5.3 Late re-validation

The guard is re-evaluated at render **and** at submit. Conditions that can change between hub and submit:

| Change | Behaviour |
|---|---|
| Document rejected by a verifier mid-review | Page-level danger Alert naming the document; submit disabled |
| Document still scanning | Info Alert; submit disabled; auto-refresh every 5 s |
| Form template deactivated | Info Alert explaining the application continues on its bound version; submit **remains enabled** |
| Fee rule changed | Fee refreshed with a notice showing old and new; explicit re-acknowledgement required |
| Session expired | SYS-02; declaration state not preserved (deliberately — a declaration must be made in a live session) |

### 5.4 Acceptance criteria

1. Submit is impossible without the declaration checkbox; the checkbox is never pre-ticked and never persisted across sessions.
2. The immutability warning appears above the fold, before review content.
3. A guard failing between page render and submit produces a specific, actionable message — never a generic error.
4. Successful submission writes the snapshot and the status transition in one transaction; a failure leaves the application in `draft` with nothing partially written.
5. Double-submission (double-tap, double-click, browser retry) produces exactly one submission.

---

## 6. APP-13 · Payment Return — the race condition screen

### 6.1 The problem this screen solves

Three events occur in a non-deterministic order: the applicant's browser returns from Stripe, Stripe's webhook arrives, and the webhook finishes processing. The webhook is authoritative. The browser return carries no trustworthy outcome. A naive implementation reads the return URL, finds the application not yet `paid`, and tells the applicant their payment failed — while the money has in fact left their account. That is the single worst defect this system can ship, and it is common.

**Governing rule: absence of confirmation is never evidence of failure.**

### 6.2 State machine

```
                    ┌──────────────────┐
   arrive ─────────▶│   CONFIRMING     │  poll: 1s ×5, 2s ×5
                    └────────┬─────────┘
                             │
      ┌──────────────────────┼──────────────────────┐
      │                      │                      │
   payment                ~15s elapsed          definitive
   .status =              no resolution         gateway failure
   succeeded                   │                (declined /
      │                        │                 cancelled)
      ▼                        ▼                      ▼
  ┌────────┐          ┌─────────────────┐      ┌──────────┐
  │APP-14  │          │   REASSURANCE   │      │  APP-15  │
  │success │          │ keep polling    │      │  failed  │
  └────────┘          │ at 5s intervals │      └──────────┘
                      │ up to 2 min,    │
                      │ then stop and   │
                      │ rely on email   │
                      └─────────────────┘
```

### 6.3 The three renderings

**CONFIRMING** (0–15 s)
```
        ◐  (indeterminate, not a progress bar —
            a progress bar would imply known duration)

     Confirming your payment

  This usually takes a few seconds.

  VISA-IND-2026-8K3F2Q
```
No cancel control. No back control. The tab bar is hidden.

**REASSURANCE** (> 15 s) — the critical copy
```
     This is taking longer than usual

  Your payment is safe. We're waiting for
  confirmation from our payment provider.

  You can close this page — we'll email you
  as soon as it's confirmed, usually within
  a few minutes.

  VISA-IND-2026-8K3F2Q

  [ Back to my application ]
```
Note what this copy does **not** say: it does not say "failed", does not say "try again", and does not offer a retry control. Offering retry here is how double charges happen.

**Definitive failure** → APP-15, reached only on an explicit failure signal from the payment record (status `failed` with a gateway reason), never on timeout.

### 6.4 Implementation notes

- Poll a narrow endpoint returning `{ status, receiptReady }` only — not the full hub payload.
- Stop polling when `document.hidden`; resume on visibility.
- Hard-stop polling at 2 minutes; the email path takes over.
- The screen is safe to close at every moment; no state lives only in the browser.
- Returning to this URL later resolves immediately from the current payment status.
- Browser back from here goes to APP-05, never to Stripe.

### 6.5 Acceptance criteria

1. A webhook delayed 60 s produces the reassurance state, never a failure state.
2. A webhook arriving **before** the browser return resolves immediately to APP-14 with no flash of the confirming state.
3. No path from this screen initiates a second payment.
4. Closing the tab at any point during confirmation loses nothing; the applicant is emailed on resolution.
5. Feature test: webhook-first, redirect-first, webhook-never, and duplicate-webhook orderings all produce correct terminal states.

---

## 7. APP-17 · Appointment Slot Picker

### 7.1 Layout

```
┌─────────────────────────────────────┐
│ ‹ Change location                   │
│ Choose a time                       │
│ Mumbai Visa Application Centre      │
├─────────────────────────────────────┤
│  ‹  September 2026  ›               │
│  M  T  W  T  F  S  S                │
│  1  2  3  4  5  6  7                │  unavailable dates:
│  8  9 [10][11] 12 13 14             │  --ink-subtle, not
│ [15][16][17] 18 19 20 21            │  disabled-invisible
├─────────────────────────────────────┤
│ Wednesday 10 September              │
│ Times shown in Mumbai time (IST)    │  ALWAYS stated
│                                     │
│ ( ) 09:00    ( ) 09:30              │
│ (•) 10:00    ( ) 11:30              │
│ ( ) 14:00    ( ) 15:30              │
│                                     │
│ 10:00 IST is 05:30 your time (BST)  │  only when zones differ
├─────────────────────────────────────┤
│ [ Confirm 10 September, 10:00 IST ] │  label restates choice
└─────────────────────────────────────┘
```

Below 400 px the month grid collapses to a horizontally scrollable date strip.

### 7.2 Timezone handling

- Slots are stored in UTC, generated from the location's local operating hours, and rendered in the **location's** timezone as primary.
- The applicant's local equivalent is shown as secondary **only when the zones differ**, prefixed "your time" with the abbreviation.
- The confirm button label restates the full choice including timezone — this is where mis-booking happens.
- The appointment letter and all notifications use location time as primary throughout, consistently.
- DST transitions at the location are handled by generating slots from local wall-clock rules, never by adding fixed offsets.

### 7.3 Concurrency — the central problem

Capacity is finite and multiple applicants browse simultaneously.

**Rejected approach:** hold a soft lock when a slot is selected. It creates phantom unavailability, needs expiry handling, and fails badly on abandonment.

**Specified approach:** optimistic selection, atomic confirmation.

```
1. Availability is read without locking; the list may be stale.
2. On Confirm, a single atomic operation:
     - row-lock the slot
     - re-check booked_count < capacity
     - insert the appointment
     - increment booked_count
   all inside one transaction.
3. Success → APP-18.
4. Capacity exhausted → SlotUnavailableException.
```

**On `SlotUnavailableException` the screen must not error out.** It:
- keeps the applicant on APP-17,
- keeps the selected **date**,
- refreshes that date's times,
- renders an inline warning above the time list: *"Sorry — 10:00 was just taken. Here are the times still available."*,
- moves focus to the warning,
- clears the selection so no accidental double-confirm.

A full-page error here is a specification failure; this is an expected outcome under normal load, not an exception path.

### 7.4 Empty and edge states

| Condition | Rendering |
|---|---|
| Date has no slots | Inline: "No times available on this date" + "Next available: 15 September →" |
| Month has no slots | Month grid renders with all dates muted + a link to the next month containing availability |
| Location has no slots at all | Return to APP-16 with an explanatory Alert; never a blank calendar |
| Capacity reduced by admin below bookings | Existing bookings are honoured; a warning is raised to operations, not to the applicant |

### 7.5 Acceptance criteria

1. Concurrency test: 50 simultaneous confirmations against a capacity-1 slot yield exactly 1 success and 49 clean recoverable failures. Zero overbooking.
2. A slot taken during selection never produces a full-page error and never loses the selected date.
3. Times always display the location's timezone label; applicant-local equivalent appears only when zones differ.
4. A DST-transition day generates the correct number of slots.
5. Every terminal state offers a next action; no dead ends.
6. Fully keyboard-navigable calendar with correct roles and date announcements.

---

## 8. APP-21 · Action-Required Workspace

### 8.1 Purpose

When the mission requests corrections, this screen contains **exactly** what was asked and nothing else. The applicant should not have to re-read their whole application to find what changed.

### 8.2 Structure

```
Action required                              h1
VISA-IND-2026-8K3F2Q

┌───────────────────────────────────────────┐
│ ⚠ We need some more information           │  danger-subtle
│                                           │
│ From the review team, 11 August 2026:     │
│ "Your bank statement doesn't cover the    │
│  full three months required, and your     │
│  employment dates don't match the         │
│  letter you provided."                    │
│                                           │
│ Please respond by 25 August 2026.         │  if deadline set
└───────────────────────────────────────────┘

What you need to do                          h2
2 of 3 items done

┌───────────────────────────────────────────┐
│ ✓ 1. Employment section                   │
│      Correct your employment dates        │
│      Updated 12 Aug           [Review ›]  │
├───────────────────────────────────────────┤
│ ✓ 2. Bank statement                       │
│      Upload a statement covering          │
│      May–July 2026                        │
│      Uploaded 12 Aug         [Replace ›]  │
├───────────────────────────────────────────┤
│ ○ 3. Employment letter                    │
│      Upload a letter matching the dates   │
│      Not yet done             [Upload ›]  │
└───────────────────────────────────────────┘

[ Resubmit application ]                     disabled + reason
Complete item 3 before resubmitting.
```

### 8.3 Item completion logic

| Item type | Marked done when |
|---|---|
| Section correction | The named section is `complete` **and** has been modified after the request timestamp |
| Document replacement | A new version exists after the request timestamp **and** its scan is clean |
| Acknowledgement | The applicant explicitly ticks it |

The "modified after the request timestamp" condition matters — a section that was already complete must not auto-satisfy a request to change it.

### 8.4 Acceptance criteria

1. Only requested items appear; the rest of the application stays read-only.
2. Resubmit is disabled until every item is done, and the disabled state names which items remain.
3. Editing a requested section from here returns here, not to the hub.
4. Resubmission transitions to `resubmitted` and notifies the requesting officer.
5. A second information request creates a new workspace; the previous one remains visible as history.

---

## 9. OFF-03 · Officer Queue

### 9.1 Layout (desktop only)

```
┌──────────────────────────────────────────────────────────────────────┐
│ My queue                                    [Saved views ▾] [Filters]│
│ 34 cases · 3 at risk · 1 breached                                    │
├──────────────────────────────────────────────────────────────────────┤
│ SLA │ Tracking      │ Applicant   │ Type    │ Status    │ Days│ Appt │
├─────┼───────────────┼─────────────┼─────────┼───────────┼─────┼──────┤
│ ●RED│ VISA-…8K3F2Q  │ P. Sharma   │ Tourist │ In review │  12 │  ✓   │
│ Brch│               │ India       │         │           │     │      │
├─────┼───────────────┼─────────────┼─────────┼───────────┼─────┼──────┤
│ ●AMB│ VISA-…2M9X1P  │ J. Okonkwo  │ Student │ Docs      │   7 │  —   │
│ 2d  │               │ Nigeria     │         │           │     │      │
├─────┼───────────────┼─────────────┼─────────┼───────────┼─────┼──────┤
│ ●GRN│ VISA-…5T7B3K  │ L. Chen     │ Business│ In review │   2 │ 15/9 │
└─────┴───────────────┴─────────────┴─────────┴───────────┴─────┴──────┘
```

SLA column shows tone **and** text — "Breached", "2d left", "8d left". Never colour alone. Default sort: SLA risk descending, then oldest submission.

### 9.2 SLA computation

SLA must not be computed per-row at render across a large table.

- A nightly job plus a transition-triggered job maintain `sla_due_at` and `sla_state` as persisted columns on the application.
- The queue reads those columns; it never computes elapsed business hours in the query or the view.
- Business-hours calculation accounts for the mission's working calendar and holidays.
- Time spent in `info_requested` is excluded from the officer's SLA clock by default — the applicant, not the officer, holds the case. *(Reschedule handling is App Flow open question AF-5 and must be settled before this is implemented.)*

### 9.3 Filters and saved views

Filters: status, visa type, destination, nationality, submitted range, appointment status, SLA state, has-rejected-documents. Senior officers additionally filter by assigned officer.

Saved views persist per officer. Three are seeded: *At risk*, *Awaiting my decision*, *Returned resubmissions*.

### 9.4 States

| State | Rendering |
|---|---|
| Empty (queue clear) | Positive state: "Your queue is clear" + **Pull from unassigned (12 waiting)** |
| Empty (filtered) | "No cases match these filters" + Clear filters |
| Empty (permission) | "No applications match your access" — no counts |
| Loading | Table skeleton with correct column widths |

### 9.5 Acceptance criteria

1. Queue loads under 1 s p95 with 100,000 applications in the table.
2. SLA state is read from a persisted column; no elapsed-time computation in the query.
3. A case officer's queue contains only their assigned cases; a policy test proves it.
4. Bulk actions are limited to assignment. Bulk decisions are not offered anywhere.
5. Every filter combination has a designed empty state.

---

## 10. OFF-05 · Case Record

### 10.1 Persistent header — never scrolls away

```
┌──────────────────────────────────────────────────────────────────────┐
│ VISA-IND-2026-8K3F2Q  ·  Priya Anand Sharma  ·  Tourist · India      │
│ In review · 12 days · ●Breached · Assigned: C. Mensah                │
│                                                                      │
│           [Request info] [Schedule appt] [Reject] [Approve ⛔]        │
└──────────────────────────────────────────────────────────────────────┘
│ Overview │ Application │ Documents │ Payments │ Appointments │ Notes │ History │
```

### 10.2 Disabled Approve — the central interaction

Approve is **always visible, never hidden**. When guards are unmet it is disabled and carries a reason available on hover, on focus, and in a persistent Overview checklist:

> **Cannot approve**
> · 2 required documents not accepted (Bank statement, Employment letter)
> · Biometrics appointment not completed

Hiding the control would leave the officer guessing and generate supervisor escalations. Disabling it with a specific reason teaches the rule and points at the fix. Each reason links to the tab that resolves it.

The reason strings are generated by the same `CanApproveApplication` guard the domain action calls — not duplicated in the panel.

### 10.3 Tab specifications

| Tab | Content | Notes |
|---|---|---|
| **Overview** | Applicant summary, key dates, fee and payment status, appointment status, **outstanding-items checklist mirroring the approval guards exactly**, SLA detail with the breach reason | Landing tab |
| **Application** | Form answers rendered from the **immutable snapshot**, section by section. A "Compare with current profile" toggle highlights fields where live profile data now differs | Read-only, always. A banner states that this is the submitted version |
| **Documents** | Rows: type, status, version count, uploaded date, scan status. Actions: Preview, Accept, Reject, Request resubmission. Version history expandable per document | Preview blocked unless scan clean; **audit entry written before the signed URL is issued**, not after |
| **Payments** | Ledger: payments, line items, webhook events with timestamps and outcomes, invoices, refunds | Read-only for officers; finance sees the same view in ADM |
| **Appointments** | Scheduled appointments, status, change history; schedule / reschedule / cancel | If the visa type requires none: "No appointment required for this visa type" |
| **Notes** | Chronological notes with author, timestamp, and visibility. Visibility selector (Internal / Visible to applicant) is explicit and **confirmed on save** | An applicant-visible note is effectively a communication; the confirm step prevents accidental disclosure |
| **History** | Append-only status history and audit entries: actor, timestamp, from/to, reason | Read-only permanently; no edit or delete affordance exists anywhere |

### 10.4 Concurrent editing

Optimistic concurrency on a version column. When an officer's write is stale:

> **This case was updated while you were working**
> C. Mensah rejected the *Bank statement* document 2 minutes ago.
> Your note has been kept below — review the change and save again.
> [Reload case] [Copy my note]

The officer's typed content is always preserved. Losing a half-written rejection reason to a colleague's unrelated edit is the fastest way to lose officer trust in the tool.

### 10.5 Accessibility

- Tabs use the standard tab pattern: arrow-key navigation, `aria-selected`, `aria-controls`, one tab stop for the tab list.
- The disabled Approve button uses `aria-describedby` → the guard reason block, so the reason is announced on focus.
- The header is a landmark region announced once, not repeated per tab change.
- Tab changes move focus to the panel and announce the tab name.

### 10.6 Acceptance criteria

1. No path — panel action, custom action, route, or domain call — approves an application with unmet guards. Tested at the domain layer, not only the UI.
2. Guard reasons in the UI and the domain guard derive from one service.
3. Document preview writes an audit entry before the signed URL is generated; a test asserts ordering.
4. Every custom panel action explicitly authorises; a test enumerates all actions and asserts each has an authorisation check.
5. The Application tab always renders from the snapshot; a test mutates the live profile and asserts the tab is unchanged.
6. A case officer cannot open a case assigned to another officer.

---

## 11. ADM-09 · Form Template Editor

### 11.1 Layout — three panes (desktop only)

```
┌──────────────┬────────────────────────┬─────────────────┐
│ VERSIONS     │ SCHEMA                 │ PREVIEW         │
│              │                        │                 │
│ v4 (active)  │ {                      │ [Personal      ]│
│ Published    │   "sections": [        │ [details       ]│
│ 12 Jun 2026  │     {                  │                 │
│              │       "key": "personal"│  Full name *    │
│ v5 (draft)   │       "label": "…"     │  ┌───────────┐  │
│ ● Editing    │       "fields": [ … ]  │  │           │  │
│              │     }                  │  └───────────┘  │
│ [+ New ver.] │   ]                    │                 │
│              │ }                      │  Date of birth *│
│              │                        │  ┌───────────┐  │
│              │ ⚠ 2 problems           │  │DD/MM/YYYY │  │
│              │ sections[1].fields[3]: │  └───────────┘  │
│              │   unknown type "phone2"│                 │
│              │ sections[2]:           │ [Diff vs v4]    │
│              │   duplicate key        │ [Publish v5]    │
└──────────────┴────────────────────────┴─────────────────┘
```

### 11.2 Validation

Live, debounced 500 ms. Errors are reported **by JSON path**, each clickable to jump the editor cursor to that location. Categories:

| Category | Example |
|---|---|
| Structural | Missing `sections`; section without `key` |
| Reference | Field type not in the registry; condition referencing an unknown field |
| Semantic | Duplicate section or field key; circular conditional dependency |
| Policy | Mandatory field inside a conditionally hidden block with no fallback |
| Migration | A field removed that carries answers in in-flight drafts on the prior version |

Publish is **impossible** while any structural, reference, or semantic error exists. Policy and migration warnings require explicit acknowledgement but do not block.

### 11.3 Diff view

Structural diff against the current active version, grouped: sections added / removed / reordered, fields added / removed / changed, validation-rule changes, conditional-logic changes. This is what an administrator actually reviews before publishing — a raw text diff of JSON is not reviewable.

### 11.4 Publish

Confirmation dialogue states, explicitly:

> Publishing v5 will:
> · make v5 the active version for **Tourist Visa · India**
> · apply to applications created **after** publication
> · leave 47 in-flight applications on v4, unchanged
> · make v5 immutable — further changes require a new version
>
> [Cancel] [Publish v5]

### 11.5 Acceptance criteria

1. An invalid schema cannot be published under any circumstance.
2. Publishing does not alter any existing draft or submitted application; a test asserts in-flight drafts retain their bound version.
3. A published version is immutable; edit attempts are rejected at the domain layer.
4. Preview renders identically to the applicant's APP-06, using the same rendering components — not a separate approximation.
5. Every publish writes an audit entry with actor, version, and diff summary.

---

## 12. AGT-10 · Agent Acting-As Hub — delta over APP-05

Structurally identical to APP-05. The differences are what matter.

### 12.1 The acting-as banner

Persistent, non-dismissible, at the top of every screen in the application context:

```
┌──────────────────────────────────────────────────────────┐
│ ⓘ You are acting on behalf of PRIYA ANAND SHARMA         │
│   Authorisation granted 2 Aug 2026 · expires 2 Feb 2027  │
│                                     [View client ›]      │
└──────────────────────────────────────────────────────────┘
```

High contrast, distinct from all status tones so it is never mistaken for a status. Sticky on scroll. Every action confirmation dialogue in this context restates the client's name.

### 12.2 Behavioural differences

| Aspect | Applicant (APP-05) | Agent (AGT-10) |
|---|---|---|
| Banner | None | Persistent acting-as |
| Passport reveal | Permitted, audited | Permitted only if linkage scope includes it |
| Delete draft | Permitted | Permitted; notifies the applicant |
| Withdraw | Permitted | **Not permitted** — withdrawal is the applicant's decision alone |
| Decision letter | Full download | Per App Flow AF-4, unresolved — assume outcome only until confirmed |
| Profile edit | Permitted | Read-only; agents propose changes, applicants confirm |
| Audit attribution | The applicant | The individual agent user, plus the agency |

### 12.3 Revocation mid-session

The linkage is checked **on every request** in the agent guard, not cached in the session. On revocation the next request lands on AGT-16, which states plainly that authorisation ended, gives the date, confirms the applicant retains full access, and shows no client data beyond the name.

### 12.4 Acceptance criteria

1. The acting-as banner is present on every screen in the application context and cannot be dismissed.
2. Linkage is verified per request; a revoked agent loses access on their next request with no cache window.
3. An agent cannot reach an unlinked applicant's application; the response is indistinguishable from not-found.
4. Every agent action is audited against the individual user, not the agency.
5. Withdrawal controls are absent from the agent context entirely — not merely disabled.

---

## 13. Cross-Cutting Acceptance Criteria

Applying to all eleven screens:

1. **Guard parity.** Every UI-side guard reason derives from the domain service enforcing that guard. A test matrix asserts UI and domain agree across application states.
2. **No colour-only status.** Automated check: every status indicator carries a text label.
3. **Contrast.** Every token pairing verified ≥ 4.5:1 (≥ 3:1 for large text and non-text indicators) in CI.
4. **Keyboard.** Every interactive element reachable and operable by keyboard; focus visible throughout; no traps outside intentional modals.
5. **Query budget.** Each screen renders in a fixed query count independent of collection size; asserted by test.
6. **No dead ends.** Every state on every screen offers a next action or explicitly states none is available.
7. **Loss prevention.** No screen loses user input to session expiry, navigation, connection loss, or a failed save.
8. **Error attribution.** Every error states what happened, whether data was lost, and what to do next — in that order.

---

## 14. Open Items Carried Forward

| ID | Item | Blocks |
|---|---|---|
| UI-1 | PRD OD-1 — Laravel 12 vs 13 | Component library baseline |
| UI-2 | PRD OD-2 — money as integer minor units | Fee rendering across APP-05/09/11 |
| UI-3 | App Flow AF-4 — agent access to decision letters | AGT-10 §12.2 |
| UI-4 | App Flow AF-5 — reschedule effect on SLA clock | OFF-03 §9.2 |
| UI-5 | Confirmation of the mission's working calendar and holidays | SLA computation |
| UI-6 | Whether `info_requested` time is excluded from officer SLA (assumed yes) | OFF-03 §9.2 |
| UI-7 | Licensing confirmation for Source Serif 4 and Public Sans in the deployment jurisdiction | Design tokens |
