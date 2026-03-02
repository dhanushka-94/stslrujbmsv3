@extends('layouts.app')

@section('title', 'Job ' . $job->ref_number)

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-7 h-7'])
            Job {{ $job->ref_number }}
        </h1>
        <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm hover:bg-slate-100 dark:hover:bg-slate-700">
            @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
            Back to jobs
        </a>
    </div>

    <div class="grid md:grid-cols-2 gap-6 mb-8">
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4">
            <h2 class="font-medium text-slate-700 dark:text-slate-300 mb-2">Details</h2>
            <dl class="space-y-1 text-sm">
                <div><span class="text-slate-500">Customer:</span> {{ $job->customer_name ?? '—' }}</div>
                <div><span class="text-slate-500">Status:</span> <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700">{{ $job->status }}</span></div>
                <div><span class="text-slate-500">Due date:</span> {{ $job->due_date ? $job->due_date->format('M d, Y') : '—' }}</div>
                <div>
                    <span class="text-slate-500">Editors:</span>
                    @if($job->editors->isNotEmpty())
                        @foreach($job->editors as $ed)
                            <span class="inline-flex items-center gap-1 mr-2 mt-1">
                                <span>{{ $ed->name }}</span>
                                @if(auth()->user()->canAddOrRemoveEditorsOn($job))
                                    <form action="{{ route('jobs.editors.remove', [$job, $ed]) }}" method="POST" class="inline" onsubmit="return confirm('Remove this editor?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-700 text-xs">✕</button>
                                    </form>
                                @endif
                            </span>
                        @endforeach
                    @else
                        —
                    @endif
                </div>
                @if(auth()->user()->canAddOrRemoveEditorsOn($job) && $editorsAvailable->diff($job->editors)->isNotEmpty())
                    <form action="{{ route('jobs.editors.add', $job) }}" method="POST" class="flex gap-2 mt-2">
                        @csrf
                        <select name="user_id" class="flex-1 px-2 py-1 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 text-sm">
                            @foreach($editorsAvailable->diff($job->editors) as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-3 py-1.5 rounded bg-[var(--color-studio-primary)] text-white text-sm">Add editor</button>
                    </form>
                @endif
                @if($job->delivered_at)
                    <div><span class="text-slate-500">Delivered:</span> {{ $job->delivered_at->format('M d, Y H:i') }} ({{ $job->delivery_method }}) by {{ $job->deliveredByUser?->name ?? '—' }}</div>
                @endif
                @if($job->notes)
                    <div><span class="text-slate-500">Notes:</span> {{ $job->notes }}</div>
                @endif
            </dl>
        </div>
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4">
            @if(auth()->user()->canTakeJob() && $job->status === 'new')
                <form action="{{ route('jobs.take', $job) }}" method="POST" class="mb-4">
                    @csrf
                    <button type="submit" class="w-full py-2 rounded bg-green-600 hover:bg-green-700 text-white font-medium">Take this job</button>
                </form>
            @endif
            @if($job->status === 'new' && (auth()->user()->isEditor() || auth()->user()->canManageJobs()))
                @if($job->isDismissedBy(auth()->user()))
                    <form action="{{ route('jobs.undismiss', $job) }}" method="POST" class="mb-4">
                        @csrf
                        <button type="submit" class="w-full py-2 rounded bg-green-600 hover:bg-green-700 text-white font-medium">Restore to job list</button>
                    </form>
                @else
                    <form action="{{ route('jobs.dismiss', $job) }}" method="POST" class="mb-4" onsubmit="return confirm('Dismiss this job? It will move to your Dismissed list and will not show on the main job list until you restore it.');">
                        @csrf
                        <button type="submit" class="w-full py-2 rounded border border-amber-500 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 font-medium">Dismiss job</button>
                    </form>
                @endif
            @endif
            @if(auth()->user()->canManageJobs() && !$job->isCompleted())
                <div class="mb-4">
                    <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Job status</div>
                    <div class="flex flex-wrap gap-2">
                        @php
                            $statusButtons = [
                                'assigned' => 'Assigned',
                                'in_progress' => 'In progress',
                                'completed' => 'Completed',
                            ];
                        @endphp
                        @foreach($statusButtons as $value => $label)
                            <form action="{{ route('jobs.status', $job) }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="status" value="{{ $value }}">
                                <button type="submit"
                                        class="px-3 py-1.5 rounded-full text-xs font-medium border transition-colors
                                            @if($job->status === $value)
                                                bg-[var(--color-studio-primary)] text-white border-[var(--color-studio-primary)] shadow-sm
                                            @else
                                                border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800
                                            @endif">
                                    {{ $label }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
            @if(auth()->user()->canDeliver() && $job->status === 'completed')
                <form action="{{ route('jobs.deliver', $job) }}" method="POST" class="flex gap-2">
                    @csrf
                    <select name="delivery_method" required class="flex-1 px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800">
                        <option value="online">Online</option>
                        <option value="walkin">Walk-in</option>
                        <option value="courier">Courier</option>
                    </select>
                    <button type="submit" class="px-4 py-2 rounded bg-green-600 text-white text-sm">Mark delivered</button>
                </form>
            @endif
        </div>
    </div>

    @php
        $globalBlockedCategoryIds = \App\Models\BlockedCategory::blockedCategoryIds();
        $globalBlockedProductIds = \App\Models\BlockedProduct::blockedProductIds();
        $visibleEdits = $job->edits->filter(function ($e) use ($globalBlockedCategoryIds, $globalBlockedProductIds) {
            $catId = $e->source_category_id ? (int) $e->source_category_id : null;
            $productId = $e->source_product_id ? (int) $e->source_product_id : null;
            $categoryBlocked = $catId !== null && in_array($catId, $globalBlockedCategoryIds, true);
            $productBlocked = $productId !== null && in_array($productId, $globalBlockedProductIds, true);
            return ! $categoryBlocked && ! $productBlocked;
        });
        $blockedEdits = $job->edits->filter(function ($e) use ($globalBlockedCategoryIds, $globalBlockedProductIds) {
            $catId = $e->source_category_id ? (int) $e->source_category_id : null;
            $productId = $e->source_product_id ? (int) $e->source_product_id : null;
            $categoryBlocked = $catId !== null && in_array($catId, $globalBlockedCategoryIds, true);
            $productBlocked = $productId !== null && in_array($productId, $globalBlockedProductIds, true);
            return $categoryBlocked || $productBlocked;
        });
    @endphp
    <h2 class="text-lg font-medium mb-3">Items ({{ $visibleEdits->count() }} shown{{ $blockedEdits->count() > 0 ? ', ' . $blockedEdits->count() . ' hidden (blocked)' : '' }}) – from POS with category/subcategory</h2>
    <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th class="text-left p-3">Item (name)</th>
                    <th class="text-left p-3">Category</th>
                    <th class="text-left p-3">Subcategory</th>
                    <th class="text-left p-3">Editing by</th>
                    <th class="text-left p-3">Edit status</th>
                    <th class="text-left p-3">Customer confirm</th>
                    <th class="text-left p-3">Print status</th>
                    <th class="text-left p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($visibleEdits as $edit)
                    @php
                        $canEdit = auth()->user()->canEditJobItem($edit);
                        $isAdmin = auth()->user()->isAdmin();
                        $isClaimedByMe = $edit->claimed_by_user_id == auth()->id();
                        $isClaimedByOther = $edit->claimed_by_user_id !== null && !$isClaimedByMe;
                        $canStartEditing = $canEdit && $edit->claimed_by_user_id === null && !$isAdmin;
                        $canChangeStatus = $canEdit && ($isClaimedByMe || $isClaimedByOther || $isAdmin);
                    @endphp
                    <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)]">
                        <td class="p-3 font-medium">{{ $edit->name }}</td>
                        <td class="p-3 text-slate-600 dark:text-slate-400">{{ $edit->category_name ?? '—' }}</td>
                        <td class="p-3 text-slate-600 dark:text-slate-400">{{ $edit->subcategory_name ?? '—' }}</td>
                        <td class="p-3 text-sm">
                            @if($edit->claimedByUser)
                                <span class="text-slate-700 dark:text-slate-300">{{ $edit->claimedByUser->name }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-slate-100 dark:bg-slate-700">{{ $edit->edit_status }}</span></td>
                        <td class="p-3">
                            @if($edit->isCustomerConfirmed())
                                <span class="text-xs text-green-700 dark:text-green-400" title="{{ $edit->customer_confirmed_at->format('M d, Y H:i') }}">Confirmed</span>
                                @if($isAdmin)
                                    <form action="{{ route('jobs.edits.customer-unconfirm', [$job, $edit]) }}" method="POST" class="inline ml-1" onsubmit="return confirm('Revert customer confirm? Item will need to be confirmed again before print.');">
                                        @csrf
                                        <button type="submit" class="text-xs px-2 py-1 rounded bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800/40">Unconfirm</button>
                                    </form>
                                @endif
                            @elseif($canEdit && ($isClaimedByMe || $isClaimedByOther || $isAdmin))
                                <form action="{{ route('jobs.edits.customer-confirm', [$job, $edit]) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs px-2 py-1 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 hover:bg-amber-200 dark:hover:bg-amber-800/40">Mark customer confirm</button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-xs
                            @if($edit->print_status === 'printed') bg-green-100 dark:bg-green-900/30
                            @elseif($edit->print_status === 'sent_to_print') bg-blue-100 dark:bg-blue-900/30
                            @elseif($edit->print_status === 'pending') bg-amber-100 dark:bg-amber-900/30
                            @else bg-slate-100 dark:bg-slate-700
                            @endif">{{ $edit->print_status }}</span>
                        </td>
                        <td class="p-3">
                            @if($canStartEditing)
                                <form action="{{ route('jobs.edits.claim', [$job, $edit]) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 text-xs px-2 py-1.5 rounded bg-green-600 text-white hover:bg-green-700">Start editing</button>
                                </form>
                            @elseif($canChangeStatus)
                                <form action="{{ route('jobs.edits.edit-status', [$job, $edit]) }}" method="POST" class="inline">
                                    @csrf
                                    <select name="edit_status" onchange="this.form.submit()" class="text-xs px-2 py-1.5 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800">
                                        <option value="pending" {{ $edit->edit_status === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="in_progress" {{ $edit->edit_status === 'in_progress' ? 'selected' : '' }}>In progress</option>
                                        <option value="completed" {{ $edit->edit_status === 'completed' ? 'selected' : '' }}>Completed</option>
                                    </select>
                                </form>
                                @if(auth()->user()->canUpdatePrintStatus())
                                    @if($edit->isCustomerConfirmed() || $isAdmin)
                                        <form action="{{ route('jobs.edits.print-status', [$job, $edit]) }}" method="POST" class="inline ml-2">
                                            @csrf
                                            <select name="print_status" onchange="this.form.submit()" class="text-xs px-2 py-1.5 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800">
                                                <option value="not_required" {{ $edit->print_status === 'not_required' ? 'selected' : '' }}>Not required</option>
                                                <option value="pending" {{ $edit->print_status === 'pending' ? 'selected' : '' }}>Pending</option>
                                                <option value="sent_to_print" {{ $edit->print_status === 'sent_to_print' ? 'selected' : '' }}>Sent to print</option>
                                                <option value="printed" {{ $edit->print_status === 'printed' ? 'selected' : '' }}>Printed</option>
                                            </select>
                                        </form>
                                        @if(!$edit->isCustomerConfirmed() && $isAdmin)
                                            <span class="ml-1 text-xs text-amber-600 dark:text-amber-400" title="Admin override">(override)</span>
                                        @endif
                                    @else
                                        <span class="ml-2 text-xs text-slate-400" title="Mark customer confirm first">Print after confirm</span>
                                    @endif
                                @endif
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-6 text-center text-slate-500">No items to show. Unblock categories in the Details panel to see more.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($blockedEdits->count() > 0)
        <details class="mt-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-600 rounded-lg overflow-hidden">
            <summary class="p-3 cursor-pointer text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
                Show blocked items ({{ $blockedEdits->count() }}) – categories or products you chose to hide
            </summary>
            <div class="border-t border-slate-200 dark:border-slate-600">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-800">
                        <tr>
                            <th class="text-left p-3">Item (name)</th>
                            <th class="text-left p-3">Category</th>
                            <th class="text-left p-3">Subcategory</th>
                            <th class="text-left p-3">Edit status</th>
                            <th class="text-left p-3">Print status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($blockedEdits as $edit)
                            <tr class="border-t border-slate-200 dark:border-slate-600">
                                <td class="p-3 font-medium text-slate-500">{{ $edit->name }}</td>
                                <td class="p-3 text-slate-400">{{ $edit->category_name ?? '—' }}</td>
                                <td class="p-3 text-slate-400">{{ $edit->subcategory_name ?? '—' }}</td>
                                <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-slate-200 dark:bg-slate-700">{{ $edit->edit_status }}</span></td>
                                <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-slate-200 dark:bg-slate-700">{{ $edit->print_status }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    {{-- Activity log for this job + included items --}}
    <div class="mt-10 grid lg:grid-cols-2 gap-6">
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden">
            <h2 class="text-lg font-medium p-4 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] flex items-center gap-2">
                @include('components.icons', ['name' => 'document-check', 'class' => 'w-5 h-5'])
                Activity log for this job
            </h2>
            <div class="max-h-80 overflow-y-auto">
                @forelse($jobActivityLog ?? [] as $log)
                    <div class="p-4 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] last:border-0">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $log->created_at->format('M d, Y H:i:s') }}</span>
                                <span class="ml-2 px-1.5 py-0.5 rounded text-xs font-mono bg-slate-100 dark:bg-slate-700">{{ $log->action }}</span>
                                @if($log->user)
                                    <span class="text-slate-600 dark:text-slate-300 text-sm"> – {{ $log->user->name }}</span>
                                @endif
                                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $log->description ?? '—' }}</p>
                            </div>
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('activity-log.show', $log) }}" class="shrink-0 text-xs px-2 py-1 rounded border border-[var(--color-studio-border)] text-[var(--color-studio-primary)] hover:bg-slate-50 dark:hover:bg-slate-800">View</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="p-4 text-sm text-slate-500">No activity recorded for this job yet.</p>
                @endforelse
            </div>
        </div>
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden">
            <h2 class="text-lg font-medium p-4 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] flex items-center gap-2">
                @include('components.icons', ['name' => 'squares-2x2', 'class' => 'w-5 h-5'])
                Job items ({{ $visibleEdits->count() }})
            </h2>
            <div class="max-h-80 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 sticky top-0">
                        <tr>
                            <th class="text-left p-2">Item</th>
                            <th class="text-left p-2">Category</th>
                            <th class="text-left p-2">Edit</th>
                            <th class="text-left p-2">Print</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($visibleEdits as $edit)
                            <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)]">
                                <td class="p-2 font-medium">{{ $edit->name }}</td>
                                <td class="p-2 text-slate-600 dark:text-slate-400">{{ $edit->category_name ?? '—' }}</td>
                                <td class="p-2"><span class="px-1.5 py-0.5 rounded text-xs bg-slate-100 dark:bg-slate-700">{{ $edit->edit_status }}</span></td>
                                <td class="p-2"><span class="px-1.5 py-0.5 rounded text-xs
                                    @if($edit->print_status === 'printed') bg-green-100 dark:bg-green-900/30
                                    @elseif($edit->print_status === 'sent_to_print') bg-blue-100 dark:bg-blue-900/30
                                    @else bg-slate-100 dark:bg-slate-700
                                    @endif">{{ $edit->print_status }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($visibleEdits->isEmpty())
                    <p class="p-4 text-sm text-slate-500">No items to show.</p>
                @endif
            </div>
        </div>
    </div>
@endsection
