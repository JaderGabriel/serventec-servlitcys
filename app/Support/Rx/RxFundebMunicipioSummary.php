<?php

namespace App\Support\Rx;

use App\Models\City;
use App\Repositories\Ieducar\DiscrepanciesRepository;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Fundeb\FundebValueLexicon;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\FundebResourceProjection;
use App\Support\Ieducar\MatriculaVolumeCounts;

/**
 * Projeção FUNDEB indicativa no RX — mesma base da aba Consultoria → FUNDEB (matrículas × VAAF).
 */
final class RxFundebMunicipioSummary
{
    /**
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        return [
            'available' => false,
            'previsao_anual' => null,
            'previsao_anual_fmt' => null,
            'matriculas_ano' => null,
            'exercicio_fundeb_ano' => null,
            'matriculas_base' => 0,
            'vaaf_fmt' => null,
            'formula_curta' => null,
            'exercicio_nota' => null,
            'analytics_url' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(
        City $city,
        IeducarFilterState $filters,
        int $matriculas,
        ?int $alunosDistintos,
        DiscrepanciesRepository $discrepancies,
    ): array {
        if (! filter_var(config('rx.fundeb_municipio_summary', true), FILTER_VALIDATE_BOOL)) {
            return self::empty();
        }

        if ($matriculas <= 0) {
            return self::empty();
        }

        $volume = [
            'matriculas' => $matriculas,
            'alunos' => $alunosDistintos,
            'alunos_available' => $alunosDistintos !== null && $alunosDistintos > 0,
        ];
        $baseCalculo = MatriculaVolumeCounts::fundebCalculationBase($volume);
        if ($baseCalculo <= 0) {
            return self::empty();
        }

        $matAno = (int) ($filters->yearFilterValue() ?? 0);
        $exercicioFundebAno = $matAno > 0 ? $matAno + 1 : null;
        $yearLabel = (string) ($matAno > 0 ? $matAno : '');

        $fundingSnapshot = $discrepancies->fundingImpactSnapshot($city, $filters);
        $fallbackRef = DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters);

        $discPayload = [
            'year_label' => $yearLabel,
            'funding_reference' => is_array($fundingSnapshot['funding_reference'] ?? null)
                ? $fundingSnapshot['funding_reference']
                : $fallbackRef,
            'summary' => is_array($fundingSnapshot['summary'] ?? null) ? $fundingSnapshot['summary'] : [],
            'total_matriculas' => $matriculas,
            'total_alunos_distintos' => $alunosDistintos,
        ];

        $projection = FundebResourceProjection::build(
            $baseCalculo,
            $yearLabel,
            ['kpis' => ['matriculas' => $matriculas, 'alunos_distintos' => $alunosDistintos]],
            $discPayload,
            $city,
            $filters,
            null,
            $matriculas,
            $alunosDistintos,
        );

        if (! ($projection['available'] ?? false)) {
            return self::empty();
        }

        $totais = is_array($projection['totais'] ?? null) ? $projection['totais'] : [];
        $previsao = (float) ($totais['fundeb_base_anual'] ?? 0);
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

        $kpis = is_array($projection['kpis'] ?? null) ? $projection['kpis'] : [];
        $previsaoKpi = $kpis[0] ?? [];
        $formulaCurta = (string) (data_get($previsaoKpi, 'funding_explicacao.formula_curta')
            ?? data_get($previsaoKpi, 'explicacao_resumo')
            ?? '');

        $vaaAno = isset($projection['vaa_ano']) && is_numeric($projection['vaa_ano'])
            ? (int) $projection['vaa_ano']
            : $matAno;
        $exercicioNota = $matAno > 0 && $exercicioFundebAno !== null
            ? FundebValueLexicon::matriculasExercicioNota($matAno, $vaaAno)
            : null;

        $analyticsUrl = route('dashboard.analytics', array_filter([
            'city_id' => $city->id,
            'tab' => 'fundeb',
            'ano_letivo' => $filters->yearFilterValue(),
        ]));

        return [
            'available' => $previsao > 0,
            'previsao_anual' => $previsao,
            'previsao_anual_fmt' => $fmt($previsao),
            'matriculas_ano' => $matAno > 0 ? $matAno : null,
            'exercicio_fundeb_ano' => $exercicioFundebAno,
            'matriculas_base' => $matriculas,
            'vaaf_fmt' => (string) ($projection['vaa_label'] ?? ''),
            'formula_curta' => $formulaCurta,
            'exercicio_nota' => $exercicioNota,
            'analytics_url' => $analyticsUrl,
        ];
    }
}
