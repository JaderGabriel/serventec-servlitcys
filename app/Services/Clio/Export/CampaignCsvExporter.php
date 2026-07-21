<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Services\Clio\Parse\CampaignParseService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV agregado da campanha — sem PII (só INEP, nomes de escola, totais, códigos).
 */
final class CampaignCsvExporter
{
    public function __construct(
        private CampaignParseService $parser,
    ) {}

    public function download(ClioCampaign $campaign): StreamedResponse
    {
        $campaign->load([
            'schools',
            'artifacts',
            'inferences',
            'findings' => fn ($q) => $q->latest('id')->limit(500),
        ]);
        $coverage = $this->parser->coverage($campaign);
        $filename = sprintf(
            'clio_%s_%d_%s.csv',
            preg_replace('/[^a-z0-9_-]+/i', '_', (string) $campaign->ibge_municipio) ?: 'mun',
            $campaign->year,
            now()->format('Ymd_His')
        );

        return response()->streamDownload(function () use ($campaign, $coverage): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['secao', 'chave', 'valor', 'nota'], ';');

            $metaRows = [
                ['campanha', 'uuid', $campaign->uuid, ''],
                ['campanha', 'municipio', $campaign->municipality_name, ''],
                ['campanha', 'uf', (string) $campaign->uf, ''],
                ['campanha', 'ibge', (string) ($campaign->ibge_municipio ?? ''), ''],
                ['campanha', 'ano', (string) $campaign->year, ''],
                ['campanha', 'perfil', $campaign->profile, $campaign->profileLabel()],
                ['campanha', 'estado', $campaign->status, $campaign->statusLabel()],
                ['campanha', 'referencia', (string) optional($campaign->reference_date)?->toDateString(), ''],
                ['cobertura', 'escolas_total', (string) ($coverage['schools_total'] ?? 0), ''],
                ['cobertura', 'triade_completa', (string) ($coverage['schools_triade_complete'] ?? 0), ''],
                ['cobertura', 'triade_pct', (string) ($coverage['triade_coverage_pct'] ?? 0), ''],
                ['cobertura', 'tem_acomp', ($coverage['has_acomp'] ?? false) ? '1' : '0', ''],
            ];
            foreach ($metaRows as $row) {
                fputcsv($out, $row, ';');
            }

            foreach ($campaign->inferences as $inf) {
                fputcsv($out, ['inferencia', $inf->code, $inf->summary, ''], ';');
                $payload = is_array($inf->payload) ? $inf->payload : [];
                foreach ($payload as $k => $v) {
                    if (is_scalar($v) || $v === null) {
                        fputcsv($out, ['inferencia_payload', $inf->code.'.'.$k, (string) ($v ?? ''), ''], ';');
                    }
                }
            }

            fputcsv($out, ['escola', 'inep', 'nome', 'triade'], ';');
            foreach ($coverage['schools'] ?? [] as $school) {
                fputcsv($out, [
                    'escola',
                    (string) ($school['inep'] ?? ''),
                    (string) ($school['name'] ?? ''),
                    ! empty($school['triade']) ? '1' : '0',
                ], ';');
            }

            fputcsv($out, ['achado', 'codigo', 'severidade', 'mensagem'], ';');
            foreach ($campaign->findings as $finding) {
                /** @var ClioCampaignFinding $finding */
                fputcsv($out, [
                    'achado',
                    $finding->code,
                    $finding->severity,
                    $this->stripPiiHint($finding->message),
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function stripPiiHint(string $message): string
    {
        // Mantém códigos/INEP; evita CPF/NIS óbvios se algum parser os ecoar.
        return (string) preg_replace('/\b\d{11}\b/', '[redacted]', $message);
    }
}
