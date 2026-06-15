<?php

declare(strict_types=1);

use App\Modules\Businesses\Http\Controllers\AddLocationController;
use App\Modules\Businesses\Http\Controllers\OnboardBusinessController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function (): void {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Super-admin onboarding — endpoint only, no UI (I3).
Route::middleware(['auth', 'super_admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::post('/businesses', OnboardBusinessController::class)->name('businesses.onboard');
    Route::post('/businesses/{business}/locations', AddLocationController::class)->name('businesses.locations.add');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
