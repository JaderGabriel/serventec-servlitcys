@include('admin.partials.import-hub-theme-overview', [
    'cards' => array_merge($syncThemeCards, [$pdfThemeCard]),
    'hrefMode' => 'sync_queue',
    'syncQueueRoutePrefix' => $syncQueueRoutePrefix ?? 'admin.sync-queue',
    'filterDomain' => $filterDomain ?? '',
    'filterStatus' => $filterStatus ?? '',
    'filterPdfStatus' => $filterPdfStatus ?? '',
])
