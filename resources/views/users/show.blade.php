@extends('layouts.app')

@section('title', $user->name)

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'users', 'class' => 'w-7 h-7'])
            {{ $user->name }}
        </h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-[var(--color-studio-primary)]">
                @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
                Users
            </a>
            @if($user->role !== 'admin' || auth()->id() === $user->id)
                <a href="{{ route('users.edit', $user) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">
                    @include('components.icons', ['name' => 'pencil-square', 'class' => 'w-4 h-4'])
                    Edit
                </a>
            @endif
        </div>
    </div>

    <div class="max-w-lg bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-6">
        <dl class="space-y-4">
            <div>
                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Name</dt>
                <dd class="mt-0.5 text-slate-800 dark:text-slate-100">{{ $user->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Email</dt>
                <dd class="mt-0.5 text-slate-800 dark:text-slate-100">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Role</dt>
                <dd class="mt-0.5"><span class="px-2 py-0.5 rounded text-sm bg-slate-100 dark:bg-slate-700">{{ $user->roleLabel() }}</span></dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Status</dt>
                <dd class="mt-0.5">
                    @if($user->isActive())
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-sm bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200">
                            @include('components.icons', ['name' => 'check-circle', 'class' => 'w-4 h-4'])
                            Active
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-sm bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300">
                            @include('components.icons', ['name' => 'no-symbol', 'class' => 'w-4 h-4'])
                            Inactive
                        </span>
                    @endif
                </dd>
            </div>
            @if(in_array($user->role, \App\Models\User::rolesWithCategoryAssignments(), true) && $user->editorCategories->isNotEmpty())
                <div>
                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Allowed categories</dt>
                    <dd class="mt-0.5 text-slate-700 dark:text-slate-300">
                        @foreach($user->editorCategories as $ec)
                            <span class="inline-block mr-2 mt-1 px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-xs">Category ID {{ $ec->source_category_id }}</span>
                        @endforeach
                    </dd>
                </div>
            @endif
        </dl>
    </div>
@endsection
