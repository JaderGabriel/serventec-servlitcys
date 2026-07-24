<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Parse\CampaignParseService;
use App\Services\Clio\Support\ClioUserCopy;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export Excel da coleta — alinhado às regras do PDF Clio
 * (nome cidade/IBGE/data, cores do sistema, distorção ordenada, NEE/AEE, Fund. I/II).
 */
final class CampaignExcelExporter
{
    private const COLOR_NAVY = '0F172A';

    private const COLOR_ACCENT = '1D4ED8';

    private const COLOR_HEADER_FONT = 'FFFFFF';

    private const COLOR_WARN = 'FFF7ED';

    public function __construct(
        private CampaignParseService $parser,
        private CampaignAnalysisPresenter $presenter,
        private CampaignPdfDetailBuilder $detailBuilder,
        private DiagnosticoGeralComposer $diagnosticoGeral,
    ) {}

    public function download(ClioCampaign $campaign): StreamedResponse
    {
        $campaign->load([
            'schools.artifacts',
            'artifacts.school',
            'inferences',
            'findings.school',
        ]);
        $coverage = $this->parser->coverage($campaign);
        $dashboard = $this->presenter->present(
            $campaign,
            $coverage,
            $campaign->inferences->keyBy('code'),
            $campaign->findings,
        );
        $pdfTables = $this->detailBuilder->build($campaign);
        $diagnostico = $this->diagnosticoGeral->compose($campaign);

        $citySlug = $this->slugPart((string) $campaign->municipality_name) ?: 'municipio';
        $ibge = preg_replace('/\D+/', '', (string) ($campaign->ibge_municipio ?? '')) ?: 'ibge';
        $refDate = $campaign->reference_date
            ? $campaign->reference_date->format('Y-m-d')
            : (string) ((int) $campaign->year);
        $filename = sprintf('clio_%s_%s_%s.xlsx', $citySlug, $ibge, $refDate);

        $tmp = storage_path('app/temp/clio-export-'.uniqid('', true).'.xlsx');
        $dir = dirname($tmp);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->writeXlsx($tmp, $campaign, $coverage, $dashboard, $pdfTables, $diagnostico);

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
     * @param  array<string, mixed>  $pdfTables
     * @param  array<string, mixed>  $diagnostico
     */
    private function writeXlsx(
        string $absolutePath,
        ClioCampaign $campaign,
        array $coverage,
        array $dashboard,
        array $pdfTables,
        array $diagnostico,
    ): void {
        $spreadsheet = new Spreadsheet;
        $active = $spreadsheet->getActiveSheet();
        $active->setTitle(__('Escolas ativas'));
        $this->fillActiveSheet($active, $campaign, $coverage, $dashboard);

        $diag = $spreadsheet->createSheet();
        $diag->setTitle(__('Diagnóstico Geral'));
        $this->fillDiagnosticoSheet($diag, $diagnostico);

        $other = $spreadsheet->createSheet();
        $other->setTitle(__('Demais status'));
        $this->fillOtherSheet($other, $dashboard);

        $dist = $spreadsheet->createSheet();
        $dist->setTitle(__('Distorção'));
        $this->fillDistortionSheet($dist, $dashboard, $pdfTables);

        $nee = $spreadsheet->createSheet();
        $nee->setTitle(__('NEE e AEE'));
        $this->fillNeeSheet($nee, $pdfTables);

        $expo = $spreadsheet->createSheet();
        $expo->setTitle(__('Exposição'));
        $this->fillCensusSheet($expo, $pdfTables['census_matrix'] ?? []);

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
            __('Identificador amostra'),
        ];
        $this->writeHeaderRow($sheet, $row, $findingHeaders);
        $row++;

        foreach ($campaign->findings as $finding) {
            /** @var ClioCampaignFinding $finding */
            $meta = is_array($finding->meta) ? $finding->meta : [];
            $this->writeDataRow($sheet, $row, [
                $finding->code,
                $finding->severity,
                ClioUserCopy::severityLabel((string) $finding->severity),
                $finding->message,
                $finding->actionHint(),
                (string) ($finding->school?->name ?? ''),
                (string) ($finding->school?->inep_code ?? ''),
                (string) ($meta['sample_id'] ?? ''),
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
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, mixed>  $pdfTables
     */
    private function fillDistortionSheet(Worksheet $sheet, array $dashboard, array $pdfTables): void
    {
        $row = 1;
        $this->writeHeaderRow($sheet, $row, [
            __('Etapa'),
            __('Elegíveis'),
            __('Distorção'),
            __('Atraso 1 ano'),
            __('Adequados'),
            __('% distorção'),
            __('Escolas (amostra)'),
            __('Alunos (amostra)'),
        ]);
        $row++;

        $byEtapa = $pdfTables['distortion_by_etapa'] ?? [];
        if ($byEtapa === []) {
            $metrics = $dashboard['stage_metrics']['distortion']['by_etapa'] ?? [];
            foreach ($metrics as $item) {
                $byEtapa[] = [
                    'etapa' => $item['etapa'] ?? '',
                    'eligible' => $item['eligible'] ?? 0,
                    'distorcao' => $item['distorcao'] ?? 0,
                    'atraso_1' => $item['atraso_1'] ?? 0,
                    'adequado' => $item['adequado'] ?? 0,
                    'pct' => $item['pct'] ?? null,
                    'escolas' => '',
                    'alunos' => $item['distorcao'] ?? 0,
                ];
            }
            $byEtapa = (new \App\Services\Clio\Analysis\EtapaLabelOrder)->sortRowsByEtapaKey($byEtapa, 'etapa');
        }

        foreach ($byEtapa as $item) {
            $this->writeDataRow($sheet, $row, [
                (string) ($item['etapa'] ?? ''),
                (string) ($item['eligible'] ?? 0),
                (string) ($item['distorcao'] ?? 0),
                (string) ($item['atraso_1'] ?? ''),
                (string) ($item['adequado'] ?? ''),
                isset($item['pct']) ? (string) $item['pct'] : '',
                (string) ($item['escolas'] ?? ''),
                (string) ($item['alunos'] ?? ''),
            ]);
            $row++;
        }

        $row += 2;
        $this->writeHeaderRow($sheet, $row, [
            __('Identificador'),
            __('Nome'),
            __('CPF'),
            __('Escola'),
            __('INEP'),
            __('Etapa'),
            __('Turma'),
            __('Matrícula'),
            __('Idade'),
            __('Esperada'),
            __('Atraso'),
        ], self::COLOR_ACCENT);
        $row++;

        foreach ($pdfTables['distortion_students'] ?? [] as $student) {
            $this->writeDataRow($sheet, $row, [
                (string) ($student['id'] ?? ''),
                (string) ($student['name'] ?? ''),
                (string) ($student['cpf'] ?? ''),
                (string) ($student['school'] ?? ''),
                (string) ($student['inep'] ?? ''),
                (string) ($student['etapa'] ?? ''),
                (string) ($student['turma'] ?? ''),
                (string) ($student['matricula'] ?? ''),
                (string) ($student['age'] ?? ''),
                (string) ($student['expected'] ?? ''),
                (string) ($student['delay'] ?? ''),
            ]);
            $row++;
        }

        $this->autosize($sheet, 11);
    }

    /**
     * @param  array<string, mixed>  $pdfTables
     */
    private function fillNeeSheet(Worksheet $sheet, array $pdfTables): void
    {
        $row = 1;
        $this->writeHeaderRow($sheet, $row, [__('Indicador'), __('Valor')]);
        $row++;
        foreach ([
            [__('Total com marcador NEE'), (string) ($pdfTables['nee_total'] ?? 0)],
            [__('NEE sem matrícula AEE'), (string) ($pdfTables['nee_without_aee'] ?? 0)],
            [__('AEE sem deficiência/TEA/AH'), (string) ($pdfTables['nee_aee_without_condition'] ?? 0)],
            [__('Com alerta de subnotificação'), (string) ($pdfTables['nee_underreporting'] ?? 0)],
        ] as $summary) {
            $this->writeDataRow($sheet, $row, $summary);
            $row++;
        }

        $row += 2;
        $headers = [
            __('Identificador'),
            __('Nome'),
            __('CPF'),
            __('Escola'),
            __('INEP'),
            __('Deficiências'),
            __('Transtornos'),
            __('AH'),
            __('Subnotificação'),
            __('Flag AEE'),
            __('AEE sem NEE'),
        ];
        $this->writeHeaderRow($sheet, $row, $headers);
        $row++;

        foreach ($pdfTables['nee_students'] ?? [] as $person) {
            $warn = ! empty($person['aee_without_nee']) || empty($person['has_aee']) || ! empty($person['has_underreporting']);
            $this->writeDataRow($sheet, $row, [
                (string) ($person['id'] ?? ''),
                (string) ($person['name'] ?? ''),
                (string) ($person['cpf'] ?? ''),
                (string) ($person['school'] ?? ''),
                (string) ($person['inep'] ?? ''),
                (string) ($person['deficiencies'] ?? ''),
                (string) ($person['disorders'] ?? ''),
                (string) ($person['ah'] ?? ''),
                (string) ($person['underreporting'] ?? ''),
                (string) ($person['aee_flag'] ?? ''),
                ! empty($person['aee_without_nee']) ? '1' : '0',
            ], $warn ? self::COLOR_WARN : null);
            $row++;
        }

        $this->autosize($sheet, count($headers));
    }

    /**
     * @param  array<string, mixed>  $matrix
     */
    private function fillCensusSheet(Worksheet $sheet, array $matrix): void
    {
        $row = 1;
        if (empty($matrix['available'])) {
            $this->writeHeaderRow($sheet, $row, [__('Exposição das matrículas')]);
            $row++;
            $this->writeDataRow($sheet, $row, [__('Sem dados de exposição para escolas ativas nesta coleta.')]);
            $this->autosize($sheet, 1);

            return;
        }

        $this->writeHeaderRow($sheet, $row, [
            __('Município'),
            __('UF'),
            __('IBGE'),
            __('Ano'),
            __('Escolas ativas'),
            __('Linhas contadas'),
        ]);
        $row++;
        $this->writeDataRow($sheet, $row, [
            (string) ($matrix['municipality'] ?? ''),
            (string) ($matrix['uf'] ?? ''),
            (string) ($matrix['ibge'] ?? ''),
            (string) ($matrix['year'] ?? ''),
            (string) ($matrix['schools_active'] ?? 0),
            (string) ($matrix['rows_counted'] ?? 0),
        ]);
        $row += 2;
        $this->writeDataRow($sheet, $row, [
            (string) ($matrix['note'] ?? ''),
            __('Fundamental I = anos iniciais (1º–5º); Fundamental II = anos finais (6º–9º).'),
        ]);
        $row += 2;

        foreach (['infantil', 'fundamental', 'eja'] as $blockKey) {
            $block = $matrix[$blockKey] ?? null;
            if (! is_array($block)) {
                continue;
            }
            $this->writeHeaderRow($sheet, $row, [
                (string) ($block['title'] ?? $blockKey),
                __('Modalidade'),
                ...array_map(
                    static fn (array $col): string => (string) ($col['label'] ?? ''),
                    $block['columns'] ?? [],
                ),
            ], self::COLOR_ACCENT);
            $row++;

            $locHeaders = ['', ''];
            foreach ($block['columns'] ?? [] as $col) {
                $locHeaders[] = __('Urbana').' / '.__('Rural');
            }
            $this->writeDataRow($sheet, $row, $locHeaders);
            $row++;

            foreach ($block['rows'] ?? [] as $modKey => $modLabel) {
                $values = [(string) $modLabel, (string) $modKey];
                foreach ($block['columns'] ?? [] as $col) {
                    $vals = $block['values'][$col['key']] ?? [];
                    $u = (int) ($vals['Urbana'][$modKey] ?? 0);
                    $r = (int) ($vals['Rural'][$modKey] ?? 0);
                    $values[] = $u.' / '.$r;
                }
                $this->writeDataRow($sheet, $row, $values);
                $row++;
            }
            $row++;
        }

        $geral = $matrix['geral'] ?? [];
        if (! empty($geral['columns'])) {
            $this->writeHeaderRow($sheet, $row, array_map(
                static fn (array $col): string => (string) ($col['label'] ?? ''),
                $geral['columns'],
            ));
            $row++;
            $geralValues = [];
            foreach ($geral['columns'] as $col) {
                $geralValues[] = (string) ((int) ($geral['values'][$col['key']] ?? 0));
            }
            $this->writeDataRow($sheet, $row, $geralValues);
            $row++;
        }

        $this->autosize($sheet, 12);
    }

    /**
     * @param  array<string, mixed>  $diagnostico
     */
    private function fillDiagnosticoSheet(Worksheet $sheet, array $diagnostico): void
    {
        $row = 1;
        $this->writeHeaderRow($sheet, $row, [
            __('INEP'),
            __('Escola'),
            __('Localidade'),
            __('Erros'),
            __('Avisos'),
            __('Alertas / pendências'),
        ]);
        $row++;

        if (empty($diagnostico['available']) || empty($diagnostico['rows'])) {
            $this->writeDataRow($sheet, $row, [
                '',
                __('Nenhuma escola em atividade nesta coleta.'),
                '',
                '',
                '',
                '',
            ]);
            $this->autosize($sheet, 6);

            return;
        }

        foreach ($diagnostico['rows'] as $schoolRow) {
            if (! is_array($schoolRow)) {
                continue;
            }
            $alerts = [];
            foreach (is_array($schoolRow['alerts'] ?? null) ? $schoolRow['alerts'] : [] as $alert) {
                if (! is_array($alert)) {
                    continue;
                }
                $sev = (string) ($alert['severity'] ?? '');
                $prefix = match ($sev) {
                    ClioCampaignFinding::SEVERITY_ERROR => '[ERRO] ',
                    ClioCampaignFinding::SEVERITY_WARNING => '[AVISO] ',
                    'ok' => '[OK] ',
                    default => '',
                };
                $alerts[] = $prefix.(string) ($alert['message'] ?? '');
            }

            $fill = null;
            if ((int) ($schoolRow['error_count'] ?? 0) > 0) {
                $fill = 'FFF1F2';
            } elseif ((int) ($schoolRow['warning_count'] ?? 0) > 0) {
                $fill = self::COLOR_WARN;
            } elseif (($schoolRow['status'] ?? '') === 'ok') {
                $fill = 'ECFDF5';
            }

            $this->writeDataRow($sheet, $row, [
                (string) ($schoolRow['inep'] ?? ''),
                (string) ($schoolRow['name'] ?? ''),
                (string) ($schoolRow['location'] ?? ''),
                (string) ((int) ($schoolRow['error_count'] ?? 0)),
                (string) ((int) ($schoolRow['warning_count'] ?? 0)),
                implode("\n", $alerts),
            ], $fill);
            $sheet->getStyle('F'.$row)->getAlignment()->setWrapText(true);
            $row++;
        }

        $row += 1;
        $totals = is_array($diagnostico['totals'] ?? null) ? $diagnostico['totals'] : [];
        $this->writeHeaderRow($sheet, $row, [__('Totalizador'), __('Quantidade')], self::COLOR_ACCENT);
        $row++;
        foreach ([
            [__('Escolas em atividade'), (int) ($totals['schools'] ?? 0)],
            [__('Total de erros'), (int) ($totals['errors'] ?? 0)],
            [__('Total de avisos'), (int) ($totals['warnings'] ?? 0)],
            [__('Escolas com alertas'), (int) ($totals['with_alerts'] ?? 0)],
            [__('Escolas sem pendências'), (int) ($totals['ok'] ?? 0)],
            [__('Escolas sem lançamento'), (int) ($totals['without_data'] ?? 0)],
        ] as [$label, $value]) {
            $this->writeDataRow($sheet, $row, [(string) $label, (string) $value]);
            $row++;
        }

        $this->autosize($sheet, 6);
        $sheet->getColumnDimension('F')->setWidth(72);
    }

    /**
     * @param  list<string>  $values
     */
    private function writeHeaderRow(Worksheet $sheet, int $row, array $values, string $fillColor = self::COLOR_NAVY): void
    {
        foreach ($values as $col => $value) {
            $cell = $this->columnLetter($col + 1).$row;
            $sheet->setCellValue($cell, $value);
            $style = $sheet->getStyle($cell);
            $style->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_HEADER_FONT);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fillColor);
            $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * @param  list<string>  $values
     */
    private function writeDataRow(Worksheet $sheet, int $row, array $values, ?string $fillColor = null): void
    {
        foreach ($values as $col => $value) {
            $cell = $this->columnLetter($col + 1).$row;
            $sheet->setCellValue($cell, $value);
            if ($fillColor !== null) {
                $sheet->getStyle($cell)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB($fillColor);
            }
        }
    }

    private function autosize(Worksheet $sheet, int $columnCount): void
    {
        foreach (range(1, max(1, $columnCount)) as $col) {
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

    private function slugPart(string $value): string
    {
        $ascii = Str::ascii($value);
        $slug = (string) preg_replace('/[^a-z0-9]+/i', '_', $ascii);
        $slug = trim($slug, '_');

        return mb_strtolower($slug);
    }
}
