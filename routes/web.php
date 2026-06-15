<?php

declare(strict_types=1);

use App\Modules\AuditLog\Http\Controllers\ExportAuditLogsCsvController;
use App\Modules\Businesses\Http\Controllers\AcceptInviteController;
use App\Modules\Documents\Http\Controllers\DeleteDocumentController;
use App\Modules\Documents\Http\Controllers\DownloadDocumentController;
use App\Modules\Documents\Http\Controllers\ViewDocumentController;
use App\Modules\Sample\Http\Controllers\HelloWorldController;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use Livewire\Volt\Volt;

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

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
    'business_member',
])->prefix('customers')->name('customers.')->group(function (): void {
    Route::livewire('/', 'pages::customers.index')->name('index');
    Route::livewire('/{ulid}', 'pages::customers.show')->name('show');
});

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
    'business_member',
])->group(function (): void {
    Route::get('/documents/{document}/view', ViewDocumentController::class)
        ->name('documents.view');
    Route::get('/documents/{document}/download', DownloadDocumentController::class)
        ->name('documents.download');
    Route::delete('/documents/{document}', DeleteDocumentController::class)
        ->name('documents.destroy');
});

Volt::route('/hello', 'sample.hello')->name('sample.hello');

Route::get('/api/hello-world', HelloWorldController::class)->name('sample.hello-world');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/public-upload.php';
