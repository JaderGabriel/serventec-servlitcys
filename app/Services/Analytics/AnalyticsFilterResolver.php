<?php

namespace App\Services\Analytics;

use App\Http\Requests\AnalyticsFilterRequest;
use App\Models\City;
use App\Services\CityDataConnection;
use App\Services\Ieducar\FilterOptionsService;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarAnalyticsMetricsScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AnalyticsFilterResolver
{
    /**
     * @return array<string, string>
     */
    public function schoolYearOptionsFallback(): array
    {
        return [
            '' => __('— Selecione o ano letivo —'),
            'all' => __('Todos os anos'),
        ];
    }

    public function bindMetricsScope(City $city, IeducarFilterState $filters): void
    {
        IeducarAnalyticsMetricsScope::bindForRequest(
            app(CityDataConnection::class),
            $city,
            $filters,
            warm: true,
        );
    }

    /**
     * Restaura filtros do pedido e aplica o último ano letivo quando o recorte ainda não tem ano.
     *
     * @param  array{years?: array<string, string>, errors?: list<string>}|null  $yearPayload
     */
    public function resolve(
        Request|AnalyticsFilterRequest $request,
        FilterOptionsService $filterOptionsService,
        City $city,
        ?array &$yearPayload = null,
    ): IeducarFilterState {
        $filters = $request instanceof AnalyticsFilterRequest
            ? $request->filters()
            : IeducarFilterState::fromRequest($request);

        try {
            return $filterOptionsService->applyLatestSchoolYearIfMissing($filters, $city, $yearPayload);
        } catch (Throwable $e) {
            Log::warning('analytics.default_year_failed', [
                'city_id' => $city->id,
                'message' => $e->getMessage(),
            ]);

            return $filters;
        }
    }
}
