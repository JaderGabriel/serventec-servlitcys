<?php

namespace App\Services\Inep;

use App\Repositories\FundebMunicipioReferenceRepository;
use App\Repositories\InepCensoMunicipioMatriculaRepository;
use App\Support\InepMicrodadosEscolasCsv;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega matrículas do Censo (microdados INEP) por município IBGE e ano.
 */
final class InepCensoMunicipioMatriculasIndexer
{
    /** @var list<string> */
    private const MATRICULA_TOTAL_ALIASES = [
        'qt_mat_bas',
        'qt_mat_inf',
        'qt_mat_fund',
        'qt_mat_med',
        'qt_mat_prof',
        'qt_mat_eja',
        'qt_mat_esp',
        'qt_mat',
        'quantidade_matricula',
        'quant_matriculas',
    ];

    /** @var list<string> */
    private const MATRICULA_REGULAR_ALIASES = ['qt_mat_inf', 'qt_mat_fund', 'qt_mat_med', 'qt_mat_bas'];

    /** @var list<string> */
    private const MATRICULA_EJA_ALIASES = ['qt_mat_eja'];

    /** @var list<string> */
    private const MATRICULA_ESPECIAL_ALIASES = ['qt_mat_esp'];

    /** @var list<string> */
    private const MATRICULA_COMPLEMENTAR_ALIASES = ['qt_mat_ativ_comp', 'qt_mat_ativ_comp_esp', 'qt_mat_prof'];

    public function __construct(
        private InepCensoMunicipioMatriculaRepository $repository,
    ) {}

    /**
     * @param  list<int>|null  $onlyIbgeFilter  Chaves IBGE 7 dígitos a restringir (opcional)
     */
    public function indexFromMicrodadosCsv(string $absolutePath, ?array $onlyIbgeFilter = null): int
    {
        if (! is_readable($absolutePath) || ! Schema::hasTable('inep_censo_municipio_matriculas')) {
            return 0;
        }

        $fh = fopen($absolutePath, 'rb');
        if ($fh === false) {
            return 0;
        }

        $firstLine = fgets($fh);
        if ($firstLine === false) {
            fclose($fh);

            return 0;
        }
        $delimiter = InepMicrodadosEscolasCsv::delimiterFromFirstLine($firstLine);
        rewind($fh);
        $header = fgetcsv($fh, 0, $delimiter);
        if ($header === false) {
            fclose($fh);

            return 0;
        }

        $map = InepMicrodadosEscolasCsv::mapHeader($header);
        $ibgeIdx = $this->ibgeColumnIndex($map);
        $anoIdx = $this->columnIndex($map, ['nu_ano_censo', 'ano']);
        $matTotalIndices = $this->matriculaColumnIndices($map, self::MATRICULA_TOTAL_ALIASES);
        $matRegularIndices = $this->matriculaColumnIndices($map, self::MATRICULA_REGULAR_ALIASES);
        $matEjaIndices = $this->matriculaColumnIndices($map, self::MATRICULA_EJA_ALIASES);
        $matEspecialIndices = $this->matriculaColumnIndices($map, self::MATRICULA_ESPECIAL_ALIASES);
        $matComplementarIndices = $this->matriculaColumnIndices($map, self::MATRICULA_COMPLEMENTAR_ALIASES);

        $depIdx = $this->columnIndex($map, ['tp_dependencia', 'dependencia_administrativa', 'tp_dependencia_adm']);

        if ($ibgeIdx === null || $anoIdx === null || $matTotalIndices === []) {
            fclose($fh);
            Log::warning('INEP censo matrículas: colunas IBGE/ano/matricula não encontradas no CSV.');

            return 0;
        }

        $ibgeAllow = null;
        if ($onlyIbgeFilter !== null) {
            $ibgeAllow = [];
            foreach ($onlyIbgeFilter as $ibge) {
                $norm = FundebMunicipioReferenceRepository::normalizeIbge((string) $ibge);
                if ($norm !== null) {
                    $ibgeAllow[$norm] = true;
                }
            }
            if ($ibgeAllow === []) {
                fclose($fh);

                return 0;
            }
        }

        /** @var array<string, array{ano: int, mat: int, escolas: int, municipal: int, nao_municipal: int, regular: int, eja: int, especial: int, complementar: int}> $agg */
        $agg = [];

        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) ($row[$ibgeIdx] ?? ''));
            if ($ibge === null || ($ibgeAllow !== null && ! isset($ibgeAllow[$ibge]))) {
                continue;
            }
            $anoRaw = trim((string) ($row[$anoIdx] ?? ''));
            $ano = ctype_digit($anoRaw) ? (int) $anoRaw : 0;
            if ($ano < 2000) {
                continue;
            }
            $mat = $this->sumRowColumns($row, $matTotalIndices);
            if ($mat <= 0) {
                continue;
            }
            $regular = $this->sumRowColumns($row, $matRegularIndices);
            $eja = $this->sumRowColumns($row, $matEjaIndices);
            $especial = $this->sumRowColumns($row, $matEspecialIndices);
            $complementar = $this->sumRowColumns($row, $matComplementarIndices);
            $key = $ibge.'|'.$ano;
            if (! isset($agg[$key])) {
                $agg[$key] = [
                    'ano' => $ano,
                    'mat' => 0,
                    'escolas' => 0,
                    'municipal' => 0,
                    'nao_municipal' => 0,
                    'regular' => 0,
                    'eja' => 0,
                    'especial' => 0,
                    'complementar' => 0,
                ];
            }
            $agg[$key]['mat'] += $mat;
            $agg[$key]['regular'] += $regular;
            $agg[$key]['eja'] += $eja;
            $agg[$key]['especial'] += $especial;
            $agg[$key]['complementar'] += $complementar;
            $agg[$key]['escolas']++;
            $dep = self::classifyMunicipalDependency($row, $depIdx);
            if ($dep === true) {
                $agg[$key]['municipal'] += $mat;
            } elseif ($dep === false) {
                $agg[$key]['nao_municipal'] += $mat;
            }
        }
        fclose($fh);

        $importedAt = now();
        $count = 0;
        foreach ($agg as $key => $data) {
            [$ibge] = explode('|', $key, 2);
            $this->repository->upsert(
                $ibge,
                $data['ano'],
                $data['mat'],
                $data['escolas'],
                'inep_microdados',
                $importedAt,
                $data['municipal'] > 0 ? $data['municipal'] : null,
                $data['nao_municipal'] > 0 ? $data['nao_municipal'] : null,
                $data['regular'] > 0 ? $data['regular'] : null,
                $data['eja'] > 0 ? $data['eja'] : null,
                $data['especial'] > 0 ? $data['especial'] : null,
                $data['complementar'] > 0 ? $data['complementar'] : null,
            );
            $count++;
        }

        Log::info('INEP censo matrículas municipais indexadas', ['municipios_anos' => $count, 'file' => $absolutePath]);

        return $count;
    }

    /**
     * @param  array<string, int>  $map
     */
    private function ibgeColumnIndex(array $map): ?int
    {
        foreach (['co_municipio', 'codigo_ibge', 'ibge', 'cod_municipio', 'codigo_municipio'] as $name) {
            if (isset($map[mb_strtolower($name)])) {
                return $map[mb_strtolower($name)];
            }
        }

        return null;
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $names
     */
    private function columnIndex(array $map, array $names): ?int
    {
        foreach ($names as $name) {
            $k = mb_strtolower($name);
            if (isset($map[$k])) {
                return $map[$k];
            }
        }

        return null;
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $aliases
     * @return list<int>
     */
    private function matriculaColumnIndices(array $map, array $aliases): array
    {
        $indices = [];
        foreach ($aliases as $alias) {
            $k = mb_strtolower($alias);
            if (isset($map[$k])) {
                $indices[] = $map[$k];
            }
        }

        return array_values(array_unique($indices));
    }

    /**
     * @param  list<string|null>  $row
     * @param  list<int>  $indices
     */
    private function sumRowColumns(array $row, array $indices): int
    {
        $sum = 0;
        foreach ($indices as $idx) {
            if (isset($row[$idx]) && is_numeric($row[$idx])) {
                $sum += max(0, (int) $row[$idx]);
            }
        }

        return $sum;
    }

    /**
     * @param  list<string|null>  $row
     */
    private static function classifyMunicipalDependency(array $row, ?int $depIdx): ?bool
    {
        if ($depIdx === null) {
            return null;
        }

        $raw = trim((string) ($row[$depIdx] ?? ''));
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw === 3;
        }

        $lower = mb_strtolower($raw);
        if (str_contains($lower, 'municipal') && ! str_contains($lower, 'estadual')) {
            return true;
        }
        if (str_contains($lower, 'estadual')
            || str_contains($lower, 'federal')
            || str_contains($lower, 'privad')) {
            return false;
        }

        return null;
    }
}
