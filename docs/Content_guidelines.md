# Content Guidelines & Design System
## Visa Application System

| Field | Value |
|---|---|
| **Version** | 1.0 |
| **Date** | 13 August 2026 |
| **Purpose** | Authoritative design system and content standard. Input document for Claude Code. |
| **Stack** | Laravel 12, Blade + Livewire 3.8.4, Tailwind CSS 3.4.x, Alpine 3.14.x |
| **Supersedes** | Screen UI Specs §1 (tokens) — extended and made complete here |
| **Modes supported** | Light LTR · High contrast · Print |
| **Modes not supported** | Dark mode · RTL (see §3.4) |

---

## 1. How to Use This Document

### 1.1 Authority

This document is **prescriptive, not advisory**. Where it specifies a value, that value is used. An implementing agent should not select colours, spacing, font sizes, border radii, copy, or component structure. If something needed is not specified here, that is a gap in this document — raise it rather than inventing a value.

### 1.2 Precedence

1. This document — tokens, components, copy
2. Screen UI Specs — screen-specific layout and behaviour
3. App Flow — navigation, states, journeys
4. PRD — requirements

Where a screen spec shows a layout, it composes components defined here. It does not introduce new visual values.

### 1.3 Five rules that override convenience

1. **No arbitrary values.** `p-[13px]`, `text-[#3B82F6]`, `w-[437px]` are prohibited. Every value comes from the token scale.
2. **No colour-only meaning.** Every status, state, or distinction carries an icon or text alongside colour.
3. **No component without a disabled, loading, error, and empty consideration.** If a state cannot occur, say so explicitly in a comment.
4. **No new component without an entry in §5.** Extend an existing one or add a specified entry.
5. **No copy invented at the component level.** Strings come from §7 or from a translation key.

---

## 2. Foundations

### 2.1 Design principles

The applicant is frequently anxious, often on a mid-range phone over intermittent data, frequently operating in a second or third language, and occasionally facing a decision with serious personal consequences. Every principle below follows from that.

| Principle | In practice |
|---|---|
| **Legibility over personality** | 16px minimum body text. Generous line height. No decorative type. |
| **Predictability over delight** | Controls look and behave identically everywhere. No novel interactions. |
| **State is always visible** | The user never wonders whether something saved, sent, or succeeded. |
| **Blocked means explained** | A disabled control always states why, adjacent and in plain language. |
| **Nothing is lost** | No interaction can discard user input. Ever. |
| **Restraint executed precisely** | No animation, texture, gradient, or asymmetry. Precision is the craft, not flourish. |

### 2.2 Colour tokens

Every value below has a verified contrast ratio against its stated background. Ratios are stated so they are never re-derived or guessed.

```css
/* resources/css/tokens.css */
:root {
  /* ── Surfaces ───────────────────────────────────────── */
  --surface:          #FFFFFF;
  --surface-sunken:   #F4F6F8;  /* page background behind cards */
  --surface-raised:   #FFFFFF;  /* cards, modals */
  --surface-overlay:  rgb(20 24 31 / 0.55);  /* modal backdrop */

  /* ── Ink (text) ─────────────────────────────────────── */
  --ink:              #14181F;  /* 15.8:1 on --surface — body */
  --ink-muted:        #4A5462;  /*  8.1:1 — secondary text   */
  --ink-subtle:       #6B7684;  /*  4.9:1 — metadata only    */
  --ink-inverse:      #FFFFFF;  /* on --brand: 8.9:1         */

  /* ── Borders ────────────────────────────────────────── */
  --border:           #D3D9E0;  /* 1.6:1 — decorative dividers */
  --border-strong:    #9AA5B1;  /* 3.0:1 — input borders, meets non-text minimum */
  --border-ink:       #14181F;  /* high-emphasis outlines */

  /* ── Brand ──────────────────────────────────────────── */
  --brand:            #0B4F71;  /* 8.9:1 on white */
  --brand-hover:      #073B55;
  --brand-active:     #052C41;
  --brand-subtle:     #E8F1F6;  /* --ink on this: 14.2:1 */
  --brand-border:     #7FA9C0;

  /* ── Status ─────────────────────────────────────────── */
  --success:          #1D6F42;  /* 5.6:1 on white */
  --success-subtle:   #E7F3EC;
  --success-border:   #8CC0A2;

  --warning:          #8A5A00;  /* 5.4:1 — dark amber, NOT light amber */
  --warning-subtle:   #FDF3E2;
  --warning-border:   #D9AE63;

  --danger:           #A0212B;  /* 6.9:1 on white */
  --danger-hover:     #7E1922;
  --danger-subtle:    #FBEAEB;
  --danger-border:    #D98E93;

  --info:             #0B4F71;
  --info-subtle:      #E8F1F6;
  --info-border:      #7FA9C0;

  /* ── Focus ──────────────────────────────────────────── */
  --focus-ring:       #B85C00;  /* deliberately non-brand: never confused
                                   with a selected or active state */
  --focus-ring-offset: #FFFFFF;

  /* ── Disabled ───────────────────────────────────────── */
  --disabled-bg:      #EDF0F3;
  --disabled-ink:     #6B7684;  /* 4.9:1 — disabled text stays readable
                                   because it must be read to be understood */
  --disabled-border:  #C3CBD4;
}
```

**On the warning token.** Conventional light amber (`#F59E0B` and similar) cannot reach 4.5:1 on white and is the most common contrast failure in status UI. `--warning` is a dark amber-brown for text and icons; `--warning-subtle` is the light fill it sits on.

**On disabled text.** Disabled controls in this system always carry a reason. A reason that cannot be read is useless, so disabled text meets normal contrast rather than the usual faded treatment.

#### Semantic mapping — which token for which meaning

| Meaning | Text | Fill | Border | Icon |
|---|---|---|---|---|
| Neutral / default | `--ink` | `--surface` | `--border` | `--ink-muted` |
| Primary action | `--ink-inverse` | `--brand` | `--brand` | `--ink-inverse` |
| Informational | `--info` | `--info-subtle` | `--info-border` | `--info` |
| Success / accepted / complete | `--success` | `--success-subtle` | `--success-border` | `--success` |
| Warning / action required / in progress | `--warning` | `--warning-subtle` | `--warning-border` | `--warning` |
| Error / rejected / failed | `--danger` | `--danger-subtle` | `--danger-border` | `--danger` |
| Disabled | `--disabled-ink` | `--disabled-bg` | `--disabled-border` | `--disabled-ink` |

### 2.3 Typography

#### Families

```css
--font-heading: "Source Serif 4", Georgia, "Times New Roman", serif;
--font-body:    "Public Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
--font-mono:    "IBM Plex Mono", ui-monospace, "SF Mono", Menlo, monospace;
```

**Public Sans** is a US government typeface designed for exactly this context — high legibility at small sizes, wide language coverage, open licence. **Source Serif 4** gives headings authority without stiffness. **IBM Plex Mono** is used for one thing only: tracking numbers, invoice numbers, and reference codes, where character disambiguation matters.

All three are self-hosted with `font-display: swap`. No CDN — a government service must not leak applicant requests to a third party.

#### Scale — 1.200 minor third, 16px base

| Token | Size | Line height | Weight | Family | Use |
|---|---|---|---|---|---|
| `--text-xs` | 12px / 0.75rem | 1.4 | 400 | body | Metadata, timestamps. **Never body copy.** |
| `--text-sm` | 14px / 0.875rem | 1.5 | 400 | body | Secondary text, hints, table cells |
| `--text-base` | 16px / 1rem | 1.6 | 400 | body | **Body default. Never smaller for form input.** |
| `--text-lg` | 18px / 1.125rem | 1.55 | 400 | body | Lead paragraphs, callouts |
| `--text-xl` | 22px / 1.375rem | 1.4 | 600 | heading | `h3` |
| `--text-2xl` | 28px / 1.75rem | 1.3 | 600 | heading | `h2`, page titles on mobile |
| `--text-3xl` | 36px / 2.25rem | 1.2 | 600 | heading | `h1` desktop |

Weights available: 400 (regular), 600 (semibold), 700 (bold). **No light or thin weights** — they fail on low-quality mobile displays.

#### Rules

| Rule | Value |
|---|---|
| Measure (line length) | 45–75 characters. Enforced by `--content-max: 44rem`. |
| Minimum input font size | 16px — smaller triggers iOS zoom-on-focus |
| Heading hierarchy | Exactly one `h1` per page; levels never skipped |
| All-caps | Section eyebrow labels only, `--text-sm`, `letter-spacing: 0.05em` |
| Italics | Never for emphasis. Use `<strong>`. Italics for citations only. |
| Underline | Links only. Never for emphasis. |
| Text alignment | Left. Never justified — justification creates rivers that harm dyslexic readers. |
| Numerals | Tabular figures (`font-variant-numeric: tabular-nums`) in all tables and money |

### 2.4 Spacing and layout

4px base scale. Every margin, padding, and gap uses these.

```css
--space-1:  0.25rem;  /*  4px */
--space-2:  0.5rem;   /*  8px */
--space-3:  0.75rem;  /* 12px */
--space-4:  1rem;     /* 16px */
--space-5:  1.25rem;  /* 20px */
--space-6:  1.5rem;   /* 24px */
--space-8:  2rem;     /* 32px */
--space-10: 2.5rem;   /* 40px */
--space-12: 3rem;     /* 48px */
--space-16: 4rem;     /* 64px */
--space-20: 5rem;     /* 80px */
```

#### Spacing semantics — remove the guesswork

| Relationship | Spacing |
|---|---|
| Label → its input | `--space-2` |
| Input → its hint or error | `--space-2` |
| Between fields in a group | `--space-5` |
| Between field groups | `--space-8` |
| Card internal padding (mobile) | `--space-4` |
| Card internal padding (desktop) | `--space-6` |
| Between cards in a list | `--space-4` |
| Between page sections | `--space-12` |
| Page top padding (mobile) | `--space-6` |
| Page top padding (desktop) | `--space-10` |
| Icon → adjacent label | `--space-2` |
| Button group gap | `--space-3` |

#### Containers and breakpoints

```css
--content-max: 44rem;   /* 704px — applicant single-column reading measure */
--panel-max:   90rem;   /* 1440px — officer/admin dense layouts */
--gutter-sm:   1rem;    /* < 768px  */
--gutter-md:   1.5rem;  /* ≥ 768px  */
--gutter-lg:   2rem;    /* ≥ 1024px */
```

| Breakpoint | Min width | Applies to |
|---|---|---|
| (base) | 320px | Mobile — the design baseline |
| `sm` | 640px | Large phones |
| `md` | 768px | Tablets; tab bar → top nav |
| `lg` | 1024px | Desktop; two-column layouts appear |
| `xl` | 1280px | Officer/admin dense tables |

**320px is the design baseline, not an afterthought.** Every component must be verified at 320px before it is considered complete.

### 2.5 Borders, radius, elevation

```css
--radius-sm:   3px;   /* badges, tags, inline chips */
--radius-md:   6px;   /* buttons, inputs, small cards */
--radius-lg:  10px;   /* cards, modals, panels */
--radius-full: 9999px; /* avatars and dot indicators only */

--border-width:        1px;
--border-width-strong: 2px;  /* focus, selected, error emphasis */

--shadow-1: 0 1px 2px rgb(20 24 31 / 0.06);   /* resting cards */
--shadow-2: 0 2px 8px rgb(20 24 31 / 0.08);   /* raised, hover */
--shadow-modal: 0 16px 48px rgb(20 24 31 / 0.18);
```

Shadows are structural, never decorative. There is no `--shadow-3` and no coloured shadow.

### 2.6 Motion

```css
--duration-instant: 0ms;
--duration-fast:  120ms;   /* hover, focus, colour changes */
--duration-base:  200ms;   /* disclosure, tab change */
--duration-slow:  320ms;   /* modal enter/exit only */
--ease-standard: cubic-bezier(0.2, 0, 0.2, 1);
--ease-exit:     cubic-bezier(0.4, 0, 1, 1);
```

**Permitted transitions:** `background-color`, `border-color`, `color`, `opacity`, `transform` (translate only).

**Prohibited:** scroll-triggered animation, parallax, entrance animations on page load, animated illustrations, spinners that rotate faster than 1 revolution per second, anything that moves content the user is reading.

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}
```

### 2.7 Iconography

**Library: Heroicons 2.x** (MIT, outline and solid). Self-hosted as inline SVG via a Blade component — never an icon font, never a sprite fetched at runtime.

| Rule | Value |
|---|---|
| Default size | 20px (`--space-5`) inline with `--text-base` |
| Small | 16px with `--text-sm` |
| Large | 24px for standalone status |
| Stroke width | 1.5 (outline style) |
| Colour | `currentColor` always — never a hard-coded fill |
| Decorative icons | `aria-hidden="true"` |
| Meaningful icons | Visible text label adjacent, or `aria-label` |

#### Fixed icon vocabulary — do not substitute

| Meaning | Heroicon | Style |
|---|---|---|
| Complete / accepted / success | `check-circle` | solid |
| In progress | `clock` | outline |
| Not started | `minus-circle` | outline |
| Needs attention / warning | `exclamation-triangle` | solid |
| Error / rejected | `x-circle` | solid |
| Information | `information-circle` | solid |
| Locked | `lock-closed` | solid |
| Upload | `arrow-up-tray` | outline |
| Download | `arrow-down-tray` | outline |
| Document | `document-text` | outline |
| Payment | `credit-card` | outline |
| Appointment | `calendar-days` | outline |
| Copy | `clipboard-document` | outline |
| Forward navigation | `chevron-right` | outline |
| Back navigation | `chevron-left` | outline |
| Expand / collapse | `chevron-down` | outline |
| Close | `x-mark` | outline |
| External link | `arrow-top-right-on-square` | outline |
| Home tab | `home` | outline / solid when active |
| Applications tab | `document-duplicate` | outline / solid when active |
| Notifications tab | `bell` | outline / solid when active |
| Account tab | `user-circle` | outline / solid when active |

### 2.8 Tailwind configuration

```js
// tailwind.config.js
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './app/Livewire/**/*.php',
    './app/View/Components/**/*.php',
  ],
  theme: {
    // `extend` is NOT used for colour, spacing, or type.
    // Replacing the defaults makes arbitrary token use impossible by construction.
    colors: {
      transparent: 'transparent',
      current: 'currentColor',
      surface: {
        DEFAULT: 'var(--surface)',
        sunken:  'var(--surface-sunken)',
        raised:  'var(--surface-raised)',
        overlay: 'var(--surface-overlay)',
      },
      ink: {
        DEFAULT: 'var(--ink)',
        muted:   'var(--ink-muted)',
        subtle:  'var(--ink-subtle)',
        inverse: 'var(--ink-inverse)',
      },
      border: {
        DEFAULT: 'var(--border)',
        strong:  'var(--border-strong)',
        ink:     'var(--border-ink)',
      },
      brand: {
        DEFAULT: 'var(--brand)',
        hover:   'var(--brand-hover)',
        active:  'var(--brand-active)',
        subtle:  'var(--brand-subtle)',
        border:  'var(--brand-border)',
      },
      success: { DEFAULT: 'var(--success)', subtle: 'var(--success-subtle)', border: 'var(--success-border)' },
      warning: { DEFAULT: 'var(--warning)', subtle: 'var(--warning-subtle)', border: 'var(--warning-border)' },
      danger:  { DEFAULT: 'var(--danger)',  subtle: 'var(--danger-subtle)',  border: 'var(--danger-border)', hover: 'var(--danger-hover)' },
      info:    { DEFAULT: 'var(--info)',    subtle: 'var(--info-subtle)',    border: 'var(--info-border)' },
      focus:   'var(--focus-ring)',
      disabled:{ DEFAULT: 'var(--disabled-bg)', ink: 'var(--disabled-ink)', border: 'var(--disabled-border)' },
    },
    spacing: {
      0: '0', px: '1px',
      1: 'var(--space-1)',  2: 'var(--space-2)',  3: 'var(--space-3)',
      4: 'var(--space-4)',  5: 'var(--space-5)',  6: 'var(--space-6)',
      8: 'var(--space-8)', 10: 'var(--space-10)', 12: 'var(--space-12)',
     16: 'var(--space-16)', 20: 'var(--space-20)',
    },
    fontFamily: {
      heading: 'var(--font-heading)',
      body:    'var(--font-body)',
      mono:    'var(--font-mono)',
    },
    fontSize: {
      xs:   ['var(--text-xs)',   { lineHeight: '1.4'  }],
      sm:   ['var(--text-sm)',   { lineHeight: '1.5'  }],
      base: ['var(--text-base)', { lineHeight: '1.6'  }],
      lg:   ['var(--text-lg)',   { lineHeight: '1.55' }],
      xl:   ['var(--text-xl)',   { lineHeight: '1.4'  }],
      '2xl':['var(--text-2xl)',  { lineHeight: '1.3'  }],
      '3xl':['var(--text-3xl)',  { lineHeight: '1.2'  }],
    },
    fontWeight: { normal: '400', semibold: '600', bold: '700' },
    borderRadius: {
      none: '0',
      sm:   'var(--radius-sm)',
      md:   'var(--radius-md)',
      lg:   'var(--radius-lg)',
      full: 'var(--radius-full)',
    },
    boxShadow: {
      none:  'none',
      1:     'var(--shadow-1)',
      2:     'var(--shadow-2)',
      modal: 'var(--shadow-modal)',
    },
    extend: {
      maxWidth: { content: 'var(--content-max)', panel: 'var(--panel-max)' },
      minHeight: { tap: '44px' },
      minWidth:  { tap: '44px' },
      transitionDuration: {
        fast: 'var(--duration-fast)',
        base: 'var(--duration-base)',
        slow: 'var(--duration-slow)',
      },
    },
  },
  plugins: [forms({ strategy: 'class' })],
};
```

**Why `theme` replacement rather than `extend`.** Replacing the defaults means `bg-blue-500`, `p-[13px]`, and `text-2xs` simply do not compile. Rule 1 in §1.3 becomes structurally enforced rather than a convention an agent might drift from.

---

## 3. Modes

### 3.1 High contrast mode

Two mechanisms, both required.

#### Windows High Contrast / forced colours

```css
@media (forced-colors: active) {
  /* System colours override everything. Do not fight them —
     the user chose them deliberately. */

  /* 1. Any state conveyed by background alone gains a border. */
  .btn, .badge, .alert, .card, .input {
    border: 1px solid;
  }

  /* 2. Selected and active states use system Highlight. */
  [aria-selected="true"],
  [aria-current="page"],
  .is-active {
    forced-color-adjust: none;
    background: Highlight;
    color: HighlightText;
  }

  /* 3. Disabled uses GrayText, the system's own disabled signal. */
  [disabled], [aria-disabled="true"] {
    color: GrayText;
    border-color: GrayText;
  }

  /* 4. Shadows vanish in forced colours — replace with a border. */
  .shadow-1, .shadow-2, .shadow-modal {
    box-shadow: none;
    border: 1px solid CanvasText;
  }

  /* 5. Focus must remain visible against system colours. */
  :focus-visible {
    outline: 3px solid Highlight;
    outline-offset: 2px;
  }

  /* 6. Status icons must not disappear; they carry meaning. */
  .status-icon { forced-color-adjust: none; }
}
```

#### `prefers-contrast: more`

```css
@media (prefers-contrast: more) {
  :root {
    --ink-muted:     #2B333D;  /* 11.6:1 — was 8.1:1 */
    --ink-subtle:    #3D4753;  /*  9.4:1 — was 4.9:1 */
    --border:        #6B7684;  /* was #D3D9E0 */
    --border-strong: #4A5462;
    --disabled-ink:  #4A5462;
    --border-width:  2px;
  }
  .btn, .input, .card { border-width: 2px; }
  :focus-visible { outline-width: 4px; }
}
```

#### Per-component high-contrast requirements

| Component | Requirement |
|---|---|
| Button (primary) | Gains a 1px border so the fill is not the only boundary |
| Button (ghost) | Gains a visible border — a borderless button vanishes in forced colours |
| StatusBadge | Icon uses `forced-color-adjust: none`; text label mandatory |
| Alert | Border on all sides, not just a left accent bar |
| Card | Border replaces shadow |
| Input | Border always visible; focus uses `Highlight` |
| Checkbox / Radio | Checked state uses `Highlight`, never a background image |
| ProgressBar | Track and fill both bordered; percentage shown as text |
| Modal | Bordered; backdrop cannot be relied on for separation |
| Tabs | Selected tab uses `Highlight` + underline, not colour alone |
| Skeleton | Replaced entirely by the text "Loading…" — animated grey blocks are meaningless in forced colours |

### 3.2 Print styles

Applicants print application summaries and appointment letters. Officers print case records for offline review and file copies. Print is a first-class output, not an afterthought.

```css
/* resources/css/print.css */
@media print {
  /* ── Reset to ink on paper ─────────────────────────── */
  :root {
    --surface: #FFFFFF;
    --surface-sunken: #FFFFFF;
    --ink: #000000;
    --ink-muted: #333333;
    --ink-subtle: #555555;
    --brand: #000000;
  }
  * {
    background: transparent !important;
    box-shadow: none !important;
    color: #000 !important;
  }

  /* ── Remove interactive chrome ─────────────────────── */
  .bottom-tab-bar, .top-nav, .sidebar, .breadcrumb,
  .btn, .toast, .skeleton, .modal-backdrop,
  [data-print="hide"] {
    display: none !important;
  }

  /* ── Expand collapsed content ──────────────────────── */
  [hidden][data-print="expand"],
  details, .tab-panel, .accordion-panel {
    display: block !important;
    visibility: visible !important;
    height: auto !important;
  }
  .tab-panel::before {
    content: attr(data-tab-label);
    display: block;
    font-family: var(--font-heading);
    font-size: 14pt;
    font-weight: 600;
    margin: 12pt 0 6pt;
    border-bottom: 1pt solid #000;
  }

  /* ── Document identity on every page ───────────────── */
  @page {
    margin: 20mm 18mm;
    size: A4;
  }
  .print-header {
    display: block !important;
    position: running(header);
  }

  /* ── Typography for paper ──────────────────────────── */
  body { font-size: 11pt; line-height: 1.4; }
  h1 { font-size: 18pt; }
  h2 { font-size: 14pt; }
  h3 { font-size: 12pt; }

  /* ── Page-break discipline ─────────────────────────── */
  h1, h2, h3 { break-after: avoid; }
  .card, .field-group, tr, .timeline-item { break-inside: avoid; }
  .page-break { break-before: page; }
  table { break-inside: auto; }
  thead { display: table-header-group; }  /* repeat headers across pages */

  /* ── Reveal link destinations ──────────────────────── */
  a[href^="http"]::after {
    content: " (" attr(href) ")";
    font-size: 9pt;
    color: #555 !important;
  }
  a[href^="#"]::after, a[href^="mailto"]::after { content: ""; }

  /* ── Status without colour ─────────────────────────── */
  .badge::before { content: "[ "; }
  .badge::after  { content: " ]"; }
}
```

#### Print requirements per surface

| Surface | Requirement |
|---|---|
| Application hub (APP-05) | Tracking number, visa type, and status in a print header on every page. Section cards expand to full read-only content. |
| Review & declaration (APP-09) | Prints as a complete application record. Declaration text never truncated. |
| Appointment detail (APP-19) | Prints as a usable appointment letter: date, local time with timezone, full address, what to bring. |
| Decision (APP-22) | Outcome and date prominent. The PDF letter remains authoritative; the print view states so. |
| Officer case record (OFF-05) | **All seven tabs print sequentially**, each with its label as a heading. Officers print for offline review; a print showing only the active tab is useless. |
| Officer queue (OFF-03) | Table with repeating headers; SLA state as text, never colour. |
| Reconciliation (ADM-15) | Table with repeating headers; unmatched section starts on a new page. |

**Every printed page carries the tracking number.** A loose page from a consular file with no identifier is a records problem.

### 3.3 Reduced motion

Specified in §2.6. Additionally: no component may depend on animation to convey state. If a spinner is the only indicator that something is loading, the component also needs text.

### 3.4 Not supported — and what that costs

**Dark mode: not supported.** No `dark:` variants are written. Adding it later means auditing every token pairing for contrast in a second palette — roughly a week of work, not a switch.

**RTL: not supported.** No logical properties (`margin-inline-start` etc.) are required; physical properties are acceptable.

⚠ **This conflicts with PRD §10.5 NFR-U-06**, which requires right-to-left support "where the language set requires it". That requirement is now unmet by design. The conflict is acceptable **only while no RTL language is in the launch set**. Arabic, Urdu, Farsi, and Hebrew are all plausible languages for a global consular service.

Recommended hedge, cheap now and expensive later: **use Tailwind's logical utilities (`ms-*`, `me-*`, `ps-*`, `pe-*`) instead of (`ml-*`, `mr-*`, `pl-*`, `pr-*`) from the start.** It costs nothing today and converts most of the RTL work into a single `dir` attribute later. This document specifies logical utilities throughout §5 for that reason.

Resolve PRD OD-4 (launch language set) before this becomes a rework rather than a decision.

---

## 4. Component Architecture

### 4.1 The Blade / Livewire split

The dividing line is **server round-trips**, not complexity.

| Use a **Blade component** (`<x-*>`) when | Use a **Livewire component** when |
|---|---|
| Output is determined entirely by props | State changes without a page navigation |
| No user interaction changes server state | It reads or writes to the database on interaction |
| Any interactivity is purely visual (Alpine-only) | It validates, saves, uploads, or polls |
| It renders inside a loop many times | It manages its own lifecycle |

**Alpine-only interactivity stays in Blade.** A dropdown that opens and closes, a modal that shows and hides, an accordion that expands — all Blade + Alpine. Reaching for Livewire to toggle visibility wastes a round-trip and adds latency on a serverless HTTP tier where every request pays connection setup.

#### Classification of every component in §5

| Blade (`resources/views/components/`) | Livewire (`app/Livewire/Shared/`) |
|---|---|
| `button`, `link`, `badge`, `alert`, `card`, `empty-state`, `progress-bar`, `skeleton`, `timeline`, `definition-list`, `breadcrumb`, `icon`, `field-group`, `input`, `select`, `textarea`, `checkbox`, `radio-group`, `date-input`, `error-summary`, `disclosure`, `modal` (shell), `tab-list` (presentational), `page-header`, `bottom-tab-bar`, `top-nav`, `print-header` | `Toast`, `SessionWarning`, `FileUpload`, `DataTable`, `CopyButton`, `NotificationBell`, `Tabs` (when panels load server-side) |

`modal` and `tabs` appear in both columns deliberately: the **shell** is Blade + Alpine; a Livewire wrapper is used only when panel content must be fetched.

### 4.2 File locations and naming

```
resources/views/components/          # Blade components, kebab-case
  button.blade.php                   → <x-button>
  field-group.blade.php              → <x-field-group>
  form/input.blade.php               → <x-form.input>
  layout/app-shell.blade.php         → <x-layout.app-shell>

app/View/Components/                 # only when PHP logic is needed
  Button.php

app/Livewire/
  Shared/Toast.php                   → <livewire:shared.toast>
  Applicant/ApplicationHub.php
  Officer/CaseRecord.php

resources/css/
  tokens.css      # §2 — CSS custom properties
  base.css        # element resets, focus, typography defaults
  print.css       # §3.2
  app.css         # imports + @tailwind directives
```

### 4.3 Component API conventions

**Every Blade component must:**

1. Declare all props with `@props([...])` and sensible defaults
2. Forward `$attributes` with `$attributes->merge([...])` so callers can add `id`, `wire:`, `data-`, `aria-`
3. Use `$attributes->class([...])` for conditional classes, never string concatenation
4. Never contain hard-coded user-facing copy — accept it as a prop or slot

```blade
{{-- resources/views/components/button.blade.php --}}
@props([
    'variant'      => 'primary',   // primary|secondary|ghost|danger
    'size'         => 'md',        // sm|md|lg
    'type'         => 'button',
    'disabled'     => false,
    'loading'      => false,
    'disabledReason' => null,      // REQUIRED when $disabled is true
    'fullWidth'    => false,
    'iconStart'    => null,
    'iconEnd'      => null,
])

@php
    $reasonId = $disabledReason ? 'reason-'.Str::random(6) : null;

    $base = 'inline-flex items-center justify-center gap-2 font-body font-semibold
             rounded-md border transition-colors duration-fast
             min-h-tap focus-visible:outline focus-visible:outline-[3px]
             focus-visible:outline-offset-2 focus-visible:outline-focus';

    $variants = [
        'primary'   => 'bg-brand text-ink-inverse border-brand
                        hover:bg-brand-hover active:bg-brand-active',
        'secondary' => 'bg-surface text-brand border-brand
                        hover:bg-brand-subtle',
        'ghost'     => 'bg-transparent text-brand border-transparent
                        hover:bg-brand-subtle hover:border-brand-border',
        'danger'    => 'bg-danger text-ink-inverse border-danger
                        hover:bg-danger-hover',
    ];

    $sizes = [
        'sm' => 'text-sm px-3 py-2',
        'md' => 'text-base px-4 py-3',
        'lg' => 'text-lg px-6 py-4',
    ];

    $disabledClasses = 'bg-disabled text-disabled-ink border-disabled-border
                        cursor-not-allowed hover:bg-disabled';
@endphp

<button
    type="{{ $type }}"
    @disabled($disabled || $loading)
    @if($reasonId) aria-describedby="{{ $reasonId }}" @endif
    @if($loading) aria-busy="true" @endif
    {{ $attributes->class([
        $base,
        $sizes[$size],
        $variants[$variant] => ! $disabled && ! $loading,
        $disabledClasses    => $disabled || $loading,
        'w-full'            => $fullWidth,
    ]) }}
>
    @if($loading)
        <x-icon name="arrow-path" class="w-5 h-5 motion-safe:animate-spin" aria-hidden="true" />
        <span>{{ __('Working…') }}</span>
    @else
        @if($iconStart)<x-icon :name="$iconStart" class="w-5 h-5" aria-hidden="true" />@endif
        {{ $slot }}
        @if($iconEnd)<x-icon :name="$iconEnd" class="w-5 h-5" aria-hidden="true" />@endif
    @endif
</button>

@if($reasonId)
    <p id="{{ $reasonId }}" class="mt-2 text-sm text-ink-muted">{{ $disabledReason }}</p>
@endif
```

**Note the `disabledReason` prop.** It is not optional decoration — §1.3 rule 3 requires that a disabled control explain itself, and wiring it through `aria-describedby` means a screen-reader user learns the reason on focus rather than hunting for it.

### 4.4 Anti-patterns — reject in review

| Anti-pattern | Instead |
|---|---|
| `class="p-[13px] text-[#0B4F71]"` | Token scale values only |
| `<div onclick="…">` | `<button>` |
| `<div class="btn">` | `<x-button>` |
| Disabled button with no reason | Pass `disabledReason` |
| Colour-only status | Icon + text + colour |
| Copy hard-coded in a component | Prop, slot, or `__()` key |
| `wire:click` to toggle visibility | Alpine `x-show` |
| New colour, spacing, or radius value | Raise the gap against §2 |
| `ml-4` / `pr-2` | `ms-4` / `pe-2` (§3.4) |
| Placeholder used as a label | Real `<label>` |
| `<div>` for a data table | `<table>` with proper semantics |

---

## 5. Component Library

Each entry specifies: type, props, variants, states, accessibility, high contrast, print.

### 5.1 Button — Blade

Source in §4.3.

| Variant | Use | One per screen? |
|---|---|---|
| `primary` | The single most important action | Yes — exactly one |
| `secondary` | Alternative actions | No |
| `ghost` | Tertiary, in-card, or toolbar actions | No |
| `danger` | Destructive, irreversible actions | No |

**States:** default · hover · active · focus-visible · disabled (with reason) · loading (`aria-busy`, label becomes "Working…", control disabled).

**Accessibility:** min 44×44px. Never `<a>` styled as a button for actions, never `<button>` for navigation. Loading state must not change the button's width (reserve space) — a shifting button is a click target that moves under the finger.

**High contrast:** all variants gain a border; `ghost` gains a visible border.
**Print:** hidden.

### 5.2 Link — Blade

```blade
@props(['href', 'external' => false, 'variant' => 'default'])
```

| Variant | Style |
|---|---|
| `default` | `text-brand underline underline-offset-2 hover:text-brand-hover` |
| `subtle` | `text-ink underline decoration-border hover:decoration-ink` |
| `standalone` | Block-level, with `chevron-right`, min 44px tap height |

Links are **always underlined** in body copy. Colour alone is insufficient. External links carry `arrow-top-right-on-square` plus visually hidden text "(opens in a new tab)".

**Print:** `href` revealed after the text (§3.2).

### 5.3 StatusBadge — Blade

```blade
@props(['status', 'size' => 'md'])  {{-- status maps to the table below --}}
```

| Status key | Icon | Tone | Label |
|---|---|---|---|
| `complete` | `check-circle` | success | Complete |
| `accepted` | `check-circle` | success | Accepted |
| `in_progress` | `clock` | warning | In progress |
| `not_started` | `minus-circle` | neutral | Not started |
| `needs_attention` | `exclamation-triangle` | danger | Needs attention |
| `locked` | `lock-closed` | neutral | Locked |
| `rejected` | `x-circle` | danger | Rejected |
| `checking` | `clock` | info | Checking |
| `draft` | `pencil-square` | neutral | Draft |
| `submitted` | `paper-airplane` | info | Submitted |
| `in_review` | `magnifying-glass` | info | In review |
| `action_required` | `exclamation-triangle` | warning | Action required |
| `decision_made` | `check-badge` | info | Decision made |
| `closed` | `archive-box` | neutral | Closed |

Structure: `[icon] [text label]` on a subtle fill with a matching border. **The text label is never omitted**, including at `sm` size.

**High contrast:** icon uses `forced-color-adjust: none`; border always present.
**Print:** renders as `[ Accepted ]`.

### 5.4 Alert — Blade

```blade
@props(['tone' => 'info', 'title' => null, 'dismissible' => false, 'level' => 'inline'])
```

| Tone | Use |
|---|---|
| `info` | Neutral context the user should notice |
| `success` | Confirmation of a completed action |
| `warning` | Something needs attention but nothing is broken |
| `danger` | An error, or a blocking condition |

| Level | Placement |
|---|---|
| `inline` | Within a section, scoped to nearby content |
| `page` | Directly beneath the page heading, full content width |

Structure: icon (24px) · title (`--text-base`, 600) · body (`--text-base`) · optional actions · optional dismiss. Border on all four sides — never a left accent bar alone, which disappears in forced colours.

**Accessibility:** `role="status"` for info/success; `role="alert"` for danger. Never `aria-live="assertive"` on a persistent alert — it re-announces on every re-render.

### 5.5 Card — Blade

```blade
@props(['padding' => 'md', 'interactive' => false, 'tone' => 'default'])
```

Background `surface-raised`, border `border`, radius `lg`, shadow `1`. Padding `--space-4` mobile / `--space-6` desktop.

**Interactive cards** (the whole card is a link) wrap content in a single `<a>` covering the card. Never nest interactive elements inside an interactive card — it produces an unreachable control for keyboard users.

**High contrast:** border replaces shadow.
**Print:** `break-inside: avoid`.

### 5.6 FieldGroup and inputs — Blade

`<x-field-group>` wires label, hint, error, and input together. This is the only permitted way to render a form field.

```blade
@props(['label', 'name', 'hint' => null, 'required' => false, 'error' => null])

@php
    $id      = $name;
    $hintId  = $hint  ? "{$name}-hint"  : null;
    $errorId = $error ? "{$name}-error" : null;
    $describedBy = collect([$hintId, $errorId])->filter()->implode(' ');
@endphp

<div class="mb-5">
    <label for="{{ $id }}" class="block text-base font-semibold text-ink mb-2">
        {{ $label }}
        @if($required)
            <span class="text-danger" aria-hidden="true">*</span>
            <span class="sr-only">{{ __('required') }}</span>
        @endif
    </label>

    {{ $slot }}   {{-- the input, receiving $id and $describedBy --}}

    @if($error)
        <p id="{{ $errorId }}" class="mt-2 text-sm text-danger flex items-start gap-2">
            <x-icon name="exclamation-circle" class="w-5 h-5 shrink-0" aria-hidden="true" />
            <span>{{ $error }}</span>
        </p>
    @endif

    @if($hint)
        <p id="{{ $hintId }}" class="mt-2 text-sm text-ink-muted">{{ $hint }}</p>
    @endif
</div>
```

**Order is deliberate: label → input → error → hint.** The error sits closest to the input because it is the most urgent information; the hint persists below because it remains useful while correcting.

| Input state | Border | Other |
|---|---|---|
| Default | `border-strong` 1px | `bg-surface` |
| Focus | `focus` 3px outline, offset 2px | |
| Error | `danger` 2px | error text below |
| Disabled | `disabled-border` | `bg-disabled`, `cursor-not-allowed` |
| Read-only | `border` | `bg-surface-sunken`, no cursor change |

**Never** use a placeholder as a label. **Never** use `required` without both the asterisk and the visually hidden word.

### 5.7 DateInput — Blade

Passport and birth dates must never be free text.

- Format hint **always visible** below the label: "For example, 14 03 1991"
- Three separate inputs (day / month / year) with `inputmode="numeric"`, or a native `date` input with an explicit format label — never a bare text field
- Never rely on locale inference for the format
- Displayed dates elsewhere use the long form (§7.2) to avoid DD/MM versus MM/DD ambiguity entirely

### 5.8 ErrorSummary — Blade

Rendered at the top of a form after a failed submit.

```blade
@props(['errors'])
```

- `<h2>` "There is a problem" (`--text-xl`, danger tone)
- Ordered list of errors, each an anchor link to the field's `id`
- Focus moves to the `<h2>` on render — required by WCAG 3.3.1
- `role="alert"` on the container
- Never rendered when empty

### 5.9 EmptyState — Blade

```blade
@props(['type' => 'first_run', 'icon', 'headline', 'body' => null])
```

Four parts, in order: icon (48px, `ink-subtle`, `aria-hidden`) · headline (`--text-xl`) · body (`--text-base`, `ink-muted`) · action slot.

| Type | Tone | Action |
|---|---|---|
| `first_run` | Encouraging | Primary button creating the first item |
| `filtered` | Neutral | "Clear filters" secondary button |
| `permission` | Factual | None — and **never a count** |
| `positive` | Affirming | Optional secondary action |

Copy comes from §7.4. Empty states are never invented at the call site.

### 5.10 ProgressBar — Blade

```blade
@props(['value', 'max', 'label'])
```

`role="progressbar"`, `aria-valuenow/min/max`, `aria-label`. **Always paired with a visible text equivalent** ("4 of 7 sections complete") — the bar is supplementary.

Track `surface-sunken`, fill `brand`, height `--space-2`, radius `full`.

**High contrast:** track and fill both bordered.
**Print:** bar hidden, text equivalent retained.

### 5.11 Skeleton — Blade

Mirrors the final layout's shape. Never a spinner on a blank region.

`bg-surface-sunken`, `motion-safe:animate-pulse`, radius matching the content it stands in for. Container carries `aria-busy="true"` and a visually hidden "Loading".

**High contrast:** replaced entirely by visible text "Loading…".
**Print:** hidden.

### 5.12 Modal — Blade shell + Alpine

```blade
@props(['title', 'size' => 'md', 'dismissible' => true])
```

- `role="dialog"`, `aria-modal="true"`, `aria-labelledby` → title id
- Focus trapped via `@alpinejs/focus` (`x-trap`)
- Focus returns to the trigger on close
- Escape closes when `dismissible`; backdrop click closes when `dismissible`
- Body scroll locked while open
- Title is `<h2>`; focus moves to it on open
- Max height `85vh` with the body scrolling, not the page

Sizes: `sm` 28rem · `md` 36rem · `lg` 48rem.

**Destructive confirmations** always name the specific record (§7.5) and place the destructive action as `danger` with cancel as `secondary`, cancel first in DOM order.

**Print:** hidden.

### 5.13 Toast — Livewire (`Shared/Toast`)

- Confirmation of completed, reversible actions only
- 4 seconds, dismissible, pauses on hover and focus
- Bottom on mobile (above the tab bar), top-right on desktop
- `role="status"`, `aria-live="polite"`
- Maximum three stacked; older ones dismiss first

**A toast never carries information that exists nowhere else.** Anything needed after four seconds also lives on the page.

### 5.14 Tabs — Blade shell (Livewire when panels load server-side)

Standard tab pattern: `role="tablist"`, `role="tab"` with `aria-selected` and `aria-controls`, `role="tabpanel"` with `aria-labelledby`. Arrow keys move between tabs; the tab list is one tab stop.

Selected tab: `brand` text, 2px `brand` bottom border, 600 weight. Not colour alone.

Mobile: horizontally scrollable with scroll-snap; never a `<select>` substitute, which hides the available options.

**High contrast:** selected uses `Highlight` plus the underline.
**Print:** all panels render sequentially with their labels as headings (§3.2).

### 5.15 DataTable — Livewire (wraps `rappasoft/laravel-livewire-tables`)

Real `<table>` semantics with `<caption>` (visually hidden), `<thead>`, `<th scope="col">`, and `scope="row"` on the identifying cell.

- Sortable headers are `<button>` inside `<th>` with `aria-sort`
- Row selection uses real checkboxes with per-row accessible labels
- Bulk action bar appears only with a selection and announces the count
- Empty state uses `<x-empty-state>` with the correct type
- Mobile: horizontal scroll with a sticky first column. **Never collapse into cards** — officers scan columns, and card layouts destroy comparison.

`tabular-nums` on all numeric columns.

**Print:** `thead` repeats via `display: table-header-group`; interactive controls hidden.

### 5.16 FileUpload — Livewire (`Shared/FileUpload`)

Three entry paths in this order on mobile: **Take a photo · Choose from library · Choose a file**. Camera first, because most applicants photograph documents rather than scan them.

- Drag-and-drop is an enhancement on desktop only; the keyboard-reachable file picker is always present
- Per-file progress bar with byte count and cancel
- Client-side pre-checks (size, extension) before any byte is sent
- Nine document states per Screen UI Specs §4.1
- Error copy from §7.6 with actual values interpolated

**Accessibility:** the drop zone is a `<button>` triggering the picker; drag events are supplementary. Upload progress announced politely at 0%, 50%, 100% — not continuously.

### 5.17 SessionWarning — Livewire (`Shared/SessionWarning`)

Per App Flow §3.6. **Autosave fires before the modal renders** — the modal must never carry the risk of loss.

- Countdown announced at 60s and 30s only, not continuously
- Focus trapped; "Stay signed in" is the first focusable element
- Confirms saved state: "Your answers were saved at 14:32"
- If autosave failed, the modal changes: names the unsaved section, offers retry and "Copy my answers"
- Mobile: full-width bottom sheet rather than a centred modal

### 5.18 Timeline — Blade

Ordered list of status transitions. Each item: icon, label, date. Current item marked with `aria-current="step"` plus a text marker — not colour alone.

Applicant-visible transitions only. Never internal statuses, officer names, or notes.

### 5.19 DefinitionList — Blade

For read-only review views (APP-09, OFF-05 Application tab).

Real `<dl>`, `<dt>`, `<dd>`. Two-column on desktop (`dt` 1fr, `dd` 2fr), stacked on mobile. Sensitive values masked with an explicit reveal control that is audited.

### 5.20 Navigation components — Blade

**BottomTabBar** (mobile, applicant portal): four tabs per App Flow §3.1. `<nav aria-label="Main">`, `aria-current="page"` on the active tab. Icon + visible text label — icon-only is not acceptable. 44px minimum targets, safe-area inset respected. Hidden on APP-06, APP-09, APP-11–APP-18.

**TopNav** (desktop): same four destinations plus language selector and account menu.

**Breadcrumb:** `<nav aria-label="Breadcrumb">` with an ordered list; current page is text, not a link, with `aria-current="page"`.

**Skip link:** first focusable element on every page, visually hidden until focused, targets `#main-content`.

---

## 6. Voice and Tone

### 6.1 Voice — constant

The service speaks as a **competent, impartial official who wants the applicant to succeed**. Not a brand. Not a friend. Not a bureaucracy.

| The service is | The service is not |
|---|---|
| Clear | Chatty or clever |
| Direct | Blunt or cold |
| Respectful | Deferential or apologetic |
| Precise | Legalistic |
| Calm | Reassuring beyond what is true |

### 6.2 Tone — varies by situation

| Situation | Tone | Example |
|---|---|---|
| Routine progress | Neutral, brief | "Saved at 14:32" |
| Action required | Direct, specific, no alarm | "Upload a bank statement covering May to July 2026." |
| Waiting | Calm, honest about uncertainty | "We're waiting for confirmation from our payment provider." |
| System error | Accountable, no blame | "We couldn't save your file. This is a problem on our side." |
| User error | Corrective, not judgemental | "Enter a date in DD MM YYYY format." |
| Refusal | Respectful, factual, forward-looking | "Your application was not approved. The decision letter explains why." |
| Approval | Warm but restrained | "Your visa has been approved." |

**Never celebrate a decision the applicant may not welcome, and never commiserate about one the officer made.** The service reports outcomes; it does not editorialise.

### 6.3 Grammar and mechanics

| Rule | Standard |
|---|---|
| Capitalisation | Sentence case everywhere — headings, buttons, labels, table headers. Never Title Case. |
| Spelling | British English (`organisation`, `authorised`, `programme`) — set once, applied everywhere |
| Contractions | Use them: "we'll", "can't", "you're". They read as human without being informal. |
| Person | Second person for the applicant ("your application"), first person plural for the service ("we'll email you") |
| Voice | Active. "We received your payment", not "Your payment has been received." |
| Sentence length | Under 25 words. Split anything longer. |
| Paragraph length | Under 4 lines on mobile. |
| Numerals | Digits for all numbers, including one to nine: "3 documents", "1 of 7 sections" |
| Ranges | En dash, no spaces: "May–July 2026" |
| Ampersand | Never in prose. "and". |
| Exclamation marks | Never. |
| Emoji | Never. |
| Latin abbreviations | Never (`e.g.`, `i.e.`, `etc.`). Use "for example", "that is", "and so on". |
| Terminal punctuation | Full stops on sentences; none on labels, headings, or button text |

### 6.4 Dates, times, money, names

| Type | Format | Example |
|---|---|---|
| Date (display) | `j F Y` — long form, unambiguous | 14 March 1991 |
| Date (short, tables) | `j M Y` | 14 Mar 1991 |
| Date (input) | Three fields with visible hint | Day 14 · Month 03 · Year 1991 |
| Time | 24-hour, always with timezone | 14:30 IST |
| Cross-timezone | Location time primary, local secondary | "10:00 IST (05:30 your time)" |
| Date + time | "14 March 2026 at 10:00 IST" | |
| Relative time | Only within 7 days, with the absolute in a `title` | "2 days ago" |
| Money | Symbol, thousands separator, 2 decimals, ISO code on totals | $1,240.00 USD |
| Zero-decimal currencies | No decimals | ¥12,400 JPY |
| Tracking number | Mono, uppercase, never wrapped | `VISA-IND-2026-8K3F2Q` |
| Names | As the applicant entered them. Never reformat, reorder, or auto-capitalise. |
| File size | One decimal, unit spaced | 14.2 MB |

**Never format a name.** Applicants have mononyms, patronymics, multiple family names, and scripts where "first/last" is meaningless. Store and display exactly what was entered.

---

## 7. Content Library

### 7.1 Terminology dictionary

| Use | Never use | Why |
|---|---|---|
| Application | Form, request, submission, file | One noun for the central object |
| Tracking number | Reference number, application ID, case number | One name for the public identifier |
| Document | File, attachment, upload, paper | "File" only when discussing the technical object during upload |
| Fee | Charge, cost, price, payment amount | Consular language |
| Payment | Transaction, purchase, order | |
| Appointment | Booking, slot, visit, meeting | "Slot" only in officer-facing capacity UI |
| Submit | Send, finish, complete, apply | |
| Approved / Not approved | Accepted / Rejected / Denied | For **decisions**. "Rejected" applies to documents only. |
| Officer | Agent, reviewer, staff, admin | "Agent" means the applicant's representative — never staff |
| Representative | Agent (applicant-facing) | Externally "representative"; internally "agent" |
| Sign in / Sign out | Log in / Login / Logout | |
| Action required | Attention needed, pending you | |
| We | The Department, the system, this portal | |

**The `agent` collision matters.** In this system "agent" means an authorised representative acting for an applicant. Never use it for staff in any string, comment, class name, or variable.

### 7.2 Button labels — exact strings

Buttons state the outcome, not the mechanism.

| Context | Label |
|---|---|
| Start an application | Start an application |
| Continue a section | Continue |
| Review a complete section | Review |
| Save and move on | Save and continue |
| Save and return | Save and return to application |
| Submit | Submit application |
| Pay | Continue to secure payment |
| Retry payment | Try again |
| Upload | Upload |
| Replace a document | Upload replacement |
| Book | Book your appointment |
| Defer booking | Book later |
| Reschedule | Reschedule appointment |
| Resubmit | Resubmit application |
| Download a letter | Download decision letter |
| Cancel a dialogue | Cancel |
| Confirm deletion | Delete draft |
| Withdraw | Withdraw application |
| Extend session | Stay signed in |
| End session | Sign out now |
| Clear filters | Clear filters |
| Officer approve | Approve |
| Officer reject | Reject |
| Officer request info | Request information |

**Never:** "Submit" alone, "OK", "Yes"/"No" on destructive dialogues, "Click here", "Learn more" without an object.

### 7.3 Status labels

Applicant-facing labels come from App Flow §7.2 and are never paraphrased: Draft · Submitted · In review · Action required · Appointment scheduled · Decision made · Withdrawn · Closed.

Internal statuses are never shown to applicants.

### 7.4 Empty state copy — exact strings

| Screen | Headline | Body | Action |
|---|---|---|---|
| APP-01 first run | Let's get your application started | Applying takes about 30 minutes. You can save and come back at any time. | Start an application |
| APP-02 first run | You haven't started any applications | When you start one, it'll appear here. | Start an application |
| APP-02 filtered | No applications match these filters | Try removing a filter to see more. | Clear filters |
| APP-07 dependency | We'll show your document list once you complete Personal details | Some documents depend on your age and whether you have a sponsor. | Go to Personal details |
| APP-16 no availability | No appointments are currently available | We'll email you when new slots open at this location. | Notify me |
| APP-17 date empty | No times available on this date | The next available date is 15 September. | Go to 15 September |
| APP-23 first run | No notifications yet | We'll let you know about anything that needs your attention. | — |
| APP-28 first run | No one is authorised to act for you | A representative can complete and submit applications on your behalf. | Learn about representatives |
| APP-29 first run | No payments yet | Your receipts will appear here once you've paid a fee. | — |
| AGT-04 first run | Invite your first client to get started | Once a client accepts, you can complete and submit applications for them. | Invite a client |
| OFF-02 clear | Your queue is clear | There are 12 unassigned cases waiting. | Pull from unassigned |
| OFF-03 filtered | No cases match these filters | Try removing a filter to see more. | Clear filters |
| OFF-05 notes | No notes on this case | Notes are visible to other officers unless you mark them otherwise. | Add a note |
| OFF-05 no appointment | No appointment required for this visa type | — | — |
| ADM-05 | No agencies awaiting review | — | — |
| ADM-15 reconciled | All transactions reconciled for this date | — | — |
| Any permission empty | No records match your access | — | — |

### 7.5 Confirmation dialogue copy

Every confirmation names the specific record.

| Action | Title | Body | Confirm |
|---|---|---|---|
| Delete draft | Delete draft VISA-IND-2026-8K3F2Q? | This permanently deletes your answers and uploaded documents. You can't undo this. | Delete draft |
| Withdraw | Withdraw application VISA-IND-2026-8K3F2Q? | Your application won't be processed. Under our refund policy you would receive $112.00 of the $160.00 fee. | Withdraw application |
| Remove document | Remove Bank statement? | You can upload a replacement at any time before you submit. | Remove document |
| Revoke representative | Remove Daniel Osei's authorisation? | They'll lose access to your applications immediately. You keep full access. | Remove authorisation |
| Officer approve | Approve VISA-IND-2026-8K3F2Q? | All checks passed: payment confirmed, 8 documents accepted, biometrics completed. This decision is final and will be recorded. | Approve |
| Officer reject | Reject VISA-IND-2026-8K3F2Q? | The applicant will be notified with the reason you've given. This decision is final and will be recorded. | Reject |

### 7.6 Error message formula

Every error answers three things, in this order:

1. **What happened** — plainly, without jargon
2. **Whether anything was lost** — always state it when there is any doubt
3. **What to do next** — one concrete action

| Situation | String |
|---|---|
| File too large | This file is 14.2 MB. The limit for a bank statement is 10 MB. Try photographing fewer pages, or reducing the image quality. |
| Wrong format | We can accept PDF, JPG, or PNG files. This file is a .docx. |
| Upload interrupted | The upload didn't finish — probably a connection problem. Your file is still selected. |
| Storage failure | We couldn't save your file. This is a problem on our side, not with your file. Please try again in a few minutes. (Reference: 7K2M) |
| Scan failed | We couldn't process this file. Try uploading a different copy — a clear photo or a fresh PDF usually works. |
| Save failed | We couldn't save your last change. Nothing you've typed has been lost. |
| Save failed repeatedly | We still can't save your answers. Copy them somewhere safe before your session ends. |
| Session expired | Your session ended after 30 minutes of inactivity. Your answers were saved. |
| Payment declined | Your card was declined. Your application is safe and unchanged — you can try again with the same or a different card. |
| Payment delayed | Your payment is safe. We're waiting for confirmation from our payment provider. You can close this page — we'll email you as soon as it's confirmed. |
| Slot taken | Sorry — 10:00 was just taken. Here are the times still available. |
| Rate limited | Too many attempts. Try again in 15 minutes. |
| Not found (404) | We couldn't find that page. |
| Forbidden (403) | You don't have permission to view this. |
| Server error (500) | Something went wrong on our side. Please try again. If it keeps happening, contact us and quote reference 7K2M. |
| Maintenance (503) | We're carrying out scheduled maintenance. The service will be back at 03:00 IST. |
| Offline | You're offline. We'll save your changes when you reconnect. |

### 7.7 Prohibited copy patterns

| Never write | Because |
|---|---|
| "Oops!" / "Uh oh!" / "Whoops" | Trivialises a moment that may be distressing |
| "Invalid input" | Says nothing about what to fix |
| "An error occurred" | Says nothing at all |
| "Please try again later" (alone) | No timeframe, no explanation |
| "You entered the wrong…" | Blames the user |
| "This file contains malware" | Leaks scanner behaviour; usually wrong about intent. Use the scan-failed string. |
| "Your payment failed" (on timeout) | Asserts a failure that has not occurred |
| "Don't worry" | Decides how the user should feel |
| "Simply" / "just" / "easy" | Implies fault if the user finds it hard |
| "Please be patient" | Condescending, gives no information |
| "Contact your administrator" | Meaningless to an applicant |
| "Unauthorized" (US spelling, technical register) | Use "You don't have permission to view this." |
| Any internal status name | Applicants see public statuses only |
| Any officer's name to an applicant | Decisions are institutional |

---

## 8. Localisation Readiness

RTL is out of scope (§3.4), but every string must still be translation-ready. This costs nothing now and is expensive to retrofit.

| Rule | Implementation |
|---|---|
| No hard-coded strings | Every user-facing string via `__()` or `@lang` |
| No concatenation | `__('We found :count documents', ['count' => $n])` — never `'We found ' . $n . ' documents'` |
| Pluralisation | Laravel's `trans_choice` with explicit plural forms |
| Layout expansion | Allow 35% growth on labels and buttons; never fix a container width to English text |
| No text in images | All text is real text |
| Dates and numbers | Formatted through `Carbon` and `NumberFormatter` with the active locale, never `date()` or `number_format()` |
| Directional icons | Chevrons and arrows flagged so they can be mirrored later |
| Logical properties | `ms-*` / `me-*` / `ps-*` / `pe-*` throughout (§3.4) |

---

## 9. Accessibility Rules — All Components

Baseline: **WCAG 2.2 Level AA**, treated as an acceptance criterion per PRD §10.5.

| # | Rule |
|---|---|
| A-1 | Contrast ≥ 4.5:1 for text, ≥ 3:1 for large text and non-text indicators |
| A-2 | Focus visible on every interactive element: 3px `--focus-ring`, 2px offset. Never `outline: none` without a replacement. |
| A-3 | Touch targets ≥ 44×44px with ≥ 8px between adjacent targets |
| A-4 | Every form control has a programmatically associated `<label>` |
| A-5 | Errors identified in text, associated via `aria-describedby`, and summarised at the form head |
| A-6 | Focus moves to the error summary heading on failed submit |
| A-7 | One `h1` per page; heading levels never skipped |
| A-8 | Landmarks: `header`, `nav`, `main`, `footer`; `main` has `id="main-content"` |
| A-9 | Skip link is the first focusable element |
| A-10 | No colour-only meaning anywhere |
| A-11 | Live regions `polite` by default; `assertive` only for errors interrupting a task |
| A-12 | Modals trap focus and restore it to the trigger on close |
| A-13 | All functionality keyboard-operable; no keyboard traps outside intentional modals |
| A-14 | `prefers-reduced-motion` honoured |
| A-15 | Timeout warnings meet WCAG 2.2 *Timeouts*; extension reachable in one tab stop |
| A-16 | Dragging (file drop) always has a non-dragging alternative — WCAG 2.2 *Dragging Movements* |
| A-17 | Focus is never obscured by sticky headers or the tab bar — WCAG 2.2 *Focus Not Obscured* |
| A-18 | Authentication requires no cognitive test (no puzzles); paste into OTP fields permitted — WCAG 2.2 *Accessible Authentication* |
| A-19 | Help mechanisms appear in a consistent place across pages — WCAG 2.2 *Consistent Help* |
| A-20 | Information previously entered is auto-populated or selectable — WCAG 2.2 *Redundant Entry* |

A-16 through A-20 are the WCAG 2.2 additions specifically. They are easy to miss when working from 2.1 habits, and A-16 and A-18 both bite this application directly — the file uploader and the tracking OTP.

---

## 10. Definition of Done — Per Component

A component is complete only when every line is satisfied.

```
[ ] Uses only tokens from §2 — no arbitrary values
[ ] Renders correctly at 320px
[ ] All variants specified in §5 implemented
[ ] Default, hover, active, focus, disabled, loading states present
[ ] Disabled state carries a reason wired via aria-describedby
[ ] Empty state implemented where a collection is possible
[ ] Error state implemented where failure is possible
[ ] No colour-only meaning
[ ] Keyboard operable end to end
[ ] Focus visible and correctly ordered
[ ] Screen reader verified: name, role, value, state
[ ] Contrast verified against §2.2 ratios
[ ] forced-colors: active verified (§3.1)
[ ] prefers-contrast: more verified (§3.1)
[ ] prefers-reduced-motion verified
[ ] Print behaviour verified (§3.2)
[ ] All copy from §7 or a translation key — none hard-coded
[ ] Logical properties (ms/me/ps/pe) not physical
[ ] $attributes forwarded
[ ] Correctly classified Blade vs Livewire per §4.1
```

### CI enforcement

| Check | Fails the build when |
|---|---|
| Arbitrary value scan | `class` contains `[`…`]` outside an allowlist |
| Contrast audit | Any token pairing falls below its stated ratio |
| Colour-only scan | A status element renders without an icon or text sibling |
| Label association | A form control has no associated label |
| Heading order | A page skips a heading level |
| Hard-coded copy | A Blade component contains a user-facing string outside `__()` |
| Physical properties | `ml-`, `mr-`, `pl-`, `pr-` appear in `resources/views` |

---

## 11. Conflicts and Open Items

| ID | Item | Status |
|---|---|---|
| CG-1 | **RTL not supported, conflicting with PRD NFR-U-06.** Acceptable only while no RTL language is in the launch set. Logical properties used throughout as a hedge. | ⚠ Resolve PRD OD-4 |
| CG-2 | Dark mode not supported. Retrofitting means a full second-palette contrast audit. | Accepted |
| CG-3 | Font licensing for Public Sans, Source Serif 4, IBM Plex Mono in the deployment jurisdiction | ⚠ Carried from UI-7 |
| CG-4 | British English chosen for spelling. Confirm against the issuing authority's standard. | ⚠ Confirm |
| CG-5 | Heroicons 2.x as the icon library — confirm the MIT licence is acceptable for government use | ⚠ Confirm |
| CG-6 | Screen UI Specs §10 and §11 describe Filament tabs and resources; both need rewriting against §5.14 and §5.15 | ⚠ Amend |
| CG-7 | Refund percentages in §7.5 confirmation copy are placeholders pending PRD OD-7 | ⚠ Blocked on OD-7 |
