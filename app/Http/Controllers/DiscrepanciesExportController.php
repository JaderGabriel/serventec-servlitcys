<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Repositories\Ieducar\DiscrepanciesRepository;
use App\Support\Auth\UserCityAccess;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\DiscrepanciesCheckCatalog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportação CSV das discrepâncias por escola (lista de correcção).
 */
class DiscrepanciesExportController extends Controller
{
    public function __construct(
        private DiscrepanciesRepository $discrepancies,
    ) {}

    public function csv(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $cities = UserCityAccess::citiesQuery($user)->get();
        $cityId = (int) $request->input('city_id', 0);
        $city = $cityId > 0 ? $cities->firstWhere('id', $cityId) : $cities->first();

        if ($city === null) {
            abort(404, __('Nenhuma cidade disponível para exportação.'));
        }

        $this->authorize('viewAnalytics', $city);

        $filters = IeducarFilterState::fromRequest($request);
        if (! $filters->hasYearSelected()) {
            abort(422, __('Seleccione o ano letivo antes de exportar.'));
        }

        $snapshot = $this->discrepancies->snapshot($city, $filters);
        $checks = is_array($snapshot['checks'] ?? null) ? $snapshot['checks'] : [];
        $catalog = DiscrepanciesCheckCatalog::definitions();
        $checkFilter = trim((string) $request->input('check_id', ''));

        $filename = sprintf(
            'discrepancias-%d-%s.csv',
            (int) $city->id,
            preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($filters->ano_letivo ?? 'ano')),
        );

        return response()->streamDownload(function () use ($checks, $catalog, $checkFilter, $city, $filters): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [
                'cidade',
                'ano_letivo',
                'check_id',
                'check_titulo',
                'escola_id',
                'escola',
                'total',
                'tipos_recurso',
                'sugestao_correcao',
            ], ';');

            foreach ($checks as $check) {
                $id = (string) ($check['id'] ?? '');
                if ($checkFilter !== '' && $id !== $checkFilter) {
                    continue;
                }
                $meta = $catalog[$id] ?? [];
                $sugestao = (string) ($meta['correction'] ?? $check['correction'] ?? '');
                $titulo = (string) ($check['title'] ?? $meta['title'] ?? $id);
                $rows = is_array($check['school_rows'] ?? null) ? $check['school_rows'] : [];

                foreach ($rows as $row) {
                    fputcsv($out, [
                        $city->name,
                        (string) ($filters->ano_letivo ?? ''),
                        $id,
                        $titulo,
                        (string) ($row['escola_id'] ?? ''),
                        (string) ($row['escola'] ?? ''),
                        (int) ($row['total'] ?? 0),
                        (string) ($row['tipos_recurso'] ?? ''),
                        $sugestao,
                    ], ';');
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
