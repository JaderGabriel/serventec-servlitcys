<?php

namespace App\Services\Cadunico;

use App\Models\City;
use App\Models\CadunicoMunicipioSnapshot;
use App\Models\InepCensoMunicipioMatricula;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Finance\MoneyMath;
use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Estima crianças/jovens em idade escolar no CadÚnico não refletidos como matrículas na rede i-Educar.
 */
final class CadunicoRedeGapAnalyzer
{
    /**
     * @param  list<array{etapa: string, matriculas: int}>  $ieducarPorEtapa
     * @param  array{nee_matriculas?: int, alunos_nee?: int, matriculas_aee_sem_cadastro?: int, alunos_aee_sem_cadastro?: int}  $inclusionHints
     * @param  array{available?: bool, metodo?: string, por_faixa?: array<string, int>}|null  $faixaCounts
     * @return array<string, mixed>
     */
    public function analyze(
        City $city,
        IeducarFilterState $filters,
        int $matriculasIeducar,
        int $alunosIeducar,
        array $ieducarPorEtapa,
        ?CadunicoMunicipioSnapshot $cadunico,
        ?int $censoMatriculas,
        float $vaaf,
        array $inclusionHints = [],
        ?array $faixaCounts = null,
        ?InepCensoMunicipioMatricula $censoRow = null,
    ): array {
        $cfg = config('ieducar.cadunico', []);
        if (! is_array($cfg)) {
            $cfg = [];
        }

        $coberturaAlerta = (float) ($cfg['cobertura_alerta_pct'] ?? 92.0);
        $baseRede = self::baseRede($matriculasIeducar, $alunosIeducar);

        if ($cadunico === null) {
            return [
                'available' => false,
                'cadunico_total_escolar' => null,
                'ieducar_matriculas' => $matriculasIeducar,
                'ieducar_alunos' => $alunosIeducar > 0 ? $alunosIeducar : null,
                'ieducar_base_calculo' => $baseRede,
                'gap_total' => null,
                'cobertura_pct' => null,
                'status' => 'missing_cadunico',
                'status_label' => __('Sem dados CadÚnico importados'),
                'por_faixa' => [],
                'por_etapa' => [],
                'vulnerabilidade' => ['available' => false],
                'cenarios_financeiros' => ['available' => false],
                'nota' => __('Sincronize CadÚnico em Admin → CadÚnico/Cecad (API → cache → CSV) ou `cadunico:sync-city`.'),
            ];
        }

        $cadTotal = $cadunico->totalCriancasEscolaridade();
        $faixaMetodo = ($faixaCounts['available'] ?? false)
            ? (string) ($faixaCounts['metodo'] ?? CadunicoFaixaEtariaMetodo::RATEIO)
            : CadunicoFaixaEtariaMetodo::RATEIO;

        $porFaixa = $this->faixasComLacuna($cadunico, $matriculasIeducar, $baseRede, $vaaf, $faixaCounts);

        $gapTotal = $cadTotal > 0 && $baseRede >= 0
            ? max(0, $cadTotal - $baseRede)
            : null;

        if ($faixaMetodo === CadunicoFaixaEtariaMetodo::IDADE && $porFaixa !== []) {
            $gapFaixas = array_sum(array_map(static fn (array $f): int => (int) ($f['gap'] ?? 0), $porFaixa));
            $gapTotal = max(0, $gapFaixas);
        }

        $gapBruto = $gapTotal;
        $censoAjuste = CadunicoCensoAjuste::apply($cadTotal, $baseRede, $gapTotal, $censoRow);
        if ($censoAjuste['aplicado']) {
            $gapTotal = $censoAjuste['gap_ajustado'];
        }

        $cadExibicao = $censoAjuste['aplicado']
            ? (int) $censoAjuste['cadunico_ajustado']
            : $cadTotal;

        $cobertura = ($cadTotal > 0 && $baseRede >= 0)
            ? round(min(100.0, 100.0 * $baseRede / $cadTotal), 1)
            : null;

        $status = match (true) {
            $gapTotal === null || $cadTotal <= 0 => 'neutral',
            $cobertura !== null && $cobertura >= $coberturaAlerta => 'success',
            ($gapTotal ?? 0) > 0 => 'warning',
            default => 'neutral',
        };

        $porEtapa = $this->distribuirGapPorEtapa($porFaixa, $ieducarPorEtapa, $baseRede, $cadTotal, $gapTotal ?? 0, $vaaf);

        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $impactoGap = ($gapTotal !== null && $gapTotal > 0 && $vaaf > 0)
            ? MoneyMath::multiplyVaaf($gapTotal, $vaaf)
            : null;

        $formulaBase = ($gapTotal !== null && $gapTotal > 0 && $vaaf > 0)
            ? self::formulaImpacto($gapTotal, $vaaf, $baseRede, $matriculasIeducar, $alunosIeducar, $fmt)
            : null;

        $censoGap = null;
        if ($censoMatriculas !== null && $censoMatriculas > 0 && $baseRede > 0) {
            $censoGap = max(0, $censoMatriculas - $baseRede);
        }

        $nota = __(
            'CadÚnico mede famílias em vulnerabilidade no município; nem toda criança cadastrada deveria estar na rede municipal (escolas estaduais, privadas, EJA). Use como busca ativa e planeamento, não como meta automática de matrícula.'
        );
        if ($faixaMetodo === CadunicoFaixaEtariaMetodo::IDADE) {
            $nota .= ' '.__(
                'Lacuna por faixa calculada com idade na data de corte (31/03) e alunos distintos no i-Educar.'
            );
        }
        if ($censoAjuste['aplicado'] && filled($censoAjuste['nota'] ?? null)) {
            $nota .= ' '.(string) $censoAjuste['nota'];
        }

        return [
            'available' => true,
            'cadunico_ano' => (int) $cadunico->ano_referencia,
            'cadunico_total_escolar' => $cadTotal,
            'cadunico_total_ajustado' => $censoAjuste['aplicado'] ? $cadExibicao : null,
            'cadunico_fonte' => (string) ($cadunico->fonte ?? ''),
            'cadunico_imported_at' => $cadunico->imported_at?->format('d/m/Y H:i'),
            'ieducar_matriculas' => $matriculasIeducar,
            'ieducar_alunos' => $alunosIeducar > 0 ? $alunosIeducar : null,
            'ieducar_base_calculo' => $baseRede,
            'censo_matriculas' => $censoMatriculas,
            'censo_gap' => $censoGap,
            'censo_nao_municipal' => $censoAjuste['nao_municipal_estimado'] > 0
                ? $censoAjuste['nao_municipal_estimado']
                : null,
            'censo_ajuste_aplicado' => $censoAjuste['aplicado'],
            'censo_ajuste_metodo' => $censoAjuste['metodo'],
            'gap_bruto' => $gapBruto,
            'gap_bruto_fmt' => $gapBruto !== null ? number_format($gapBruto, 0, ',', '.') : null,
            'gap_total' => $gapTotal,
            'gap_total_fmt' => $gapTotal !== null ? number_format($gapTotal, 0, ',', '.') : '—',
            'faixa_metodo' => $faixaMetodo,
            'faixa_cobertura_nascimento_pct' => ($faixaCounts['cobertura_nascimento_pct'] ?? null),
            'cobertura_pct' => $cobertura,
            'cobertura_label' => $cobertura !== null ? number_format($cobertura, 1, ',', '.').'%' : '—',
            'status' => $status,
            'status_label' => match ($status) {
                'success' => __('Cobertura elevada face ao CadÚnico'),
                'warning' => __('Potencial fora da rede municipal'),
                default => __('Leitura indicativa'),
            },
            'por_faixa' => $porFaixa,
            'por_etapa' => $porEtapa,
            'vulnerabilidade' => CadunicoVulnerabilidadeIndicators::fromSnapshot($cadunico),
            'cenarios_financeiros' => CadunicoFinanceScenarioBuilder::build(
                (int) ($gapTotal ?? 0),
                $vaaf,
                $matriculasIeducar,
                $alunosIeducar > 0 ? $alunosIeducar : $matriculasIeducar,
                $inclusionHints,
            ),
            'impacto_financeiro' => [
                'vaaf' => $vaaf,
                'vaaf_label' => $vaaf > 0 ? $fmt($vaaf) : '—',
                'gap_anual' => $impactoGap,
                'gap_anual_label' => $impactoGap !== null ? $fmt($impactoGap) : '—',
                'formula' => $formulaBase,
            ],
            'nota' => $nota,
        ];
    }

    private static function baseRede(int $matriculas, int $alunos): int
    {
        if ($alunos > 0 && $alunos < $matriculas) {
            return $alunos;
        }

        return max(0, $matriculas);
    }

    /**
     * @param  callable(float): string  $fmt
     */
    private static function formulaImpacto(
        int $gap,
        float $vaaf,
        int $baseRede,
        int $matriculas,
        int $alunos,
        callable $fmt,
    ): string {
        $total = $fmt(MoneyMath::multiplyVaaf($gap, $vaaf));
        if ($alunos > 0 && $alunos < $matriculas) {
            return __(':gap aluno(s) distinto(s) fora da rede (:mat registo(s) de matrícula) × :vaaf ≈ :total/ano.', [
                'gap' => number_format($gap, 0, ',', '.'),
                'mat' => number_format($matriculas, 0, ',', '.'),
                'vaaf' => $fmt($vaaf),
                'total' => $total,
            ]);
        }

        return __(':n criança(s)/jovem(ns) × :vaaf ≈ :total/ano (FUNDEB indicativo, matrículas adicionais se integradas à rede).', [
            'n' => number_format($gap, 0, ',', '.'),
            'vaaf' => $fmt($vaaf),
            'total' => $total,
        ]);
    }

    /**
     * @param  array{available?: bool, metodo?: string, por_faixa?: array<string, int>}|null  $faixaCounts
     * @return list<array<string, mixed>>
     */
    private function faixasComLacuna(
        CadunicoMunicipioSnapshot $snap,
        int $matriculasTotal,
        int $baseRede,
        float $vaaf,
        ?array $faixaCounts = null,
    ): array {
        $faixas = config('ieducar.cadunico.faixas_etarias', []);
        if (! is_array($faixas)) {
            return [];
        }

        $ieducarPorFaixa = is_array($faixaCounts['por_faixa'] ?? null) ? $faixaCounts['por_faixa'] : [];
        $usaIdade = ($faixaCounts['available'] ?? false)
            && ($faixaCounts['metodo'] ?? '') === CadunicoFaixaEtariaMetodo::IDADE
            && $ieducarPorFaixa !== [];

        $cadSum = max(1, $snap->totalCriancasEscolaridade());
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $out = [];

        foreach ($faixas as $faixa) {
            if (! is_array($faixa)) {
                continue;
            }
            $key = (string) ($faixa['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $cad = (int) ($snap->{$key} ?? 0);
            $share = $cad / $cadSum;
            $ieducarReal = $usaIdade ? max(0, (int) ($ieducarPorFaixa[$key] ?? 0)) : 0;
            $ieducarEst = $ieducarReal > 0
                ? $ieducarReal
                : ($matriculasTotal > 0 ? (int) round($baseRede * $share) : 0);
            $gap = max(0, $cad - $ieducarEst);
            $cob = $cad > 0 ? round(min(100.0, 100.0 * $ieducarEst / $cad), 1) : null;
            $fundeb = ($gap > 0 && $vaaf > 0) ? MoneyMath::multiplyVaaf($gap, $vaaf) : 0.0;

            $row = [
                'faixa' => (string) ($faixa['label'] ?? $key),
                'key' => $key,
                'cadunico' => $cad,
                'ieducar_estimado' => $ieducarEst,
                'gap' => $gap,
                'gap_fmt' => number_format($gap, 0, ',', '.'),
                'cobertura_pct' => $cob,
                'cobertura_label' => $cob !== null ? number_format($cob, 1, ',', '.').'%' : '—',
                'fundeb_gap_label' => $fundeb > 0 ? $fmt($fundeb) : '—',
                'tone' => $gap > 0 ? 'amber' : 'emerald',
            ];
            if ($ieducarReal > 0) {
                $row['ieducar'] = $ieducarReal;
                $row['ieducar_fonte'] = CadunicoFaixaEtariaMetodo::IDADE;
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $porFaixa
     * @param  list<array{etapa: string, matriculas: int}>  $ieducarPorEtapa
     * @return list<array<string, mixed>>
     */
    private function distribuirGapPorEtapa(
        array $porFaixa,
        array $ieducarPorEtapa,
        int $baseRede,
        int $cadTotal,
        int $gapTotal,
        float $vaaf,
    ): array {
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $rows = [];

        $prelim = [];
        foreach ($ieducarPorEtapa as $etapaRow) {
            $etapa = (string) ($etapaRow['etapa'] ?? '');
            $mat = (int) ($etapaRow['matriculas'] ?? 0);
            $cadEst = $this->matchCadunicoForEtapa($etapa, $porFaixa, $cadTotal, $baseRede);
            if ($cadEst <= 0 && $cadTotal > 0 && $baseRede > 0 && $mat > 0) {
                $cadEst = (int) round($cadTotal * ($mat / $baseRede));
            }
            $prelim[] = ['etapa' => $etapa, 'mat' => $mat, 'cad_est' => $cadEst];
        }

        foreach ($prelim as $item) {
            $gap = max(0, $item['cad_est'] - $item['mat']);
            $fundebGap = $vaaf > 0 ? MoneyMath::multiplyVaaf($gap, $vaaf) : 0.0;

            $rows[] = [
                'etapa' => $item['etapa'],
                'cadunico_estimado' => $item['cad_est'],
                'ieducar_matriculas' => $item['mat'],
                'gap' => $gap,
                'gap_fmt' => $gap > 0 ? number_format($gap, 0, ',', '.') : '0',
                'fundeb_gap_label' => $fundebGap > 0 ? $fmt($fundebGap) : '—',
                'tone' => $gap > 0 ? 'amber' : 'emerald',
            ];
        }

        if ($rows === [] && $gapTotal > 0) {
            $rows[] = [
                'etapa' => __('Rede municipal (total)'),
                'cadunico_estimado' => $cadTotal,
                'ieducar_matriculas' => $baseRede,
                'gap' => $gapTotal,
                'gap_fmt' => number_format($gapTotal, 0, ',', '.'),
                'fundeb_gap_label' => $vaaf > 0 ? $fmt(MoneyMath::multiplyVaaf($gapTotal, $vaaf)) : '—',
                'tone' => 'amber',
            ];
        }

        usort($rows, static fn ($a, $b) => ($b['gap'] ?? 0) <=> ($a['gap'] ?? 0));

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $porFaixa
     */
    private function matchCadunicoForEtapa(string $etapa, array $porFaixa, int $cadTotal, int $baseRede): int
    {
        $etapaLower = mb_strtolower($etapa);
        $faixasCfg = config('ieducar.cadunico.faixas_etarias', []);
        if (! is_array($faixasCfg)) {
            $faixasCfg = [];
        }

        foreach ($faixasCfg as $cfg) {
            if (! is_array($cfg)) {
                continue;
            }
            $keywords = $cfg['etapa_keywords'] ?? [];
            if (! is_array($keywords)) {
                continue;
            }
            foreach ($keywords as $kw) {
                if ($kw !== '' && str_contains($etapaLower, mb_strtolower((string) $kw))) {
                    $key = (string) ($cfg['key'] ?? '');
                    foreach ($porFaixa as $f) {
                        if (($f['key'] ?? '') === $key) {
                            return (int) ($f['cadunico'] ?? 0);
                        }
                    }
                }
            }
        }

        return 0;
    }
}
