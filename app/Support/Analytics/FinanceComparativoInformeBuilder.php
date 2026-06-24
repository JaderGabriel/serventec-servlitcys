<?php

namespace App\Support\Analytics;

/**
 * Informes narrativos para a aba Comparativo (consultoria municipal).
 */
final class FinanceComparativoInformeBuilder
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *   available: bool,
     *   aviso: string,
     *   blocos: list<array<string, mixed>>
     * }
     */
    public static function build(array $data): array
    {
        if (! (bool) ($data['available'] ?? false)) {
            return self::empty();
        }

        $blocos = array_values(array_filter([
            self::blocoEvolucaoCadastro($data),
            self::blocoImpactoFinanceiro($data),
            self::blocoProjecao($data),
            self::blocoRecomendacoes($data),
        ]));

        return [
            'available' => $blocos !== [],
            'aviso' => __(
                'Síntese automática para reuniões com a gestão municipal. Cruza cadastro i-Educar e referências FUNDEB importadas — não substitui portaria FNDE nem prestação de contas.'
            ),
            'blocos' => $blocos,
        ];
    }

    /**
     * @return array{available: bool, aviso: string, blocos: list<array<string, mixed>>}
     */
    private static function empty(): array
    {
        return [
            'available' => false,
            'aviso' => '',
            'blocos' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return ?array<string, mixed>
     */
    private static function blocoEvolucaoCadastro(array $data): ?array
    {
        $baseYear = (int) ($data['base_year'] ?? 0);
        $prevYear = (int) ($data['prev_year'] ?? 0);
        $variacoes = is_array($data['variacoes'] ?? null) ? $data['variacoes'] : [];
        $cadastro = array_values(array_filter(
            $variacoes,
            static fn (array $r): bool => ($r['kind'] ?? '') === 'count',
        ));

        if ($cadastro === []) {
            return null;
        }

        $indicadores = [];
        $acoes = [];
        $status = 'neutral';
        $statusLabel = __('Leitura cadastral');

        foreach ($cadastro as $row) {
            $indicadores[] = [
                'label' => (string) ($row['label'] ?? ''),
                'value' => (string) ($row['base_fmt'] ?? '—'),
                'hint' => (string) ($row['delta_label'] ?? '').' · '.($row['leitura'] ?? ''),
            ];
            $dir = (string) ($row['direction'] ?? '');
            if ($dir === 'down') {
                $status = 'danger';
                $statusLabel = __('Atenção — retração');
            } elseif ($dir === 'up' && $status !== 'danger') {
                $status = 'success';
                $statusLabel = __('Avanço cadastral');
            } elseif (in_array($dir, ['missing_base', 'missing_prev'], true)) {
                $status = 'warning';
                $statusLabel = __('Dados incompletos');
            }
        }

        if ($status === 'danger') {
            $acoes[] = __('Validar matrículas e enturmações no i-Educar antes do fecho do Censo.');
            $acoes[] = __('Confrontar com a aba Matrículas e com o ritmo de cadastro em Censo.');
        } elseif ($status === 'success') {
            $acoes[] = __('Manter conferência de duplicidades e distorção idade-série para consolidar o ganho.');
        }

        return [
            'id' => 'evolucao_cadastro',
            'titulo' => __('Evolução da rede — :base × :anterior', ['base' => (string) $baseYear, 'anterior' => (string) $prevYear]),
            'subtitulo' => __('Matrículas, alunos distintos e turmas no recorte dos filtros aplicados.'),
            'status' => $status,
            'status_label' => $statusLabel,
            'paragrafos' => [
                __(
                    'O comparativo mede o volume cadastral entre dois exercícios letivos na mesma rede filtrada (escola, curso e turno quando seleccionados).'
                ),
            ],
            'indicadores' => $indicadores,
            'acoes' => $acoes,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return ?array<string, mixed>
     */
    private static function blocoImpactoFinanceiro(array $data): ?array
    {
        $detail = is_array($data['base_year_detail'] ?? null) ? $data['base_year_detail'] : [];
        $recursos = null;
        foreach (is_array($data['variacoes'] ?? null) ? $data['variacoes'] : [] as $row) {
            if (($row['kind'] ?? '') === 'money') {
                $recursos = $row;
                break;
            }
        }

        if ($recursos === null && ($detail['previsao_base_label'] ?? '—') === '—') {
            return null;
        }

        $status = match ((string) ($recursos['direction'] ?? 'missing')) {
            'up' => 'success',
            'down' => 'danger',
            'missing_base', 'missing_prev', 'missing' => 'warning',
            default => 'neutral',
        };

        $indicadores = [
            [
                'label' => __('Previsão base (:ano)', ['ano' => (string) ($data['base_year'] ?? '')]),
                'value' => (string) ($detail['previsao_base_label'] ?? '—'),
                'hint' => (string) ($detail['vaaf_fonte'] ?? ''),
            ],
            [
                'label' => __('VAAF de referência'),
                'value' => (string) ($detail['vaaf_label'] ?? '—'),
                'hint' => __('Matrículas: :n', ['n' => (string) ($detail['matriculas_fmt'] ?? '—')]),
            ],
        ];

        if ($recursos !== null) {
            $indicadores[] = [
                'label' => __('Variação face ao ano anterior'),
                'value' => (string) ($recursos['delta_label'] ?? '—'),
                'hint' => (string) ($recursos['leitura'] ?? ''),
            ];
        }

        $acoes = [
            __('Importar ou actualizar VAAF municipal em Admin → Compatibilidade → FUNDEB.'),
            __('Cruzar com Discrepâncias para estimar perdas por pendências de cadastro.'),
        ];

        return [
            'id' => 'impacto_financeiro',
            'titulo' => __('Impacto financeiro indicativo (FUNDEB)'),
            'subtitulo' => __('Modelo matrículas × VAAF por exercício — valores de planejamento, não repasse oficial.'),
            'status' => $status,
            'status_label' => match ($status) {
                'success' => __('Pressão financeira positiva'),
                'danger' => __('Queda de volume indicativo'),
                'warning' => __('Referência incompleta'),
                default => __('Estimativa estável'),
            },
            'paragrafos' => [
                __(
                    'A mudança de recursos reflecte simultaneamente o volume de matrículas e o VAAF aplicável em cada ano. Variações fortes com matrículas estáveis sugerem actualização de portaria ou troca de fonte de referência.'
                ),
            ],
            'indicadores' => $indicadores,
            'acoes' => $acoes,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return ?array<string, mixed>
     */
    private static function blocoProjecao(array $data): ?array
    {
        $proj = is_array($data['next_year_projection'] ?? null) ? $data['next_year_projection'] : [];
        if (! (bool) ($proj['available'] ?? false)) {
            return [
                'id' => 'projecao_exercicio',
                'titulo' => __('Projeção para :ano', ['ano' => (string) ($data['next_year'] ?? '')]),
                'subtitulo' => __('Cenário indicativo indisponível.'),
                'status' => 'warning',
                'status_label' => __('Sem VAAF ou matrículas'),
                'paragrafos' => [
                    __('Configure referência FUNDEB do exercício seguinte ou confirme matrículas no ano base para estimar o cenário.'),
                ],
                'indicadores' => [],
                'acoes' => [
                    __('Abrir a aba FUNDEB e importar dados abertos FNDE quando disponíveis.'),
                ],
            ];
        }

        $tone = (string) ($proj['tone'] ?? 'blue');

        return [
            'id' => 'projecao_exercicio',
            'titulo' => __('Projeção para :ano', ['ano' => (string) ($proj['year'] ?? $data['next_year'] ?? '')]),
            'subtitulo' => __('Pressupõe matrículas do ano base mantidas.'),
            'status' => $tone === 'rose' ? 'danger' : ($tone === 'emerald' ? 'success' : 'neutral'),
            'status_label' => __('Cenário :ano', ['ano' => (string) ($proj['year'] ?? '')]),
            'paragrafos' => [
                (string) ($proj['note'] ?? ''),
            ],
            'indicadores' => [
                ['label' => __('Previsão indicativa'), 'value' => (string) ($proj['previsao_label'] ?? '—'), 'hint' => null],
                ['label' => __('Δ face ao ano base'), 'value' => (string) ($proj['delta_label'] ?? '—'), 'hint' => null],
            ],
            'acoes' => [
                __('Apresentar à equipe de planejamento como ordem de grandeza — validar com portaria publicada.'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return ?array<string, mixed>
     */
    private static function blocoRecomendacoes(array $data): ?array
    {
        $alerts = is_array($data['alerts'] ?? null) ? $data['alerts'] : [];
        if ($alerts === []) {
            return null;
        }

        $acoes = [];
        foreach ($alerts as $alert) {
            $title = (string) ($alert['title'] ?? '');
            if ($title !== '') {
                $acoes[] = $title.' — '.(string) ($alert['message'] ?? '');
            }
        }

        $hasDanger = count(array_filter($alerts, static fn ($a) => in_array($a['tone'] ?? '', ['danger', 'rose'], true))) > 0;

        return [
            'id' => 'recomendacoes',
            'titulo' => __('Prioridades para a consultoria'),
            'subtitulo' => __('Consolidação dos alertas automáticos desta análise.'),
            'status' => $hasDanger ? 'warning' : 'neutral',
            'status_label' => $hasDanger ? __('Ações prioritárias') : __('Monitorização'),
            'paragrafos' => [
                __('Use este bloco como roteiro de reunião com secretaria e equipe técnica do município.'),
            ],
            'indicadores' => [],
            'acoes' => array_slice($acoes, 0, 8),
        ];
    }
}
