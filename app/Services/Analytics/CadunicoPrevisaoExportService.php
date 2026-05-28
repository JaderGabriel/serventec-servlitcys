<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Repositories\Ieducar\CadunicoPrevisaoRepository;
use App\Support\Analytics\CadunicoPrevisaoExportRowsBuilder;
use App\Support\Analytics\CadunicoPrevisaoExportWriter;
use App\Support\Dashboard\IeducarFilterState;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CadunicoPrevisaoExportService
{
    public function __construct(
        private CadunicoPrevisaoRepository $previsao,
    ) {}

    public function buildReport(City $city, IeducarFilterState $filters): array
    {
        return $this->previsao->buildReport($city, $filters);
    }

    public function download(City $city, IeducarFilterState $filters, string $format): StreamedResponse
    {
        $data = $this->buildReport($city, $filters);
        if (! ($data['available'] ?? false)) {
            abort(422, (string) ($data['error'] ?? __('Dados CadÚnico indisponíveis para exportação.')));
        }

        $rows = CadunicoPrevisaoExportRowsBuilder::fromReport($data);
        $slug = $this->filenameSlug($city, (int) ($data['year_label'] ?? 0));

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
                CadunicoPrevisaoExportWriter::streamCsv($rows);
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
        $tmp = storage_path('app/temp/cadunico-'.uniqid('', true).'.xlsx');
        CadunicoPrevisaoExportWriter::writeXlsx($tmp, $rows);
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
        $pdf = Pdf::loadView('pdf.cadunico-previsao-report.document', [
            'data' => $data,
            'generated_at' => now()->timezone(config('app.timezone', 'America/Sao_Paulo'))->format('d/m/Y H:i'),
            'colors' => [
                'primary' => '#4338ca',
                'secondary' => '#0f766e',
                'primary_light' => '#e0e7ff',
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

    private function filenameSlug(City $city, int $year): string
    {
        $ibge = preg_replace('/\D+/', '', (string) ($city->ibge_municipio ?? '')) ?: 'city_'.$city->id;
        $name = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($city->name ?? 'municipio'));

        return 'cadunico-previsao-'.$name.'-'.$ibge.'-'.$year.'-'.now()->format('Ymd-His');
    }
}
