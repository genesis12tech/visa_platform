// tailwind.config.js — Content_guidelines.md §2.8, verbatim
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
      // modal-sm/md/lg: Content_guidelines.md §5.12's exact modal sizes (28/36/48rem).
      // Not part of the §2.4 container scale (content/panel), so added here the
      // same way — a named token, never an arbitrary-value bracket on the component.
      maxWidth: { content: 'var(--content-max)', panel: 'var(--panel-max)', 'modal-sm': '28rem', 'modal-md': '36rem', 'modal-lg': '48rem' },
      // §5.12: modal body scrolls, not the page, capped at 85vh — viewport-relative,
      // so (like the modal widths above) it's a named token rather than an
      // arbitrary-value bracket on the component.
      maxHeight: { modal: '85vh' },
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
