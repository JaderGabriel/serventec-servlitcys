<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Support\Analytics\ComparativoExportRowsBuilder;
use App\Support\Analytics\ComparativoExportWriter;
use App\Support\Dashboard\IeducarFilterState;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ComparativoExportService
{
    public function __construct(
        private FinanceComparativoService $comparativo,
    ) {}

    /**
     * @param  list<int|string>  $yearOptions
     */
    public function buildReport(City $city, IeducarFilterState $filters, int $baseYear, array $yearOptions = []): array
    {
        return $this->comparativo->build($city, $baseYear, $filters, $yearOptions);
    }

    /**
     * @param  list<int|string>  $yearOptions
     */
    public function download(
        City $city,
        IeducarFilterState $filters,
        int $baseYear,
        string $format,
        array $yearOptions = [],
    ): StreamedResponse {
        $data = $this->buildReport($city, $filters, $baseYear, $yearOptions);
        $rows = ComparativoExportRowsBuilder::fromReport($data);
        $slug = $this->filenameSlug($city, $baseYear);

        return match ($format) {
            'pdf' => $this->pdfResponse($data, $slug),
            'xlsx' => $this->xlsxResponse($rows, $slug),
            default => $this->csvResponse($rows, $slug),
        };
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function csvResponse(array $rows, string $slug): StreamedResponse
    {
        return response()->streamDownload(
            static function () use ($rows): void {
                ComparativoExportWriter::streamCsv($rows);
            },
            $slug.'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function xlsxResponse(array $rows, string $slug): StreamedResponse
    {
        $tmp = storage_path('app/temp/comparativo-'.uniqid('', true).'.xlsx');
        ComparativoExportWriter::writeXlsx($tmp, $rows);
        $binary = file_get_contents($tmp);
        @unlink($tmp);

        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary;
            },
            $slug.'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pdfResponse(array $data, string $slug): StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.comparativo-report.document', [
            'data' => $data,
            'generated_at' => now()->timezone(config('app.timezone', 'America/Sao_Paulo'))->format('d/m/Y H:i'),
            'colors' => [
                'primary' => '#0f766e',
                'secondary' => '#4338ca',
                'primary_light' => '#ccfbf1',
            ],
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf->output();
            },
            $slug.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function filenameSlug(City $city, int $baseYear): string
    {
        $ibge = preg_replace('/\D+/', '', (string) ($city->ibge_municipio ?? '')) ?: 'city_'.$city->id;
        $name = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($city->name ?? 'municipio'));

        return 'comparativo-'.$name.'-'.$ibge.'-'.$baseYear.'-'.now()->format('Ymd-His');
    }
}
