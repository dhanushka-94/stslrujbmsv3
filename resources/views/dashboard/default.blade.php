@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'dashboard', 'class' => 'w-7 h-7'])
            Dashboard
        </h1>
        <p class="mt-1 text-slate-600 dark:text-slate-400">Welcome, {{ auth()->user()->name }}.</p>
    </div>

    <div class="grid gap-4 max-w-md">
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-6 shadow-sm">
            <h2 class="text-lg font-medium mb-2 flex items-center gap-2">Quick links</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">
                    @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4'])
                    View jobs
                </a>
                <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                    @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4'])
                    My profile
                </a>
            </div>
        </div>
    </div>
@endsection
