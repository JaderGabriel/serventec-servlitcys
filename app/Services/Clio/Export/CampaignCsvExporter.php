<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Parse\CampaignParseService;
use App\Services\Clio\Support\ClioUserCopy;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV agregado da coleta — sem PII (só INEP, nomes de escola, totais, códigos).
 */
final class CampaignCsvExporter
{
    public function __construct(
        private CampaignParseService $parser,
        private CampaignAnalysisPresenter $presenter,
    ) {}

    public function download(ClioCampaign $campaign): StreamedResponse
    {
        $campaign->load([
            'schools',
            'artifacts',
            'inferences',
            'findings' => fn ($q) => $q->latest('id')->limit(500),
            'findings.school',
        ]);
        $coverage = $this->parser->coverage($campaign);
        $dashboard = $this->presenter->present(
            $campaign,
            $coverage,
            $campaign->inferences->keyBy('code'),
            $campaign->findings,
        );
        $counters = $dashboard['counters'] ?? [];
        $filename = sprintf(
            'clio_%s_%d_%s.csv',
            preg_replace('/[^a-z0-9_-]+/i', '_', (string) $campaign->ibge_municipio) ?: 'mun',
            $campaign->year,
            now()->format('Ymd_His')
        );

        return response()->streamDownload(function () use ($campaign, $coverage, $dashboard, $counters): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['secao', 'chave', 'valor', 'nota'], ';');

            $metaRows = [
                ['coleta', 'uuid', $campaign->uuid, __('Identificador da coleta')],
                ['coleta', 'municipio', $campaign->municipality_name, ''],
                ['coleta', 'uf', (string) $campaign->uf, ''],
                ['coleta', 'ibge', (string) ($campaign->ibge_municipio ?? ''), ''],
                ['coleta', 'ano', (string) $campaign->year, ''],
                ['coleta', 'perfil', $campaign->profile, $campaign->profileLabel()],
                ['coleta', 'estado', $campaign->status, $campaign->statusLabel()],
                ['coleta', 'referencia', (string) optional($campaign->reference_date)?->toDateString(), __('Data de referência dos arquivos')],
            ];
            foreach ($metaRows as $row) {
                fputcsv($out, $row, ';');
            }

            $counterRows = [
                ['contadores', 'erros_a_corrigir', (string) ($counters['errors'] ?? 0), __('Prioridade: corrigir antes de fechar')],
                ['contadores', 'pontos_de_atencao', (string) ($counters['warnings'] ?? 0), __('Revisar — podem não bloquear')],
                ['contadores', 'informacoes', (string) ($counters['infos'] ?? 0), __('Só contexto')],
                ['contadores', 'escolas_total', (string) ($counters['schools_total'] ?? 0), ''],
                ['contadores', 'escolas_triade_completa', (string) ($counters['schools_triade'] ?? 0), __('Alunos + turmas + profissionais')],
                ['contadores', 'escolas_em_boa_forma', (string) ($counters['schools_ok'] ?? 0), __('Tríade ok e sem erro')],
                ['contadores', 'escolas_com_erros', (string) ($counters['schools_with_errors'] ?? 0), ''],
                ['contadores', 'escolas_incompletas', (string) ($counters['schools_incomplete'] ?? 0), __('Falta arquivo da tríade')],
                ['cobertura', 'triade_pct', (string) ($coverage['triade_coverage_pct'] ?? 0), '%'],
                ['cobertura', 'tem_acomp', ($coverage['has_acomp'] ?? false) ? '1' : '0', __('1 = Acompanhamento municipal presente')],
            ];
            foreach ($counterRows as $row) {
                fputcsv($out, $row, ';');
            }

            foreach ($campaign->inferences as $inf) {
                fputcsv($out, ['resumo_dados', $inf->code, $inf->summary, ''], ';');
                $payload = is_array($inf->payload) ? $inf->payload : [];
                foreach ($payload as $k => $v) {
                    if (is_scalar($v) || $v === null) {
                        fputcsv($out, ['resumo_dados_detalhe', $inf->code.'.'.$k, (string) ($v ?? ''), ''], ';');
                    }
                }
            }

            fputcsv($out, ['escola', 'inep', 'nome', 'situacao', 'triade', 'erros', 'avisos'], ';');
            foreach ($dashboard['schools'] ?? [] as $school) {
                fputcsv($out, [
                    'escola',
                    (string) ($school['inep'] ?? ''),
                    (string) ($school['name'] ?? ''),
                    (string) ($school['status'] ?? ''),
                    ! empty($school['triade']) ? '1' : '0',
                    (string) ($school['errors'] ?? 0),
                    (string) ($school['warnings'] ?? 0),
                ], ';');
            }

            fputcsv($out, [
                'o_que_corrigir',
                'codigo',
                'severidade',
                'severidade_rotulo',
                'mensagem',
                'o_que_fazer',
                'escola',
                'inep',
            ], ';');
            foreach ($campaign->findings as $finding) {
                /** @var ClioCampaignFinding $finding */
                fputcsv($out, [
                    'o_que_corrigir',
                    $finding->code,
                    $finding->severity,
                    ClioUserCopy::severityLabel((string) $finding->severity),
                    $this->stripPiiHint($finding->message),
                    $finding->actionHint(),
                    (string) ($finding->school?->name ?? ''),
                    (string) ($finding->school?->inep_code ?? ''),
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
