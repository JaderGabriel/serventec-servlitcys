import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pulse-notifications.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    const normalized = id.replace(/\\/g, '/');
                    if (
                        normalized.includes('node_modules/leaflet') ||
                        normalized.includes('leaflet.markercluster')
                    ) {
                        return 'leaflet';
                    }
                    if (normalized.includes('/resources/js/horizonteMap.js')) {
                        return 'horizonte';
                    }
                    if (normalized.includes('/resources/js/clioReportCard.js')) {
                        return 'clio';
                    }
                    if (
                        normalized.includes('/resources/js/schoolUnitsMap.js') ||
                        normalized.includes('/resources/js/cadunicoTerritoryMap.js') ||
                        normalized.includes('/resources/js/brazilMunicipalitiesMap.js')
                    ) {
                        return 'analytics-maps';
                    }
                },
            },
        },
    },
});
