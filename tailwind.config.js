import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [
        forms,
        // `sidebar-collapsed:` styles apply while <html> has the
        // .sidebar-collapsed class. That class is set before first paint
        // (layouts/app.blade.php) and restored on every wire:navigate swap
        // (resources/js/app.js), so the collapsed sidebar is drawn correctly
        // straight away instead of after Alpine starts — which is what used
        // to make it flash open and the labels blink on every navigation.
        plugin(({ addVariant }) => {
            addVariant('sidebar-collapsed', '.sidebar-collapsed &');
        }),
    ],
};
