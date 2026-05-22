<?php

use App\Http\Controllers\Admin\AdminConnectionsController;
use App\Http\Controllers\Admin\AdminSyncQueueController;
use App\Http\Controllers\Admin\AnalyticsDiagnosticsController;
use App\Http\Controllers\Admin\ArtisanCommandsController;
use App\Http\Controllers\Admin\DocumentationController;
use App\Http\Controllers\Admin\GeoSyncController;
use App\Http\Controllers\Admin\IeducarCompatibilityController;
use App\Http\Controllers\Admin\PedagogicalSyncController;
use App\Http\Controllers\AnalyticsDashboardController;
use App\Http\Controllers\AnalyticsReportExportController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardMunicipalityMapController;
use App\Http\Controllers\DiscrepanciesExportController;
use App\Http\Controllers\FirstAccessProfileController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\NotificationController;
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
    Route::get('/dashboard/municipality-map/{city}/school-years', [DashboardMunicipalityMapController::class, 'schoolYears'])
        ->name('dashboard.municipality-map.school-years');
    Route::get('/dashboard/analytics', [AnalyticsDashboardController::class, 'index'])->name('dashboard.analytics');
    Route::get('/dashboard/analytics/tab', [AnalyticsDashboardController::class, 'tabPartial'])->name('dashboard.analytics.tab');
    Route::get('/dashboard/analytics/filter-options', [AnalyticsDashboardController::class, 'filterOptions'])->name('dashboard.analytics.filter-options');
    Route::get('/dashboard/analytics/filter-options-bootstrap', [AnalyticsDashboardController::class, 'filterOptionsBootstrap'])->name('dashboard.analytics.filter-options-bootstrap');
    Route::get('/dashboard/analytics/discrepancies/export', [DiscrepanciesExportController::class, 'csv'])->name('dashboard.analytics.discrepancies.export');
    Route::post('/dashboard/analytics/pdf-export', [AnalyticsReportExportController::class, 'store'])->name('dashboard.analytics.pdf.store');
    Route::get('/dashboard/analytics/pdf-export/{export}/status', [AnalyticsReportExportController::class, 'status'])->name('dashboard.analytics.pdf.status');
    Route::get('/dashboard/analytics/pdf-export/{export}/download', [AnalyticsReportExportController::class, 'download'])->name('dashboard.analytics.pdf.download');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('manage.users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.update-status');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
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
    Route::get('/admin/ieducar-compatibility/fundeb-matrix-export', [IeducarCompatibilityController::class, 'exportFundebMatrix'])->name('admin.ieducar-compatibility.fundeb-matrix-export');
    Route::post('/admin/ieducar-compatibility/fundeb-import', [IeducarCompatibilityController::class, 'importFundeb'])->name('admin.ieducar-compatibility.fundeb-import');
    Route::post('/admin/ieducar-compatibility/fundeb-import-bulk', [IeducarCompatibilityController::class, 'importFundebBulk'])->name('admin.ieducar-compatibility.fundeb-import-bulk');
    Route::post('/admin/ieducar-compatibility/fundeb-sync-all', [IeducarCompatibilityController::class, 'syncFundebAll'])->name('admin.ieducar-compatibility.fundeb-sync-all');

    Route::get('/admin/artisan-commands', [ArtisanCommandsController::class, 'index'])->name('admin.artisan-commands.index');

    Route::get('/admin/sync-queue', [AdminSyncQueueController::class, 'index'])->name('admin.sync-queue.index');
    Route::get('/admin/sync-queue/{task}', [AdminSyncQueueController::class, 'show'])->name('admin.sync-queue.show');
    Route::get('/admin/sync-queue/{task}/download', [AdminSyncQueueController::class, 'download'])->name('admin.sync-queue.download');

    Route::get('/admin/conexoes', [AdminConnectionsController::class, 'index'])
        ->name('admin.connections.index');

    Route::get('/admin/documentacao', [DocumentationController::class, 'index'])
        ->name('admin.documentation.index');
    Route::get('/admin/documentacao/ver', [DocumentationController::class, 'show'])
        ->name('admin.documentation.show');

    Route::get('/admin/analytics-diagnostics', AnalyticsDiagnosticsController::class)
        ->middleware('analytics.diagnostics')
        ->name('admin.analytics-diagnostics');
});

require __DIR__.'/auth.php';
