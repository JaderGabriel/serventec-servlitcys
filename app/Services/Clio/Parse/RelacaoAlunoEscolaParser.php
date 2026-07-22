<?php

namespace App\Services\Clio\Parse;

use App\Models\Clio\ClioCampaignArtifact;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use Throwable;

final class RelacaoAlunoEscolaParser implements ArtifactParser
{
    /** @var list<string> */
    private const REQUIRED_ANY = [
        ['Código da Matrícula', 'Identificação única'],
        ['Código da turma'],
    ];

    public function __construct(
        private readonly CsvReader $csv,
        private readonly ?RelationCsvAggregator $aggregator = null,
    ) {}

    public function supports(string $kind): bool
    {
        return $kind === 'relacao_aluno_escola';
    }

    public function parse(string $absolutePath, ClioCampaignArtifact $artifact): ParseResult
    {
        try {
            $data = $this->csv->read($absolutePath, headerOffset: 1);
        } catch (Throwable $e) {
            return ParseResult::failed('EDU-REL-READ', $e->getMessage());
        }

        foreach (self::REQUIRED_ANY as $group) {
            $missing = $this->csv->missingHeaders($data['headers'], $group);
            if (count($missing) === count($group)) {
                return ParseResult::failed(
                    'EDU-REL-COLS',
                    __('Colunas obrigatórias ausentes (alunos): :cols', ['cols' => implode(' | ', $group)]),
                    ['missing_group' => $group, 'headers' => $data['headers']],
                );
            }
        }

        $agg = ($this->aggregator ?? new RelationCsvAggregator)->aggregateAlunos(
            $data['rows'],
            $this->csv,
            $artifact->campaign?->year ? (int) $artifact->campaign->year : null,
        );
        $withTurma = $agg['total'] - $agg['without_turma'];

        $warnings = [];
        if ($withTurma === 0 && $agg['total'] > 0) {
            $warnings[] = __('Nenhuma linha com Código da turma preenchido.');
        }
        if ($agg['without_etapa'] > 0) {
            $warnings[] = __('Matrículas sem Etapa de ensino: :n', ['n' => $agg['without_etapa']]);
        }

        return new ParseResult(
            status: $warnings === [] ? ParseResult::STATUS_OK : ParseResult::STATUS_WARNING,
            rowCount: count($data['rows']),
            warnings: $warnings,
            meta: [
                'header_offset' => 1,
                'rows_with_turma' => $withTurma,
                'delimiter' => CsvReader::DELIMITER,
                'aggregates' => $agg,
            ],
        );
    }
}
