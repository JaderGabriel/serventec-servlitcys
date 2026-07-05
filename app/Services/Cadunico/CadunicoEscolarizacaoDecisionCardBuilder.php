<?php

namespace App\Services\Cadunico;

use App\Models\InepCensoMunicipioMatricula;
use App\Services\Cadunico\CadunicoFaixaEtariaMetodo;

/** Card de decisão: escolarização por faixa etária (CadÚnico × rede × Censo) e bloco EJA. */
final class CadunicoEscolarizacaoDecisionCardBuilder
{
    /**
     * @var array<string, array{total: string, municipal: string}>
     */
    private const FAIXA_CENSO_MAP = [
        'criancas_4_5' => [
            'total' => 'matriculas_infantil',
            'municipal' => 'matriculas_infantil_municipal',
        ],
        'criancas_6_10' => [
            'total' => 'matriculas_fundamental_1',
            'municipal' => 'matriculas_fundamental_1_municipal',
        ],
        'criancas_11_14' => [
            'total' => 'matriculas_fundamental_2',
            'municipal' => 'matriculas_fundamental_2_municipal',
        ],
        'criancas_15_17' => [
            'total' => 'matriculas_medio',
            'municipal' => 'matriculas_medio_municipal',
        ],
    ];

    /**
     * @param  array<string, mixed>  $gap  Resultado de CadunicoRedeGapAnalyzer::analyze
     * @return array<string, mixed>
     */
    public function build(array $gap, ?InepCensoMunicipioMatricula $censoRow, ?int $ieducarEjaMatriculas = null): array
    {
        if (! ($gap['available'] ?? false)) {
            return [
                'available' => false,
                'message' => (string) ($gap['nota'] ?? __('Importe CadÚnico e aplique filtros para ver o painel de escolarização.')),
            ];
        }

        $porFaixa = is_array($gap['por_faixa'] ?? null) ? $gap['por_faixa'] : [];
        if ($porFaixa === []) {
            return [
                'available' => false,
                'message' => __('Sem faixas etárias CadÚnico para montar o painel.'),
            ];
        }

        $linhas = [];
        $totais = [
            'cadunico' => 0,
            'na_rede_municipal' => 0,
            'no_municipio_censo' => 0,
            'fora_rede_municipal' => 0,
            'possivel_fora_escola' => 0,
        ];

        foreach ($porFaixa as $faixaRow) {
            $linha = $this->linhaFromFaixa($faixaRow, $censoRow);
            $linhas[] = $linha;
            $totais['cadunico'] += (int) $linha['cadunico'];
            $totais['na_rede_municipal'] += (int) $linha['na_rede_municipal'];
            $totais['fora_rede_municipal'] += (int) $linha['fora_rede_municipal'];
            if ($linha['no_municipio_censo'] !== null) {
                $totais['no_municipio_censo'] += (int) $linha['no_municipio_censo'];
            }
            if ($linha['possivel_fora_escola'] !== null) {
                $totais['possivel_fora_escola'] += (int) $linha['possivel_fora_escola'];
            }
        }

        usort($linhas, static fn (array $a, array $b): int => ($b['fora_rede_municipal'] ?? 0) <=> ($a['fora_rede_municipal'] ?? 0));

        $eja = $this->buildEjaBlock($censoRow, $ieducarEjaMatriculas);
        $prioridades = $this->prioridadesAcao($linhas, $eja, $gap);

        return [
            'available' => true,
            'metodo_faixa' => (string) ($gap['faixa_metodo'] ?? CadunicoFaixaEtariaMetodo::RATEIO),
            'faixa_cobertura_nascimento_pct' => $gap['faixa_cobertura_nascimento_pct'] ?? null,
            'censo_ajuste_aplicado' => (bool) ($gap['censo_ajuste_aplicado'] ?? false),
            'linhas' => $linhas,
            'totais' => $this->formatTotais($totais, $gap),
            'eja' => $eja,
            'prioridades_acao' => $prioridades,
            'legenda' => [
                'cadunico' => __('Famílias CadÚnico na faixa etária (vulnerabilidade no município).'),
                'na_rede' => __('Alunos distintos na rede municipal filtrada (i-Educar).'),
                'censo' => __('Matrículas no território municipal (Censo INEP — todas as redes).'),
                'fora_rede' => __('CadÚnico − rede municipal: candidatos a busca ativa na rede filtrada.'),
                'fora_escola' => __('CadÚnico − Censo na faixa: estimativa sem matrícula em qualquer rede do município.'),
                'eja' => __('EJA não entra nas faixas 4–17; bloco separado com oferta Censo e rede municipal.'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $faixaRow
     * @return array<string, mixed>
     */
    private function linhaFromFaixa(array $faixaRow, ?InepCensoMunicipioMatricula $censoRow): array
    {
        $key = (string) ($faixaRow['key'] ?? '');
        $cad = (int) ($faixaRow['cadunico'] ?? 0);
        $rede = (int) ($faixaRow['ieducar_estimado'] ?? 0);
        $foraRede = (int) ($faixaRow['gap'] ?? max(0, $cad - $rede));

        $censoTotal = $this->censoValue($censoRow, self::FAIXA_CENSO_MAP[$key]['total'] ?? null);
        $censoMunicipal = $this->censoValue($censoRow, self::FAIXA_CENSO_MAP[$key]['municipal'] ?? null);

        $possivelForaEscola = ($censoTotal !== null && $cad > 0)
            ? max(0, $cad - min($cad, $censoTotal))
            : null;

        $cobRede = $cad > 0 ? round(min(100.0, 100.0 * $rede / $cad), 1) : null;
        $cobTerritorio = ($cad > 0 && $censoTotal !== null)
            ? round(min(100.0, 100.0 * $censoTotal / $cad), 1)
            : null;

        $prioridade = match (true) {
            $foraRede >= 150 => 'alta',
            $foraRede >= 50 => 'media',
            $foraRede > 0 => 'baixa',
            default => null,
        };

        return [
            'faixa' => (string) ($faixaRow['faixa'] ?? $key),
            'key' => $key,
            'cadunico' => $cad,
            'cadunico_fmt' => number_format($cad, 0, ',', '.'),
            'na_rede_municipal' => $rede,
            'na_rede_municipal_fmt' => number_format($rede, 0, ',', '.'),
            'ieducar_por_idade' => isset($faixaRow['ieducar']),
            'no_municipio_censo' => $censoTotal,
            'no_municipio_censo_fmt' => $censoTotal !== null ? number_format($censoTotal, 0, ',', '.') : '—',
            'censo_municipal' => $censoMunicipal,
            'censo_municipal_fmt' => $censoMunicipal !== null ? number_format($censoMunicipal, 0, ',', '.') : '—',
            'fora_rede_municipal' => $foraRede,
            'fora_rede_municipal_fmt' => number_format($foraRede, 0, ',', '.'),
            'possivel_fora_escola' => $possivelForaEscola,
            'possivel_fora_escola_fmt' => $possivelForaEscola !== null ? number_format($possivelForaEscola, 0, ',', '.') : '—',
            'cobertura_rede_pct' => $cobRede,
            'cobertura_rede_label' => $cobRede !== null ? number_format($cobRede, 1, ',', '.').'%' : '—',
            'cobertura_territorio_pct' => $cobTerritorio,
            'cobertura_territorio_label' => $cobTerritorio !== null ? number_format($cobTerritorio, 1, ',', '.').'%' : '—',
            'fundeb_gap_label' => (string) ($faixaRow['fundeb_gap_label'] ?? '—'),
            'prioridade' => $prioridade,
            'decisao' => $this->decisaoFaixa($foraRede, $possivelForaEscola, $cobRede, (string) ($faixaRow['faixa'] ?? $key)),
            'tone' => $foraRede > 0 ? 'amber' : 'emerald',
        ];
    }

    private function decisaoFaixa(int $foraRede, ?int $possivelForaEscola, ?float $cobRede, string $faixaLabel): string
    {
        if ($foraRede <= 0) {
            return __('Cobertura alinhada na faixa :faixa — manter monitoramento.', ['faixa' => $faixaLabel]);
        }

        if ($possivelForaEscola !== null && $possivelForaEscola >= max(20, (int) round($foraRede * 0.4))) {
            return __(':faixa: :n possivelmente sem matrícula em nenhuma rede do município — priorizar busca ativa e articulação CRAS/escola.', [
                'faixa' => $faixaLabel,
                'n' => number_format($possivelForaEscola, 0, ',', '.'),
            ]);
        }

        if ($cobRede !== null && $cobRede < 85.0) {
            return __(':faixa: :n fora da rede municipal — verificar matrícula em rede estadual/privada/EJA antes de ampliar vagas.', [
                'faixa' => $faixaLabel,
                'n' => number_format($foraRede, 0, ',', '.'),
            ]);
        }

        return __(':faixa: :n candidatos a integração na rede municipal filtrada.', [
            'faixa' => $faixaLabel,
            'n' => number_format($foraRede, 0, ',', '.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEjaBlock(?InepCensoMunicipioMatricula $censoRow, ?int $ieducarEja): array
    {
        $censoTotal = $this->censoValue($censoRow, 'matriculas_eja');
        $censoMunicipal = $this->censoValue($censoRow, 'matriculas_eja_municipal');
        $censoNaoMunicipal = $this->censoValue($censoRow, 'matriculas_eja_nao_municipal');

        if ($censoTotal === null && ($ieducarEja === null || $ieducarEja <= 0)) {
            return [
                'available' => false,
                'message' => __('Importe Educacenso municipal para comparar oferta EJA.'),
            ];
        }

        $rede = max(0, (int) ($ieducarEja ?? 0));
        $decisao = __('EJA atende jovens e adultos fora das faixas 4–17 do CadÚnico — use para reinserção escolar e certificação.');

        if ($censoTotal !== null && $censoMunicipal !== null && $censoNaoMunicipal !== null && $censoNaoMunicipal > $censoMunicipal) {
            $decisao = __('Maior parte da EJA no município está fora da rede municipal (:n) — oportunidade de parceria ou expansão municipal.', [
                'n' => number_format($censoNaoMunicipal, 0, ',', '.'),
            ]);
        } elseif ($rede > 0 && $censoMunicipal !== null && $rede < (int) round($censoMunicipal * 0.85)) {
            $decisao = __('Matrículas EJA no i-Educar abaixo do Censo municipal — conferir cadastro de turmas/cursos EJA.');
        }

        return [
            'available' => true,
            'ieducar_municipal' => $rede > 0 ? $rede : null,
            'ieducar_municipal_fmt' => $rede > 0 ? number_format($rede, 0, ',', '.') : '—',
            'censo_total' => $censoTotal,
            'censo_total_fmt' => $censoTotal !== null ? number_format($censoTotal, 0, ',', '.') : '—',
            'censo_municipal' => $censoMunicipal,
            'censo_municipal_fmt' => $censoMunicipal !== null ? number_format($censoMunicipal, 0, ',', '.') : '—',
            'censo_nao_municipal' => $censoNaoMunicipal,
            'censo_nao_municipal_fmt' => $censoNaoMunicipal !== null ? number_format($censoNaoMunicipal, 0, ',', '.') : '—',
            'decisao' => $decisao,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @param  array<string, mixed>  $eja
     * @param  array<string, mixed>  $gap
     * @return list<string>
     */
    private function prioridadesAcao(array $linhas, array $eja, array $gap): array
    {
        $out = [];
        $top = array_slice(array_filter($linhas, static fn (array $l): bool => ((int) ($l['fora_rede_municipal'] ?? 0)) > 0), 0, 2);

        foreach ($top as $linha) {
            $out[] = (string) ($linha['decisao'] ?? '');
        }

        if (($gap['censo_ajuste_aplicado'] ?? false) && ($gap['censo_nao_municipal'] ?? 0) > 0) {
            $out[] = __('Desconto Censo aplicado: :n matrícula(s) em redes não municipais (estadual/privada/EJA) já consideradas na lacuna global.', [
                'n' => number_format((int) $gap['censo_nao_municipal'], 0, ',', '.'),
            ]);
        }

        if (($eja['available'] ?? false) && filled($eja['decisao'] ?? null)) {
            $out[] = (string) $eja['decisao'];
        }

        return array_values(array_filter($out));
    }

    /**
     * @param  array<string, int>  $totais
     * @param  array<string, mixed>  $gap
     * @return array<string, mixed>
     */
    private function formatTotais(array $totais, array $gap): array
    {
        $cad = max(0, (int) ($totais['cadunico'] ?? 0));
        $rede = max(0, (int) ($totais['na_rede_municipal'] ?? 0));
        $fora = max(0, (int) ($totais['fora_rede_municipal'] ?? 0));
        $censo = (int) ($totais['no_municipio_censo'] ?? 0);
        $foraEscola = (int) ($totais['possivel_fora_escola'] ?? 0);

        return [
            'cadunico' => $cad,
            'cadunico_fmt' => number_format($cad, 0, ',', '.'),
            'na_rede_municipal' => $rede,
            'na_rede_municipal_fmt' => number_format($rede, 0, ',', '.'),
            'no_municipio_censo' => $censo > 0 ? $censo : null,
            'no_municipio_censo_fmt' => $censo > 0 ? number_format($censo, 0, ',', '.') : '—',
            'fora_rede_municipal' => $fora,
            'fora_rede_municipal_fmt' => number_format($fora, 0, ',', '.'),
            'possivel_fora_escola' => $foraEscola > 0 ? $foraEscola : null,
            'possivel_fora_escola_fmt' => $foraEscola > 0 ? number_format($foraEscola, 0, ',', '.') : '—',
            'cobertura_rede_label' => $cad > 0
                ? number_format(min(100.0, 100.0 * $rede / $cad), 1, ',', '.').'%'
                : '—',
            'gap_total_fmt' => (string) ($gap['gap_total_fmt'] ?? '—'),
        ];
    }

    private function censoValue(?InepCensoMunicipioMatricula $row, ?string $column): ?int
    {
        if ($row === null || $column === null || $column === '') {
            return null;
        }

        $value = (int) ($row->{$column} ?? 0);

        return $value > 0 ? $value : null;
    }
}
