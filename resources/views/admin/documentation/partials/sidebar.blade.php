@include('documentation.partials.sidebar', [
    'sections' => $sections,
    'currentPath' => $currentPath ?? null,
    'documentationRoutePrefix' => $documentationRoutePrefix ?? 'admin.documentation',
])
