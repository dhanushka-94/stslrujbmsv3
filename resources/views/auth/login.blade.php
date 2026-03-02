<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Login – {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-studio-bg)] min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] rounded-xl shadow-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] p-8">
        <div class="flex justify-center mb-4">
            <img src="{{ asset('studio_salaru_logo.jpg') }}" alt="{{ config('app.name') }}" class="h-14 w-auto object-contain" />
        </div>
        <h1 class="text-xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] mb-2 text-center">{{ config('app.name') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Sign in to your account</p>

        @if($errors->any())
            <div class="mb-4 p-3 rounded bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                    class="w-full px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
            </div>
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password</label>
                <input type="password" name="password" id="password" required
                    class="w-full px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
            </div>
            <div class="mb-6">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="remember" class="rounded border-slate-300">
                    <span class="ml-2 text-sm text-slate-600 dark:text-slate-400">Remember me</span>
                </label>
            </div>
            <button type="submit" class="w-full py-2 px-4 rounded bg-[var(--color-studio-primary)] hover:bg-[var(--color-studio-primary-hover)] text-white font-medium">Sign in</button>
        </form>

        <p class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-600 text-center text-xs text-slate-500 dark:text-slate-400">
            Developed by
            <a href="https://olextodigital.com" target="_blank" rel="noopener noreferrer" class="text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] hover:underline font-medium">Olexto Digital Solutions (Pvt) Ltd</a>
        </p>
    </div>
</body>
</html>
