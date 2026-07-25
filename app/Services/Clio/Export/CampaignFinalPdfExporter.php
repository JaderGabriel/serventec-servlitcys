<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * PDF Final — retrato municipal por tema educativo (uma página por tema) + Diagnóstico Geral.
 */
final class CampaignFinalPdfExporter
{
    public function __construct(
        private readonly CampaignFinalPdfComposer $composer,
    ) {}

    public function download(ClioCampaign $campaign): Response
    {
        $payload = $this->composer->compose($campaign);
        $generatedAt = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');

        $pdf = Pdf::loadView('pdf.clio-campaign.final', [
            'campaign' => $campaign,
            'themes' => $payload['themes'],
            'diagnosticoGeral' => $payload['diagnostico_geral'],
            'schoolsTriade' => $payload['schools_triade'],
            'triadeSummary' => $payload['triade_summary'] ?? [],
            'dashboard' => $payload['dashboard'],
            'coverage' => $payload['coverage'],
            'generated_at' => $generatedAt,
            'colors' => [
                'primary' => '#0f172a',
                'secondary' => '#0f766e',
                'primary_light' => '#e2e8f0',
            ],
        ])->setPaper('a4')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        $ibge = (string) ($campaign->ibge_municipio ?: $campaign->city?->ibge_municipio ?? '');
        $citySlug = $this->slugPart((string) $campaign->municipality_name) ?: 'municipio';
        $ibgeSlug = preg_replace('/\D+/', '', $ibge) ?: 'ibge';
        $refDate = $campaign->reference_date
            ? $campaign->reference_date->format('Y-m-d')
            : (string) ((int) $campaign->year);
        $filename = sprintf('clio_final_%s_%s_%s.pdf', $citySlug, $ibgeSlug, $refDate);

        return $pdf->download($filename);
    }

    private function slugPart(string $value): string
    {
        $ascii = Str::ascii($value);
        $slug = (string) preg_replace('/[^a-z0-9]+/i', '_', $ascii);
        $slug = trim($slug, '_');

        return mb_strtolower($slug);
    }
}
