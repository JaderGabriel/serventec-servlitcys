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

        $lines = [];

        if ($filters->hasYearSelected()) {
            $lines[] = $filters->isAllSchoolYears()
                ? __('Ano letivo: todos')
                : __('Ano letivo: :ano', ['ano' => $filters->ano_letivo]);
        } else {
            $lines[] = __('Ano letivo: —');
        }

        $escolaName = self::findPairName($ieducarOptions['escolas'] ?? [], $filters->escola_id);
        if ($escolaName !== null) {
            $lines[] = __('Escola: :n', ['n' => $escolaName]);
        }

        $cursoName = self::findPairName($ieducarOptions['cursos'] ?? [], $filters->curso_id);
        if ($cursoName !== null) {
            $lines[] = __('Tipo/Segmento: :n', ['n' => $cursoName]);
        }

        $turnoName = self::findPairName($ieducarOptions['turnos'] ?? [], $filters->turno_id);
        if ($turnoName !== null) {
            $lines[] = __('Turno: :n', ['n' => $turnoName]);
        }

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
