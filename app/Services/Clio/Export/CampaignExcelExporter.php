<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Parse\CampaignParseService;
use App\Services\Clio\Support\ClioUserCopy;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export Excel da coleta — 2 abas: escolas ativas (indicadores) e demais status.
 * Sem PII (só INEP, nomes de escola, totais, códigos).
 */
final class CampaignExcelExporter
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

        $filename = sprintf(
            'clio_%s_%d_%s.xlsx',
            preg_replace('/[^a-z0-9_-]+/i', '_', (string) $campaign->ibge_municipio) ?: 'mun',
            $campaign->year,
            now()->format('Ymd_His')
        );

        $tmp = storage_path('app/temp/clio-export-'.uniqid('', true).'.xlsx');
        $dir = dirname($tmp);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->writeXlsx($tmp, $campaign, $coverage, $dashboard);

        $binary = file_get_contents($tmp);
        @unlink($tmp);

        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary !== false ? $binary : '';
            },
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @param  array<string, mixed>  $coverage
     * @param  array<string, mixed>  $dashboard
     */
    private function writeXlsx(string $absolutePath, ClioCampaign $campaign, array $coverage, array $dashboard): void
    {
        $spreadsheet = new Spreadsheet;
        $active = $spreadsheet->getActiveSheet();
        $active->setTitle(__('Escolas ativas'));
        $this->fillActiveSheet($active, $campaign, $coverage, $dashboard);

        $other = $spreadsheet->createSheet();
        $other->setTitle(__('Demais status'));
        $this->fillOtherSheet($other, $dashboard);

        (new Xlsx($spreadsheet))->save($absolutePath);
    }

    /**
     * @param  array<string, mixed>  $coverage
     * @param  array<string, mixed>  $dashboard
     */
    private function fillActiveSheet(Worksheet $sheet, ClioCampaign $campaign, array $coverage, array $dashboard): void
    {
        $counters = $dashboard['counters'] ?? [];
        $row = 1;

        $metaHeaders = [__('Secção'), __('Chave'), __('Valor'), __('Nota')];
        $this->writeHeaderRow($sheet, $row, $metaHeaders);
        $row++;

        $metaRows = [
            [__('Coleta'), 'uuid', $campaign->uuid, __('Identificador da coleta')],
            [__('Coleta'), 'municipio', $campaign->municipality_name, ''],
            [__('Coleta'), 'uf', (string) $campaign->uf, ''],
            [__('Coleta'), 'ibge', (string) ($campaign->ibge_municipio ?? ''), ''],
            [__('Coleta'), 'ano', (string) $campaign->year, ''],
            [__('Coleta'), 'perfil', $campaign->profile, $campaign->profileLabel()],
            [__('Coleta'), 'estado', $campaign->status, $campaign->statusLabel()],
            [__('Coleta'), 'referencia', (string) optional($campaign->reference_date)?->toDateString(), __('Data de referência dos arquivos')],
            [__('Contadores'), 'escolas_ativas', (string) ($counters['schools_active'] ?? 0), __('Escopo desta aba')],
            [__('Contadores'), 'escolas_demais_status', (string) ($counters['schools_other'] ?? $counters['schools_inactive'] ?? 0), __('Ver aba Demais status')],
            [__('Contadores'), 'erros_a_corrigir', (string) ($counters['errors'] ?? 0), __('Prioridade: corrigir antes de fechar')],
            [__('Contadores'), 'pontos_de_atencao', (string) ($counters['warnings'] ?? 0), __('Revisar — podem não bloquear')],
            [__('Contadores'), 'informacoes', (string) ($counters['infos'] ?? 0), __('Só contexto')],
            [__('Contadores'), 'escolas_triade_completa', (string) ($counters['schools_triade'] ?? 0), __('Ativas com alunos + turmas + profissionais')],
            [__('Contadores'), 'escolas_em_boa_forma', (string) ($counters['schools_ok'] ?? 0), __('Ativas com tríade ok e sem erro')],
            [__('Contadores'), 'escolas_com_erros', (string) ($counters['schools_with_errors'] ?? 0), ''],
            [__('Contadores'), 'escolas_incompletas', (string) ($counters['schools_incomplete'] ?? 0), __('Falta arquivo da tríade')],
            [__('Cobertura'), 'triade_pct_ativas', (string) ($dashboard['triade']['pct'] ?? 0), '%'],
            [__('Cobertura'), 'tem_acomp', ($coverage['has_acomp'] ?? false) ? '1' : '0', __('1 = Acompanhamento municipal presente')],
        ];
        foreach ($metaRows as $metaRow) {
            $this->writeDataRow($sheet, $row, $metaRow);
            $row++;
        }

        $row++;
        foreach ($campaign->inferences as $inf) {
            $this->writeDataRow($sheet, $row, [__('Resumo'), $inf->code, $inf->summary, '']);
            $row++;
            $payload = is_array($inf->payload) ? $inf->payload : [];
            foreach ($payload as $k => $v) {
                if (is_scalar($v) || $v === null) {
                    $this->writeDataRow($sheet, $row, [__('Resumo detalhe'), $inf->code.'.'.$k, (string) ($v ?? ''), '']);
                    $row++;
                }
            }
        }

        $row += 2;
        $schoolHeaders = [
            __('INEP'),
            __('Escola'),
            __('Funcionamento'),
            __('Situação operacional'),
            __('Tríade'),
            __('Alunos'),
            __('Turmas'),
            __('Profissionais'),
            __('Erros'),
            __('Avisos'),
            __('Dependência'),
        ];
        $this->writeHeaderRow($sheet, $row, $schoolHeaders);
        $row++;

        foreach ($dashboard['schools_active'] ?? [] as $school) {
            $this->writeDataRow($sheet, $row, [
                (string) ($school['inep'] ?? ''),
                (string) ($school['name'] ?? ''),
                (string) ($school['functioning'] ?? ''),
                (string) ($school['status'] ?? ''),
                ! empty($school['triade']) ? '1' : '0',
                ! empty($school['aluno']) ? '1' : '0',
                ! empty($school['turma']) ? '1' : '0',
                ! empty($school['profissional']) ? '1' : '0',
                (string) ($school['errors'] ?? 0),
                (string) ($school['warnings'] ?? 0),
                (string) ($school['dependency'] ?? ''),
            ]);
            $row++;
        }

        $jornada = $dashboard['jornada'] ?? [];
        if (! empty($jornada['available'])) {
            $row += 2;
            $this->writeHeaderRow($sheet, $row, [
                __('INEP'),
                __('Escola'),
                __('Turmas'),
                __('Fund.+AEE contraturno'),
                __('Regular+AC'),
                __('Infantil turma estendida'),
                __('≥2 matrículas'),
            ]);
            $row++;
            foreach ($jornada['schools_active'] ?? [] as $jorRow) {
                $this->writeDataRow($sheet, $row, [
                    (string) ($jorRow['inep'] ?? ''),
                    (string) ($jorRow['name'] ?? ''),
                    (string) ($jorRow['turmas'] ?? 0),
                    (string) ($jorRow['fund_aee_contraturno'] ?? 0),
                    (string) ($jorRow['curricular_ac'] ?? 0),
                    (string) ($jorRow['infantil_turma_estendida'] ?? 0),
                    (string) ($jorRow['multi_enrollment'] ?? 0),
                ]);
                $row++;
            }
        }

        $transporte = $dashboard['transporte'] ?? [];
        if (! empty($transporte['available'])) {
            $row += 2;
            $this->writeHeaderRow($sheet, $row, [
                __('INEP'),
                __('Escola'),
                __('Localização'),
                __('Usam transporte'),
                __('Matrículas'),
                __('%'),
                __('Tipos de veículo'),
            ]);
            $row++;
            foreach ($transporte['schools_active'] ?? [] as $traRow) {
                $veiculos = collect($traRow['by_veiculo'] ?? [])
                    ->map(fn (array $v) => ($v['label'] ?? '').' ('.$v['count'].')')
                    ->implode('; ');
                $this->writeDataRow($sheet, $row, [
                    (string) ($traRow['inep'] ?? ''),
                    (string) ($traRow['name'] ?? ''),
                    (string) ($traRow['location'] ?? ''),
                    (string) ($traRow['flagged'] ?? 0),
                    (string) ($traRow['scanned'] ?? 0),
                    (string) ($traRow['pct'] ?? 0),
                    $veiculos,
                ]);
                $row++;
            }
        }

        $row += 2;
        $findingHeaders = [
            __('Código'),
            __('Severidade'),
            __('Rótulo'),
            __('Mensagem'),
            __('O que fazer'),
            __('Escola'),
            __('INEP'),
        ];
        $this->writeHeaderRow($sheet, $row, $findingHeaders);
        $row++;

        foreach ($campaign->findings as $finding) {
            /** @var ClioCampaignFinding $finding */
            $this->writeDataRow($sheet, $row, [
                $finding->code,
                $finding->severity,
                ClioUserCopy::severityLabel((string) $finding->severity),
                $this->stripPiiHint($finding->message),
                $finding->actionHint(),
                (string) ($finding->school?->name ?? ''),
                (string) ($finding->school?->inep_code ?? ''),
            ]);
            $row++;
        }

        $this->autosize($sheet, max(count($metaHeaders), count($schoolHeaders), count($findingHeaders)));
    }

    /**
     * @param  array<string, mixed>  $dashboard
     */
    private function fillOtherSheet(Worksheet $sheet, array $dashboard): void
    {
        $headers = [
            __('INEP'),
            __('Escola'),
            __('Funcionamento'),
            __('Situação'),
            __('Tríade'),
            __('Erros'),
            __('Avisos'),
            __('Dependência'),
            __('Nota'),
        ];
        $this->writeHeaderRow($sheet, 1, $headers);

        $row = 2;
        $others = $dashboard['schools_other'] ?? [];
        if ($others instanceof \Illuminate\Support\Collection) {
            $others = $others->all();
        }

        foreach ($others as $school) {
            $this->writeDataRow($sheet, $row, [
                (string) ($school['inep'] ?? ''),
                (string) ($school['name'] ?? ''),
                (string) ($school['functioning'] ?? ''),
                (string) ($school['status'] ?? ''),
                ! empty($school['triade']) ? '1' : '0',
                (string) ($school['errors'] ?? 0),
                (string) ($school['warnings'] ?? 0),
                (string) ($school['dependency'] ?? ''),
                (string) ($school['status_note'] ?? __('Fora do escopo operacional')),
            ]);
            $row++;
        }

        if ($row === 2) {
            $this->writeDataRow($sheet, $row, [
                '',
                __('Nenhuma escola fora de atividade nesta coleta.'),
                '', '', '', '', '', '', '',
            ]);
            $row++;
        }

        $jornada = $dashboard['jornada'] ?? [];
        $jorHeaders = [];
        if (! empty($jornada['available']) && ! empty($jornada['schools_other'])) {
            $row += 2;
            $jorHeaders = [
                __('INEP'),
                __('Escola'),
                __('Turmas'),
                __('Fund.+AEE contraturno'),
                __('Regular+AC'),
                __('Infantil turma estendida'),
                __('≥2 matrículas'),
            ];
            $this->writeHeaderRow($sheet, $row, $jorHeaders);
            $row++;
            foreach ($jornada['schools_other'] as $jorRow) {
                $this->writeDataRow($sheet, $row, [
                    (string) ($jorRow['inep'] ?? ''),
                    (string) ($jorRow['name'] ?? ''),
                    (string) ($jorRow['turmas'] ?? 0),
                    (string) ($jorRow['fund_aee_contraturno'] ?? 0),
                    (string) ($jorRow['curricular_ac'] ?? 0),
                    (string) ($jorRow['infantil_turma_estendida'] ?? 0),
                    (string) ($jorRow['multi_enrollment'] ?? 0),
                ]);
                $row++;
            }
        }

        $transporte = $dashboard['transporte'] ?? [];
        $traHeaders = [];
        if (! empty($transporte['available']) && ! empty($transporte['schools_other'])) {
            $row += 2;
            $traHeaders = [
                __('INEP'),
                __('Escola'),
                __('Localização'),
                __('Usam transporte'),
                __('Matrículas'),
                __('%'),
                __('Tipos de veículo'),
            ];
            $this->writeHeaderRow($sheet, $row, $traHeaders);
            $row++;
            foreach ($transporte['schools_other'] as $traRow) {
                $veiculos = collect($traRow['by_veiculo'] ?? [])
                    ->map(fn (array $v) => ($v['label'] ?? '').' ('.$v['count'].')')
                    ->implode('; ');
                $this->writeDataRow($sheet, $row, [
                    (string) ($traRow['inep'] ?? ''),
                    (string) ($traRow['name'] ?? ''),
                    (string) ($traRow['location'] ?? ''),
                    (string) ($traRow['flagged'] ?? 0),
                    (string) ($traRow['scanned'] ?? 0),
                    (string) ($traRow['pct'] ?? 0),
                    $veiculos,
                ]);
                $row++;
            }
        }

        $width = count($headers);
        if ($jorHeaders !== []) {
            $width = max($width, count($jorHeaders));
        }
        if ($traHeaders !== []) {
            $width = max($width, count($traHeaders));
        }
        $this->autosize($sheet, $width);
    }

    /**
     * @param  list<string>  $values
     */
    private function writeHeaderRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $col => $value) {
            $cell = $this->columnLetter($col + 1).$row;
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }
    }

    /**
     * @param  list<string>  $values
     */
    private function writeDataRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $col => $value) {
            $sheet->setCellValue($this->columnLetter($col + 1).$row, $value);
        }
    }

    private function autosize(Worksheet $sheet, int $columnCount): void
    {
        foreach (range(1, $columnCount) as $col) {
            $sheet->getColumnDimension($this->columnLetter($col))->setAutoSize(true);
        }
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    private function stripPiiHint(string $message): string
    {
        return (string) preg_replace('/\b\d{11}\b/', '[redacted]', $message);
    }
}
