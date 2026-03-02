<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $user->load('editorCategories');

        return view('profile.show', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $valid = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'name' => $valid['name'],
            'email' => $valid['email'],
        ]);

        if (! empty($valid['password'])) {
            $user->update(['password' => Hash::make($valid['password'])]);
        }

        ActivityLog::log('profile_updated', 'Updated their profile');

        return redirect()->route('profile.show')->with('success', 'Profile updated.');
    }
}
