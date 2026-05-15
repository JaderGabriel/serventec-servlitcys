<?php

use App\Http\Controllers\Admin\ArtisanCommandsController;
use App\Http\Controllers\Admin\GeoSyncController;
use App\Http\Controllers\Admin\IeducarCompatibilityController;
use App\Http\Controllers\Admin\PedagogicalSyncController;
use App\Http\Controllers\DiscrepanciesExportController;
use App\Http\Controllers\AnalyticsDashboardController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FirstAccessProfileController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserLoginHistoryController;
use App\Http\Controllers\UserSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile/first-access', [FirstAccessProfileController::class, 'edit'])->name('profile.first-access');
    Route::post('/profile/first-access', [FirstAccessProfileController::class, 'update'])->name('profile.first-access.update');
});

Route::middleware(['auth', 'verified', 'profile.complete'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/analytics', [AnalyticsDashboardController::class, 'index'])->name('dashboard.analytics');
    Route::get('/dashboard/analytics/tab', [AnalyticsDashboardController::class, 'tabPartial'])->name('dashboard.analytics.tab');
    Route::get('/dashboard/analytics/filter-options', [AnalyticsDashboardController::class, 'filterOptions'])->name('dashboard.analytics.filter-options');
    Route::get('/dashboard/analytics/discrepancies/export', [DiscrepanciesExportController::class, 'csv'])->name('dashboard.analytics.discrepancies.export');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('manage.users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/terminate-sessions', [UserController::class, 'terminateSessions'])->name('users.terminate-sessions');
    });
});

Route::middleware(['auth', 'verified', 'profile.complete', 'admin'])->group(function () {
    Route::get('/users/sessoes', [UserSessionController::class, 'index'])->name('users.sessions.index');
    Route::delete('/users/sessoes/{session}', [UserSessionController::class, 'destroy'])->name('users.sessions.destroy');
    Route::get('/users/{user}/logins', [UserLoginHistoryController::class, 'index'])->name('users.logins');

    Route::get('cities/{city}/db-status', [CityController::class, 'dbStatus'])->name('cities.db-status');
    Route::resource('cities', CityController::class)->except(['show']);

    Route::get('/settings/mail', [MailSettingsController::class, 'edit'])->name('settings.mail.edit');
    Route::put('/settings/mail', [MailSettingsController::class, 'update'])->name('settings.mail.update');

    Route::get('/admin/geo-sync', [GeoSyncController::class, 'index'])->name('admin.geo-sync.index');
    Route::post('/admin/geo-sync', [GeoSyncController::class, 'run'])->name('admin.geo-sync.run');

    Route::get('/admin/pedagogical-sync', [PedagogicalSyncController::class, 'index'])->name('admin.pedagogical-sync.index');
    Route::post('/admin/pedagogical-sync', [PedagogicalSyncController::class, 'run'])->name('admin.pedagogical-sync.run');

    Route::get('/admin/ieducar-compatibility', [IeducarCompatibilityController::class, 'index'])->name('admin.ieducar-compatibility.index');
    Route::get('/admin/ieducar-compatibility/export', [IeducarCompatibilityController::class, 'export'])->name('admin.ieducar-compatibility.export');
    Route::post('/admin/ieducar-compatibility/fundeb-import', [IeducarCompatibilityController::class, 'importFundeb'])->name('admin.ieducar-compatibility.fundeb-import');

    Route::get('/admin/artisan-commands', [ArtisanCommandsController::class, 'index'])->name('admin.artisan-commands.index');
});

require __DIR__.'/auth.php';
