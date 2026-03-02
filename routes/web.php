<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BlockedCategoriesController;
use App\Http\Controllers\BlockedProductsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SourceCategoriesController;
use App\Http\Controllers\SourceProductsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('users/{user}/report', [UserController::class, 'report'])->name('users.report');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('jobs/live', [JobController::class, 'live'])->name('jobs.live');
    Route::post('jobs/sync', [JobController::class, 'syncFromSource'])->name('jobs.sync');
    Route::post('jobs/from-source/{sale}', [JobController::class, 'createFromSource'])->name('jobs.from-source');
    Route::get('jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
    Route::post('jobs/{job}/take', [JobController::class, 'take'])->name('jobs.take');
    Route::post('jobs/{job}/status', [JobController::class, 'updateStatus'])->name('jobs.status');
    Route::post('jobs/{job}/edits/{edit}/claim', [JobController::class, 'claimEdit'])->name('jobs.edits.claim');
    Route::post('jobs/{job}/edits/{edit}/customer-confirm', [JobController::class, 'confirmCustomer'])->name('jobs.edits.customer-confirm');
    Route::post('jobs/{job}/edits/{edit}/customer-unconfirm', [JobController::class, 'unconfirmCustomer'])->name('jobs.edits.customer-unconfirm');
    Route::post('jobs/{job}/edits/{edit}/edit-status', [JobController::class, 'updateEditStatus'])->name('jobs.edits.edit-status');
    Route::post('jobs/{job}/edits/{edit}/print-status', [JobController::class, 'updatePrintStatus'])->name('jobs.edits.print-status');
    Route::post('jobs/{job}/deliver', [JobController::class, 'deliver'])->name('jobs.deliver');
    Route::post('jobs/{job}/editors', [JobController::class, 'addEditor'])->name('jobs.editors.add');
    Route::delete('jobs/{job}/editors/{editor}', [JobController::class, 'removeEditor'])->name('jobs.editors.remove');
    Route::post('jobs/{job}/dismiss', [JobController::class, 'dismiss'])->name('jobs.dismiss');
    Route::post('jobs/{job}/undismiss', [JobController::class, 'undismiss'])->name('jobs.undismiss');

    Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index')->middleware('role:admin');
    Route::get('activity-log/{activity_log}', [ActivityLogController::class, 'show'])->name('activity-log.show')->middleware('role:admin');

    Route::middleware('role:admin')->group(function () {
        // Settings: Catalog & Visibility
        Route::get('source-products', [SourceProductsController::class, 'index'])->name('source-products.index');
        Route::get('source-categories', [SourceCategoriesController::class, 'index'])->name('source-categories.index');
        Route::get('settings/block-categories', [BlockedCategoriesController::class, 'index'])->name('settings.block-categories');
        Route::post('settings/block-categories', [BlockedCategoriesController::class, 'store'])->name('settings.block-categories.store');
        Route::get('settings/block-products', [BlockedProductsController::class, 'index'])->name('settings.block-products');
        Route::post('settings/block-products', [BlockedProductsController::class, 'store'])->name('settings.block-products.store');

        // Users management
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
