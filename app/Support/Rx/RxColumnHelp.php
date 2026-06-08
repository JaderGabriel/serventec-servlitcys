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
                'description' => __('Pessoas distintas com pelo menos uma matrícula ativa no :ano. Se matrículas > alunos, pode haver transferências duplicadas — ver Discrepâncias.', ['ano' => $vigenteYear]),
            ],
            [
                'key' => 'matriculas',
                'title' => __('Matrículas'),
                'description' => __('Registos de matrícula ativos no :ano (cada vínculo aluno↔rede no ano letivo). Número grande = vigente; linha inferior = total no :a para comparar evolução — não confundir com a meta alvo.', ['ano' => $vigenteYear, 'a' => $anteriorYear]),
            ],
            [
                'key' => 'turmas',
                'title' => __('Turmas'),
                'description' => __('Classes/salas distintas abertas no :ano. Abrir turma é um passo à parte de matricular o aluno.', ['ano' => $vigenteYear]),
            ],
            [
                'key' => 'delta',
                'title' => __('Δ vs :ano', ['ano' => $anteriorYear]),
                'description' => __('Variação só de matrículas: vigente menos :ano imediato. Não inclui turmas nem alunos. Se :ano estiver zerado, mostra «novo cadastro».', ['ano' => $anteriorYear]),
            ],
            [
                'key' => 'meta',
                'title' => __('Meta de cadastro'),
                'description' => __(
                    'Bloco «Agora» repete turmas e matrículas vigentes; «Meta alvo» é o volume esperado (referência histórica + :pct% por salto de ano sem dados). Ritmo = cadastros recentes nas últimas 24h, 48h e 72h (data gravada no i-Educar).',
                    [
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
                'description' => __('Percentual face à meta em turmas e matrículas. O valor principal usa o menor dos dois — o que mais falta define o semáforo.'),
            ],
            [
                'key' => 'falta',
                'title' => __('Pendente'),
                'description' => __('Quanto falta para a meta: turmas e matrículas em separado (enturmações não entram, para não contar o mesmo trabalho duas vezes).'),
            ],
            [
                'key' => 'dias',
                'title' => __('Dias p/ meta'),
                'description' => __('Prazo estimado para fechar o gap de matrículas/turmas, com base no ritmo de cadastro da quinzena recente.'),
            ],
            [
                'key' => 'situacao',
                'title' => __('Leitura dos dados'),
                'description' => __('Se o RX conseguiu carregar as métricas do i-Educar neste município. Completa: blocos principais obtidos. Parcial: conexão OK mas algum bloco (Censo, meta, ritmo) falhou. Conexão: host/credenciais. Consulta: SQL/schema — diferente do teste rápido na aba Conexões. Não indica se o cadastro escolar está correto.'),
            ],
        ];
    }
}
