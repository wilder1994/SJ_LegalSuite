<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ ($uiTheme ?? 'light') === 'dark' ? 'dark' : '' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ @filemtime(public_path('favicon.ico')) }}">
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ @filemtime(public_path('favicon.ico')) }}">
        <link rel="apple-touch-icon" href="{{ \App\Support\Disciplinary\DisciplinaryAssets::logoPublicUrl() }}">

        <title>{{ config('app.name', 'SJ LegalSuite') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @auth
            @if (\App\Support\Broadcasting\PusherBroadcasting::isEnabled())
                <script>
                    window.__appBroadcasting = {
                        userId: {{ auth()->id() }},
                        key: @json((string) config('broadcasting.connections.pusher.key')),
                        cluster: @json((string) config('broadcasting.connections.pusher.options.cluster', 'mt1')),
                        forceTls: @json((bool) (config('broadcasting.connections.pusher.options.useTLS') ?? true)),
                    };
                </script>
            @endif
        @endauth

        @vite(['resources/js/app.js'])
    </head>
    @php
        $sidebarVariant = ($uiTheme ?? 'light') === 'dark' ? 'neon' : 'light';
        $logoutVariant = ($uiTheme ?? 'light') === 'dark' ? 'dark' : 'light';
    @endphp
    <body class="font-sans antialiased bg-slate-50 text-slate-900 dark:bg-dash-void dark:text-slate-100">
        <div x-data="{ sidebarOpen: false }"
             x-on:sidebar-toggle.window="sidebarOpen = !sidebarOpen"
             class="flex h-screen max-h-screen min-h-0 overflow-hidden">

            {{-- Backdrop móvil --}}
            <div x-show="sidebarOpen"
                 x-transition.opacity
                 x-on:click="sidebarOpen = false"
                 class="fixed inset-0 z-30 bg-black/50 lg:hidden dark:bg-black/60"
                 style="display: none;"></div>

            <x-app-sidebar :variant="$sidebarVariant" />

            {{-- min-h-0: permite que main reciba altura real del flex y que cockpits llenen el hueco sin pelear con 100dvh. --}}
            <div class="flex min-h-0 min-w-0 flex-1 flex-col">

                @php
                    $hasModuleNav = ! empty(trim($__env->yieldPushContent('module-nav')));
                    $informesOnlyNav = auth()->check()
                        && ! auth()->user()->canSeeFullAppSidebar()
                        && ! request()->routeIs('password.force-change');
                @endphp

                @if ($hasModuleNav)
                    @stack('module-nav')
                @elseif ($informesOnlyNav)
                    <x-disciplinary.nav />
                @else
                    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white dark:border-white/10 dark:bg-dash-ink/90 dark:backdrop-blur-md">
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 lg:px-6">
                            <button type="button" x-on:click="sidebarOpen = true"
                                    class="lg:hidden -ml-2 rounded-lg p-2 text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                </svg>
                            </button>

                            <div class="flex-1"></div>

                            <div class="flex shrink-0 items-center gap-1 sm:gap-1.5">
                                @auth
                                    <livewire:ui.notification-bell />
                                    <livewire:ui.theme-toggle />
                                @endauth
                                <a href="{{ route('profile') }}" wire:navigate
                                   class="hidden h-9 items-center rounded-lg px-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 sm:inline-flex dark:text-dash-muted dark:hover:bg-white/10 dark:hover:text-white">
                                    Mi perfil
                                </a>
                                <livewire:auth.logout-button :variant="$logoutVariant" />
                            </div>
                        </div>
                    </header>
                @endif

                {{-- overflow-y-auto: válvula de scroll de página si el contenido supera el hueco (sin clip). --}}
                <main class="relative flex min-h-0 flex-1 flex-col overflow-y-auto">
                    <div class="pointer-events-none absolute inset-0 hidden bg-[radial-gradient(ellipse_120%_80%_at_50%_-20%,rgba(217,70,239,0.18),transparent_55%),radial-gradient(ellipse_90%_60%_at_100%_50%,rgba(34,211,238,0.12),transparent_45%),radial-gradient(ellipse_70%_50%_at_0%_80%,rgba(251,146,60,0.08),transparent_40%)] dark:block"></div>
                    <div class="relative flex min-h-full flex-1 flex-col">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        @stack('scripts')
    </body>
</html>
