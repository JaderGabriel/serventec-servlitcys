<?php

namespace App\Support\Ieducar;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Finance\MoneyMath;

/**
 * Sinais operacionais CadÚnico (snapshot Cecad × matrículas i-Educar) no painel de discrepâncias.
 */
final class CadunicoOperationalSignals
{
    /**
     * @param  list<array<string, mixed>>  $dimensions
     * @return list<array<string, mixed>>
     */
    public static function append(
        array $dimensions,
        int $totalMat,
        ?City $city = null,
        ?IeducarFilterState $filters = null,
        ?int $fundebAnchorAno = null,
    ): array {
        if ($city === null || ! filter_var(config('ieducar.cadunico.enabled', true), FILTER_VALIDATE_BOOL)) {
            return $dimensions;
        }

        $existing = [];
        foreach ($dimensions as $d) {
            $existing[(string) ($d['id'] ?? '')] = true;
        }

        $out = $dimensions;
        foreach (self::buildSignals($city, $totalMat, $filters, $fundebAnchorAno) as $signal) {
            $id = (string) ($signal['id'] ?? '');
            if ($id === '' || isset($existing[$id])) {
                continue;
            }
            $out[] = $signal;
            $existing[$id] = true;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSignals(
        City $city,
        int $totalMat,
        ?IeducarFilterState $filters,
        ?int $fundebAnchorAno = null,
    ): array {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            return [];
        }

        $ano = self::anchorAno($filters, $fundebAnchorAno);
        $snap = CadunicoMunicipioSnapshot::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano_referencia', $ano)
            ->first();

        $signals = [self::signalSnapshot($snap, $ano)];

        if ($snap !== null) {
            $signals[] = self::signalRedeLacuna($city, $snap, $totalMat, $ano, $filters);
        } else {
            $signals[] = self::signalRedeLacunaIndisponivel($ano);
        }

        return array_values(array_filter($signals));
    }

    private static function anchorAno(?IeducarFilterState $filters, ?int $fundebAnchorAno = null): int
    {
        if ($filters !== null && $filters->hasYearSelected() && ! $filters->isAllSchoolYears()) {
            return (int) $filters->yearFilterValue();
        }

        if ($fundebAnchorAno !== null && $fundebAnchorAno >= 2000) {
            return $fundebAnchorAno;
        }

        return max(2000, (int) date('Y') - 1);
    }

    /**
     * @return array<string, mixed>
     */
    private static function signalSnapshot(?CadunicoMunicipioSnapshot $snap, int $ano): array
    {
        $id = 'cadunico_snapshot_ausente';
        $hasSnap = $snap !== null;

        return [
            'id' => $id,
            'title' => __('CadÚnico — snapshot municipal ausente'),
            'vaar_refs' => [__('CadÚnico Cecad'), __('Busca ativa')],
            'availability' => 'available',
            'has_issue' => ! $hasSnap,
            'detected' => ! $hasSnap,
            'total' => $hasSnap ? 0 : 1,
            'status' => $hasSnap ? DiscrepanciesRoutineStatus::OK : DiscrepanciesRoutineStatus::NO_DATA,
            'severity' => $hasSnap ? 'info' : 'warning',
            'analyzed' => true,
            'operational_note' => $hasSnap
                ? __(
                    'Snapshot Cecad :ano gravado (:total crianças/jovens 4–17, fonte :fonte, importado :em).',
                    [
                        'ano' => (string) $ano,
                        'total' => number_format($snap->totalCriancasEscolaridade(), 0, ',', '.'),
                        'fonte' => (string) ($snap->fonte ?? __('local')),
                        'em' => $snap->imported_at?->format('d/m/Y H:i') ?? '—',
                    ]
                )
                : __(
                    'Sem agregados CadÚnico para o exercício :ano. Sincronize em Admin → CadÚnico ou execute `cadunico:sync-city`.',
                    ['ano' => (string) $ano]
                ),
            'source_tab' => 'cadunico_previsao',
            'correction_tab' => 'cadunico_previsao',
            'correction_label' => __('Ver CadÚnico'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function signalRedeLacunaIndisponivel(int $ano): array
    {
        return [
            'id' => 'cadunico_rede_lacuna',
            'title' => __('CadÚnico — crianças fora da rede municipal'),
            'vaar_refs' => [__('CadÚnico'), __('FUNDEB — busca ativa')],
            'availability' => 'no_data',
            'has_issue' => false,
            'detected' => false,
            'total' => 0,
            'status' => DiscrepanciesRoutineStatus::NO_DATA,
            'severity' => 'warning',
            'analyzed' => false,
            'operational_note' => __('Importe o CadÚnico :ano para estimar a lacuna face às matrículas i-Educar.', ['ano' => (string) $ano]),
            'source_tab' => 'cadunico_previsao',
            'correction_tab' => 'cadunico_previsao',
            'correction_label' => __('Ver CadÚnico'),
            'unavailable_reason' => __('Snapshot CadÚnico ausente para o exercício.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function signalRedeLacuna(
        City $city,
        CadunicoMunicipioSnapshot $snap,
        int $totalMat,
        int $ano,
        ?IeducarFilterState $filters,
    ): array {
        $id = 'cadunico_rede_lacuna';
        $cadTotal = $snap->totalCriancasEscolaridade();
        $baseRede = max(0, $totalMat);
        $gap = ($cadTotal > 0 && $baseRede >= 0) ? max(0, $cadTotal - $baseRede) : null;
        $cobertura = ($cadTotal > 0 && $baseRede >= 0)
            ? round(min(100.0, 100.0 * $baseRede / $cadTotal), 1)
            : null;
        $alertaPct = (float) config('ieducar.cadunico.cobertura_alerta_pct', 92.0);
        $minGap = max(1, (int) config('ieducar.cadunico.discrepancia_min_gap', 10));
        $hasIssue = $gap !== null && $gap >= $minGap && ($cobertura === null || $cobertura < $alertaPct);

        $vaaf = (float) FundebMunicipalReferenceResolver::vaafParaCalculo($city, $filters)['vaaf'];
        $perda = ($hasIssue && $vaaf > 0)
            ? round((float) MoneyMath::multiplyVaaf($gap, $vaaf) * (float) config('ieducar.discrepancies.peso_por_check.'.$id, 0.35), 2)
            : 0.0;

        return [
            'id' => $id,
            'title' => __('CadÚnico — crianças fora da rede municipal'),
            'vaar_refs' => [__('CadÚnico'), __('FUNDEB — busca ativa')],
            'availability' => 'available',
            'has_issue' => $hasIssue,
            'detected' => $hasIssue,
            'total' => $hasIssue ? (int) $gap : 0,
            'pct_rede' => $cobertura,
            'perda_estimada_anual' => $perda,
            'ganho_potencial_anual' => $perda,
            'status' => $hasIssue
                ? (($cobertura !== null && $cobertura < 80.0) ? 'danger' : 'warning')
                : DiscrepanciesRoutineStatus::OK,
            'severity' => $hasIssue
                ? (($cobertura !== null && $cobertura < 80.0) ? 'danger' : 'warning')
                : 'info',
            'analyzed' => true,
            'operational_note' => $hasIssue
                ? __(
                    'CadÚnico :ano: :cad crianças/jovens 4–17; rede i-Educar: :mat matrícula(s) no filtro; lacuna estimada: :gap (:cobertura% de cobertura). Indicativo para busca ativa — nem todo cadastrado deve estar na rede municipal.',
                    [
                        'ano' => (string) $ano,
                        'cad' => number_format($cadTotal, 0, ',', '.'),
                        'mat' => number_format($baseRede, 0, ',', '.'),
                        'gap' => number_format((int) $gap, 0, ',', '.'),
                        'cobertura' => $cobertura !== null ? number_format($cobertura, 1, ',', '.') : '—',
                    ]
                )
                : ($cadTotal > 0
                    ? __(
                        'Cobertura :cobertura% face ao CadÚnico :ano (:cad no CadÚnico × :mat na rede). Sem lacuna relevante no filtro.',
                        [
                            'cobertura' => $cobertura !== null ? number_format($cobertura, 1, ',', '.') : '—',
                            'ano' => (string) $ano,
                            'cad' => number_format($cadTotal, 0, ',', '.'),
                            'mat' => number_format($baseRede, 0, ',', '.'),
                        ]
                    )
                    : __('CadÚnico importado sem população escolar agregada para o exercício.')),
            'source_tab' => 'cadunico_previsao',
            'correction_tab' => 'cadunico_previsao',
            'correction_label' => __('Ver CadÚnico'),
        ];
    }
}
