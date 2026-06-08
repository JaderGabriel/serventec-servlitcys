<?php

namespace App\Support\Dashboard;

use App\Support\Rx\RxSemaphore;

/**
 * Resumo de cadastro (meta RX, ano vigente) para o mapa de municípios no Início.
 */
final class MunicipalityMapCadastroPresenter
{
    /** @var array<string, string> */
    public const FILL_COLORS = [
        'cadastro_green' => '#10b981',
        'cadastro_yellow' => '#fbbf24',
        'cadastro_red' => '#f43f5e',
        'cadastro_neutral' => '#cbd5e1',
        'cadastro_error' => '#64748b',
        'cadastro_pending' => '#94a3b8',
    ];

    /**
     * @param  array<string, mixed>  $row  Linha do {@see \App\Support\Rx\RxCityMetricsCollector}
     * @return array<string, mixed>
     */
    public static function fromRxRow(array $row, int $vigenteYear): array
    {
        $sem = RxSemaphore::fromRow($row);
        $semaforo = (string) ($sem['status'] ?? 'neutral');
        $fillKey = self::fillKeyForSemaforo($semaforo);
        $prog = $row['progresso_cadastro_pct'] ?? null;
        $attention = self::attentionFor($semaforo, $row);

        return [
            'vigente_ano' => $vigenteYear,
            'ok' => (bool) ($row['ok'] ?? false),
            'semaforo' => $semaforo,
            'semaforo_label' => (string) ($sem['label'] ?? ''),
            'semaforo_title' => (string) ($sem['title'] ?? ''),
            'map_fill_key' => $fillKey,
            'map_fill_color' => self::FILL_COLORS[$fillKey] ?? self::FILL_COLORS['cadastro_neutral'],
            'progresso_pct' => $prog !== null ? round((float) $prog, 1) : null,
            'progresso_label' => $prog !== null
                ? number_format((float) $prog, 1, ',', '.').'%'
                : null,
            'registros_restantes' => (int) ($row['registros_restantes'] ?? 0),
            'falta_matriculas' => (int) ($row['falta_matriculas'] ?? 0),
            'falta_turmas' => (int) ($row['falta_turmas'] ?? 0),
            'meta_matriculas_alvo' => (int) ($row['meta_matriculas_alvo'] ?? 0),
            'meta_referencia_ano' => (int) ($row['meta_referencia_ano'] ?? 0),
            'meta_saltos' => (int) ($row['meta_saltos'] ?? 0),
            'meta_ano_imediato_zerado' => (bool) ($row['meta_ano_imediato_zerado'] ?? false),
            'anterior_ano' => (int) ($row['anterior_ano'] ?? 0),
            'matriculas_vigente' => (int) ($row['matriculas_vigente'] ?? 0),
            'attention_level' => $attention['level'],
            'attention_message' => $attention['message'],
            'rx_url' => route('dashboard.rx'),
        ];
    }

    /**
     * Chave de cor do marcador: conexão quando não há base; senão cadastro RX.
     */
    public static function resolveMapFillKey(string $connectionStatus, ?array $cadastro): string
    {
        if (! in_array($connectionStatus, ['ready', 'inactive_setup'], true)) {
            return $connectionStatus;
        }

        if ($cadastro === null) {
            return 'cadastro_pending';
        }

        return (string) ($cadastro['map_fill_key'] ?? 'cadastro_neutral');
    }

    public static function fillKeyForSemaforo(string $semaforo): string
    {
        return match ($semaforo) {
            'green' => 'cadastro_green',
            'yellow' => 'cadastro_yellow',
            'red' => 'cadastro_red',
            'error' => 'cadastro_error',
            default => 'cadastro_neutral',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{level: string, message: string}
     */
    private static function attentionFor(string $semaforo, array $row): array
    {
        if (! ($row['ok'] ?? false) && ! (($row['situacao_codigo'] ?? '') === 'parcial')) {
            $err = (string) ($row['error'] ?? __('Consulta indisponível'));

            return [
                'level' => 'unavailable',
                'message' => $err,
            ];
        }

        $anoImediatoZerado = (bool) ($row['meta_ano_imediato_zerado'] ?? false);
        $saltos = (int) ($row['meta_saltos'] ?? 0);
        $refAno = (int) ($row['meta_referencia_ano'] ?? 0);

        return match ($semaforo) {
            'green' => [
                'level' => 'praise',
                'message' => $anoImediatoZerado && $saltos > 0 && $refAno > 0
                    ? __('Meta RX atingida com referência em :ref (+:n salto(s)). O ano anterior imediato estava sem cadastro — confira o painel RX.', [
                        'ref' => (string) $refAno,
                        'n' => $saltos,
                    ])
                    : __('Meta de cadastro atingida — reconheça o trabalho da equipe municipal.'),
            ],
            'yellow' => [
                'level' => 'watch',
                'message' => $anoImediatoZerado && $saltos > 0 && $refAno > 0
                    ? __('Cadastro em curso — meta com referência em :ref (+:n salto(s)); :ant sem cadastro.', [
                        'ref' => (string) $refAno,
                        'n' => $saltos,
                        'ant' => (int) ($row['anterior_ano'] ?? 0) > 0
                            ? (string) (int) $row['anterior_ano']
                            : __('ano anterior'),
                    ])
                    : __('Cadastro em curso — acompanhe até concluir a meta do ano vigente.'),
            ],
            'red' => [
                'level' => 'urgent',
                'message' => __('Prioridade de atenção: cadastro abaixo da meta RX.'),
            ],
            'neutral' => [
                'level' => 'neutral',
                'message' => __('Sem referência histórica para meta — valide anos anteriores na base.'),
            ],
            default => [
                'level' => 'unavailable',
                'message' => (string) ($row['error'] ?? __('Situação de cadastro indisponível.')),
            ],
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $byCityId
     * @return list<array{status: string, label: string, description: string, color: string, count: int}>
     */
    public static function legendItems(array $byCityId): array
    {
        $counts = array_fill_keys(array_keys(self::FILL_COLORS), 0);
        foreach ($byCityId as $cadastro) {
            $key = (string) ($cadastro['map_fill_key'] ?? 'cadastro_neutral');
            if (array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        $definitions = [
            'cadastro_green' => [
                'label' => __('Meta OK'),
                'description' => __('Progresso de cadastro atinge ou supera a meta RX (ano vigente).'),
            ],
            'cadastro_yellow' => [
                'label' => __('Em curso'),
                'description' => __('Entre :pct% e 100% da meta — acompanhar.', [
                    'pct' => number_format((float) config('rx.semaphore.yellow_min_progress', 75), 0, ',', '.'),
                ]),
            ],
            'cadastro_red' => [
                'label' => __('Atenção'),
                'description' => __('Abaixo do limiar RX — necessidade de intervenção no cadastro.'),
            ],
            'cadastro_neutral' => [
                'label' => __('Sem base meta'),
                'description' => __('Sem turmas/matrículas de referência para calcular a meta.'),
            ],
            'cadastro_error' => [
                'label' => __('Erro consulta'),
                'description' => __('Falha de conexão ou consulta à base i-Educar.'),
            ],
        ];

        $items = [];
        foreach ($definitions as $status => $meta) {
            $items[] = [
                'status' => $status,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'color' => self::FILL_COLORS[$status],
                'count' => $counts[$status],
            ];
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    public static function fillColorsForJs(): array
    {
        return self::FILL_COLORS;
    }
}
