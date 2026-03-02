<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Reports hub: links to My report, Activity log (admin), and User reports list (admin).
     */
    public function index(): View
    {
        $users = auth()->user()->isAdmin()
            ? User::orderBy('name')->get(['id', 'name', 'email', 'role'])
            : collect();

        return view('reports.index', compact('users'));
    }
}
