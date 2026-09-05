<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ __('Log in') }} — {{ \App\Models\ApplicationSetting::current()->application_name }}</title>

        <script>
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
    <body class="h-full font-sans antialiased">
        <div class="min-h-full flex">
            @php $settings = \App\Models\ApplicationSetting::current(); @endphp

            {{-- Feature panel — hidden on small screens so the form gets full
                 width there rather than competing for space. --}}
            <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col justify-between bg-indigo-600 dark:bg-indigo-900 text-white px-12 py-12">
                <div class="flex items-center gap-2">
                    @if ($settings->logo)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($settings->logo) }}" alt="" class="h-9 w-9 rounded object-cover shrink-0">
                    @else
                        <x-application-logo class="h-9 w-9 fill-current text-white" />
                    @endif
                    <span class="text-lg font-semibold">{{ $settings->application_name }}</span>
                </div>

                <div class="max-w-md">
                    <h1 class="text-3xl font-semibold leading-tight text-balance">
                        {{ __('Run your gym from one place.') }}
                    </h1>
                    <p class="mt-4 text-indigo-100">
                        {{ __('Members, memberships, payments, and revenue — tracked accurately and updated in real time.') }}
                    </p>

                    <ul class="mt-8 space-y-4">
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 shrink-0 h-8 w-8 rounded-md bg-white/10 flex items-center justify-center">
                                <x-icon name="members" class="h-4 w-4" />
                            </span>
                            <div>
                                <p class="font-medium">{{ __('Centralized member management') }}</p>
                                <p class="text-sm text-indigo-200">{{ __('Registration, renewals, and status — always up to date.') }}</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 shrink-0 h-8 w-8 rounded-md bg-white/10 flex items-center justify-center">
                                <x-icon name="revenue" class="h-4 w-4" />
                            </span>
                            <div>
                                <p class="font-medium">{{ __('Accurate revenue tracking') }}</p>
                                <p class="text-sm text-indigo-200">{{ __('Every payment recorded and reconciled automatically.') }}</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 shrink-0 h-8 w-8 rounded-md bg-white/10 flex items-center justify-center">
                                <x-icon name="dashboard" class="h-4 w-4" />
                            </span>
                            <div>
                                <p class="font-medium">{{ __('Live dashboards') }}</p>
                                <p class="text-sm text-indigo-200">{{ __('Membership and revenue figures update instantly for every device.') }}</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 shrink-0 h-8 w-8 rounded-md bg-white/10 flex items-center justify-center">
                                <x-icon name="users" class="h-4 w-4" />
                            </span>
                            <div>
                                <p class="font-medium">{{ __('Role-based access') }}</p>
                                <p class="text-sm text-indigo-200">{{ __('Admins and receptionists each see exactly what their role needs.') }}</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <p class="text-xs text-indigo-300">&copy; {{ now()->year }} {{ $settings->application_name }}</p>
            </div>

            <div class="flex-1 flex flex-col justify-center items-center px-6 py-12 bg-white dark:bg-gray-900">
                <div class="w-full max-w-sm">
                    <div class="lg:hidden flex items-center gap-2 mb-8 justify-center">
                        @if ($settings->logo)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($settings->logo) }}" alt="" class="h-9 w-9 rounded object-cover shrink-0">
                        @else
                            <x-application-logo class="h-9 w-9 fill-current text-indigo-600" />
                        @endif
                        <span class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $settings->application_name }}</span>
                    </div>

                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Welcome back') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Log in to continue.') }}</p>

                    <div class="mt-8">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
