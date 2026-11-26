import './bootstrap';

import { Chart } from 'chart.js/auto';
window.Chart = Chart;

/**
 * Theme state lives in an Alpine store (a JS-level singleton), not a
 * per-element x-data — wire:navigate morphs in fresh server HTML on every
 * soft navigation, which would otherwise reset a local x-data's state (and,
 * since that HTML never has the dark class server-side, strip dark mode
 * every time you navigated anywhere with the toggle applied).
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.store('theme', {
        dark: document.documentElement.classList.contains('dark'),
        toggle() {
            this.dark = !this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            localStorage.setItem('theme', this.dark ? 'dark' : 'light');
        },
    });

    /**
     * Same reasoning as the theme store: the collapse toggle now lives in
     * topnav (a sibling of the sidebar, not a descendant of it), so a local
     * x-data on the sidebar can't be reached from there — and wire:navigate
     * would reset a local x-data on every soft navigation anyway.
     */
    window.Alpine.store('sidebar', {
        collapsed: localStorage.getItem('sidebarCollapsed') === 'true',
        // Not persisted like collapsed — the mobile drawer should always
        // start closed on a fresh page load, only opened via the hamburger.
        mobileOpen: false,
        toggle() {
            this.collapsed = !this.collapsed;
            localStorage.setItem('sidebarCollapsed', this.collapsed ? 'true' : 'false');
            // The sidebar's look is driven by this class (Tailwind's
            // `sidebar-collapsed:` variant), not by Alpine bindings.
            document.documentElement.classList.toggle('sidebar-collapsed', this.collapsed);
        },
    });
});

/**
 * Re-applies the per-device UI state that lives as classes on <html>:
 * dark mode and the collapsed sidebar.
 */
function applyStoredHtmlState() {
    const stored = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const dark = stored ? stored === 'dark' : (window.__defaultTheme === 'dark' || (!window.__defaultTheme && prefersDark));
    const collapsed = localStorage.getItem('sidebarCollapsed') === 'true';

    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.classList.toggle('sidebar-collapsed', collapsed);

    if (window.Alpine?.store('theme')) {
        window.Alpine.store('theme').dark = dark;
    }

    if (window.Alpine?.store('sidebar')) {
        window.Alpine.store('sidebar').collapsed = collapsed;
        window.Alpine.store('sidebar').mobileOpen = false;
    }
}

// wire:navigate replaces <html>'s attributes with the server's copy, which
// never has these classes. onSwap runs straight after that swap, before the
// browser paints, so neither the theme nor the sidebar visibly flips back
// and forth. (livewire:navigated alone fires after paint — that delay was
// the flash of the expanded sidebar and its labels.)
document.addEventListener('livewire:navigating', (event) => {
    event.detail.onSwap(applyStoredHtmlState);
});

// Belt and braces for back/forward restores from Livewire's cache.
document.addEventListener('livewire:navigated', applyStoredHtmlState);
