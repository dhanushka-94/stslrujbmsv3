<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-studio-bg)] min-h-screen text-slate-800 dark:text-slate-100 flex flex-col">
    {{-- Sticky site header: brand strip + main nav (stays visible while scrolling) --}}
    <div class="sticky top-0 z-40 border-b border-slate-200/90 bg-[var(--color-studio-bg)]/85 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.12)] backdrop-blur-md dark:border-slate-700/80 dark:bg-slate-950/80 dark:shadow-[0_12px_40px_-16px_rgba(0,0,0,0.45)]">
        <header class="border-b border-slate-200/70 bg-gradient-to-r from-white/90 via-slate-50/80 to-white/90 dark:border-slate-700/60 dark:from-slate-900/90 dark:via-slate-900/70 dark:to-slate-900/90">
            <div class="mx-auto flex w-full max-w-none items-center justify-between gap-3 px-4 py-2 sm:px-6 lg:px-8">
                <a href="{{ route('dashboard') }}" class="inline-flex min-w-0 items-center gap-2.5 rounded-lg py-0.5 font-semibold text-[var(--color-studio-primary)] ring-[var(--color-studio-primary)]/15 transition hover:opacity-90 focus:outline-none focus-visible:ring-2 dark:text-[var(--color-studio-accent)] dark:ring-[var(--color-studio-accent)]/20">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-800 dark:ring-slate-600">
                        <img src="{{ asset('studio_salaru_logo.jpg') }}" alt="{{ config('app.name') }}" class="h-7 w-auto max-h-full max-w-full object-contain" />
                    </span>
                    <span class="truncate text-sm sm:text-base">{{ config('app.name') }}</span>
                </a>
                <span id="live-datetime" class="inline-flex max-w-[min(100%,18rem)] items-center rounded-full border border-slate-200/90 bg-white/90 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600 shadow-sm tabular-nums dark:border-slate-600 dark:bg-slate-800/90 dark:text-slate-300 sm:max-w-none sm:text-xs sm:normal-case sm:tracking-normal" aria-live="polite">
                    {{ now()->format('l, F j, Y') }} &nbsp; {{ now()->format('h:i:s A') }}
                </span>
            </div>
        </header>
        {{-- Main menu: full-width nav with ordered items and grouped Settings submenu --}}
        <nav class="relative bg-[var(--color-studio-bg-card)]/92 dark:bg-[var(--color-studio-dark-card)]/92">
            <div class="mx-auto flex w-full max-w-none items-center justify-between gap-2 px-4 py-2.5 sm:gap-4 sm:px-6 lg:px-8">
            @auth
                <div class="flex flex-wrap items-center gap-1.5 min-w-0 flex-1">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-sm px-3 py-2 rounded-full whitespace-nowrap transition-colors
                        {{ request()->routeIs('dashboard') ? 'bg-[var(--color-studio-primary)] text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-[var(--color-studio-primary)] hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                        @include('components.icons', ['name' => 'dashboard', 'class' => 'w-4 h-4'])
                        Dashboard
                    </a>
                    <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-1.5 text-sm px-3 py-2 rounded-full whitespace-nowrap transition-colors
                        {{ request()->routeIs('jobs.index','jobs.show') ? 'bg-[var(--color-studio-primary)] text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-[var(--color-studio-primary)] hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                        @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4'])
                        Jobs
                    </a>
                    <a href="{{ route('jobs.live') }}" class="inline-flex items-center gap-1.5 text-sm px-3 py-2 rounded-full whitespace-nowrap transition-colors
                        {{ request()->routeIs('jobs.live') ? 'bg-[var(--color-studio-primary)] text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-[var(--color-studio-primary)] hover:bg-slate-100 dark:hover:bg-slate-700' }}"
                        @if(filled($jobPoolNotifyTitle ?? null)) title="{{ $jobPoolNotifyTitle }}" @endif>
                        @include('components.icons', ['name' => 'bolt', 'class' => 'w-4 h-4'])
                        Job Pool
                        @if(isset($jobPoolNewCount) && $jobPoolNewCount > 0)
                            <span class="ml-1 inline-flex items-center justify-center rounded-full bg-red-600 text-white text-[10px] min-w-[16px] h-4 px-1" aria-label="{{ $jobPoolNotifyTitle ?? 'New Job Pool items' }}">
                                {{ $jobPoolNewCount > 9 ? '9+' : $jobPoolNewCount }}
                            </span>
                        @endif
                    </a>
                    <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-1.5 text-sm px-3 py-2 rounded-full whitespace-nowrap transition-colors
                        {{ request()->routeIs('profile.*') ? 'bg-[var(--color-studio-primary)]/10 text-[var(--color-studio-primary)] dark:bg-[var(--color-studio-accent)]/10 dark:text-[var(--color-studio-accent)]' : 'text-slate-600 dark:text-slate-400 hover:text-[var(--color-studio-primary)] hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                        @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4'])
                        Profile
                    </a>
                    <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-1.5 text-sm px-3 py-2 rounded-full whitespace-nowrap transition-colors
                        {{ request()->routeIs('reports.*','users.report') ? 'bg-[var(--color-studio-primary)]/10 text-[var(--color-studio-primary)] dark:bg-[var(--color-studio-accent)]/10 dark:text-[var(--color-studio-accent)]' : 'text-slate-600 dark:text-slate-400 hover:text-[var(--color-studio-primary)] hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                        @include('components.icons', ['name' => 'document-check', 'class' => 'w-4 h-4'])
                        Reports
                    </a>
                    {{-- Settings dropdown: Catalog, Blocking, Administration (Admin only) --}}
                    @if(auth()->user()->isAdmin())
                        <div class="relative z-40 group">
                            <button type="button" class="inline-flex items-center gap-1.5 text-sm px-3 py-2 rounded-full whitespace-nowrap text-slate-600 dark:text-slate-400 hover:text-[var(--color-studio-primary)] hover:bg-slate-100 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--color-studio-primary)]/30" aria-expanded="false" aria-haspopup="true" id="settings-menu-btn">
                                @include('components.icons', ['name' => 'cog-6-tooth', 'class' => 'w-4 h-4'])
                                Settings
                                <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div class="absolute left-0 top-full mt-1 w-52 py-1 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-600 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-[100]" role="menu" aria-labelledby="settings-menu-btn">
                                <div class="px-3 py-1.5 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Catalog</div>
                                <a href="{{ route('source-products.index') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700" role="menuitem">
                                    @include('components.icons', ['name' => 'squares-2x2', 'class' => 'w-4 h-4 text-slate-500'])
                                    Products
                                </a>
                                <a href="{{ route('source-categories.index') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700" role="menuitem">
                                    @include('components.icons', ['name' => 'folder', 'class' => 'w-4 h-4 text-slate-500'])
                                    Categories
                                </a>
                                <a href="{{ route('settings.pos-sales') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700" role="menuitem">
                                    @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4 text-slate-500'])
                                    POS sales
                                </a>
                                <div class="border-t border-slate-200 dark:border-slate-600 my-1"></div>
                                <div class="px-3 py-1.5 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Visibility</div>
                                <a href="{{ route('settings.block-categories') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700" role="menuitem">
                                    @include('components.icons', ['name' => 'archive', 'class' => 'w-4 h-4 text-slate-500'])
                                    Block categories
                                </a>
                                <a href="{{ route('settings.block-products') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700" role="menuitem">
                                    @include('components.icons', ['name' => 'squares-2x2', 'class' => 'w-4 h-4 text-slate-500'])
                                    Block products
                                </a>
                                <div class="border-t border-slate-200 dark:border-slate-600 my-1"></div>
                                <div class="px-3 py-1.5 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Administration</div>
                                <a href="{{ route('users.index') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700" role="menuitem">
                                    @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4 text-slate-500'])
                                    Users
                                </a>
                                <a href="{{ route('activity-log.index') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-b-lg" role="menuitem">
                                    @include('components.icons', ['name' => 'document-check', 'class' => 'w-4 h-4 text-slate-500'])
                                    Activity log
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 border border-[var(--color-studio-border)]/60 dark:border-[var(--color-studio-dark-border)]/60">
                        <span class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ auth()->user()->name }}</span>
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-[var(--color-studio-primary)]/10 dark:bg-[var(--color-studio-accent)]/10 text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] uppercase tracking-wide">
                            {{ auth()->user()->roleLabel() }}
                        </span>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="rounded-full border border-slate-300/90 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-studio-primary)]/30 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                            Log out
                        </button>
                    </form>
                </div>
            @endauth
            </div>
        </nav>
    </div>

    {{-- pb: clears fixed footer; bulk bars on job detail use z-30 so they sit above this footer (z-20) --}}
    <main class="min-w-0 flex-1 w-full max-w-none px-4 pb-20 pt-4 sm:px-6 sm:pb-20 sm:pt-6 lg:px-8">
        @if(session('success'))
            <div class="mb-4 px-4 py-2 rounded bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-2 rounded bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>

    <footer class="fixed bottom-0 left-0 right-0 z-20 border-t border-slate-200/90 bg-[var(--color-studio-bg-card)]/92 py-2.5 shadow-[0_-6px_24px_-8px_rgba(15,23,42,0.1)] backdrop-blur-md dark:border-slate-700/80 dark:bg-[var(--color-studio-dark-card)]/92 dark:shadow-[0_-8px_28px_-10px_rgba(0,0,0,0.35)] pb-[max(0.625rem,env(safe-area-inset-bottom))]">
        <div class="mx-auto flex w-full max-w-none flex-col items-center justify-center gap-1 px-4 text-center sm:flex-row sm:gap-2 sm:px-6 lg:px-8">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Studio</span>
            <span class="hidden h-3 w-px bg-slate-300 dark:bg-slate-600 sm:inline" aria-hidden="true"></span>
            <p class="text-xs text-slate-600 dark:text-slate-300 sm:text-sm">
                Crafted by
                <a href="https://olextodigital.com" target="_blank" rel="noopener noreferrer" class="font-medium text-[var(--color-studio-primary)] underline decoration-slate-300 underline-offset-2 transition hover:decoration-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] dark:decoration-slate-600 dark:hover:decoration-[var(--color-studio-accent)]">Olexto Digital Solutions (Pvt) Ltd</a>
            </p>
        </div>
    </footer>
    <script>
        (function () {
            var el = document.getElementById('live-datetime');
            if (!el) return;
            var opts = { timeZone: 'Asia/Colombo', weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
            function update() {
                el.textContent = new Date().toLocaleString('en-LK', opts).replace(',', ' \u00A0');
            }
            update();
            setInterval(update, 1000);
        })();
    </script>
</body>
</html>
