<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\EditorCategory;
use App\Models\Job;
use App\Models\JobEdit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('name')->get();
        return view('users.index', compact('users'));
    }

    public function show(User $user): View
    {
        $user->load('editorCategories');
        return view('users.show', compact('user'));
    }

    /**
     * User report page: activity summary, jobs involved, recent activity log.
     * Accessible by the user themselves or by Admin.
     */
    public function report(User $user): View
    {
        if (auth()->id() !== $user->id && ! auth()->user()->isAdmin()) {
            abort(403);
        }
        $user->load('editorCategories');

        $recentActivity = ActivityLog::where('user_id', $user->id)->orderByDesc('created_at')->take(30)->get();

        $jobsAsEditor = Job::where('assigned_editor_id', $user->id)
            ->orWhereHas('editors', fn ($q) => $q->where('user_id', $user->id))
            ->withCount('edits')
            ->orderByDesc('updated_at')
            ->take(20)
            ->get();

        $stats = [
            'jobs_involved' => Job::where('assigned_editor_id', $user->id)
                ->orWhereHas('editors', fn ($q) => $q->where('user_id', $user->id))
                ->count(),
            'jobs_completed' => Job::where(function ($q) use ($user) {
                $q->where('assigned_editor_id', $user->id)->orWhereHas('editors', fn ($q) => $q->where('user_id', $user->id));
            })->whereIn('status', [Job::STATUS_COMPLETED, Job::STATUS_DELIVERED])->count(),
            'jobs_delivered_by' => $user->id ? Job::where('delivered_by', $user->id)->count() : 0,
            'activity_count' => ActivityLog::where('user_id', $user->id)->count(),
            'estimated_total_minutes' => JobEdit::where('claimed_by_user_id', $user->id)->whereNotNull('estimated_minutes')->sum('estimated_minutes'),
            'estimated_item_count' => JobEdit::where('claimed_by_user_id', $user->id)->whereNotNull('estimated_minutes')->count(),
        ];

        $estimatedTimeItems = JobEdit::where('claimed_by_user_id', $user->id)
            ->whereNotNull('estimated_minutes')
            ->with('job:id,ref_number')
            ->orderByDesc('estimated_minutes_at')
            ->take(50)
            ->get();

        return view('users.report', compact('user', 'recentActivity', 'jobsAsEditor', 'stats', 'estimatedTimeItems'));
    }

    public function create(): View
    {
        $sourceCategories = $this->getSourceCategoriesForDropdown();
        return view('users.create', compact('sourceCategories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $valid = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(array_keys(User::ROLES_FOR_CREATE))],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $valid['password'] = Hash::make($valid['password']);
        $valid['is_active'] = $request->boolean('is_active', true);
        $user = User::create($valid);
        if (in_array($user->role, User::rolesWithCategoryAssignments(), true)) {
            $ids = $request->input('category_ids', []);
            $ids = is_array($ids) ? array_filter(array_map('intval', $ids)) : [];
            foreach ($ids as $sourceCategoryId) {
                EditorCategory::create(['user_id' => $user->id, 'source_category_id' => $sourceCategoryId]);
            }
        }
        ActivityLog::log('user_created', 'Created user: ' . $user->name . ' (' . $user->email . ')', 'user', $user->id);
        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        $sourceCategories = $this->getSourceCategoriesForDropdown();
        $user->load('editorCategories');
        return view('users.edit', compact('user', 'sourceCategories'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $valid = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => ['required', Rule::in(array_merge([User::ROLE_ADMIN], array_keys(User::ROLES_FOR_CREATE)))],
            'is_active' => ['nullable', 'boolean'],
        ]);
        if (auth()->id() === $user->id && $user->role !== $valid['role']) {
            return redirect()->back()->with('error', 'You cannot change your own role.');
        }
        if (auth()->id() === $user->id && ! $request->boolean('is_active')) {
            return redirect()->back()->with('error', 'You cannot deactivate your own account.');
        }
        if ($user->isAdmin() && $valid['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return redirect()->back()->with('error', 'You cannot remove the last Admin. Promote another user to Admin first.');
        }
        $valid['is_active'] = $request->boolean('is_active', true);
        $user->update($valid);
        if ($request->filled('password')) {
            $request->validate(['password' => ['confirmed', Password::defaults()]]);
            $user->update(['password' => Hash::make($request->password)]);
        }
        if (in_array($user->role, User::rolesWithCategoryAssignments(), true)) {
            $ids = $request->input('category_ids', []);
            $ids = is_array($ids) ? array_filter(array_map('intval', $ids)) : [];
            EditorCategory::where('user_id', $user->id)->delete();
            foreach ($ids as $sourceCategoryId) {
                EditorCategory::create(['user_id' => $user->id, 'source_category_id' => $sourceCategoryId]);
            }
        } else {
            EditorCategory::where('user_id', $user->id)->delete();
        }
        $desc = 'Updated user: ' . $user->name;
        if (! $valid['is_active']) {
            $desc .= ' (deactivated)';
        } elseif ($user->getOriginal('is_active') == 0) {
            $desc .= ' (activated)';
        }
        ActivityLog::log('user_updated', $desc, 'user', $user->id);
        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account.');
        }
        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return redirect()->route('users.index')->with('error', 'You cannot delete the last Admin.');
        }
        $name = $user->name;
        $email = $user->email;
        $user->delete();
        ActivityLog::log('user_deleted', 'Deleted user: ' . $name . ' (' . $email . ')');
        return redirect()->route('users.index')->with('success', 'User deleted.');
    }

    /** Top-level categories from source DB for editor category assignment dropdown. */
    private function getSourceCategoriesForDropdown(): array
    {
        $conn = 'source';
        if (empty(config("database.connections.{$conn}.database"))) {
            return [];
        }
        try {
            return DB::connection($conn)
                ->table('sma_categories')
                ->where(function ($q) {
                    $q->where('parent_id', 0)->orWhereNull('parent_id');
                })
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'code' => $r->code, 'name' => $r->name])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
