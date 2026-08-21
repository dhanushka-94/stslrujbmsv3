@extends('layouts.app')

@section('title', 'Add user')

@section('content')
    <div class="max-w-6xl mx-auto pb-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
                    @include('components.icons', ['name' => 'user-plus', 'class' => 'w-7 h-7'])
                    Add user
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create a new team member and assign their role</p>
            </div>
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-[var(--color-studio-primary)] dark:hover:text-[var(--color-studio-accent)] transition-colors">
                @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
                Back to users
            </a>
        </div>

        <form action="{{ route('users.store') }}" method="POST">
            @csrf
            @include('users._form', [
                'sourceCategories' => $sourceCategories,
                'roles' => \App\Models\User::ROLES_FOR_CREATE,
                'submitLabel' => 'Create user',
                'submitIcon' => 'plus',
            ])
        </form>
    </div>
@endsection
