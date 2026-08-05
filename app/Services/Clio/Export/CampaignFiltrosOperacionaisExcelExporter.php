<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Support\Filesystem\AppTemp;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel dedicado aos filtros operacionais (CLI-XLS) — workbook separado do Excel completo.
 */
final class CampaignFiltrosOperacionaisExcelExporter
{
    private const COLOR_NAVY = '0F172A';

    private const COLOR_HEADER_FONT = 'FFFFFF';

    private const COLOR_WARN = 'FFF7ED';

    public function __construct(
        private readonly CampaignFiltrosOperacionaisComposer $composer,
    ) {}

    public function download(ClioCampaign $campaign): StreamedResponse
    {
        $payload = $this->composer->compose($campaign);

        $citySlug = $this->slugPart((string) $campaign->municipality_name) ?: 'municipio';
        $ibge = preg_replace('/\D+/', '', (string) ($campaign->ibge_municipio ?? '')) ?: 'ibge';
        $refDate = $campaign->reference_date
            ? $campaign->reference_date->format('Y-m-d')
            : (string) ((int) $campaign->year);
        $filename = sprintf('clio_filtros_operacionais_%s_%s_%s.xlsx', $citySlug, $ibge, $refDate);

        $tmp = AppTemp::path('clio-filtros-'.uniqid('', true).'.xlsx', 'exports');
        $this->writeXlsx($tmp, $payload);
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
     * @param  array<string, mixed>  $payload
     */
    private function writeXlsx(string $absolutePath, array $payload): void
    {
        $spreadsheet = new Spreadsheet;
        $index = $spreadsheet->getActiveSheet();
        $index->setTitle(__('00-Índice'));
        $this->fillIndex($index, $payload);

        $aptas = $spreadsheet->createSheet();
        $aptas->setTitle(__('01-Escolas aptas'));
        $this->fillEscolas($aptas, $payload['escolas_aptas'] ?? []);

        $acomp = $spreadsheet->createSheet();
        $acomp->setTitle(__('02-Somatórios Acomp'));
        $this->fillAcompSums($acomp, $payload['somatarios_acomp'] ?? []);

        $turmas = $spreadsheet->createSheet();
        $turmas->setTitle(__('03-Turmas'));
        $this->fillTurmas($turmas, $payload['turmas'] ?? []);

        $turmaSums = $spreadsheet->createSheet();
        $turmaSums->setTitle(__('04-Somatórios turmas'));
        $this->fillTurmaSums($turmaSums, $payload['somatarios_turmas'] ?? []);

        $demo = $spreadsheet->createSheet();
        $demo->setTitle(__('05-Demografia'));
        $this->fillDemografia($demo, $payload['demografia'] ?? []);

        $nee = $spreadsheet->createSheet();
        $nee->setTitle(__('06-NEE-TRS'));
        $this->fillNee($nee, $payload['nee'] ?? []);

        $pnate = $spreadsheet->createSheet();
        $pnate->setTitle(__('07-PNATE'));
        $this->fillPnate($pnate, $payload['pnate'] ?? []);

        $etapas = $spreadsheet->createSheet();
        $etapas->setTitle(__('08-Etapas aluno'));
        $this->fillEtapas($etapas, $payload['etapas'] ?? []);

        $ti = $spreadsheet->createSheet();
        $ti->setTitle(__('09-Tempo integral'));
        $this->fillTempoIntegral($ti, $payload['tempo_integral'] ?? []);

        $alerts = $spreadsheet->createSheet();
        $alerts->setTitle(__('10-Alertas'));
        $this->fillAlerts($alerts, $payload['alerts'] ?? []);

        $fora = $spreadsheet->createSheet();
        $fora->setTitle(__('11-Fora do escopo'));
        $this->fillEscolas($fora, $payload['escolas_fora'] ?? []);

        (new Xlsx($spreadsheet))->save($absolutePath);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fillIndex(Worksheet $sheet, array $payload): void
    {
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $rules = is_array($meta['rules'] ?? null) ? $meta['rules'] : [];
        $row = 1;
        $sheet->setCellValue('A1', __('Clio — Excel de filtros operacionais'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $row = 3;
        foreach ([
            __('Município') => $meta['municipality'] ?? '',
            __('UF') => $meta['uf'] ?? '',
            __('IBGE') => $meta['ibge'] ?? '',
            __('Ano') => $meta['year'] ?? '',
            __('Referência') => $meta['reference_date'] ?? '',
            __('UUID') => $meta['uuid'] ?? '',
            __('Emissão') => $meta['emitted_at'] ?? '',
            __('Escolas aptas') => $meta['schools_aptas'] ?? 0,
            __('Fora do escopo') => $meta['schools_fora'] ?? 0,
        ] as $label => $value) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $value);
            $row++;
        }
        $row += 1;
        $sheet->setCellValue('A'.$row, __('Regras aplicadas'));
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $row++;
        foreach ($rules as $key => $text) {
            $sheet->setCellValue('A'.$row, (string) $key);
            $sheet->setCellValue('B'.$row, (string) $text);
            $row++;
        }
        $row += 1;
        $sheet->setCellValue('A'.$row, __('Uso interno — abas com listagens podem conter identificação de alunos.'));
        $this->autosize($sheet, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function fillEscolas(Worksheet $sheet, array $rows): void
    {
        $this->writeHeaderRow($sheet, 1, [
            __('INEP'), __('Escola'), __('Dependência'), __('Situação'), __('Localização'),
            __('Categoria privada'), __('Parceria'), __('Curricular'), __('AEE'), __('AC'), __('Curricular+AC'),
        ]);
        $r = 2;
        foreach ($rows as $row) {
            $this->writeDataRow($sheet, $r, [
                $row['inep'] ?? '',
                $row['name'] ?? '',
                $row['dependency'] ?? '',
                $row['functioning'] ?? '',
                $row['location'] ?? '',
                $row['private_category'] ?? '',
                $row['partnership_authority'] ?? '',
                $row['total_curricular'] ?? '',
                $row['total_aee'] ?? '',
                $row['total_ac'] ?? '',
                $row['total_curricular_ac'] ?? '',
            ]);
            $r++;
        }
        $this->autosize($sheet, 11);
    }

    /**
     * @param  array<string, mixed>  $sums
     */
    private function fillAcompSums(Worksheet $sheet, array $sums): void
    {
        $this->writeHeaderRow($sheet, 1, [__('Indicador'), __('Valor'), __('Nota')]);
        $pairs = [
            [__('Tot. curricular'), $sums['total_curricular'] ?? 0, ''],
            [__('Tot. AEE'), $sums['total_aee'] ?? 0, ''],
            [__('Tot. AC'), $sums['total_ac'] ?? 0, ''],
            [__('Tot. curricular+AC'), $sums['total_curricular_ac'] ?? 0, ''],
            [__('Total Infantil (Creche+Pré)'), $sums['infantil'] ?? 0, ''],
            [__('Total Fundamental'), $sums['fundamental'] ?? 0, ''],
            [__('Total EJA'), $sums['eja'] ?? 0, ''],
            [__('Proxy integral Fund. (AC + Curricular+AC)'), $sums['proxy_integral_fund'] ?? 0, $sums['proxy_integral_note'] ?? ''],
        ];
        $r = 2;
        foreach ($pairs as [$label, $value, $note]) {
            $this->writeDataRow($sheet, $r, [$label, $value, $note], $note !== '' ? self::COLOR_WARN : null);
            $r++;
        }
        $this->autosize($sheet, 3);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function fillTurmas(Worksheet $sheet, array $rows): void
    {
        $this->writeHeaderRow($sheet, 1, [
            __('INEP'), __('Escola'), __('Código'), __('Tipo'), __('Bucket'), __('Agregada'), __('Etapa'),
            __('Turno'), __('CH'), __('Jornada'), __('Alunos'), __('Profissionais'), __('Alertas'),
        ]);
        $r = 2;
        foreach ($rows as $row) {
            $flags = [];
            if (! empty($row['alert_eja_low'])) {
                $flags[] = 'EJA<20h';
            }
            if (! empty($row['alert_ac_below'])) {
                $flags[] = 'AC<15h';
            }
            if (! empty($row['ac_proxy_ok'])) {
                $flags[] = 'AC≥15h';
            }
            $this->writeDataRow($sheet, $r, [
                $row['school_inep'] ?? '',
                $row['school_name'] ?? '',
                $row['codigo'] ?? '',
                $row['tipo'] ?? '',
                $row['bucket'] ?? '',
                $row['agregada'] ?? '',
                $row['etapa'] ?? '',
                $row['turno'] ?? '',
                $row['ch_label'] ?? '',
                $row['jornada'] ?? '',
                $row['alunos'] ?? 0,
                $row['profissionais'] ?? 0,
                implode(', ', $flags),
            ], $flags !== [] ? self::COLOR_WARN : null);
            $r++;
        }
        $this->autosize($sheet, 13);
    }

    /**
     * @param  array<string, mixed>  $sums
     */
    private function fillTurmaSums(Worksheet $sheet, array $sums): void
    {
        $this->writeHeaderRow($sheet, 1, [__('Indicador'), __('Valor'), __('Nota')]);
        $pairs = [
            [__('Total de turmas (linhas)'), $sums['turmas'] ?? 0, __('Contagem de turmas após filtro — não usar coluna de profissionais')],
            [__('Total de alunos (soma col. alunos)'), $sums['alunos'] ?? 0, ''],
            [__('Soma profissionais (col. W)'), $sums['profissionais'] ?? 0, __('Não confundir com nº de turmas')],
            [__('Turmas parciais'), $sums['parcial'] ?? 0, __('CH &lt; 35 h')],
            [__('Turmas integrais'), $sums['integral'] ?? 0, __('CH ≥ 35 h ou turno integral')],
            [__('Turmas AEE'), $sums['aee'] ?? 0, ''],
            [__('Turmas AC'), $sums['ac'] ?? 0, ''],
            [__('Turmas AC com CH ≥ 15 h'), $sums['ac_proxy_ok'] ?? 0, ''],
        ];
        $r = 2;
        foreach ($pairs as [$label, $value, $note]) {
            $this->writeDataRow($sheet, $r, [$label, $value, $note]);
            $r++;
        }
        $this->autosize($sheet, 3);
    }

    /**
     * @param  array<string, mixed>  $demo
     */
    private function fillDemografia(Worksheet $sheet, array $demo): void
    {
        $this->writeHeaderRow($sheet, 1, [__('Cor/Raça'), __('Total')]);
        $r = 2;
        foreach (is_array($demo['by_cor'] ?? null) ? $demo['by_cor'] : [] as $label => $n) {
            $this->writeDataRow($sheet, $r, [$label, $n]);
            $r++;
        }
        $r += 1;
        $sheet->setCellValue('A'.$r, __('Listagem — Não declarado'));
        $sheet->getStyle('A'.$r)->getFont()->setBold(true);
        $r++;
        $this->writeHeaderRow($sheet, $r, [__('INEP'), __('Escola'), __('ID'), __('Nome')]);
        $r++;
        foreach (is_array($demo['undeclared'] ?? null) ? $demo['undeclared'] : [] as $row) {
            $this->writeDataRow($sheet, $r, [
                $row['inep'] ?? '',
                $row['school'] ?? '',
                $row['id'] ?? '',
                $row['nome'] ?? '',
            ], self::COLOR_WARN);
            $r++;
        }
        $this->autosize($sheet, 4);
    }

    /**
     * @param  array<string, mixed>  $nee
     */
    private function fillNee(Worksheet $sheet, array $nee): void
    {
        $this->writeHeaderRow($sheet, 1, [__('Indicador'), __('Valor')]);
        $this->writeDataRow($sheet, 2, [__('Linhas aluno'), $nee['total_rows'] ?? 0]);
        $this->writeDataRow($sheet, 3, [__('Com deficiência/NEE (K)'), $nee['with_k'] ?? 0]);
        $this->writeDataRow($sheet, 4, [__('Com transtorno (L)'), $nee['with_l'] ?? 0]);
        $this->writeDataRow($sheet, 5, [__('L preenchido e K vazio'), count($nee['l_without_k'] ?? [])]);
        $r = 7;
        $sheet->setCellValue('A'.$r, __('Listagem — transtorno sem deficiência'));
        $sheet->getStyle('A'.$r)->getFont()->setBold(true);
        $r++;
        $this->writeHeaderRow($sheet, $r, [__('INEP'), __('Escola'), __('ID'), __('Nome'), __('Transtorno')]);
        $r++;
        foreach (is_array($nee['l_without_k'] ?? null) ? $nee['l_without_k'] : [] as $row) {
            $this->writeDataRow($sheet, $r, [
                $row['inep'] ?? '',
                $row['school'] ?? '',
                $row['id'] ?? '',
                $row['nome'] ?? '',
                $row['transtorno'] ?? '',
            ], self::COLOR_WARN);
            $r++;
        }
        $this->autosize($sheet, 5);
    }

    /**
     * @param  array<string, mixed>  $pnate
     */
    private function fillPnate(Worksheet $sheet, array $pnate): void
    {
        $this->writeHeaderRow($sheet, 1, [__('Indicador'), __('Valor'), __('Nota')]);
        $this->writeDataRow($sheet, 2, [
            __('Coluna residência detectada'),
            ! empty($pnate['residence_column']) ? __('Sim') : __('Não'),
            empty($pnate['residence_column'])
                ? __('Sem coluna de residência — exclusão urbano–urbano não aplicada')
                : '',
        ]);
        $this->writeDataRow($sheet, 3, [__('Elegíveis PNATE'), $pnate['elegivel'] ?? 0, '']);
        $this->writeDataRow($sheet, 4, [__('Excluídos urbano–urbano'), $pnate['excluido_urbano_urbano'] ?? 0, '']);
        $this->writeDataRow($sheet, 5, [__('Sem transporte'), $pnate['sem_transporte'] ?? 0, '']);
        $r = 7;
        $sheet->setCellValue('A'.$r, __('Veículos (elegíveis)'));
        $sheet->getStyle('A'.$r)->getFont()->setBold(true);
        $r++;
        $this->writeHeaderRow($sheet, $r, [__('Veículo'), __('Total')]);
        $r++;
        foreach (is_array($pnate['by_veiculo'] ?? null) ? $pnate['by_veiculo'] : [] as $label => $n) {
            $this->writeDataRow($sheet, $r, [$label, $n]);
            $r++;
        }
        $r += 1;
        $sheet->setCellValue('A'.$r, __('Amostra excluídos urbano–urbano'));
        $sheet->getStyle('A'.$r)->getFont()->setBold(true);
        $r++;
        $this->writeHeaderRow($sheet, $r, [__('INEP'), __('Escola'), __('ID'), __('Residência')]);
        $r++;
        foreach (is_array($pnate['excluded_sample'] ?? null) ? $pnate['excluded_sample'] : [] as $row) {
            $this->writeDataRow($sheet, $r, [
                $row['inep'] ?? '',
                $row['school'] ?? '',
                $row['id'] ?? '',
                $row['residencia'] ?? '',
            ], self::COLOR_WARN);
            $r++;
        }
        $this->autosize($sheet, 3);
    }

    /**
     * @param  array<string, int>  $etapas
     */
    private function fillEtapas(Worksheet $sheet, array $etapas): void
    {
        $this->writeHeaderRow($sheet, 1, [__('Etapa / perfil'), __('Total')]);
        $r = 2;
        foreach ($etapas as $label => $n) {
            $this->writeDataRow($sheet, $r, [$label, $n]);
            $r++;
        }
        $this->autosize($sheet, 2);
    }

    /**
     * @param  array<string, mixed>  $ti
     */
    private function fillTempoIntegral(Worksheet $sheet, array $ti): void
    {
        $this->writeHeaderRow($sheet, 1, [__('Indicador'), __('Valor'), __('Nota')]);
        $this->writeDataRow($sheet, 2, [__('Alunos em turmas integrais (pleno ≥35 h)'), $ti['pleno'] ?? 0, $ti['note'] ?? '']);
        $this->writeDataRow($sheet, 3, [__('Alunos em AC ≥15 h (proxy contraturno)'), $ti['contraturno_proxy'] ?? 0, __('Não substitui CH curricular+AC ≥35 por pessoa')]);
        $this->writeDataRow($sheet, 4, [__('Alunos em AEE'), $ti['aee'] ?? 0, __('Fora do tempo integral')]);
        $this->writeDataRow($sheet, 5, [__('Alunos EJA em turmas ≥35 h (excluídos do integral)'), $ti['eja_excluido'] ?? 0, '']);
        $this->autosize($sheet, 3);
    }

    /**
     * @param  list<array<string, mixed>>  $alerts
     */
    private function fillAlerts(Worksheet $sheet, array $alerts): void
    {
        $this->writeHeaderRow($sheet, 1, [__('Código'), __('INEP'), __('Escola'), __('Detalhe'), __('Mensagem')]);
        $r = 2;
        foreach ($alerts as $alert) {
            $this->writeDataRow($sheet, $r, [
                $alert['code'] ?? '',
                $alert['school_inep'] ?? '',
                $alert['school_name'] ?? '',
                $alert['detail'] ?? '',
                $alert['message'] ?? '',
            ], self::COLOR_WARN);
            $r++;
        }
        if ($alerts === []) {
            $this->writeDataRow($sheet, $r, [__('Sem alertas operacionais nesta coleta.'), '', '', '', '']);
        }
        $this->autosize($sheet, 5);
    }

    /**
     * @param  list<string|int|float|null>  $values
     */
    private function writeHeaderRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $col => $value) {
            $cell = $this->columnLetter($col + 1).$row;
            $sheet->setCellValue($cell, $value);
            $style = $sheet->getStyle($cell);
            $style->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_HEADER_FONT);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_NAVY);
            $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * @param  list<string|int|float|null>  $values
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
