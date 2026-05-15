<?php

namespace App\Services\AdminSync;

use Illuminate\Http\Request;

final class AdminSyncGeoPayloadBuilder
{
    /**
     * @return array{step: string, title: string, artisan_command: string, artisan_args: array<string, string>}
     */
    public static function fromRequest(Request $request, string $step): array
    {
        $cityId = $request->filled('city_id') ? (int) $request->input('city_id') : null;
        $threshold = $request->filled('threshold')
            ? (float) $request->input('threshold')
            : (float) config('ieducar.inep_geocoding.divergence_threshold_meters', 100);
        if ($threshold <= 0) {
            $threshold = (float) config('ieducar.inep_geocoding.divergence_threshold_meters', 100);
        }

        $title = match ($step) {
            'ieducar' => __('i-Educar → school_unit_geos'),
            'microdados' => __('Import MICRODADOS INEP (cadastro de escolas)'),
            'official' => __('Coordenadas oficiais INEP + divergência'),
            'pipeline' => __('Pipeline completo'),
            'probe' => __('Diagnóstico fontes INEP'),
            default => $step,
        };

        $args = match ($step) {
            'ieducar' => array_filter([
                '--only-missing' => $request->boolean('ieducar_only_missing') ? '1' : '0',
                '--city' => $cityId !== null ? (string) $cityId : null,
            ], static fn ($v) => $v !== null),
            'microdados' => array_filter([
                '--also-map-coords' => $request->boolean('microdados_also_map_coords') ? '1' : '0',
                '--skip-if-missing' => '1',
                '--only-missing' => '1',
                '--threshold' => (string) $threshold,
                '--fetch' => $request->boolean('microdados_fetch', true) ? '1' : '0',
                '--city' => $cityId !== null ? (string) $cityId : null,
            ], static fn ($v) => $v !== null),
            'official' => array_filter([
                '--only-missing' => $request->boolean('official_only_missing') ? '1' : '0',
                '--threshold' => (string) $threshold,
                '--dry-run' => $request->boolean('dry_run') ? '1' : '0',
                '--city' => $cityId !== null ? (string) $cityId : null,
            ], static fn ($v) => $v !== null),
            'pipeline' => array_filter([
                '--skip-ieducar' => $request->boolean('pipeline_skip_ieducar') ? '1' : '0',
                '--ieducar-only-missing' => $request->boolean('ieducar_only_missing') ? '1' : '0',
                '--official-only-missing' => $request->boolean('official_only_missing') ? '1' : '0',
                '--threshold' => (string) $threshold,
                '--dry-run' => $request->boolean('dry_run') ? '1' : '0',
                '--skip-microdados-if-missing' => $request->boolean('pipeline_skip_microdados_if_missing') ? '1' : '0',
                '--microdados-also-map-coords' => $request->boolean('pipeline_microdados_map_coords') ? '1' : '0',
                '--microdados-fetch' => $request->boolean('pipeline_microdados_fetch', true) ? '1' : '0',
                '--city' => $cityId !== null ? (string) $cityId : null,
            ], static fn ($v) => $v !== null),
            'probe' => array_filter([
                '--city' => $cityId !== null ? (string) $cityId : null,
            ], static fn ($v) => $v !== null),
            default => [],
        };

        $command = match ($step) {
            'ieducar' => 'app:sync-school-unit-geos',
            'microdados' => 'app:import-inep-microdados-cadastro-escolas-geo',
            'official' => 'app:sync-school-unit-geos-official',
            'pipeline' => 'app:sync-school-unit-geos-pipeline',
            'probe' => 'app:probe-inep-geo-fallbacks',
            default => '',
        };

        return [
            'step' => $step,
            'title' => $title,
            'artisan_command' => $command,
            'artisan_args' => $args,
        ];
    }
}
