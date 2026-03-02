@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'users', 'class' => 'w-7 h-7'])
            Users
        </h1>
        <div class="flex items-center gap-2">
            @if(auth()->user()->isAdmin())
                <a href="{{ route('activity-log.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                    @include('components.icons', ['name' => 'document-check', 'class' => 'w-4 h-4'])
                    Activity log
                </a>
            @endif
            <a href="{{ route('users.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">
                @include('components.icons', ['name' => 'user-plus', 'class' => 'w-4 h-4'])
                Add user
            </a>
        </div>
    </div>

    <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th class="text-left p-3">Name</th>
                    <th class="text-left p-3">Email</th>
                    <th class="text-left p-3">Role</th>
                    <th class="text-left p-3">Status</th>
                    <th class="text-left p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)]">
                        <td class="p-3 font-medium">{{ $user->name }}</td>
                        <td class="p-3">{{ $user->email }}</td>
                        <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-slate-100 dark:bg-slate-700">{{ $user->roleLabel() }}</span></td>
                        <td class="p-3">
                            @if($user->isActive())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200">
                                    @include('components.icons', ['name' => 'check-circle', 'class' => 'w-3.5 h-3.5'])
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300">
                                    @include('components.icons', ['name' => 'no-symbol', 'class' => 'w-3.5 h-3.5'])
                                    Inactive
                                </span>
                            @endif
                        </td>
                        <td class="p-3">
                            <a href="{{ route('users.show', $user) }}" class="inline-flex items-center gap-1 text-[var(--color-studio-primary)] hover:underline mr-3">
                                @include('components.icons', ['name' => 'eye', 'class' => 'w-4 h-4'])
                                View
                            </a>
                            <a href="{{ route('users.report', $user) }}" class="inline-flex items-center gap-1 text-[var(--color-studio-primary)] hover:underline mr-3">
                                @include('components.icons', ['name' => 'document-check', 'class' => 'w-4 h-4'])
                                Report
                            </a>
                            @if($user->role !== 'admin' || auth()->id() === $user->id)
                                <a href="{{ route('users.edit', $user) }}" class="inline-flex items-center gap-1 text-[var(--color-studio-primary)] hover:underline mr-3">
                                    @include('components.icons', ['name' => 'pencil-square', 'class' => 'w-4 h-4'])
                                    Edit
                                </a>
                            @endif
                            @if(auth()->id() !== $user->id && !($user->isAdmin() && \App\Models\User::where('role', 'admin')->count() <= 1))
                                <form action="{{ route('users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1 text-red-600 hover:text-red-700 hover:underline">
                                        @include('components.icons', ['name' => 'trash', 'class' => 'w-4 h-4'])
                                        Delete
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
