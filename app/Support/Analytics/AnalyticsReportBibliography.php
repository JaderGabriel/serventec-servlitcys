<?php

namespace App\Support\Analytics;

use App\Models\AnalyticsReportExport;
use App\Models\City;
use Illuminate\Support\Str;

/**
 * Identificador bibliográfico do relatório municipal (estilo catálogo / EducaDados).
 */
final class AnalyticsReportBibliography
{
    public static function assignPublicId(AnalyticsReportExport $export): string
    {
        if (filled($export->public_id)) {
            return (string) $export->public_id;
        }

        $export->public_id = self::generatePublicId();
        $export->save();

        return (string) $export->public_id;
    }

    public static function generatePublicId(): string
    {
        do {
            $id = 'SRV-'.strtoupper(Str::random(12));
        } while (AnalyticsReportExport::query()->where('public_id', $id)->exists());

        return $id;
    }

    /**
     * @return array{
     *   public_id: string,
     *   citation: string,
     *   short_label: string,
     *   issued_at: ?string,
     *   municipality: string,
     *   uf: string,
     *   year_label: string
     * }
     */
    public static function forExport(AnalyticsReportExport $export, ?City $city = null): array
    {
        $city ??= $export->city;
        $filters = is_array($export->filters) ? $export->filters : [];
        $ano = (string) ($filters['ano_letivo'] ?? '');
        $yearLabel = $ano !== '' && $ano !== 'all'
            ? __('ano letivo :ano', ['ano' => $ano])
            : __('recorte plurianual');

        $municipio = trim((string) ($city?->name ?? ''));
        $uf = strtoupper(trim((string) ($city?->uf ?? '')));
        $publicId = filled($export->public_id)
            ? (string) $export->public_id
            : self::generatePublicId();

        $issued = $export->completed_at?->format('d/m/Y') ?? $export->created_at?->format('d/m/Y');

        $citation = __(
            'Serventec Assessoria. Relatório educacional municipal — :municipio/:uf (:year). Identificador :id. Gerado em :date. Plataforma SERVLITCYS.',
            [
                'municipio' => $municipio !== '' ? $municipio : __('Município'),
                'uf' => $uf !== '' ? $uf : '—',
                'year' => $yearLabel,
                'id' => $publicId,
                'date' => $issued ?? '—',
            ]
        );

        return [
            'public_id' => $publicId,
            'citation' => $citation,
            'short_label' => $publicId,
            'issued_at' => $issued,
            'municipality' => $municipio,
            'uf' => $uf,
            'year_label' => $yearLabel,
        ];
    }
}
