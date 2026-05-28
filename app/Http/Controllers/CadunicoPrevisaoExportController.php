<?php

namespace App\Http\Controllers;

use App\Services\Analytics\CadunicoPrevisaoExportService;
use App\Support\Auth\UserCityAccess;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Pulse\PulseOperationRecorder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportação PDF, CSV e Excel da aba CadÚnico (previsão fora da rede).
 */
class CadunicoPrevisaoExportController extends Controller
{
    public function __construct(
        private CadunicoPrevisaoExportService $exportService,
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
        if (! $filters->hasYearSelected() || $filters->isAllSchoolYears()) {
            abort(422, __('Selecione um ano letivo específico antes de exportar.'));
        }

        $format = strtolower((string) $request->query('format', 'csv'));
        if (! in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            abort(422, __('Formato inválido. Use csv, xlsx ou pdf.'));
        }

        return PulseOperationRecorder::measure(
            'export:cadunico-previsao:'.$format.'|cid:'.(int) $city->id,
            fn () => $this->exportService->download($city, $filters, $format),
        );
    }
}
