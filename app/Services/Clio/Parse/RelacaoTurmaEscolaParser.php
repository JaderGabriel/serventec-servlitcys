<?php

namespace App\Services\Clio\Parse;

use App\Models\Clio\ClioCampaignArtifact;
use Throwable;

final class RelacaoTurmaEscolaParser implements ArtifactParser
{
    /** @var list<string> */
    private const REQUIRED = [
        'Código da turma',
    ];

    public function __construct(
        private readonly CsvReader $csv,
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

        return new ParseResult(
            status: ParseResult::STATUS_OK,
            rowCount: count($data['rows']),
            meta: [
                'header_offset' => 1,
                'delimiter' => CsvReader::DELIMITER,
            ],
        );
    }
}
