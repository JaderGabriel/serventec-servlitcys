<?php

namespace App\Services\Dashboard;

use App\Models\City;
use App\Services\CityDataConnection;
use Illuminate\Support\Facades\Cache;

/**
 * Fluxograma de integrações — estado visual das ligações entre sistemas.
 */
final class AdminSystemFlowStatus
{
    public function __construct(
        private readonly CityDataConnection $cityData,
    ) {}

    /**
     * @return array{
     *     nodes: list<array<string, mixed>>,
     *     edges: list<array<string, mixed>>,
     *     legend: list<array{status: string, label: string}>
     * }
     */
    public function diagram(int $citiesReady, int $citiesActive): array
    {
        $ieducar = $this->ieducarLinkStatus($citiesReady, $citiesActive);
        $fnde = $this->configStatus(
            filled(config('ieducar.fundeb.open_data.resource_id'))
                || filled(config('ieducar.fundeb.open_data.json_url')),
            __('CKAN / JSON FUNDEB'),
        );
        $inep = $this->configStatus(
            filter_var(config('ieducar.saeb.microdados_enabled'), FILTER_VALIDATE_BOOL)
                || filter_var(config('ieducar.saeb.public_api_enabled'), FILTER_VALIDATE_BOOL),
            __('SAEB / microdados INEP'),
        );
        $portal = $this->configStatus(
            filled(config('ieducar.other_funding.public_queries.portal_transparencia.api_key'))
                && filter_var(config('ieducar.other_funding.public_queries.portal_transparencia.enabled'), FILTER_VALIDATE_BOOL),
            __('API Portal da Transparência'),
        );
        $tesouro = $this->configStatus(
            filter_var(config('ieducar.other_funding.public_queries.tesouro_ckan.enabled'), FILTER_VALIDATE_BOOL),
            __('CKAN Tesouro Transparente'),
        );
        $arcgis = $this->configStatus(true, __('ArcGIS / catálogo INEP (geocodificação)'));

        $hub = [
            'id' => 'servlitcys',
            'label' => config('app.name'),
            'sublabel' => __('Consultoria municipal'),
            'status' => 'ok',
            'hint' => __('Agrega dados locais e públicos por município'),
            'row' => 'hub',
        ];

        $nodes = [
            [
                'id' => 'ieducar',
                'label' => __('i-Educar'),
                'sublabel' => __('BD municipal'),
                'status' => $ieducar['status'],
                'hint' => $ieducar['hint'],
                'metric' => $ieducar['metric'],
                'row' => 'sources',
            ],
            [
                'id' => 'fnde',
                'label' => __('FNDE'),
                'sublabel' => __('FUNDEB / VAAT'),
                'status' => $fnde['status'],
                'hint' => $fnde['hint'],
                'row' => 'externals',
            ],
            [
                'id' => 'inep',
                'label' => __('MEC / INEP'),
                'sublabel' => __('SAEB · IDEB'),
                'status' => $inep['status'],
                'hint' => $inep['hint'],
                'row' => 'externals',
            ],
            [
                'id' => 'portal',
                'label' => __('Transparência'),
                'sublabel' => __('Despesas federais'),
                'status' => $portal['status'],
                'hint' => $portal['hint'],
                'row' => 'externals',
            ],
            [
                'id' => 'tesouro',
                'label' => __('Tesouro'),
                'sublabel' => __('Transferências'),
                'status' => $tesouro['status'],
                'hint' => $tesouro['hint'],
                'row' => 'externals',
            ],
            [
                'id' => 'arcgis',
                'label' => __('INEP / ArcGIS'),
                'sublabel' => __('Escolas · geo'),
                'status' => $arcgis['status'],
                'hint' => $arcgis['hint'],
                'row' => 'externals',
            ],
            $hub,
        ];

        $edges = [
            $this->edge('ieducar', 'servlitcys', $ieducar['status'], __('Cadastro, matrículas, Censo')),
            $this->edge('fnde', 'servlitcys', $fnde['status'], __('VAAF · repasses')),
            $this->edge('inep', 'servlitcys', $inep['status'], __('Desempenho · SAEB')),
            $this->edge('portal', 'servlitcys', $portal['status'], __('Financiamentos')),
            $this->edge('tesouro', 'servlitcys', $tesouro['status'], __('Financiamentos')),
            $this->edge('arcgis', 'servlitcys', 'ok', __('Mapa de unidades')),
        ];

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'legend' => [
                ['status' => 'ok', 'label' => __('Ligação activa')],
                ['status' => 'partial', 'label' => __('Parcial / configurar')],
                ['status' => 'off', 'label' => __('Indisponível')],
            ],
        ];
    }

    /**
     * @return array{status: string, hint: string, metric: string}
     */
    private function ieducarLinkStatus(int $ready, int $active): array
    {
        if ($active === 0) {
            return [
                'status' => 'off',
                'hint' => __('Nenhum município activo cadastrado'),
                'metric' => '0',
            ];
        }

        $probe = $this->sampleConnectionProbe();

        $status = match (true) {
            $ready === $active && $probe === 'ok' => 'ok',
            $ready > 0 && $probe !== 'fail' => 'partial',
            $ready > 0 => 'partial',
            default => 'off',
        };

        $hint = match ($probe) {
            'ok' => __('Amostra: ligação remota OK'),
            'fail' => __('Amostra: falha na ligação remota'),
            default => __('Sem teste recente de ligação'),
        };

        return [
            'status' => $status,
            'hint' => $hint,
            'metric' => number_format($ready).' / '.number_format($active),
        ];
    }

    private function sampleConnectionProbe(): string
    {
        return Cache::remember('admin_home_ieducar_probe', 300, function (): string {
            $city = City::query()->active()->withDataSetup()->orderBy('id')->first();
            if ($city === null) {
                return 'none';
            }

            $result = $this->cityData->probe($city);

            return ($result['ok'] ?? false) ? 'ok' : 'fail';
        });
    }

    /**
     * @return array{status: string, hint: string}
     */
    private function configStatus(bool $configured, string $label): array
    {
        return [
            'status' => $configured ? 'ok' : 'partial',
            'hint' => $configured
                ? __('Integração configurada')
                : __('Configurar no .env — :label', ['label' => $label]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function edge(string $from, string $to, string $status, string $label): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'label' => $label,
        ];
    }
}
