<?php

namespace App\Support\Rx;

/**
 * Textos de ajuda das colunas do painel RX.
 */
final class RxColumnHelp
{
    /**
     * @return list<array{key: string, title: string, description: string}>
     */
    public static function columns(int $vigenteYear, int $anteriorYear): array
    {
        $pctSalto = (float) config('rx.meta_pct_per_salto', 5.0);

        return [
            [
                'key' => 'semaforo',
                'title' => __('Semáforo'),
                'description' => __('Verde: meta de cadastro cumprida. Amarelo: em curso (≥ :pct% da meta). Vermelho: abaixo do limiar. Cinza: sem ano de referência com dados.', ['pct' => (int) config('rx.semaphore.yellow_min_progress', 75)]),
            ],
            [
                'key' => 'municipio',
                'title' => __('Município'),
                'description' => __('Unidade da rede com base i-Educar configurada no ServLitcys.'),
            ],
            [
                'key' => 'alunos',
                'title' => __('Alunos'),
                'description' => __('Alunos distintos com matrícula ativa no ano letivo :ano (filtro de turma/ano).', ['ano' => $vigenteYear]),
            ],
            [
                'key' => 'matriculas',
                'title' => __('Matrículas'),
                'description' => __('Matrículas ativas no ano :v. A linha inferior mostra o volume em :a para comparação.', ['v' => $vigenteYear, 'a' => $anteriorYear]),
            ],
            [
                'key' => 'delta',
                'title' => __('Δ vs :ano', ['ano' => $anteriorYear]),
                'description' => __('Diferença absoluta e percentual das matrículas vigentes face ao ano :ano imediato (não usa a meta ajustada).', ['ano' => $anteriorYear]),
            ],
            [
                'key' => 'turmas',
                'title' => __('Turmas'),
                'description' => __('Turmas distintas no ano letivo :ano.', ['ano' => $vigenteYear]),
            ],
            [
                'key' => 'meta',
                'title' => __('Meta de cadastro'),
                'description' => __(
                    'Ano de referência com turmas ou matrículas > 0 (busca até :n anos para trás se :a estiver zerado). Meta alvo = volume desse ano × (1 + :pct%)^saltos, em que cada salto é um ano a mais para trás em relação a :a.',
                    [
                        'n' => (int) config('rx.meta_lookback_years', 10),
                        'a' => $anteriorYear,
                        'pct' => number_format($pctSalto, 0, ',', '.'),
                    ]
                ),
            ],
            [
                'key' => 'censo',
                'title' => __('Censo'),
                'description' => __('Percentagem de escolas com exportação ou fecho no Censo Escolar (ano :ano).', ['ano' => $vigenteYear]),
            ],
            [
                'key' => 'progresso',
                'title' => __('Progresso cad.'),
                'description' => __('Percentagem das matrículas vigentes face à meta de matrículas (após fator de saltos).'),
            ],
            [
                'key' => 'falta',
                'title' => __('Em falta'),
                'description' => __('Soma estimada de turmas, matrículas e enturmações ainda abaixo da meta.'),
            ],
            [
                'key' => 'dias',
                'title' => __('Dias p/ meta'),
                'description' => __('Dias estimados para fechar o cadastro ao ritmo observado na quinzena (quando há movimento recente).'),
            ],
            [
                'key' => 'situacao',
                'title' => __('Situação'),
                'description' => __('OK: métricas principais obtidas. Parcial: conexão OK mas algum bloco (Censo, meta, ritmo) falhou. Conexão: host/credenciais. Consulta: SQL/schema i-Educar — diferente do teste rápido na aba Conexões.'),
            ],
        ];
    }
}
