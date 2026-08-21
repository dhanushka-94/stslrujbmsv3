@php
    $isEdit = isset($user) && $user !== null;
    $selectedCategoryIds = old(
        'category_ids',
        $isEdit
            ? $user->editorCategories->pluck('source_category_id')->map(fn ($i) => (int) $i)->all()
            : []
    );
    $inputClass = 'w-full px-3 py-2.5 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm shadow-sm placeholder:text-slate-400 focus:border-[var(--color-studio-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-studio-primary)]/20 dark:focus:border-[var(--color-studio-accent)] dark:focus:ring-[var(--color-studio-accent)]/20';
    $checkboxClass = 'h-4 w-4 shrink-0 rounded border-slate-300 dark:border-slate-600 text-[var(--color-studio-primary)] focus:ring-[var(--color-studio-primary)] focus:ring-offset-0 dark:bg-slate-800';
@endphp

<div class="grid gap-6 lg:grid-cols-5">
    {{-- Sidebar: context & tips --}}
    <aside class="lg:col-span-2 space-y-4">
        @if($isEdit)
            <div class="rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] p-5 shadow-sm">
                <div class="flex items-start gap-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-[var(--color-studio-primary)]/15 text-xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)]" aria-hidden="true">
                        {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-slate-900 dark:text-slate-100 truncate">{{ $user->name }}</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400 truncate">{{ $user->email }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-700 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:text-slate-200">{{ $user->roleLabel() }}</span>
                            @if($user->isActive())
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:text-emerald-200">
                                    @include('components.icons', ['name' => 'check-circle', 'class' => 'w-3.5 h-3.5'])
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-200 dark:bg-slate-600 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                                    @include('components.icons', ['name' => 'no-symbol', 'class' => 'w-3.5 h-3.5'])
                                    Inactive
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2 border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] pt-4">
                    <a href="{{ route('users.show', $user) }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-studio-primary)] hover:underline dark:text-[var(--color-studio-accent)]">
                        @include('components.icons', ['name' => 'eye', 'class' => 'w-3.5 h-3.5'])
                        View profile
                    </a>
                    <a href="{{ route('users.report', $user) }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-[var(--color-studio-primary)] dark:hover:text-[var(--color-studio-accent)]">
                        @include('components.icons', ['name' => 'document-check', 'class' => 'w-3.5 h-3.5'])
                        User report
                    </a>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-slate-50/80 dark:bg-slate-800/40 p-5">
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">New team member</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Choose a role that matches what they do in the studio. You can assign POS categories for editors and framers after saving.</p>
            </div>
        @endif

        <div class="rounded-xl border border-sky-200/80 dark:border-sky-800/50 bg-sky-50/60 dark:bg-sky-950/30 p-4 text-sm text-sky-950 dark:text-sky-100">
            <p class="font-medium flex items-center gap-2">
                @include('components.icons', ['name' => 'folder', 'class' => 'w-4 h-4 shrink-0'])
                Category access
            </p>
            <p class="mt-1.5 text-sky-900/90 dark:text-sky-200/90 text-xs leading-relaxed">For every role except Admin, selected categories limit which POS job lines they see in Job Pool and on jobs. Leave none selected to allow <strong>all</strong> categories.</p>
        </div>
    </aside>

    {{-- Main form --}}
    <div class="lg:col-span-3 space-y-5">
        {{-- Account --}}
        <section class="rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-slate-50/80 dark:bg-slate-800/40">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4 text-slate-500'])
                    Account details
                </h2>
            </div>
            <div class="p-5 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">Full name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $isEdit ? $user->name : '') }}" required autocomplete="name" class="{{ $inputClass }}">
                    @error('name')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $isEdit ? $user->email : '') }}" required autocomplete="email" class="{{ $inputClass }}">
                    @error('email')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- Password --}}
        <section class="rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-slate-50/80 dark:bg-slate-800/40">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    @include('components.icons', ['name' => 'cog-6-tooth', 'class' => 'w-4 h-4 text-slate-500'])
                    {{ $isEdit ? 'Change password' : 'Password' }}
                </h2>
                @if($isEdit)
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Leave blank to keep the current password</p>
                @endif
            </div>
            <div class="p-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">{{ $isEdit ? 'New password' : 'Password' }}</label>
                    <input type="password" name="password" id="password" {{ $isEdit ? '' : 'required' }} autocomplete="new-password" class="{{ $inputClass }}">
                    @error('password')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">Confirm password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" {{ $isEdit ? '' : 'required' }} autocomplete="new-password" class="{{ $inputClass }}">
                </div>
            </div>
        </section>

        {{-- Role & status --}}
        <section class="rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-slate-50/80 dark:bg-slate-800/40">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4 text-slate-500'])
                    Role & access
                </h2>
            </div>
            <div class="p-5 space-y-5">
                <div>
                    <label for="role" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">Job role</label>
                    <select name="role" id="role" required class="{{ $inputClass }}">
                        @foreach($roles as $value => $label)
                            <option value="{{ $value }}" {{ old('role', $isEdit ? $user->role : '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-between gap-4 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-slate-50/50 dark:bg-slate-800/30 px-4 py-3">
                    <div>
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Account active</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Inactive users cannot sign in</p>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center {{ ($isEdit && auth()->id() === $user->id) ? 'opacity-60 cursor-not-allowed' : '' }}">
                        <input type="checkbox" name="is_active" value="1" class="peer sr-only"
                            {{ old('is_active', $isEdit ? $user->is_active : true) ? 'checked' : '' }}
                            {{ ($isEdit && auth()->id() === $user->id) ? 'disabled' : '' }}>
                        <span class="h-7 w-12 rounded-full bg-slate-300 dark:bg-slate-600 peer-checked:bg-[var(--color-studio-primary)] peer-focus:ring-2 peer-focus:ring-[var(--color-studio-primary)]/30 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-6 after:w-6 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
                    </label>
                    @if($isEdit && auth()->id() === $user->id)
                        <input type="hidden" name="is_active" value="1">
                    @endif
                </div>
                @if($isEdit && auth()->id() === $user->id)
                    <p class="text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1.5">
                        @include('components.icons', ['name' => 'exclamation-triangle', 'class' => 'w-4 h-4 shrink-0'])
                        You cannot change your own role or deactivate your account here.
                    </p>
                @endif
            </div>
        </section>

        {{-- Categories --}}
        @if(count($sourceCategories ?? []) > 0)
        <section id="category-panel" class="rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] shadow-sm overflow-hidden transition-opacity">
            <div class="px-5 py-3.5 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-slate-50/80 dark:bg-slate-800/40 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        @include('components.icons', ['name' => 'folder', 'class' => 'w-4 h-4 text-slate-500'])
                        Allowed POS categories
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">All roles except Admin</p>
                </div>
                <span id="category-selected-count" class="inline-flex items-center rounded-full bg-[var(--color-studio-primary)]/10 px-2.5 py-0.5 text-xs font-semibold tabular-nums text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)]">
                    {{ count($selectedCategoryIds) }} selected
                </span>
            </div>

            <div class="p-4 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-900/20 space-y-3">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        @include('components.icons', ['name' => 'magnifying-glass', 'class' => 'w-4 h-4'])
                    </span>
                    <input type="search" id="category-search" placeholder="Search by name or code…" autocomplete="off"
                        class="w-full pl-9 pr-3 py-2 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:border-[var(--color-studio-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-studio-primary)]/20">
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" id="category-select-all" class="text-xs font-medium px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        Select all
                    </button>
                    <button type="button" id="category-clear-all" class="text-xs font-medium px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        Clear all
                    </button>
                    <span class="text-xs text-slate-400 dark:text-slate-500 ml-auto hidden sm:inline">None selected = all categories</span>
                </div>
            </div>

            <div id="category-list" class="p-4 max-h-72 overflow-y-auto grid gap-2 sm:grid-cols-2">
                @foreach($sourceCategories as $cat)
                    @php
                        $searchText = strtolower(($cat['code'] ?? '').' '.($cat['name'] ?? ''));
                        $isChecked = in_array($cat['id'], $selectedCategoryIds, true);
                    @endphp
                    <label data-category-item data-search="{{ $searchText }}"
                        class="category-item flex items-start gap-3 rounded-lg border px-3 py-2.5 cursor-pointer transition-colors
                            {{ $isChecked
                                ? 'border-[var(--color-studio-primary)]/40 bg-[var(--color-studio-primary)]/5 ring-1 ring-[var(--color-studio-primary)]/20 dark:border-[var(--color-studio-accent)]/40 dark:bg-[var(--color-studio-accent)]/5'
                                : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800/50 hover:border-slate-300 dark:hover:border-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                        <input type="checkbox" name="category_ids[]" value="{{ $cat['id'] }}" class="{{ $checkboxClass }} category-checkbox mt-0.5" {{ $isChecked ? 'checked' : '' }}>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-slate-800 dark:text-slate-100 leading-snug">{{ $cat['name'] }}</span>
                            @if(!empty($cat['code']))
                                <span class="block text-xs font-mono text-slate-500 dark:text-slate-400 mt-0.5">{{ $cat['code'] }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
            <p id="category-no-results" class="hidden px-4 pb-4 text-sm text-center text-slate-500 dark:text-slate-400">No categories match your search.</p>
        </section>
        @endif

        {{-- Actions --}}
        <div class="flex flex-wrap items-center gap-3 pt-1 sticky bottom-20 z-30 rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white/95 dark:bg-slate-900/95 backdrop-blur px-4 py-3 shadow-lg">
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-[var(--color-studio-primary)] text-white text-sm font-medium shadow-sm hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-studio-primary)] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">
                @include('components.icons', ['name' => $submitIcon ?? 'document-check', 'class' => 'w-4 h-4'])
                {{ $submitLabel ?? ($isEdit ? 'Update user' : 'Create user') }}
            </button>
            <a href="{{ route('users.index') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
                Cancel
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const roleSelect = document.getElementById('role');
    const categoryPanel = document.getElementById('category-panel');
    const searchInput = document.getElementById('category-search');
    const listEl = document.getElementById('category-list');
    const noResults = document.getElementById('category-no-results');
    const countEl = document.getElementById('category-selected-count');
    const selectAllBtn = document.getElementById('category-select-all');
    const clearAllBtn = document.getElementById('category-clear-all');

    function visibleCategoryCheckboxes() {
        if (!listEl) return [];
        return [...listEl.querySelectorAll('[data-category-item]:not(.hidden) .category-checkbox')];
    }

    function allCategoryCheckboxes() {
        if (!listEl) return [];
        return [...listEl.querySelectorAll('.category-checkbox')];
    }

    function updateSelectedCount() {
        if (!countEl) return;
        const n = allCategoryCheckboxes().filter(c => c.checked).length;
        countEl.textContent = n + ' selected';
    }

    function updateCategoryItemStyles() {
        listEl?.querySelectorAll('[data-category-item]').forEach(label => {
            const cb = label.querySelector('.category-checkbox');
            const on = cb && cb.checked;
            label.classList.toggle('border-[var(--color-studio-primary)]/40', on);
            label.classList.toggle('bg-[var(--color-studio-primary)]/5', on);
            label.classList.toggle('ring-1', on);
            label.classList.toggle('ring-[var(--color-studio-primary)]/20', on);
            label.classList.toggle('border-slate-200', !on);
            label.classList.toggle('dark:border-slate-600', !on);
            label.classList.toggle('bg-white', !on);
            label.classList.toggle('dark:bg-slate-800/50', !on);
        });
    }

    function toggleCategoryPanel() {
        if (!categoryPanel || !roleSelect) return;
        const show = roleSelect.value !== 'admin';
        categoryPanel.classList.toggle('hidden', !show);
        categoryPanel.classList.toggle('opacity-50', !show);
        categoryPanel.setAttribute('aria-hidden', show ? 'false' : 'true');
        allCategoryCheckboxes().forEach(c => { c.disabled = !show; });
    }

    function filterCategories() {
        if (!searchInput || !listEl) return;
        const q = searchInput.value.trim().toLowerCase();
        let visible = 0;
        listEl.querySelectorAll('[data-category-item]').forEach(item => {
            const text = item.getAttribute('data-search') || '';
            const match = !q || text.includes(q);
            item.classList.toggle('hidden', !match);
            if (match) visible++;
        });
        if (noResults) {
            noResults.classList.toggle('hidden', visible > 0 || !q);
        }
    }

    roleSelect?.addEventListener('change', toggleCategoryPanel);
    searchInput?.addEventListener('input', filterCategories);

    selectAllBtn?.addEventListener('click', () => {
        visibleCategoryCheckboxes().forEach(c => { c.checked = true; });
        updateSelectedCount();
        updateCategoryItemStyles();
    });

    clearAllBtn?.addEventListener('click', () => {
        allCategoryCheckboxes().forEach(c => { c.checked = false; });
        updateSelectedCount();
        updateCategoryItemStyles();
    });

    listEl?.addEventListener('change', e => {
        if (e.target.classList.contains('category-checkbox')) {
            updateSelectedCount();
            updateCategoryItemStyles();
        }
    });

    toggleCategoryPanel();
    updateSelectedCount();
    updateCategoryItemStyles();
})();
</script>
@endpush
