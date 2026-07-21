<?php

namespace App\Services\Clio\Parse;

use App\Models\Clio\ClioCampaignArtifact;
use Throwable;

/**
 * Relação profissionais — cabeçalho na linha 2 (offset 2).
 */
final class RelacaoProfissionalEscolaParser implements ArtifactParser
{
    public const HEADER_OFFSET = 2;

    /** @var list<string> */
    private const REQUIRED_ANY = [
        ['Identificação única', 'Nome', 'CPF'],
    ];

    public function __construct(
        private readonly CsvReader $csv,
    ) {}

    public function supports(string $kind): bool
    {
        return $kind === 'relacao_profissional_escola';
    }

    public function parse(string $absolutePath, ClioCampaignArtifact $artifact): ParseResult
    {
        try {
            $data = $this->csv->read($absolutePath, headerOffset: self::HEADER_OFFSET);
        } catch (Throwable $e) {
            return ParseResult::failed('EDU-REL-READ', $e->getMessage());
        }

        foreach (self::REQUIRED_ANY as $group) {
            $found = false;
            foreach ($group as $col) {
                if ($this->csv->missingHeaders($data['headers'], [$col]) === []) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return ParseResult::failed(
                    'EDU-REL-COLS',
                    __('Colunas obrigatórias ausentes (profissionais): :cols', ['cols' => implode(' | ', $group)]),
                    [
                        'missing_group' => $group,
                        'headers' => $data['headers'],
                        'header_offset' => self::HEADER_OFFSET,
                    ],
                );
            }
        }

        return new ParseResult(
            status: ParseResult::STATUS_OK,
            rowCount: count($data['rows']),
            meta: [
                'header_offset' => self::HEADER_OFFSET,
                'delimiter' => CsvReader::DELIMITER,
                'dual_header' => true,
            ],
        );
    }
}
