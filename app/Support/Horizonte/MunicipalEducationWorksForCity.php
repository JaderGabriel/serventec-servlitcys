<?php

namespace App\Support\Horizonte;

use App\Models\MunicipalEducationWork;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Support\Facades\Schema;

/**
 * Obras Canteiro para a aba Unidades escolares (por IBGE do município).
 *
 * Pins no mapa foram descontinuados (coordenadas Obrasgov costumam ser da capital da UF).
 * A UI consome `items` em tabela gestional.
 */
final class MunicipalEducationWorksForCity
{
    /**
     * @return array{
     *     items: list<array<string, mixed>>,
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
            'items' => [],
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
            'especie_intervencao',
            'natureza_intervencao',
            'desc_meta_global',
            'percentual_execucao_fisica',
            'valor_pago',
            'valor_empenhado',
        ];
        if (Schema::hasColumn('municipal_education_works', 'valor_previsto')) {
            $columns = array_merge($columns, [
                'valor_previsto',
                'data_inicio',
                'data_paralisacao',
                'data_ultima_afericao',
            ]);
        }
        foreach (['populacao_beneficiada', 'desc_populacao_beneficiada', 'salas_projeto', 'tipology'] as $col) {
            if (Schema::hasColumn('municipal_education_works', $col)) {
                $columns[] = $col;
            }
        }

        $rows = MunicipalEducationWork::query()
            ->where('ibge_municipio', $ibge)
            ->get($columns);

        $items = [];
        foreach ($rows as $row) {
            $items[] = self::rowToArray($row, $simecUrl);
        }

        usort($items, [self::class, 'sortBySituacao']);

        return [
            'items' => $items,
            // Mantidos vazios para não marcar pins no mapa (coords não são do território municipal).
            'markers' => [],
            'without_geo' => [],
            'total' => count($items),
            'simec_url' => $simecUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function rowToArray(MunicipalEducationWork $row, string $simecUrl): array
    {
        $porte = ObrasgovWorkFieldExtractor::porte([
            'desc_meta_global' => $row->desc_meta_global,
            'desc_nome' => $row->desc_nome,
            'populacao_beneficiada' => $row->populacao_beneficiada ?? null,
            'desc_populacao_beneficiada' => $row->desc_populacao_beneficiada ?? null,
        ]);

        if (isset($row->salas_projeto) && $row->salas_projeto !== null && (int) $row->salas_projeto > 0) {
            $porte['salas'] = (int) $row->salas_projeto;
        }
        if (isset($row->populacao_beneficiada) && $row->populacao_beneficiada !== null && (int) $row->populacao_beneficiada > 0) {
            $porte['populacao_beneficiada'] = (int) $row->populacao_beneficiada;
            $porte['populacao_fonte'] = $porte['populacao_fonte'] ?? 'populacao_beneficiada';
        }
        if (! empty($row->tipology) && is_string($row->tipology)) {
            $porte['tipology'] = $row->tipology;
            if (($porte['meta_global'] ?? null) === null || $porte['tipology_label'] === __('Não informado')) {
                $porte['tipology_label'] = ObrasgovWorkFieldExtractor::tipologyLabel($row->tipology, (string) ($row->desc_meta_global ?? ''));
            }
        }

        $parts = [];
        if (($porte['tipology_label'] ?? '') !== '' && $porte['tipology_label'] !== __('Não informado')) {
            $parts[] = $porte['tipology_label'];
        } elseif (! empty($porte['meta_global'])) {
            $parts[] = $porte['meta_global'];
        }
        if (($porte['salas'] ?? null) !== null && ! preg_match('/\bsalas?\b/iu', implode(' ', $parts))) {
            $parts[] = (int) $porte['salas'] === 1
                ? __('1 sala')
                : __(':n salas', ['n' => (string) $porte['salas']]);
        }
        $porteResumo = $parts !== [] ? implode(' · ', $parts) : __('Não informado');

        return [
            'id' => (string) $row->id_projeto_investimento,
            'nome' => trim((string) ($row->desc_nome ?? '')) ?: __('Obra sem nome'),
            'situacao' => trim((string) ($row->situacao ?? '')),
            'especie' => trim((string) ($row->especie_intervencao ?? '')) ?: null,
            'natureza' => trim((string) ($row->natureza_intervencao ?? '')) ?: null,
            'meta_global' => $porte['meta_global'],
            'tipology' => $porte['tipology'],
            'tipology_label' => $porte['tipology_label'],
            'porte_resumo' => $porteResumo,
            'salas' => $porte['salas'],
            'populacao_beneficiada' => $porte['populacao_beneficiada'],
            'percentual' => $row->percentual_execucao_fisica !== null ? (float) $row->percentual_execucao_fisica : null,
            'valor_pago' => $row->valor_pago !== null ? (float) $row->valor_pago : null,
            'valor_empenhado' => $row->valor_empenhado !== null ? (float) $row->valor_empenhado : null,
            'valor_previsto' => isset($row->valor_previsto) && $row->valor_previsto !== null ? (float) $row->valor_previsto : null,
            'data_inicio' => isset($row->data_inicio) ? $row->data_inicio?->toDateString() : null,
            'data_paralisacao' => isset($row->data_paralisacao) ? $row->data_paralisacao?->toDateString() : null,
            'data_ultima_afericao' => isset($row->data_ultima_afericao) ? $row->data_ultima_afericao?->toDateString() : null,
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

        $bySit = $rank((string) ($a['situacao'] ?? '')) <=> $rank((string) ($b['situacao'] ?? ''));
        if ($bySit !== 0) {
            return $bySit;
        }

        return strcmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
    }
}
