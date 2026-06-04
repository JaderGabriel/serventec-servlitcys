<?php

namespace App\Services\Cadunico;

use App\Models\City;
use App\Repositories\CadunicoTerritorioSnapshotRepository;
use App\Repositories\FundebMunicipioReferenceRepository;

final class CadunicoTerritorioCsvImportService
{
    public function __construct(
        private CadunicoTerritorioSnapshotRepository $repository,
    ) {}

    /**
     * @return array{success: bool, imported: int, message: string}
     */
    public function importFile(string $path, int $ano, ?City $city = null): array
    {
        if (! is_readable($path)) {
            return ['success' => false, 'imported' => 0, 'message' => __('Ficheiro não encontrado.')];
        }

        $delimiter = (string) config('ieducar.cadunico.territorio.delimiter', ';');
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ['success' => false, 'imported' => 0, 'message' => __('Não foi possível abrir o CSV.')];
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if (! is_array($header)) {
            fclose($handle);

            return ['success' => false, 'imported' => 0, 'message' => __('CSV vazio.')];
        }

        $map = $this->resolveColumns($header);
        if ($map['codigo'] === null || $map['nome'] === null) {
            fclose($handle);

            return ['success' => false, 'imported' => 0, 'message' => __('Colunas obrigatórias: territorio_codigo, territorio_nome.')];
        }

        $imported = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (! is_array($row) || $this->rowEmpty($row)) {
                continue;
            }

            $ibge = $this->cell($row, $map['ibge']) ?? FundebMunicipioReferenceRepository::normalizeIbge($city?->ibge_municipio);
            if ($ibge === null) {
                continue;
            }

            $codigo = $this->cell($row, $map['codigo']);
            $nome = $this->cell($row, $map['nome']);
            if ($codigo === null || $nome === null) {
                continue;
            }

            $rowAno = $this->intCell($row, $map['ano']) ?? $ano;
            $c417 = $this->intCell($row, $map['criancas_4_17'])
                ?? $this->sumBands($row, $map);

            $this->repository->upsert($ibge, $rowAno, $codigo, [
                'territorio_nome' => $nome,
                'territorio_tipo' => $this->cell($row, $map['tipo']) ?? 'bairro',
                'criancas_4_17' => max(0, $c417),
                'criancas_4_5' => $this->intCell($row, $map['criancas_4_5']) ?? 0,
                'criancas_6_10' => $this->intCell($row, $map['criancas_6_10']) ?? 0,
                'criancas_11_14' => $this->intCell($row, $map['criancas_11_14']) ?? 0,
                'criancas_15_17' => $this->intCell($row, $map['criancas_15_17']) ?? 0,
                'familias_beneficio' => $this->intCell($row, $map['familias_beneficio']) ?? 0,
                'indice_vulnerabilidade' => $this->floatCell($row, $map['vulnerabilidade']),
                'latitude' => $this->floatCell($row, $map['lat']),
                'longitude' => $this->floatCell($row, $map['lng']),
                'fonte' => 'csv_territorio',
                'metadados' => ['import_path' => basename($path)],
            ]);
            $imported++;
        }

        fclose($handle);

        return [
            'success' => $imported > 0,
            'imported' => $imported,
            'message' => $imported > 0
                ? __(':n território(s) importado(s).', ['n' => $imported])
                : __('Nenhuma linha válida.'),
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array<string, ?int>
     */
    private function resolveColumns(array $header): array
    {
        $norm = [];
        foreach ($header as $i => $col) {
            $norm[mb_strtolower(trim($col))] = $i;
        }

        $find = static function (array $aliases) use ($norm): ?int {
            foreach ($aliases as $a) {
                if (isset($norm[mb_strtolower($a)])) {
                    return $norm[mb_strtolower($a)];
                }
            }

            return null;
        };

        $cfg = config('ieducar.cadunico.territorio.column_map', []);

        return [
            'ibge' => $find($cfg['ibge'] ?? ['codigo_ibge', 'ibge']),
            'ano' => $find($cfg['ano'] ?? ['ano', 'ano_referencia']),
            'codigo' => $find($cfg['codigo'] ?? ['territorio_codigo', 'codigo', 'cod_bairro']),
            'nome' => $find($cfg['nome'] ?? ['territorio_nome', 'bairro', 'nome', 'regiao']),
            'tipo' => $find($cfg['tipo'] ?? ['territorio_tipo', 'tipo']),
            'criancas_4_17' => $find($cfg['criancas_4_17'] ?? ['criancas_4_17', 'pop_4_17', 'populacao_escolar']),
            'criancas_4_5' => $find($cfg['criancas_4_5'] ?? ['criancas_4_5']),
            'criancas_6_10' => $find($cfg['criancas_6_10'] ?? ['criancas_6_10']),
            'criancas_11_14' => $find($cfg['criancas_11_14'] ?? ['criancas_11_14']),
            'criancas_15_17' => $find($cfg['criancas_15_17'] ?? ['criancas_15_17']),
            'familias_beneficio' => $find($cfg['familias_beneficio'] ?? ['familias_pbf', 'familias_beneficio']),
            'vulnerabilidade' => $find($cfg['vulnerabilidade'] ?? ['indice_vulnerabilidade', 'vulnerabilidade', 'ivs']),
            'lat' => $find($cfg['lat'] ?? ['latitude', 'lat']),
            'lng' => $find($cfg['lng'] ?? ['longitude', 'lng', 'lon']),
        ];
    }

    /**
     * @param  list<string|null>  $row
     */
    private function rowEmpty(array $row): bool
    {
        foreach ($row as $c) {
            if (trim((string) $c) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function cell(array $row, ?int $idx): ?string
    {
        if ($idx === null || ! isset($row[$idx])) {
            return null;
        }
        $v = trim((string) $row[$idx]);

        return $v !== '' ? $v : null;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function intCell(array $row, ?int $idx): ?int
    {
        $v = $this->cell($row, $idx);
        if ($v === null || ! is_numeric(str_replace(['.', ','], ['', ''], $v))) {
            return is_numeric($v ?? '') ? (int) $v : null;
        }

        return (int) round((float) str_replace(',', '.', preg_replace('/[^\d.,-]/', '', $v) ?? '0'));
    }

    /**
     * @param  list<string|null>  $row
     */
    private function floatCell(array $row, ?int $idx): ?float
    {
        $v = $this->cell($row, $idx);
        if ($v === null) {
            return null;
        }
        $n = (float) str_replace(',', '.', preg_replace('/[^\d.,-]/', '', $v));

        return $n > 0 ? $n : null;
    }

    /**
     * @param  array<string, ?int>  $map
     * @param  list<string|null>  $row
     */
    private function sumBands(array $row, array $map): int
    {
        $sum = 0;
        foreach (['criancas_4_5', 'criancas_6_10', 'criancas_11_14', 'criancas_15_17'] as $k) {
            $sum += $this->intCell($row, $map[$k]) ?? 0;
        }

        return $sum;
    }
}
