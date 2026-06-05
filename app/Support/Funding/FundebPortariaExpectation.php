<?php

namespace App\Support\Funding;

use App\Models\FundebMunicipioReference;
use App\Models\MunicipalTransferSnapshot;
use App\Support\Finance\MoneyMath;
use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Expectativa FUNDEB anual e periódica com metadados de portaria FNDE (receita e complementações).
 */
final class FundebPortariaExpectation
{
    /**
     * @param  FundebMunicipioReference|array<string, mixed>|null  $reference
     * @return array{
     *   annual: float,
     *   base_mat_vaaf: float,
     *   receita_portaria: ?float,
     *   source: string,
     *   portaria_publication_year: ?int,
     *   url_portaria: ?string,
     *   adjustments: list<array{key: string, label: string, value: float, value_fmt: string}>,
     *   adjustments_note: ?string
     * }
     */
    public static function buildAnnual(
        int $matriculas,
        float $vaaf,
        FundebMunicipioReference|array|null $reference = null,
    ): array {
        $base = $matriculas > 0 && $vaaf > 0
            ? MoneyMath::multiplyVaaf($matriculas, $vaaf)
            : 0.0;

        $receita = self::receitaTotal($reference);
        $adjustments = self::adjustmentLines($reference);
        $pubYear = self::publicationYear($reference);
        $url = self::urlPortaria($reference);

        if ($receita !== null && $receita > 0) {
            return [
                'annual' => MoneyMath::roundMoney($receita),
                'base_mat_vaaf' => $base,
                'receita_portaria' => $receita,
                'source' => 'portaria_receita',
                'portaria_publication_year' => $pubYear,
                'url_portaria' => $url,
                'adjustments' => $adjustments,
                'adjustments_note' => $adjustments !== []
                    ? __('Complementações da portaria FNDE (:pub) — componentes da receita, não somadas em duplicado.', [
                        'pub' => $pubYear !== null ? (string) $pubYear : __('recente'),
                    ])
                    : null,
            ];
        }

        return [
            'annual' => MoneyMath::roundMoney($base),
            'base_mat_vaaf' => $base,
            'receita_portaria' => null,
            'source' => 'matricula_vaaf',
            'portaria_publication_year' => $pubYear,
            'url_portaria' => $url,
            'adjustments' => $adjustments,
            'adjustments_note' => $adjustments !== []
                ? __('Complementações importadas da portaria — aguardando receita total FNDE para expectativa anual oficial.')
                : null,
        ];
    }

    /**
     * @param  list<MunicipalTransferSnapshot>  $transferRows
     * @return array{
     *   monthly: float,
     *   months_in_year: int,
     *   months_with_transfers: int,
     *   periodic_expected: float,
     *   label: string
     * }
     */
    public static function periodicSchedule(float $annual, int $filterYear, array $transferRows = []): array
    {
        $monthsInYear = 12;
        $monthly = $annual > 0 ? MoneyMath::roundMoney($annual / $monthsInYear) : 0.0;
        $monthsWithTransfers = self::countMonthsWithTransfers($transferRows, $filterYear);
        $effectiveMonths = max(1, min($monthsInYear, $monthsWithTransfers > 0 ? $monthsWithTransfers : $monthsInYear));
        $periodic = $annual > 0
            ? MoneyMath::roundMoney($annual * ($effectiveMonths / $monthsInYear))
            : 0.0;

        $label = $monthsWithTransfers > 0 && $monthsWithTransfers < $monthsInYear
            ? __('Expectativa proporcional (:m meses com repasse × :mensal/mês)', [
                'm' => (string) $monthsWithTransfers,
                'mensal' => DiscrepanciesFundingImpact::formatBrl($monthly),
            ])
            : __('Expectativa mensal (:mensal × 12 meses)', [
                'mensal' => DiscrepanciesFundingImpact::formatBrl($monthly),
            ]);

        return [
            'monthly' => $monthly,
            'months_in_year' => $monthsInYear,
            'months_with_transfers' => $monthsWithTransfers,
            'periodic_expected' => $periodic,
            'label' => $label,
        ];
    }

    /**
     * @param  FundebMunicipioReference|array<string, mixed>|null  $reference
     * @return list<array{key: string, label: string, value: float, value_fmt: string}>
     */
    public static function adjustmentLines(FundebMunicipioReference|array|null $reference): array
    {
        $lines = [];
        foreach ([
            'complementacao_vaaf' => __('Complementação VAAF (portaria)'),
            'vaat' => __('Complementação VAAT (portaria)'),
            'complementacao_vaar' => __('Complementação VAAR (portaria)'),
        ] as $key => $label) {
            $value = self::moneyField($reference, $key);
            if ($value === null || $value <= 0) {
                continue;
            }
            $lines[] = [
                'key' => $key,
                'label' => $label,
                'value' => $value,
                'value_fmt' => DiscrepanciesFundingImpact::formatBrl($value),
            ];
        }

        return $lines;
    }

    /**
     * @param  FundebMunicipioReference|array<string, mixed>|null  $reference
     */
    public static function summaryForImport(FundebMunicipioReference|array|null $reference): ?string
    {
        $parts = [];
        $receita = self::receitaTotal($reference);
        if ($receita !== null && $receita > 0) {
            $parts[] = __('Receita portaria: :v', ['v' => DiscrepanciesFundingImpact::formatBrl($receita)]);
        }

        foreach (self::adjustmentLines($reference) as $line) {
            $parts[] = $line['label'].': '.$line['value_fmt'];
        }

        $pub = self::publicationYear($reference);
        if ($pub !== null) {
            $parts[] = __('Publicação FNDE :ano', ['ano' => (string) $pub]);
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * @param  FundebMunicipioReference|array<string, mixed>  $reference
     * @return array<string, mixed>
     */
    public static function referencePayload(FundebMunicipioReference|array $reference): array
    {
        if ($reference instanceof FundebMunicipioReference) {
            $meta = is_array($reference->meta) ? $reference->meta : [];

            return [
                'ano' => (int) $reference->ano,
                'vaaf' => (float) $reference->vaaf,
                'vaat' => $reference->vaat !== null ? (float) $reference->vaat : null,
                'complementacao_vaar' => $reference->complementacao_vaar !== null ? (float) $reference->complementacao_vaar : null,
                'complementacao_vaaf' => $reference->complementacao_vaaf !== null ? (float) $reference->complementacao_vaaf : null,
                'receita_total' => $reference->receita_total !== null ? (float) $reference->receita_total : null,
                'url_portaria' => $reference->url_portaria,
                'portaria_publication_year' => isset($meta['ano_publicacao']) ? (int) $meta['ano_publicacao'] : null,
                'fonte' => (string) $reference->fonte,
                'imported_at' => $reference->imported_at?->toIso8601String(),
                'portaria_summary' => self::summaryForImport($reference),
                'adjustments' => self::adjustmentLines($reference),
            ];
        }

        $meta = is_array($reference['meta'] ?? null) ? $reference['meta'] : [];

        return [
            'ano' => (int) ($reference['ano'] ?? 0),
            'vaaf' => (float) ($reference['vaaf'] ?? 0),
            'vaat' => isset($reference['vaat']) ? (float) $reference['vaat'] : null,
            'complementacao_vaar' => isset($reference['complementacao_vaar']) ? (float) $reference['complementacao_vaar'] : null,
            'complementacao_vaaf' => isset($reference['complementacao_vaaf']) ? (float) $reference['complementacao_vaaf'] : null,
            'receita_total' => isset($reference['receita_total']) ? (float) $reference['receita_total'] : null,
            'url_portaria' => $reference['url_portaria'] ?? null,
            'portaria_publication_year' => $reference['portaria_publication_year'] ?? ($meta['ano_publicacao'] ?? null),
            'fonte' => (string) ($reference['fonte'] ?? ''),
            'imported_at' => $reference['imported_at'] ?? null,
            'portaria_summary' => self::summaryForImport($reference),
            'adjustments' => self::adjustmentLines($reference),
        ];
    }

    /**
     * @param  list<MunicipalTransferSnapshot>  $rows
     */
    private static function countMonthsWithTransfers(array $rows, int $filterYear): int
    {
        $months = [];
        foreach ($rows as $row) {
            $meta = $row->meta;
            if (! is_array($meta)) {
                if (is_string($meta) && $meta !== '') {
                    $decoded = json_decode($meta, true);
                    $meta = is_array($decoded) ? $decoded : [];
                } else {
                    $meta = [];
                }
            }
            $mensal = $meta['mensal'] ?? null;
            if (! is_array($mensal)) {
                continue;
            }
            foreach ($mensal as $month => $valor) {
                if (! is_numeric($valor) || (float) $valor <= 0) {
                    continue;
                }
                $m = (int) $month;
                if ($m >= 1 && $m <= 12) {
                    $months[$filterYear.'-'.$m] = true;
                }
            }
        }

        return count($months);
    }

    /**
     * @param  FundebMunicipioReference|array<string, mixed>|null  $reference
     */
    private static function receitaTotal(FundebMunicipioReference|array|null $reference): ?float
    {
        $value = self::moneyField($reference, 'receita_total');

        return $value !== null && $value > 0 ? $value : null;
    }

    /**
     * @param  FundebMunicipioReference|array<string, mixed>|null  $reference
     */
    private static function publicationYear(FundebMunicipioReference|array|null $reference): ?int
    {
        if ($reference === null) {
            return null;
        }
        if ($reference instanceof FundebMunicipioReference) {
            $meta = is_array($reference->meta) ? $reference->meta : [];

            return isset($meta['ano_publicacao']) ? (int) $meta['ano_publicacao'] : null;
        }

        if (isset($reference['portaria_publication_year']) && is_numeric($reference['portaria_publication_year'])) {
            return (int) $reference['portaria_publication_year'];
        }
        $meta = is_array($reference['meta'] ?? null) ? $reference['meta'] : [];

        return isset($meta['ano_publicacao']) ? (int) $meta['ano_publicacao'] : null;
    }

    /**
     * @param  FundebMunicipioReference|array<string, mixed>|null  $reference
     */
    private static function urlPortaria(FundebMunicipioReference|array|null $reference): ?string
    {
        if ($reference === null) {
            return null;
        }
        $url = $reference instanceof FundebMunicipioReference
            ? $reference->url_portaria
            : ($reference['url_portaria'] ?? null);

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @param  FundebMunicipioReference|array<string, mixed>|null  $reference
     */
    private static function moneyField(FundebMunicipioReference|array|null $reference, string $field): ?float
    {
        if ($reference === null) {
            return null;
        }
        if ($reference instanceof FundebMunicipioReference) {
            $raw = $reference->{$field} ?? null;
        } else {
            $raw = $reference[$field] ?? null;
        }

        return is_numeric($raw) ? (float) $raw : null;
    }
}
