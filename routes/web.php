<?php

use App\Http\Controllers\MemberController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// The Livewire/Volt auth stack has no plain logout route of its own (Breeze's
// Volt login/register pages handle auth via component actions) — this is a
// simple, non-reactive action with nothing to gain from being a Livewire
// component, so a plain route is the more direct fit.
Route::post('logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'verified'])->group(function () {
    // Shared: both Admin and Receptionist manage members, renewals, and
    // record payments (§3.1/§3.2 — both roles can create/edit/renew members
    // and record payments; only archiving/restoring/viewing the Archived
    // filter is Admin-only, gated per-action via MemberPolicy).
    Route::resource('members', MemberController::class)->except(['edit']);
    Route::post('members/{member}/restore', [MemberController::class, 'restore'])->name('members.restore');
    Route::post('members/{member}/renew', [SubscriptionController::class, 'renew'])->name('members.renew');
    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');

    // Admin-only: plan management, financial reports, user management, and
    // settings are all restricted at the routing layer (§3.2 Restricted
    // Actions), not just hidden in the UI.
    Route::middleware('role:admin')->group(function () {
        Route::resource('plans', PlanController::class)->except(['create', 'edit', 'show']);
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);
        Route::get('settings', [SettingController::class, 'show'])->name('settings.show');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });
});

require __DIR__.'/auth.php';
