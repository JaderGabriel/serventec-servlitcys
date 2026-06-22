<?php

namespace App\Support\Horizonte;

use App\Models\MunicipalTransferSnapshot;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Funding\FundebExtratoFontePriority;
use App\Support\Funding\FundebTransferScope;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega repasses municipais para o mapa Horizonte (total, FUNDEB e verbas de educação).
 */
final class HorizonteTransferBreakdown
{
    /** @var list<string> */
    private const EDUCATION_PROGRAM_IDS = [
        'fundeb',
        'pnae',
        'pnate',
        'pdde',
        'pdde-qualidade',
        'salario-educacao',
        'geral_educacao',
    ];

    /**
     * @return array<string, array{
     *     total: float,
     *     programas: int,
     *     ano: int,
     *     fundeb: float,
     *     educacao: float,
     *     pct_fundeb: ?float,
     *     pct_educacao: ?float
     * }>
     */
    public static function aggregateByIbge(int $year, ?string $ibgePrefix = null): array
    {
        if (! Schema::hasTable('municipal_transfer_snapshots')) {
            return [];
        }

        foreach (array_values(array_unique([$year, $year - 1])) as $candidateYear) {
            $aggregated = self::aggregateForYear($candidateYear, $ibgePrefix);
            if ($aggregated !== []) {
                return $aggregated;
            }
        }

        return [];
    }

    /**
     * @return array<string, array{
     *     total: float,
     *     programas: int,
     *     ano: int,
     *     fundeb: float,
     *     educacao: float,
     *     pct_fundeb: ?float,
     *     pct_educacao: ?float
     * }>
     */
    private static function aggregateForYear(int $year, ?string $ibgePrefix): array
    {
        $query = MunicipalTransferSnapshot::query()->where('ano', $year);
        if ($ibgePrefix !== null && $ibgePrefix !== '') {
            $query->where('ibge_municipio', 'like', $ibgePrefix.'%');
        }

        /** @var array<string, list<MunicipalTransferSnapshot>> $byIbge */
        $byIbge = [];
        foreach ($query->get() as $row) {
            if (! $row instanceof MunicipalTransferSnapshot) {
                continue;
            }
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $row->ibge_municipio);
            if ($ibge === null) {
                continue;
            }
            $byIbge[$ibge][] = $row;
        }

        if ($byIbge === []) {
            return [];
        }

        $out = [];
        foreach ($byIbge as $ibge => $rows) {
            $primary = FundebExtratoFontePriority::pickPrimaryPerProgram($rows);
            if ($primary === []) {
                continue;
            }

            $total = 0.0;
            $fundeb = 0.0;
            $educacao = 0.0;

            foreach ($primary as $row) {
                $valor = (float) $row->valor;
                $total += $valor;

                if (self::isFundebProgram((string) $row->programa_id, (string) ($row->programa_label ?? ''))) {
                    $fundeb += $valor;
                }
                if (self::isEducationProgram($row)) {
                    $educacao += $valor;
                }
            }

            if ($total <= 0) {
                continue;
            }

            $out[$ibge] = [
                'total' => round($total, 2),
                'programas' => count($primary),
                'ano' => $year,
                'fundeb' => round($fundeb, 2),
                'educacao' => round($educacao, 2),
                'pct_fundeb' => self::pctOf($fundeb, $total),
                'pct_educacao' => self::pctOf($educacao, $total),
            ];
        }

        return $out;
    }

    public static function isFundebProgram(string $programaId, ?string $programaLabel = null): bool
    {
        $pid = mb_strtolower(trim($programaId));
        if ($pid === 'fundeb') {
            return true;
        }

        $terms = config('ieducar.other_funding.public_queries.program_keywords.fundeb', ['fundeb', 'fnde']);
        if (! is_array($terms)) {
            $terms = ['fundeb'];
        }

        $blob = mb_strtolower($pid.' '.trim((string) $programaLabel));
        foreach ($terms as $term) {
            $term = mb_strtolower(trim((string) $term));
            if ($term !== '' && str_contains($blob, $term)) {
                return true;
            }
        }

        return false;
    }

    public static function isEducationProgram(MunicipalTransferSnapshot $row): bool
    {
        if (FundebTransferScope::isUfAggregated($row)) {
            return false;
        }

        $pid = mb_strtolower(trim((string) $row->programa_id));
        if (in_array($pid, self::EDUCATION_PROGRAM_IDS, true)) {
            return true;
        }

        if (FundebTransferScope::matchesFinanceRealtimeProgram($row)) {
            return true;
        }

        $blob = mb_strtolower($pid.' '.trim((string) ($row->programa_label ?? '')).' '.trim((string) $row->fonte));
        $keywords = self::educationKeywords();
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($blob, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function educationKeywords(): array
    {
        $portal = config('ieducar.other_funding.public_queries.portal_transparencia.education_keywords', []);
        $programMap = config('ieducar.other_funding.public_queries.program_keywords', []);
        $finance = config('ieducar.finance_realtime.program_keywords', []);

        $flat = [];
        foreach ([$portal, $finance] as $group) {
            if (! is_array($group)) {
                continue;
            }
            foreach ($group as $item) {
                $item = mb_strtolower(trim((string) $item));
                if ($item !== '') {
                    $flat[] = $item;
                }
            }
        }

        if (is_array($programMap)) {
            foreach ($programMap as $terms) {
                if (! is_array($terms)) {
                    continue;
                }
                foreach ($terms as $term) {
                    $term = mb_strtolower(trim((string) $term));
                    if ($term !== '') {
                        $flat[] = $term;
                    }
                }
            }
        }

        return array_values(array_unique($flat));
    }

    private static function pctOf(float $part, float $total): ?float
    {
        if ($total <= 0 || $part <= 0) {
            return null;
        }

        return round(min(100.0, ($part / $total) * 100.0), 1);
    }
}
