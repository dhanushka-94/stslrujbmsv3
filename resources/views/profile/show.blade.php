@extends('layouts.app')

@section('title', 'My profile')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'users', 'class' => 'w-7 h-7'])
            My profile
        </h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-[var(--color-studio-primary)]">
                @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
                Dashboard
            </a>
            <a href="{{ route('users.report', $user) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                @include('components.icons', ['name' => 'document-check', 'class' => 'w-4 h-4'])
                My report
            </a>
        </div>
    </div>

    <div class="grid gap-6 max-w-2xl">
        {{-- Profile info (read-only) --}}
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-6">
            <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-4">Account details</h2>
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
                @if($user->role === \App\Models\User::ROLE_EDITOR && $user->editorCategories->isNotEmpty())
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
            <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Role and status are managed by an administrator. Use the form below to update your name, email, or password.</p>
        </div>

        {{-- Edit profile form --}}
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-6">
            <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-4 flex items-center gap-2">
                @include('components.icons', ['name' => 'pencil-square', 'class' => 'w-4 h-4'])
                Update profile
            </h2>
            <form action="{{ route('profile.update') }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                        class="w-full px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                    @error('name')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                        class="w-full px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                    @error('email')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">New password (leave blank to keep current)</label>
                    <input type="password" name="password" id="password"
                        class="w-full px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"
                        autocomplete="new-password">
                    @error('password')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Confirm new password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                        class="w-full px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"
                        autocomplete="new-password">
                </div>
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">
                    @include('components.icons', ['name' => 'document-check', 'class' => 'w-4 h-4'])
                    Save changes
                </button>
            </form>
        </div>
    </div>
@endsection
