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
                'title' => __('Indicador meta'),
                'description' => __('Cumprimento da meta de cadastro (volume vigente em relação à meta). Verde: meta atingida. Amarelo: em andamento (≥ :pct% da meta). Vermelho: abaixo do limiar. Cinza: sem ano de referência com dados. Não mede qualidade cadastral nem Censo aprovado.', ['pct' => (int) config('rx.semaphore.yellow_min_progress', 75)]),
            ],
            [
                'key' => 'municipio',
                'title' => __('Município'),
                'description' => __('Unidade da rede com barra Censo e, abaixo, projeção FUNDEB indicativa em R$ para o próximo exercício (matrículas vigentes × VAAF — mesma base da aba Finanças → FUNDEB na Consultoria).'),
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
                'description' => __('Diferença das matrículas vigentes em relação ao ano :ano imediato (não é a meta). Se :ano estiver zerado e houver matrículas no vigente, mostra "novo cadastro" em vez de percentual.', ['ano' => $anteriorYear]),
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
                    'Ano de referência com turmas ou matrículas > 0 (busca até :n anos para trás se :a estiver zerado). Meta alvo = volume desse ano × (1 + :pct%)^saltos, em que cada salto é um ano a mais para trás em relação a :a. Abaixo do alvo: ritmo recente (turmas e matrículas em 24h, 48h e 72h) e mini-gráfico das últimas 72h — passe o rato para ver o detalhe por intervalo.',
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
                'description' => __('Percentual em relação à meta (turmas e matrículas com alvo > 0): usa o menor progresso entre as dimensões — o gargalo define o indicador.'),
            ],
            [
                'key' => 'falta',
                'title' => __('Pendente'),
                'description' => __('Turmas e matrículas ainda abaixo da meta alvo (não soma enturmações, para evitar duplicar o mesmo cadastro).'),
            ],
            [
                'key' => 'dias',
                'title' => __('Dias p/ meta'),
                'description' => __('Dias estimados para fechar o cadastro ao ritmo observado na quinzena (quando há movimento recente).'),
            ],
            [
                'key' => 'situacao',
                'title' => __('Leitura dos dados'),
                'description' => __('Se o RX conseguiu carregar as métricas do i-Educar neste município. Completa: blocos principais obtidos. Parcial: conexão OK mas algum bloco (Censo, meta, ritmo) falhou. Conexão: host/credenciais. Consulta: SQL/schema — diferente do teste rápido na aba Conexões. Não indica se o cadastro escolar está correto.'),
            ],
        ];
    }
}
