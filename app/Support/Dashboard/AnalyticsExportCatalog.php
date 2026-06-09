<?php

namespace App\Support\Dashboard;

use App\Models\City;
use App\Models\User;
use App\Support\SyncQueue\SyncQueueUserScope;
use Illuminate\Http\Request;

/**
 * Menu centralizado de exportações da consultoria (cabeçalho).
 */
final class AnalyticsExportCatalog
{
    /**
     * @return list<array{id: string, label: string, items: list<array<string, mixed>>}>
     */
    public static function menu(?User $user, ?City $city, ?IeducarFilterState $filters, bool $yearFilterReady, ?Request $request = null): array
    {
        if ($user === null || $city === null) {
            return [];
        }

        $request ??= request();
        $params = self::queryParams($city, $filters, $request);
        $groups = [];

        if ($user->canExportAnalyticsPdf()) {
            $groups[] = [
                'id' => 'report',
                'label' => __('Relatório completo'),
                'items' => [
                    self::item(
                        id: 'pdf_full',
                        label: __('PDF Serventec'),
                        mode: 'queue',
                        method: 'POST',
                        url: route('dashboard.analytics.pdf.store'),
                        format: 'pdf',
                        enabled: $yearFilterReady,
                    ),
                ],
            ];
        }

        $groups[] = [
            'id' => 'discrepancies',
            'label' => __('Discrepâncias'),
            'items' => [
                self::item(
                    id: 'discrepancies_csv',
                    label: __('CSV'),
                    mode: 'download',
                    method: 'GET',
                    url: route('dashboard.analytics.discrepancies.export', $params),
                    format: 'csv',
                    enabled: $yearFilterReady,
                ),
            ],
        ];

        $groups[] = [
            'id' => 'comparativo',
            'label' => __('Comparativo'),
            'items' => [
                self::item('comparativo_pdf', __('PDF'), 'download', 'GET', route('dashboard.analytics.comparativo.export', array_merge($params, ['format' => 'pdf'])), 'pdf', $yearFilterReady),
                self::item('comparativo_csv', __('CSV'), 'download', 'GET', route('dashboard.analytics.comparativo.export', array_merge($params, ['format' => 'csv'])), 'csv', $yearFilterReady),
                self::item('comparativo_xlsx', __('Excel'), 'download', 'GET', route('dashboard.analytics.comparativo.export', array_merge($params, ['format' => 'xlsx'])), 'xlsx', $yearFilterReady),
            ],
        ];

        $groups[] = [
            'id' => 'cadunico',
            'label' => __('CadÚnico'),
            'items' => [
                self::item('cadunico_pdf', __('PDF'), 'download', 'GET', route('dashboard.analytics.cadunico-previsao.export', array_merge($params, ['format' => 'pdf'])), 'pdf', $yearFilterReady),
                self::item('cadunico_csv', __('CSV'), 'download', 'GET', route('dashboard.analytics.cadunico-previsao.export', array_merge($params, ['format' => 'csv'])), 'csv', $yearFilterReady),
                self::item('cadunico_xlsx', __('Excel'), 'download', 'GET', route('dashboard.analytics.cadunico-previsao.export', array_merge($params, ['format' => 'xlsx'])), 'xlsx', $yearFilterReady),
            ],
        ];

        if ($user->canExportInclusionNee()) {
            $groups[] = [
                'id' => 'inclusion',
                'label' => __('Inclusão NEE'),
                'items' => [
                    self::item(
                        id: 'inclusion_csv',
                        label: __('CSV'),
                        mode: 'queue',
                        method: 'POST',
                        url: route('dashboard.analytics.inclusion.export.queue'),
                        format: 'csv',
                        enabled: $yearFilterReady,
                    ),
                    self::item(
                        id: 'inclusion_xlsx',
                        label: __('Excel'),
                        mode: 'queue',
                        method: 'POST',
                        url: route('dashboard.analytics.inclusion.export.queue'),
                        format: 'xlsx',
                        enabled: $yearFilterReady,
                    ),
                ],
            ];
        }

        return array_values(array_filter(
            $groups,
            static fn (array $group): bool => ($group['items'] ?? []) !== [],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadForHub(?User $user, ?City $city, ?IeducarFilterState $filters, bool $yearFilterReady): array
    {
        $request = request();
        $params = self::queryParams($city, $filters, $request);

        return [
            'groups' => self::menu($user, $city, $filters, $yearFilterReady, $request),
            'csrf' => csrf_token(),
            'queueUrl' => route(SyncQueueUserScope::routePrefix($user).'.index'),
            'pdfStatusUrl' => url('/dashboard/analytics/pdf-export'),
            'filterFields' => $params,
            'yearReady' => $yearFilterReady,
            'messages' => [
                'queued' => __('Enviado para a fila'),
                'queuedDetail' => __('O pedido será processado em segundo plano.'),
                'download' => __('Exportação em processamento'),
                'downloadDetail' => __('O download iniciará em instantes.'),
                'needYear' => __('Aplique cidade e ano letivo antes de exportar.'),
                'error' => __('Não foi possível enfileirar a exportação.'),
                'openQueue' => __('Abrir fila'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(
        string $id,
        string $label,
        string $mode,
        string $method,
        string $url,
        string $format,
        bool $enabled,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'mode' => $mode,
            'method' => $method,
            'url' => $url,
            'format' => $format,
            'enabled' => $enabled,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function queryParams(?City $city, ?IeducarFilterState $filters, Request $request): array
    {
        $params = $filters !== null ? $filters->toQueryParams() : [];
        if ($city !== null) {
            $params['city_id'] = $city->id;
        }
        if ($request->filled('ano_base')) {
            $params['ano_base'] = $request->input('ano_base');
        }
        if ($request->filled('inclusion_scope')) {
            $params['inclusion_scope'] = $request->input('inclusion_scope');
        }

        return array_filter(
            $params,
            static fn ($value): bool => $value !== null && $value !== '',
        );
    }
}
