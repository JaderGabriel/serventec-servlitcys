<?php

namespace App\Services\Cadunico;

use App\Repositories\CadunicoMunicipioSnapshotRepository;
use App\Repositories\FundebMunicipioReferenceRepository;

/**
 * Importa agregados municipais exportados do Cecad (CSV) para cadunico_municipio_snapshots.
 */
final class CadunicoCecadCsvImportService
{
    public function __construct(
        private CadunicoMunicipioSnapshotRepository $repository,
    ) {}

    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function importFile(string $absolutePath, ?int $defaultYear = null, ?string $filterIbge = null): array
    {
        if (! is_readable($absolutePath)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => [__('Ficheiro não encontrado: :path', ['path' => $absolutePath])]];
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => [__('Não foi possível abrir o CSV.')]];
        }

        $delimiter = (string) config('ieducar.cadunico.cecad.delimiter', ';');
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0, 'errors' => [__('CSV vazio ou inválido.')]];
        }

        $map = $this->resolveColumnIndexes($header);
        if ($map['ibge'] === null) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0, 'errors' => [__('Coluna IBGE não identificada no cabeçalho Cecad.')]];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($this->cell($row, $map['ibge']));
            if ($ibge === null) {
                $skipped++;

                continue;
            }

            if ($filterIbge !== null && $ibge !== $filterIbge) {
                $skipped++;

                continue;
            }

            $ano = $this->intCell($row, $map['ano']) ?? $defaultYear ?? (int) date('Y');
            if ($ano < 2000) {
                $skipped++;

                continue;
            }

            $popEscolar = $this->intCell($row, $map['pop_escolar']);
            $c45 = $this->intCell($row, $map['criancas_4_5']) ?? 0;
            $c610 = $this->intCell($row, $map['criancas_6_10']) ?? 0;
            $c1114 = $this->intCell($row, $map['criancas_11_14']) ?? 0;
            $c1517 = $this->intCell($row, $map['criancas_15_17']) ?? 0;
            $bandsSum = $c45 + $c610 + $c1114 + $c1517;

            if ($popEscolar === null || $popEscolar <= 0) {
                $popEscolar = $bandsSum > 0 ? $bandsSum : null;
            }

            $this->repository->upsert($ibge, $ano, [
                'pessoas_cadastradas' => $this->intCell($row, $map['pessoas']) ?? 0,
                'familias_cadastradas' => $this->intCell($row, $map['familias']) ?? 0,
                'criancas_0_3' => $this->intCell($row, $map['criancas_0_3']) ?? 0,
                'criancas_4_5' => $c45,
                'criancas_6_10' => $c610,
                'criancas_11_14' => $c1114,
                'criancas_15_17' => $c1517,
                'populacao_escolar_estimada' => $popEscolar ?? 0,
                'fonte' => 'cecad_csv',
                'schema_version' => '1',
                'metadados' => ['source_file' => basename($absolutePath)],
            ]);
            $imported++;
        }

        fclose($handle);

        return compact('imported', 'skipped', 'errors');
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, ?int>
     */
    private function resolveColumnIndexes(array $header): array
    {
        $norm = [];
        foreach ($header as $i => $col) {
            $norm[$i] = $this->normalizeHeader((string) $col);
        }

        $cfg = config('ieducar.cadunico.cecad.column_map', []);
        if (! is_array($cfg)) {
            $cfg = [];
        }

        $out = [];
        foreach ($cfg as $field => $aliases) {
            $out[$field] = null;
            if (! is_array($aliases)) {
                continue;
            }
            foreach ($norm as $idx => $name) {
                foreach ($aliases as $alias) {
                    if ($name === $this->normalizeHeader($alias)) {
                        $out[$field] = $idx;
                        break 2;
                    }
                }
            }
        }

        return $out;
    }

    private function normalizeHeader(string $value): string
    {
        $v = mb_strtolower(trim($value));
        $v = preg_replace('/\s+/', '_', $v) ?? $v;
        $v = str_replace(['á', 'à', 'ã', 'â', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ç'], [
            'a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'c',
        ], $v);

        return $v;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function cell(array $row, ?int $index): ?string
    {
        if ($index === null || ! isset($row[$index])) {
            return null;
        }

        $v = trim((string) $row[$index]);

        return $v === '' ? null : $v;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function intCell(array $row, ?int $index): ?int
    {
        $v = $this->cell($row, $index);
        if ($v === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $v);
        if ($digits === '' || $digits === null) {
            return null;
        }

        return (int) $digits;
    }
}
