<?php

namespace App\Services\Dashboard;

use App\Models\City;
use App\Services\CityDataConnection;
use Illuminate\Support\Facades\Cache;

/**
 * Fluxograma de integrações — estado visual das conexões entre sistemas.
 */
final class AdminSystemFlowStatus
{
    public function __construct(
        private readonly CityDataConnection $cityData,
    ) {}

    /**
     * @return array{
     *     summary: array{status: string, label: string, detail: string},
     *     zones: list<array{id: string, step?: int, title: string, description: string}>,
     *     flow_steps: list<array{step: int, label: string, detail: string}>,
     *     nodes: list<array<string, mixed>>,
     *     edges: list<array<string, mixed>>,
     *     legend: list<array{status: string, label: string, description: string, count: int}>
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
        $arcgis = $this->configStatus(
            true,
            __('ArcGIS / catálogo INEP (geocodificação)'),
        );
        $cadunico = $this->cadunicoLinkStatus();

        $hub = [
            'id' => 'servlitcys',
            'label' => config('app.name'),
            'sublabel' => __('Motor de consultoria'),
            'status' => 'ok',
            'hint' => __('Cruza cadastro municipal com referências federais e INEP por cidade/ano.'),
            'row' => 'hub',
            'zone' => 'platform',
        ];

        $nodes = [
            [
                'id' => 'ieducar',
                'label' => __('i-Educar'),
                'sublabel' => __('PostgreSQL / MySQL'),
                'status' => $ieducar['status'],
                'hint' => $ieducar['hint'],
                'metric' => $ieducar['metric'],
                'metric_label' => __('Municípios prontos'),
                'row' => 'municipal',
                'zone' => 'municipal',
            ],
            [
                'id' => 'fnde',
                'label' => __('FNDE'),
                'acronym' => 'FNDE',
                'category' => 'financeiro',
                'sublabel' => __('FUNDEB · VAAT'),
                'status' => $fnde['status'],
                'hint' => $fnde['hint'],
                'row' => 'external',
                'zone' => 'external',
            ],
            [
                'id' => 'inep',
                'label' => __('MEC / INEP'),
                'acronym' => 'INEP',
                'category' => 'pedagogico',
                'sublabel' => __('SAEB · IDEB'),
                'status' => $inep['status'],
                'hint' => $inep['hint'],
                'row' => 'external',
                'zone' => 'external',
            ],
            [
                'id' => 'portal',
                'label' => __('Transparência'),
                'acronym' => 'CGU',
                'category' => 'transparencia',
                'sublabel' => __('Portal federal'),
                'status' => $portal['status'],
                'hint' => $portal['hint'],
                'row' => 'external',
                'zone' => 'external',
            ],
            [
                'id' => 'tesouro',
                'label' => __('Tesouro'),
                'acronym' => 'STN',
                'category' => 'financeiro',
                'sublabel' => __('Transferências'),
                'status' => $tesouro['status'],
                'hint' => $tesouro['hint'],
                'row' => 'external',
                'zone' => 'external',
            ],
            [
                'id' => 'arcgis',
                'label' => __('INEP / ArcGIS'),
                'acronym' => 'GEO',
                'category' => 'geografia',
                'sublabel' => __('Geografia escolar'),
                'status' => $arcgis['status'],
                'hint' => $arcgis['hint'],
                'row' => 'external',
                'zone' => 'external',
            ],
            [
                'id' => 'cadunico',
                'label' => __('CadÚnico / Cecad'),
                'acronym' => 'MDS',
                'category' => 'social',
                'sublabel' => __('Agregados 4–17 anos'),
                'status' => $cadunico['status'],
                'hint' => $cadunico['hint'],
                'row' => 'external',
                'zone' => 'external',
            ],
            $hub,
        ];

        $edges = [
            $this->edge('ieducar', 'servlitcys', $ieducar['status'], __('Matrículas, turmas, Censo'), true),
            $this->edge('fnde', 'servlitcys', $fnde['status'], __('VAAF e repasses indicativos')),
            $this->edge('inep', 'servlitcys', $inep['status'], __('Desempenho e SAEB')),
            $this->edge('portal', 'servlitcys', $portal['status'], __('Programas e despesas')),
            $this->edge('tesouro', 'servlitcys', $tesouro['status'], __('Repasses e CKAN Tesouro')),
            $this->edge('arcgis', 'servlitcys', 'ok', __('Mapa e catálogo de escolas')),
            $this->edge('cadunico', 'servlitcys', $cadunico['status'], __('Lacuna rede e previsão FUNDEB')),
        ];

        $statuses = array_merge(
            [$ieducar['status']],
            array_column(array_filter($nodes, fn (array $n): bool => ($n['zone'] ?? '') === 'external'), 'status'),
        );
        $summary = $this->buildSummary($statuses, $citiesReady, $citiesActive);
        $statusCounts = $this->statusCounts($nodes, $edges);

        return [
            'summary' => $summary,
            'zones' => [
                [
                    'id' => 'municipal',
                    'step' => 1,
                    'title' => __('1 · Entrada — base municipal'),
                    'description' => __('i-Educar: matrículas, turmas, Censo e cadastro escolar — fonte de verdade do município.'),
                ],
                [
                    'id' => 'platform',
                    'step' => 2,
                    'title' => __('2 · Agregação — motor de consultoria'),
                    'description' => __('Cruza o cadastro local com referências federais; gera discrepâncias, FUNDEB indicativo e relatórios.'),
                ],
                [
                    'id' => 'external',
                    'step' => 3,
                    'title' => __('3 · Enriquecimento — fontes públicas'),
                    'description' => __('Importações e APIs (FNDE, INEP, MDS/Cecad, Tesouro, Transparência) — complementam, não substituem o i-Educar.'),
                ],
            ],
            'flow_steps' => [
                ['step' => 1, 'label' => __('Cadastro municipal'), 'detail' => __('i-Educar')],
                ['step' => 2, 'label' => __('Agregação'), 'detail' => config('app.name')],
                ['step' => 3, 'label' => __('Referências federais'), 'detail' => __('FNDE · INEP · MDS')],
                ['step' => 4, 'label' => __('Saída'), 'detail' => __('Consultoria · Filas · PDF')],
            ],
            'nodes' => $nodes,
            'edges' => $edges,
            'legend' => $this->legendItems($statusCounts),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     * @return array{ok: int, partial: int, off: int}
     */
    private function statusCounts(array $nodes, array $edges): array
    {
        $counts = ['ok' => 0, 'partial' => 0, 'off' => 0];
        foreach (array_merge($nodes, $edges) as $item) {
            $st = (string) ($item['status'] ?? 'partial');
            if (! isset($counts[$st])) {
                $counts[$st] = 0;
            }
            $counts[$st]++;
        }

        return $counts;
    }

    /**
     * @param  array{ok: int, partial: int, off: int}  $counts
     * @return list<array{status: string, label: string, description: string, count: int}>
     */
    private function legendItems(array $counts): array
    {
        return [
            [
                'status' => 'ok',
                'label' => __('Operacional'),
                'description' => __('Integração activa; dados ou conexão disponíveis para o recorte actual.'),
                'count' => (int) ($counts['ok'] ?? 0),
            ],
            [
                'status' => 'partial',
                'label' => __('A configurar'),
                'description' => __('Chaves no .env em falta ou apenas parte dos municípios com base pronta.'),
                'count' => (int) ($counts['partial'] ?? 0),
            ],
            [
                'status' => 'off',
                'label' => __('Indisponível'),
                'description' => __('Sem município ativo, conexão remota falhou ou fonte desativada.'),
                'count' => (int) ($counts['off'] ?? 0),
            ],
        ];
    }

    /**
     * @param  list<string>  $statuses
     * @return array{status: string, label: string, detail: string}
     */
    private function buildSummary(array $statuses, int $ready, int $active): array
    {
        $hasOff = in_array('off', $statuses, true);
        $hasPartial = in_array('partial', $statuses, true);

        if ($hasOff || ($active > 0 && $ready === 0)) {
            return [
                'status' => 'off',
                'label' => __('Integrações com bloqueio'),
                'detail' => __('Revise credenciais i-Educar ou municípios ativos antes de auditar repasses.'),
            ];
        }

        if ($hasPartial) {
            return [
                'status' => 'partial',
                'label' => __('Operação parcial'),
                'detail' => __(':ready de :active município(s) prontos; complete .env ou bases em falta.', [
                    'ready' => number_format($ready),
                    'active' => number_format($active),
                ]),
            ];
        }

        return [
            'status' => 'ok',
            'label' => __('Fluxo operacional'),
            'detail' => __('Fontes alinhadas ao painel. Use Consultoria para validar cada município/ano.'),
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
                'hint' => __('Cadastre e active municípios em Cidades.'),
                'metric' => '0 / 0',
            ];
        }

        $probe = $this->sampleConnectionProbe();

        $status = match (true) {
            $ready === $active && $probe === 'ok' => 'ok',
            $ready > 0 => 'partial',
            default => 'off',
        };

        $hint = match ($probe) {
            'ok' => __('Teste de conexão remota: OK (amostra)'),
            'fail' => __('Teste de conexão remota: falhou — ver Conexões i-Educar'),
            default => __('Conexão não testada recentemente'),
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
                ? __('Variáveis configuradas no ambiente')
                : __('Definir no .env: :label', ['label' => $label]),
        ];
    }

    /**
     * @return array{status: string, hint: string}
     */
    private function cadunicoLinkStatus(): array
    {
        if (! filter_var(config('ieducar.cadunico.enabled', true), FILTER_VALIDATE_BOOL)) {
            return [
                'status' => 'off',
                'hint' => __('CadÚnico desativado (IEDUCAR_CADUNICO_ENABLED=false).'),
            ];
        }

        $hasPipeline = filled(config('ieducar.cadunico.auto_sync.nacional_csv_url_template'))
            || filled(config('ieducar.cadunico.open_data.api_url_template'))
            || filled(config('ieducar.cadunico.open_data.resource_id'));
        $autoSync = filter_var(config('ieducar.cadunico.auto_sync.enabled', true), FILTER_VALIDATE_BOOL);
        $scheduled = filter_var(config('ieducar.cadunico.auto_sync.schedule.enabled', true), FILTER_VALIDATE_BOOL);

        if ($hasPipeline && $autoSync && $scheduled) {
            return [
                'status' => 'ok',
                'hint' => __('Pipeline Cecad configurado; fila cadastro::auto_sync e cron semanal.'),
            ];
        }

        if ($hasPipeline || $autoSync) {
            return [
                'status' => 'partial',
                'hint' => __('Defina IEDUCAR_CADUNICO_NACIONAL_CSV_URL e mantenha cadunico:auto-sync no schedule:run.'),
            ];
        }

        return [
            'status' => 'partial',
            'hint' => __('Configure URL nacional, API ou hub /admin/dados-publicos → CadÚnico.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function edge(string $from, string $to, string $status, string $label, bool $bidirectional = false): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'label' => $label,
            'bidirectional' => $bidirectional,
        ];
    }
}
