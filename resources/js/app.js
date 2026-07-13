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
});

function applyStoredTheme() {
    const stored = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const dark = stored ? stored === 'dark' : (window.__defaultTheme === 'dark' || (!window.__defaultTheme && prefersDark));

    document.documentElement.classList.toggle('dark', dark);

    if (window.Alpine?.store('theme')) {
        window.Alpine.store('theme').dark = dark;
    }
}

// The inline <head> script only prevents flash-of-wrong-theme on a hard
// load; wire:navigate's soft transitions need this re-applied afterward.
document.addEventListener('livewire:navigated', applyStoredTheme);
