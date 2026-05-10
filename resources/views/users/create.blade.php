@extends('layouts.app')

@section('title', 'Add user')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'user-plus', 'class' => 'w-7 h-7'])
            Add user
        </h1>
        <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-[var(--color-studio-primary)]">
            @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
            Users
        </a>
    </div>

    <form action="{{ route('users.store') }}" method="POST" class="max-w-md space-y-4">
        @csrf
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required class="w-full px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required class="w-full px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password</label>
            <input type="password" name="password" id="password" required class="w-full px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800">
            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Confirm password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required class="w-full px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800">
        </div>
        <div>
            <label for="role" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Role</label>
            <select name="role" id="role" required class="w-full px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800">
                @foreach(\App\Models\User::ROLES_FOR_CREATE as $value => $label)
                    <option value="{{ $value }}" {{ old('role') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('role')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-slate-300 dark:border-slate-600 text-[var(--color-studio-primary)] focus:ring-[var(--color-studio-primary)]">
                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Active</span>
            </label>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Inactive users cannot log in.</p>
        </div>
        @if(count($sourceCategories ?? []) > 0)
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Allowed categories (editor / framing roles)</label>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">When the role includes editing or framing: only job items in selected categories apply. Leave all unchecked = any category.</p>
            <div class="space-y-2 max-h-48 overflow-y-auto rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] p-3 bg-slate-50 dark:bg-slate-800/50">
                @foreach(($sourceCategories ?? []) as $cat)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="category_ids[]" value="{{ $cat['id'] }}" {{ in_array($cat['id'], old('category_ids', [])) ? 'checked' : '' }}>
                        <span class="text-sm"><span class="font-mono text-slate-500">{{ $cat['code'] }}</span> {{ $cat['name'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded bg-[var(--color-studio-primary)] text-white hover:opacity-90">
                @include('components.icons', ['name' => 'plus', 'class' => 'w-4 h-4'])
                Create user
            </button>
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)]">
                @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
                Cancel
            </a>
        </div>
    </form>
@endsection
