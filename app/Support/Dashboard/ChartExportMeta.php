<?php

namespace App\Support\Dashboard;

use App\Models\City;

/**
 * Metadados para exportação PNG/PDF dos gráficos do painel analítico (cidade, filtros, rodapé).
 */
final class ChartExportMeta
{
    /** Fuso usado no rodapé PNG/PDF dos gráficos (Brasil — GMT-3). */
    private const EXPORT_TIMEZONE = 'America/Sao_Paulo';

    /**
     * @param  array{
     *   years?: array<string|int, mixed>,
     *   escolas?: list<array{id: string, name: string}>,
     *   cursos?: list<array{id: string, name: string}>,
     *   turnos?: list<array{id: string, name: string}>
     * }  $ieducarOptions
     * @return array{
     *   documentTitle: string,
     *   cityLine: string,
     *   filterLines: list<string>,
     *   footerLine: string,
     *   generatedAt: string (data/hora em America/Sao_Paulo, sufixo GMT-3)
     * }
     */
    public static function forAnalytics(?City $city, IeducarFilterState $filters, array $ieducarOptions): array
    {
        $generatedAt = now()
            ->timezone(self::EXPORT_TIMEZONE)
            ->format('d/m/Y H:i')
            .' GMT-3';

        if ($city === null) {
            return [
                'documentTitle' => __('Análise educacional'),
                'cityLine' => '',
                'filterLines' => [],
                'footerLine' => (string) config('app.name'),
                'generatedAt' => $generatedAt,
            ];
        }

        $lines = array_map(
            static fn (array $part): string => $part['label'].': '.$part['value'],
            self::appliedFilterParts($filters, $ieducarOptions, includeUnsetDimensions: false),
        );

        $author = trim((string) config('chart_export.author'));
        $footer = $author !== ''
            ? __(':app · :author', ['app' => config('app.name'), 'author' => $author])
            : (string) config('app.name');

        return [
            'documentTitle' => __('Análise educacional'),
            'cityLine' => $city->name.' — '.$city->uf,
            'filterLines' => $lines,
            'footerLine' => $footer,
            'generatedAt' => $generatedAt,
        ];
    }

    /**
     * Contexto para o cabeçalho fixo do painel (`/dashboard/analytics`).
     *
     * @param  array{
     *   years?: array<string|int, mixed>,
     *   escolas?: list<array{id: string, name: string}>,
     *   cursos?: list<array{id: string, name: string}>,
     *   turnos?: list<array{id: string, name: string}>
     * }  $ieducarOptions
     * @return array{
     *   hasCity: bool,
     *   cityTitle: string,
     *   parts: list<array{label: string, value: string, muted?: bool}>
     * }
     */
    public static function pageHeaderContext(?City $city, IeducarFilterState $filters, array $ieducarOptions): array
    {
        if ($city === null) {
            return [
                'hasCity' => false,
                'cityTitle' => '',
                'parts' => [],
            ];
        }

        return [
            'hasCity' => true,
            'cityTitle' => trim($city->name.($city->uf ? ' — '.$city->uf : '')),
            'parts' => self::appliedFilterParts($filters, $ieducarOptions, includeUnsetDimensions: true),
            'labels' => [
                'ano' => __('Ano letivo'),
                'escola' => __('Escola'),
                'curso' => __('Tipo/Segmento'),
                'turno' => __('Turno'),
                'todas' => __('Todas'),
                'todos' => __('Todos'),
                'naoSelecionado' => __('Não seleccionado'),
            ],
        ];
    }

    /**
     * @param  array{
     *   escolas?: list<array{id: string, name: string}>,
     *   cursos?: list<array{id: string, name: string}>,
     *   turnos?: list<array{id: string, name: string}>
     * }  $ieducarOptions
     * @return list<array{label: string, value: string, muted?: bool}>
     */
    private static function appliedFilterParts(
        IeducarFilterState $filters,
        array $ieducarOptions,
        bool $includeUnsetDimensions,
    ): array {
        $parts = [];

        if ($filters->hasYearSelected()) {
            $parts[] = [
                'label' => __('Ano letivo'),
                'value' => $filters->isAllSchoolYears()
                    ? __('Todos')
                    : (string) $filters->ano_letivo,
            ];
        } else {
            $parts[] = [
                'label' => __('Ano letivo'),
                'value' => __('Não seleccionado'),
                'muted' => true,
            ];
        }

        $dimensionParts = [
            [
                'label' => __('Escola'),
                'value' => self::findPairName($ieducarOptions['escolas'] ?? [], $filters->escola_id),
                'fallback' => __('Todas'),
            ],
            [
                'label' => __('Tipo/Segmento'),
                'value' => self::findPairName($ieducarOptions['cursos'] ?? [], $filters->curso_id),
                'fallback' => __('Todos'),
            ],
            [
                'label' => __('Turno'),
                'value' => self::findPairName($ieducarOptions['turnos'] ?? [], $filters->turno_id),
                'fallback' => __('Todos'),
            ],
        ];

        foreach ($dimensionParts as $dimension) {
            $value = $dimension['value'];
            if ($value !== null && $value !== '') {
                $parts[] = ['label' => $dimension['label'], 'value' => $value];

                continue;
            }

            if ($includeUnsetDimensions) {
                $parts[] = [
                    'label' => $dimension['label'],
                    'value' => $dimension['fallback'],
                    'muted' => true,
                ];
            }
        }

        if ($filters->inclusion_somente_nee) {
            $parts[] = ['label' => __('Inclusão'), 'value' => __('Só NEE')];
        } elseif ($filters->inclusion_somente_inconsistencias) {
            $parts[] = ['label' => __('Inclusão'), 'value' => __('Só inconsistências')];
        }

        return $parts;
    }

    /**
     * @param  list<array{id: string, name: string}>  $pairs
     */
    private static function findPairName(array $pairs, ?string $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        foreach ($pairs as $opt) {
            if ((string) ($opt['id'] ?? '') === (string) $id) {
                return (string) ($opt['name'] ?? '');
            }
        }

        return null;
    }
}
