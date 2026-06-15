<?php

declare(strict_types=1);

use App\Modules\AuditLog\Http\Controllers\ExportAuditLogsCsvController;
use App\Modules\Businesses\Http\Controllers\AcceptInviteController;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::view('/', 'welcome')->name('home');

Route::get('/invites/{token}', AcceptInviteController::class)->name('invites.accept');

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function (): void {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
    'super_admin',
])->prefix('admin')->name('admin.')->group(function (): void {
    Route::livewire('/businesses', 'pages::admin.businesses.index')->name('businesses.index');
});

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
    'owner',
])->group(function (): void {
    Route::livewire('/members', 'pages::members.index')->name('members.index');
    Route::livewire('/audit-logs', 'pages::audit-logs.index')->name('audit-logs.index');
    Route::get('/audit-logs/export', ExportAuditLogsCsvController::class)->name('audit-logs.export');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
