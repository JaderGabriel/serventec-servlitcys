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
     *     summary: array{status: string, label: string, detail: string},
     *     zones: list<array{id: string, title: string, description: string}>,
     *     nodes: list<array<string, mixed>>,
     *     edges: list<array<string, mixed>>,
     *     legend: list<array{status: string, label: string, description: string}>
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
            $hub,
        ];

        $edges = [
            $this->edge('ieducar', 'servlitcys', $ieducar['status'], __('Matrículas, turmas, Censo'), true),
            $this->edge('fnde', 'servlitcys', $fnde['status'], __('VAAF e repasses indicativos')),
            $this->edge('inep', 'servlitcys', $inep['status'], __('Desempenho e SAEB')),
            $this->edge('portal', 'servlitcys', $portal['status'], __('Programas e despesas')),
            $this->edge('tesouro', 'servlitcys', $tesouro['status'], __('Financiamentos complementares')),
            $this->edge('arcgis', 'servlitcys', 'ok', __('Mapa e catálogo de escolas')),
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
                    'id' => 'external',
                    'title' => __('1 · Fontes públicas e federais'),
                    'description' => __('APIs e bases abertas consultadas por município — não substituem o cadastro local.'),
                ],
                [
                    'id' => 'platform',
                    'title' => __('2 · Plataforma de consultoria'),
                    'description' => __('Agrega, valida e expõe indicadores no painel analítico e relatórios.'),
                ],
                [
                    'id' => 'municipal',
                    'title' => __('3 · Base municipal (i-Educar)'),
                    'description' => __('Fonte de verdade do cadastro: matrículas, turmas, Censo e rotinas de discrepância.'),
                ],
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
                'description' => __('Integração activa; dados ou ligação disponíveis para o recorte actual.'),
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
                'description' => __('Sem município activo, ligação remota falhou ou fonte desactivada.'),
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
                'detail' => __('Revise credenciais i-Educar ou municípios activos antes de auditar repasses.'),
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
            'ok' => __('Teste de ligação remota: OK (amostra)'),
            'fail' => __('Teste de ligação remota: falhou — ver Conexões i-Educar'),
            default => __('Ligação não testada recentemente'),
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
