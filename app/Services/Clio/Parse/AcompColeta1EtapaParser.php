<?php

namespace App\Services\Clio\Parse;

use App\Models\Clio\ClioCampaignArtifact;
use Throwable;

final class AcompColeta1EtapaParser implements ArtifactParser
{
    /** @var list<string> */
    private const REQUIRED = [
        'Código da escola',
        'Nome da escola',
    ];

    public function __construct(
        private readonly CsvReader $csv,
    ) {}

    public function supports(string $kind): bool
    {
        return $kind === 'acomp_coleta_1etapa';
    }

    public function parse(string $absolutePath, ClioCampaignArtifact $artifact): ParseResult
    {
        try {
            $data = $this->csv->read($absolutePath, headerOffset: 1);
        } catch (Throwable $e) {
            return ParseResult::failed('EDU-REL-READ', $e->getMessage());
        }

        $missing = $this->csv->missingHeaders($data['headers'], self::REQUIRED);
        if ($missing !== []) {
            return ParseResult::failed(
                'EDU-REL-COLS',
                __('Colunas obrigatórias ausentes: :cols', ['cols' => implode(', ', $missing)]),
                ['missing' => $missing, 'headers' => $data['headers']],
            );
        }

        $schools = [];
        $warnings = [];
        $referenceDate = null;
        $emptyInep = 0;

        foreach ($data['rows'] as $row) {
            $inep = preg_replace('/\D+/', '', $this->csv->value($row, 'Código da escola')) ?? '';
            $name = $this->csv->value($row, 'Nome da escola');

            if ($referenceDate === null) {
                $ref = $this->csv->value($row, 'Data de Referência');
                if ($ref !== '') {
                    $referenceDate = $this->normalizeDate($ref);
                }
            }

            if (strlen($inep) < 8) {
                $emptyInep++;

                continue;
            }

            $inep = substr($inep, 0, 8);
            $schools[] = [
                'inep_code' => $inep,
                'name' => $name !== '' ? $name : __('Escola :inep', ['inep' => $inep]),
                'dependency' => $this->nullIfEmpty($this->csv->value($row, 'Dependência Administrativa')),
                'collection_form' => $this->nullIfEmpty($this->csv->value($row, 'Forma de Coleta')),
                'functioning_status' => $this->nullIfEmpty($this->csv->value($row, 'Situação de Funcionamento')),
                'meta' => [
                    'blocked' => $this->csv->value($row, 'Escola Bloqueada'),
                    'location' => $this->csv->value($row, 'Localização'),
                    'total_curricular' => $this->optionalNumeric($row, [
                        'Total matrículas - Curricular',
                    ]),
                    'total_aee' => $this->optionalNumeric($row, [
                        'Total matrículas - AEE',
                        'Total matrículas - Atendimento Educacional Especializado',
                    ]),
                    'total_ac' => $this->optionalNumeric($row, [
                        'Total matrículas - AC',
                        'Total matrículas - Atividade Complementar',
                        'Total matrículas - Atividade complementar',
                    ]),
                    'matriculas_a_confirmar' => $this->optionalNumeric($row, [
                        'Matrículas a confirmar ou desconsiderar',
                    ]),
                ],
            ];
        }

        if ($emptyInep > 0) {
            $warnings[] = __('Linhas sem Código da escola válido: :n', ['n' => $emptyInep]);
        }

        if ($schools === []) {
            return ParseResult::failed(
                'EDU-REL-EMPTY',
                __('Acompanhamento sem escolas válidas.'),
                ['header_offset' => 1],
            );
        }

        if ($referenceDate === null) {
            $referenceDate = $this->dateFromFilename($artifact->original_name);
        }

        $status = $warnings === [] ? ParseResult::STATUS_OK : ParseResult::STATUS_WARNING;

        return new ParseResult(
            status: $status,
            rowCount: count($data['rows']),
            code: null,
            warnings: $warnings,
            schools: $schools,
            referenceDate: $referenceDate,
            meta: [
                'header_offset' => 1,
                'school_rows' => count($schools),
                'delimiter' => CsvReader::DELIMITER,
            ],
        );
    }

    private function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $headers
     */
    private function optionalNumeric(array $row, array $headers): ?int
    {
        foreach ($headers as $header) {
            $raw = $this->csv->value($row, $header);
            if ($raw === '') {
                continue;
            }
            $normalized = preg_replace('/[^\d\-]/', '', $raw) ?? '';
            if ($normalized !== '' && is_numeric($normalized)) {
                return (int) $normalized;
            }
        }

        return null;
    }

    private function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m) === 1) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw) === 1) {
            return $raw;
        }

        return null;
    }

    private function dateFromFilename(string $name): ?string
    {
        if (preg_match('/Relatorio_Acomp_Coleta_1Etapa_(\d{2})(\d{2})(\d{4})/i', $name, $m) === 1) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }

        return null;
    }
}
