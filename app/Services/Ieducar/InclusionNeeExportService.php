<?php

namespace App\Services\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\InclusionNeeExportQuery;
use App\Support\Ieducar\InclusionNeeExportWriter;
use Illuminate\Support\Str;

final class InclusionNeeExportService
{
    public function __construct(
        private CityDataConnection $cityData,
    ) {}

    /**
     * @return array{success: bool, message: string, export_path: string, export_filename: string, row_count: int, format: string}
     */
    public function generate(City $city, IeducarFilterState $filters, string $format): array
    {
        $format = $format === 'xlsx' ? 'xlsx' : 'csv';

        $rows = $this->cityData->run($city, function ($db) use ($city, $filters) {
            return InclusionNeeExportQuery::rows($db, $city, $filters);
        });

        $ibge = preg_replace('/\D+/', '', (string) ($city->ibge_municipio ?? '')) ?: 'city_'.$city->id;
        $ano = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($filters->ano_letivo ?? 'ano'));
        $ext = $format === 'xlsx' ? 'xlsx' : 'csv';
        $filename = 'inclusao-nee_'.$ibge.'_'.$ano.'_'.now()->format('Y-m-d_His').'.'.$ext;
        $absolute = storage_path('app/admin_sync/exports/'.$filename);

        if ($format === 'xlsx') {
            InclusionNeeExportWriter::writeXlsx($absolute, $rows);
        } else {
            InclusionNeeExportWriter::writeCsv($absolute, $rows);
        }

        return [
            'success' => true,
            'message' => __('Exportação NEE concluída (:n linhas).', ['n' => number_format(count($rows))]),
            'export_path' => $absolute,
            'export_filename' => $filename,
            'export_mime' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
            'row_count' => count($rows),
            'format' => $format,
            'city_id' => $city->id,
        ];
    }

    public function buildFilename(City $city, IeducarFilterState $filters, string $format): string
    {
        $ibge = preg_replace('/\D+/', '', (string) ($city->ibge_municipio ?? '')) ?: 'city_'.$city->id;
        $ano = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($filters->ano_letivo ?? 'ano'));
        $ext = $format === 'xlsx' ? 'xlsx' : 'csv';

        return 'inclusao-nee_'.$ibge.'_'.$ano.'_'.Str::slug(now()->format('Y-m-d_His')).'.'.$ext;
    }
}
