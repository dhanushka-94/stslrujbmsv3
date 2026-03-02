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
    {{-- Mini header: logo + app name (left), live date & time (right) - full width --}}
    <header class="bg-slate-100 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700">
        <div class="w-full max-w-none px-4 sm:px-6 lg:px-8 py-1.5 flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)]">
                <img src="{{ asset('studio_salaru_logo.jpg') }}" alt="{{ config('app.name') }}" class="h-7 w-auto object-contain" />
                <span class="text-sm sm:text-base">{{ config('app.name') }}</span>
            </a>
            <span id="live-datetime" class="text-xs font-medium text-slate-600 dark:text-slate-400 tabular-nums" aria-live="polite">
                {{ now()->format('l, F j, Y') }} &nbsp; {{ now()->format('h:i:s A') }}
            </span>
        </div>
    </header>
    {{-- Main menu: full-width nav with ordered items and grouped Settings submenu --}}
    <nav class="relative z-30 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)]/95 dark:bg-[var(--color-studio-dark-card)]/95 backdrop-blur-sm shadow-sm">
        <div class="w-full max-w-none px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between gap-2 sm:gap-4">
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
                        {{ request()->routeIs('jobs.live') ? 'bg-[var(--color-studio-primary)] text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-[var(--color-studio-primary)] hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                        @include('components.icons', ['name' => 'bolt', 'class' => 'w-4 h-4'])
                        Job Pool
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
                        <button type="submit" class="text-sm px-3 py-1.5 rounded-full border border-[var(--color-studio-border)] hover:bg-slate-100 dark:hover:bg-slate-700">
                            Logout
                        </button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>

    <main class="w-full max-w-none px-4 sm:px-6 lg:px-8 py-4 sm:py-6 flex-1 min-w-0">
        @if(session('success'))
            <div class="mb-4 px-4 py-2 rounded bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-2 rounded bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>

    <footer class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] py-3 mt-auto">
        <div class="w-full max-w-none px-4 sm:px-6 lg:px-8 text-center text-sm text-slate-500 dark:text-slate-400">
            Developed by <a href="https://olextodigital.com" target="_blank" rel="noopener noreferrer" class="text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] hover:underline">Olexto Digital Solutions (Pvt) Ltd</a>
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
