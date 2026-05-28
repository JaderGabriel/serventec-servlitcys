<?php

namespace App\Http\Controllers;

use App\Services\Analytics\ComparativoExportService;
use App\Services\Analytics\FinanceComparativoService;
use App\Services\Ieducar\FilterOptionsService;
use App\Support\Auth\UserCityAccess;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Pulse\PulseOperationRecorder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportação PDF, CSV e Excel da aba Comparativo (Finanças).
 */
class ComparativoExportController extends Controller
{
    public function __construct(
        private ComparativoExportService $exportService,
        private FilterOptionsService $filterOptions,
    ) {}

    public function download(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $cityId = (int) $request->input('city_id', 0);
        $city = $cityId > 0
            ? UserCityAccess::citiesQuery($user)->whereKey($cityId)->first()
            : UserCityAccess::citiesQuery($user)->first();

        if ($city === null) {
            abort(404, __('Nenhuma cidade disponível para exportação.'));
        }

        $this->authorize('viewAnalytics', $city);

        $filters = IeducarFilterState::fromRequest($request);
        $baseYear = FinanceComparativoService::resolveBaseYear($request, $filters);
        if ($baseYear === null) {
            abort(422, __('Selecione o ano letivo ou o ano base antes de exportar.'));
        }

        $format = strtolower((string) $request->query('format', 'csv'));
        if (! in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            abort(422, __('Formato inválido. Use csv, xlsx ou pdf.'));
        }

        $yearOptions = [];
        try {
            $loaded = $this->filterOptions->loadAll($city, $filters);
            $yearOptions = is_array($loaded['years'] ?? null) ? $loaded['years'] : [];
        } catch (\Throwable) {
            $yearOptions = [];
        }

        return PulseOperationRecorder::measure(
            'export:comparativo:'.$format.'|cid:'.(int) $city->id,
            fn () => $this->exportService->download($city, $filters, $baseYear, $format, $yearOptions),
        );
    }
}
