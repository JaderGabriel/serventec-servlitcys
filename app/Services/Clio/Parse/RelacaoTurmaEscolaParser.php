<?php

namespace App\Services\Clio\Parse;

use App\Models\Clio\ClioCampaignArtifact;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use Throwable;

final class RelacaoTurmaEscolaParser implements ArtifactParser
{
    /** @var list<string> */
    private const REQUIRED = [
        'Código da turma',
    ];

    public function __construct(
        private readonly CsvReader $csv,
        private readonly ?RelationCsvAggregator $aggregator = null,
    ) {}

    public function supports(string $kind): bool
    {
        return $kind === 'relacao_turma_escola';
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
                __('Colunas obrigatórias ausentes (turmas): :cols', ['cols' => implode(', ', $missing)]),
                ['missing' => $missing, 'headers' => $data['headers']],
            );
        }

        $agg = ($this->aggregator ?? new RelationCsvAggregator)->aggregateTurmas($data['rows'], $this->csv);
        $warnings = [];
        if ($agg['without_etapa'] > 0) {
            $warnings[] = __('Turmas sem Etapa de ensino: :n', ['n' => $agg['without_etapa']]);
        }

        return new ParseResult(
            status: $warnings === [] ? ParseResult::STATUS_OK : ParseResult::STATUS_WARNING,
            rowCount: count($data['rows']),
            warnings: $warnings,
            meta: [
                'header_offset' => 1,
                'delimiter' => CsvReader::DELIMITER,
                'aggregates' => $agg,
            ],
        );
    }
}
