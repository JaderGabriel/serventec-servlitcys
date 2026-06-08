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
                'description' => __('Cumprimento da meta de cadastro (volume vigente em relação à meta). Verde: meta atingida. Amarelo: em andamento (≥ :pct% da meta). Vermelho: abaixo do limiar. Cinza: sem ano de referência com dados.', ['pct' => (int) config('rx.semaphore.yellow_min_progress', 75)]),
            ],
            [
                'key' => 'municipio',
                'title' => __('Município'),
                'description' => __('Unidade da rede com barra Censo e projeção FUNDEB indicativa.'),
            ],
            [
                'key' => 'meta',
                'title' => __('Meta alvo'),
                'description' => __(
                    'Volume esperado de turmas e matrículas (referência histórica + :pct% por salto de ano sem dados). Coluna violeta — compare com as colunas verdes à direita.',
                    ['pct' => number_format($pctSalto, 0, ',', '.')]
                ),
            ],
            [
                'key' => 'alunos',
                'title' => __('Alunos'),
                'description' => __('Pessoas distintas com matrícula activa no :ano.', ['ano' => $vigenteYear]),
            ],
            [
                'key' => 'matriculas',
                'title' => __('Matrículas'),
                'description' => __('Registos activos no :ano. Linha inferior = total em :a (comparação histórica).', ['ano' => $vigenteYear, 'a' => $anteriorYear]),
            ],
            [
                'key' => 'turmas',
                'title' => __('Turmas'),
                'description' => __('Classes abertas no :ano.', ['ano' => $vigenteYear]),
            ],
            [
                'key' => 'progresso',
                'title' => __('Progresso'),
                'description' => __('Percentual face à meta (gargalo entre turmas e matrículas), barra visual e ritmo de cadastro nas últimas 72h.'),
            ],
            [
                'key' => 'falta',
                'title' => __('Falta'),
                'description' => __('Registos que ainda faltam para atingir a meta — turmas e matrículas em separado.'),
            ],
            [
                'key' => 'dias',
                'title' => __('Dias p/ meta'),
                'description' => __('Prazo estimado para fechar o gap, com base no ritmo recente de cadastro.'),
            ],
            [
                'key' => 'delta',
                'title' => __('Δ vs :ano', ['ano' => $anteriorYear]),
                'description' => __('Variação de matrículas face ao ano :ano imediato (contexto, não é a meta).', ['ano' => $anteriorYear]),
            ],
            [
                'key' => 'censo',
                'title' => __('Censo'),
                'description' => __('Percentagem de escolas exportadas ou fechadas no Censo (:ano).', ['ano' => $vigenteYear]),
            ],
            [
                'key' => 'situacao',
                'title' => __('Leitura dos dados'),
                'description' => __('Estado da consulta i-Educar neste município (completa, parcial, conexão ou SQL).'),
            ],
        ];
    }
}
