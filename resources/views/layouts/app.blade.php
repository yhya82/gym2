<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ \App\Models\ApplicationSetting::current()->application_name }}</title>

        <!-- Applied before first paint so there's no flash of the wrong theme.
             Preference is saved in localStorage — a per-device choice rather
             than a per-account one, which fits a shared reception desk. A
             device with no stored choice yet falls back to the org-wide
             Settings > Default Theme, ahead of the OS preference, since a
             shared front-desk terminal should open in whatever look the
             gym configured rather than whatever the OS happens to prefer. -->
        <script>
            // Read by resources/js/app.js too, so the same fallback chain
            // (stored > org default > OS preference) applies consistently
            // after wire:navigate soft-navigations, not just this hard load.
            window.__defaultTheme = @json(\App\Models\ApplicationSetting::current()->default_theme->value);

            (function () {
                const stored = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const dark = stored ? stored === 'dark' : (window.__defaultTheme === 'dark' || (! window.__defaultTheme && prefersDark));
                if (dark) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        <div class="flex h-full">
            @include('layouts.sidebar')

            <div class="flex-1 flex flex-col min-w-0 pt-16 lg:pt-0">
                @include('layouts.topnav')

                <main class="flex-1 overflow-y-auto">
                    @if (session('status'))
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                            <div class="rounded-md bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                                {{ session('status') }}
                            </div>
                        </div>
                    @endif

                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
