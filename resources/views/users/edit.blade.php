@extends('layouts.app')

@section('title', 'Edit user – ' . $user->name)

@section('content')
    <div class="max-w-6xl mx-auto pb-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
                    @include('components.icons', ['name' => 'pencil-square', 'class' => 'w-7 h-7'])
                    Edit user
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Update account, role, and category access</p>
            </div>
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-[var(--color-studio-primary)] dark:hover:text-[var(--color-studio-accent)] transition-colors">
                @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
                Back to users
            </a>
        </div>

        <form action="{{ route('users.update', $user) }}" method="POST">
            @csrf
            @method('PUT')
            @include('users._form', [
                'user' => $user,
                'sourceCategories' => $sourceCategories,
                'roles' => \App\Models\User::ROLES,
                'submitLabel' => 'Save changes',
                'submitIcon' => 'document-check',
            ])
        </form>
    </div>
@endsection
