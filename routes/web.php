<?php

use App\Http\Controllers\Admin\AdminConnectionsController;
use App\Http\Controllers\Admin\AdminSyncQueueController;
use App\Http\Controllers\Admin\AnalyticsDiagnosticsController;
use App\Http\Controllers\Admin\ArtisanCommandsController;
use App\Http\Controllers\Admin\DocumentationController as AdminDocumentationController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\Admin\GeoSyncController;
use App\Http\Controllers\Admin\IeducarCompatibilityController;
use App\Http\Controllers\Admin\ModuleMonitorController;
use App\Http\Controllers\Admin\LegalConsentReportController;
use App\Http\Controllers\Admin\LegalConsentRevocationController;
use App\Http\Controllers\Admin\LegalDocumentAdminController;
use App\Http\Controllers\Admin\CadunicoSyncController;
use App\Http\Controllers\Admin\PedagogicalSyncController;
use App\Http\Controllers\Admin\HorizonteSgeRegistryController;
use App\Http\Controllers\Admin\HorizonteImportController;
use App\Http\Controllers\Admin\PublicDataImportController;
use App\Http\Controllers\HorizonteController;
use App\Http\Controllers\AnalyticsDashboardController;
use App\Http\Controllers\CadunicoPrevisaoExportController;
use App\Http\Controllers\ComparativoExportController;
use App\Http\Controllers\AnalyticsReportExportController;
use App\Http\Controllers\AnalyticsReportPublicationController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardMunicipalityMapController;
use App\Http\Controllers\DiscrepanciesExportController;
use App\Http\Controllers\EducacensoAnalysisController;
use App\Http\Controllers\InclusionNeeExportController;
use App\Http\Controllers\FirstAccessProfileController;
use App\Http\Controllers\LegalConsentController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RxDashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserLoginHistoryController;
use App\Http\Controllers\UserSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/privacidade', [PrivacyPolicyController::class, 'show'])
    ->name('legal.privacy');

Route::post('/legal/consentimento-visitante', [LegalConsentController::class, 'storeGuest'])
    ->name('legal.consent.guest');

Route::get('/relatorio/{publicId}', [AnalyticsReportPublicationController::class, 'show'])
    ->name('analytics.report.public');
Route::get('/relatorio/{publicId}/pdf', [AnalyticsReportPublicationController::class, 'download'])
    ->name('analytics.report.public.download');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile/first-access', [FirstAccessProfileController::class, 'edit'])->name('profile.first-access');
    Route::post('/profile/first-access', [FirstAccessProfileController::class, 'update'])->name('profile.first-access.update');
});

Route::middleware(['auth', 'verified', 'profile.complete'])->group(function () {
    Route::get('/consentimento', [LegalConsentController::class, 'show'])->name('legal.consent');
    Route::post('/consentimento', [LegalConsentController::class, 'store'])->name('legal.consent.store');

    Route::middleware('legal.consent')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/municipality-map/cadastro-snapshot', [DashboardMunicipalityMapController::class, 'cadastroSnapshot'])
            ->name('dashboard.municipality-map.cadastro-snapshot');
        Route::get('/dashboard/municipality-map/{city}/school-years', [DashboardMunicipalityMapController::class, 'schoolYears'])
            ->name('dashboard.municipality-map.school-years');
        Route::get('/dashboard/analytics', [AnalyticsDashboardController::class, 'index'])->name('dashboard.analytics');
        Route::get('/dashboard/rx', [RxDashboardController::class, 'index'])->name('dashboard.rx');
        Route::get('/dashboard/horizonte', [HorizonteController::class, 'index'])->name('dashboard.horizonte');
        Route::get('/dashboard/horizonte/map-data', [HorizonteController::class, 'mapData'])->name('dashboard.horizonte.map-data');
        Route::get('/dashboard/horizonte/map-geo', [HorizonteController::class, 'mapGeo'])->name('dashboard.horizonte.map-geo');
        Route::get('/dashboard/horizonte/municipality/{ibge}/enrollment-series', [HorizonteController::class, 'enrollmentSeries'])->name('dashboard.horizonte.enrollment-series');

        Route::prefix('clio')->name('clio.')->group(function () {
            Route::get('/campanhas', [\App\Http\Controllers\Clio\CampaignController::class, 'index'])->name('campaigns.index');
            Route::get('/campanhas/nova', [\App\Http\Controllers\Clio\CampaignController::class, 'create'])->name('campaigns.create');
            Route::post('/campanhas', [\App\Http\Controllers\Clio\CampaignController::class, 'store'])->name('campaigns.store');
            Route::get('/campanhas/{campaign}', [\App\Http\Controllers\Clio\CampaignController::class, 'show'])->name('campaigns.show');
            Route::get('/campanhas/{campaign}/upload', [\App\Http\Controllers\Clio\CampaignUploadController::class, 'edit'])->name('campaigns.upload');
            Route::post('/campanhas/{campaign}/upload', [\App\Http\Controllers\Clio\CampaignUploadController::class, 'store'])->name('campaigns.upload.store');
            Route::get('/municipios/ficha-leve', [\App\Http\Controllers\Clio\CatalogCityController::class, 'create'])->name('cities.create');
            Route::post('/municipios/ficha-leve', [\App\Http\Controllers\Clio\CatalogCityController::class, 'store'])->name('cities.store');
        });

        Route::get('/dashboard/analytics/tab', [AnalyticsDashboardController::class, 'tabPartial'])->name('dashboard.analytics.tab');
        Route::get('/dashboard/analytics/filter-options', [AnalyticsDashboardController::class, 'filterOptions'])->name('dashboard.analytics.filter-options');
        Route::get('/dashboard/analytics/filter-options-bootstrap', [AnalyticsDashboardController::class, 'filterOptionsBootstrap'])->name('dashboard.analytics.filter-options-bootstrap');
        Route::get('/dashboard/analytics/filter-options-years', [AnalyticsDashboardController::class, 'filterOptionsYears'])->name('dashboard.analytics.filter-options-years');
        Route::get('/dashboard/analytics/discrepancies/export', [DiscrepanciesExportController::class, 'csv'])->name('dashboard.analytics.discrepancies.export');
        Route::post('/dashboard/analytics/educacenso-analyze', [EducacensoAnalysisController::class, 'store'])->name('dashboard.analytics.educacenso.analyze');
        Route::delete('/dashboard/analytics/educacenso-analyze', [EducacensoAnalysisController::class, 'destroy'])->name('dashboard.analytics.educacenso.clear');
        Route::get('/dashboard/analytics/educacenso-analyze/export', [EducacensoAnalysisController::class, 'exportFindings'])->name('dashboard.analytics.educacenso.export');
        Route::get('/dashboard/analytics/comparativo/export', [ComparativoExportController::class, 'download'])
            ->name('dashboard.analytics.comparativo.export');
        Route::get('/dashboard/analytics/cadunico-previsao/export', [CadunicoPrevisaoExportController::class, 'download'])
            ->name('dashboard.analytics.cadunico-previsao.export');
        Route::get('/dashboard/analytics/inclusion/export', [InclusionNeeExportController::class, 'download'])
            ->name('dashboard.analytics.inclusion.export');
        Route::post('/dashboard/analytics/inclusion/export/queue', [InclusionNeeExportController::class, 'queue'])
            ->name('dashboard.analytics.inclusion.export.queue');

        Route::get('/documentacao', [DocumentationController::class, 'index'])->name('documentation.index');
        Route::get('/documentacao/buscar', [DocumentationController::class, 'search'])->name('documentation.search');
        Route::get('/documentacao/ver', [DocumentationController::class, 'show'])->name('documentation.show');

        Route::get('/filas', [AdminSyncQueueController::class, 'index'])->name('sync-queue.index');
        Route::get('/filas/{task}', [AdminSyncQueueController::class, 'show'])->name('sync-queue.show');
        Route::get('/filas/{task}/download', [AdminSyncQueueController::class, 'download'])->name('sync-queue.download');
        Route::post('/dashboard/analytics/pdf-export', [AnalyticsReportExportController::class, 'store'])->name('dashboard.analytics.pdf.store');
        Route::get('/dashboard/analytics/pdf-export/{export}/status', [AnalyticsReportExportController::class, 'status'])->name('dashboard.analytics.pdf.status');
        Route::get('/dashboard/analytics/pdf-export/{export}/download', [AnalyticsReportExportController::class, 'download'])->name('dashboard.analytics.pdf.download');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
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
});

Route::middleware(['auth', 'verified', 'profile.complete', 'legal.consent', 'admin'])->group(function () {
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

    Route::get('/admin/cadunico-sync', [CadunicoSyncController::class, 'index'])->name('admin.cadunico-sync.index');
    Route::post('/admin/cadunico-sync', [CadunicoSyncController::class, 'run'])->name('admin.cadunico-sync.run');

    Route::get('/admin/dados-publicos', [PublicDataImportController::class, 'index'])->name('admin.public-data.index');
    Route::post('/admin/dados-publicos/verificar-oficial', [PublicDataImportController::class, 'checkOfficial'])->name('admin.public-data.check-official');
    Route::post('/admin/dados-publicos', [PublicDataImportController::class, 'run'])->name('admin.public-data.run');

    Route::get('/admin/horizonte/abastecimento', [HorizonteImportController::class, 'index'])->name('admin.horizonte-import.index');
    Route::match(['get', 'post'], '/admin/horizonte/abastecimento/feed', [HorizonteImportController::class, 'feed'])->name('admin.horizonte-import.feed');
    Route::post('/admin/horizonte/abastecimento/educacenso-sync', [HorizonteImportController::class, 'educacensoSync'])->name('admin.horizonte-import.educacenso-sync');
    Route::post('/admin/horizonte/abastecimento/municipal-geo-sync', [HorizonteImportController::class, 'municipalGeoSync'])->name('admin.horizonte-import.municipal-geo-sync');
    Route::post('/admin/horizonte/abastecimento/bundle-export', [HorizonteImportController::class, 'bundleExport'])->name('admin.horizonte-import.bundle-export');
    Route::post('/admin/horizonte/abastecimento/bundle-import', [HorizonteImportController::class, 'bundleImport'])->name('admin.horizonte-import.bundle-import');

    Route::match(['get', 'post'], '/admin/dados-publicos/horizonte-feed', [HorizonteImportController::class, 'feed'])->name('admin.public-data.horizonte-feed');
    Route::post('/admin/dados-publicos/horizonte-educacenso-sync', [HorizonteImportController::class, 'educacensoSync'])->name('admin.public-data.horizonte-educacenso-sync');
    Route::post('/admin/dados-publicos/horizonte-municipal-geo-sync', [HorizonteImportController::class, 'municipalGeoSync'])->name('admin.public-data.horizonte-municipal-geo-sync');
    Route::post('/admin/dados-publicos/horizonte-bundle-export', [HorizonteImportController::class, 'bundleExport'])->name('admin.public-data.horizonte-bundle-export');
    Route::post('/admin/dados-publicos/horizonte-bundle-import', [HorizonteImportController::class, 'bundleImport'])->name('admin.public-data.horizonte-bundle-import');

    Route::get('/admin/horizonte/sge/{ibge}', [HorizonteSgeRegistryController::class, 'show'])->name('admin.horizonte.sge.show');
    Route::put('/admin/horizonte/sge/{ibge}', [HorizonteSgeRegistryController::class, 'upsert'])->name('admin.horizonte.sge.upsert');
    Route::delete('/admin/horizonte/sge/{ibge}', [HorizonteSgeRegistryController::class, 'destroy'])->name('admin.horizonte.sge.destroy');

    Route::get('/admin/ieducar-compatibility', [IeducarCompatibilityController::class, 'index'])->name('admin.ieducar-compatibility.index');
    Route::get('/admin/ieducar-compatibility/export', [IeducarCompatibilityController::class, 'export'])->name('admin.ieducar-compatibility.export');
    Route::get('/admin/ieducar-compatibility/fundeb-matrix-export', [IeducarCompatibilityController::class, 'exportFundebMatrix'])->name('admin.ieducar-compatibility.fundeb-matrix-export');
    Route::post('/admin/ieducar-compatibility/fundeb-import', [IeducarCompatibilityController::class, 'importFundeb'])->name('admin.ieducar-compatibility.fundeb-import');
    Route::post('/admin/ieducar-compatibility/fundeb-import-bulk', [IeducarCompatibilityController::class, 'importFundebBulk'])->name('admin.ieducar-compatibility.fundeb-import-bulk');
    Route::post('/admin/ieducar-compatibility/fundeb-sync-all', [IeducarCompatibilityController::class, 'syncFundebAll'])->name('admin.ieducar-compatibility.fundeb-sync-all');

    Route::get('/admin/artisan-commands', [ArtisanCommandsController::class, 'index'])->name('admin.artisan-commands.index');

    Route::get('/admin/sync-queue', [AdminSyncQueueController::class, 'index'])->name('admin.sync-queue.index');
    Route::get('/admin/sync-queue/{task}', [AdminSyncQueueController::class, 'show'])->name('admin.sync-queue.show');
    Route::post('/admin/sync-queue/{task}/resume', [AdminSyncQueueController::class, 'resume'])->name('admin.sync-queue.resume');
    Route::get('/admin/sync-queue/{task}/download', [AdminSyncQueueController::class, 'download'])->name('admin.sync-queue.download');

    Route::get('/admin/conexoes', [AdminConnectionsController::class, 'index'])
        ->name('admin.connections.index');

    Route::get('/admin/documentacao', [AdminDocumentationController::class, 'index'])
        ->name('admin.documentation.index');
    Route::get('/admin/documentacao/buscar', [AdminDocumentationController::class, 'search'])
        ->name('admin.documentation.search');
    Route::get('/admin/documentacao/ver', [AdminDocumentationController::class, 'show'])
        ->name('admin.documentation.show');

    Route::get('/admin/analytics-diagnostics', AnalyticsDiagnosticsController::class)
        ->middleware('analytics.diagnostics')
        ->name('admin.analytics-diagnostics');

    Route::get('/admin/monitor-modulos', [ModuleMonitorController::class, 'index'])
        ->name('admin.module-monitor.index');

    Route::get('/admin/consentimentos-legais', [LegalConsentReportController::class, 'index'])
        ->name('admin.legal-consents.index');
    Route::post('/admin/consentimentos-legais/revogar-todos', [LegalConsentRevocationController::class, 'revokeAll'])
        ->name('admin.legal-consents.revoke-all');
    Route::post('/admin/consentimentos-legais/{user}/revogar', [LegalConsentRevocationController::class, 'revokeUser'])
        ->name('admin.legal-consents.revoke-user');

    Route::get('/admin/documentos-legais', [LegalDocumentAdminController::class, 'index'])
        ->name('admin.legal-documents.index');
    Route::get('/admin/documentos-legais/{type}', [LegalDocumentAdminController::class, 'edit'])
        ->where('type', 'privacy|cookies')
        ->name('admin.legal-documents.edit');
    Route::post('/admin/documentos-legais/{type}/publicar', [LegalDocumentAdminController::class, 'publish'])
        ->where('type', 'privacy|cookies')
        ->name('admin.legal-documents.publish');
});

require __DIR__.'/auth.php';
