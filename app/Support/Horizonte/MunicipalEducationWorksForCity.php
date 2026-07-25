<?php

namespace App\Support\Horizonte;

use App\Models\MunicipalEducationWork;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Support\Facades\Schema;

/**
 * Marcadores Canteiro para a aba Unidades escolares (por IBGE do município).
 */
final class MunicipalEducationWorksForCity
{
    /**
     * @return array{
     *     markers: list<array<string, mixed>>,
     *     without_geo: list<array<string, mixed>>,
     *     total: int,
     *     simec_url: string
     * }
     */
    public static function payloadForIbge(?string $ibge): array
    {
        $simecUrl = (string) config(
            'horizonte.obras.alerts.simec_painel_url',
            'https://simec.mec.gov.br/painelObras/'
        );

        $empty = [
            'markers' => [],
            'without_geo' => [],
            'total' => 0,
            'simec_url' => $simecUrl,
        ];

        if (! filter_var(config('horizonte.obras.enabled', true), FILTER_VALIDATE_BOOL)) {
            return $empty;
        }

        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($ibge);
        if ($ibge === null || ! Schema::hasTable('municipal_education_works')) {
            return $empty;
        }

        $columns = [
            'id_projeto_investimento',
            'desc_nome',
            'situacao',
            'percentual_execucao_fisica',
            'valor_pago',
            'valor_empenhado',
            'latitude',
            'longitude',
        ];
        if (Schema::hasColumn('municipal_education_works', 'valor_previsto')) {
            $columns = array_merge($columns, [
                'valor_previsto',
                'data_inicio',
                'data_paralisacao',
                'data_ultima_afericao',
            ]);
        }

        $rows = MunicipalEducationWork::query()
            ->where('ibge_municipio', $ibge)
            ->get($columns);

        $markers = [];
        $withoutGeo = [];

        foreach ($rows as $row) {
            $item = self::rowToArray($row, $simecUrl);
            $lat = $item['lat'];
            $lng = $item['lng'];
            if ($lat !== null && $lng !== null) {
                $markers[] = $item;
            } else {
                $withoutGeo[] = $item;
            }
        }

        usort($markers, [self::class, 'sortBySituacao']);
        usort($withoutGeo, [self::class, 'sortBySituacao']);

        return [
            'markers' => $markers,
            'without_geo' => $withoutGeo,
            'total' => count($markers) + count($withoutGeo),
            'simec_url' => $simecUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function rowToArray(MunicipalEducationWork $row, string $simecUrl): array
    {
        $lat = $row->latitude !== null ? (float) $row->latitude : null;
        $lng = $row->longitude !== null ? (float) $row->longitude : null;
        if ($lat !== null && (abs($lat) < 0.0001 || abs($lat) > 90)) {
            $lat = null;
        }
        if ($lng !== null && (abs($lng) < 0.0001 || abs($lng) > 180)) {
            $lng = null;
        }

        return [
            'id' => (string) $row->id_projeto_investimento,
            'nome' => trim((string) ($row->desc_nome ?? '')) ?: __('Obra sem nome'),
            'situacao' => trim((string) ($row->situacao ?? '')),
            'percentual' => $row->percentual_execucao_fisica !== null ? (float) $row->percentual_execucao_fisica : null,
            'valor_pago' => $row->valor_pago !== null ? (float) $row->valor_pago : null,
            'valor_empenhado' => $row->valor_empenhado !== null ? (float) $row->valor_empenhado : null,
            'valor_previsto' => isset($row->valor_previsto) && $row->valor_previsto !== null ? (float) $row->valor_previsto : null,
            'data_inicio' => isset($row->data_inicio) ? $row->data_inicio?->toDateString() : null,
            'data_paralisacao' => isset($row->data_paralisacao) ? $row->data_paralisacao?->toDateString() : null,
            'data_ultima_afericao' => isset($row->data_ultima_afericao) ? $row->data_ultima_afericao?->toDateString() : null,
            'lat' => $lat,
            'lng' => $lng,
            'simec_url' => $simecUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function sortBySituacao(array $a, array $b): int
    {
        $rank = static function (string $s): int {
            return match ($s) {
                'Paralisada' => 0,
                'Em execução' => 1,
                'Inacabada' => 2,
                'Cancelada' => 3,
                'Cadastrada' => 4,
                default => 5,
            };
        };

        return $rank((string) ($a['situacao'] ?? '')) <=> $rank((string) ($b['situacao'] ?? ''));
    }
}
