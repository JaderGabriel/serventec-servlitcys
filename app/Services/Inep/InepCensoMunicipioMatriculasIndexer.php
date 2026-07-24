<?php

namespace App\Services\Inep;

use App\Repositories\FundebMunicipioReferenceRepository;
use App\Repositories\InepCensoMunicipioMatriculaRepository;
use App\Support\Inep\InepEducacensoMatriculaColumns;
use App\Support\InepMicrodadosEscolasCsv;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega matrículas do Censo (microdados INEP) por município IBGE e ano.
 */
final class InepCensoMunicipioMatriculasIndexer
{
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
        $header = fgetcsv($fh, 0, $delimiter, '"', '\\');
        if ($header === false) {
            fclose($fh);

            return 0;
        }

        $map = InepMicrodadosEscolasCsv::mapHeader($header);
        $ibgeIdx = $this->ibgeColumnIndex($map);
        $anoIdx = $this->columnIndex($map, ['nu_ano_censo', 'ano']);

        $depIdx = $this->columnIndex($map, ['tp_dependencia', 'dependencia_administrativa', 'tp_dependencia_adm']);

        if ($ibgeIdx === null || $anoIdx === null || ! InepEducacensoMatriculaColumns::hasMatriculaColumns($map)) {
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

        /** @var array<string, array<string, int>> $agg */
        $agg = [];

        while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) ($row[$ibgeIdx] ?? ''));
            if ($ibge === null || ($ibgeAllow !== null && ! isset($ibgeAllow[$ibge]))) {
                continue;
            }
            $anoRaw = trim((string) ($row[$anoIdx] ?? ''));
            $ano = ctype_digit($anoRaw) ? (int) $anoRaw : 0;
            if ($ano < 2000) {
                continue;
            }
            $segments = InepEducacensoMatriculaColumns::fromRow($row, $map);
            $etapas = InepEducacensoMatriculaColumns::etapasFromRow($row, $map);
            $mat = $segments['total'];
            if ($mat <= 0) {
                continue;
            }
            $regular = $segments['regular'];
            $eja = $segments['eja'];
            $especial = $segments['especial'];
            $complementar = $segments['complementar'];
            $key = $ibge.'|'.$ano;
            if (! isset($agg[$key])) {
                $agg[$key] = $this->emptyAggregateRow($ano);
            }
            $agg[$key]['mat'] += $mat;
            $agg[$key]['regular'] += $regular;
            $agg[$key]['eja'] += $eja;
            $agg[$key]['especial'] += $especial;
            $agg[$key]['complementar'] += $complementar;
            $agg[$key]['infantil'] += $etapas['infantil'];
            $agg[$key]['fundamental_1'] += $etapas['fundamental_1'];
            $agg[$key]['fundamental_2'] += $etapas['fundamental_2'];
            $agg[$key]['medio'] += $etapas['medio'];
            $agg[$key]['profissional'] += $etapas['profissional'];
            $agg[$key]['escolas']++;
            $dep = self::classifyMunicipalDependency($row, $depIdx);
            if ($dep === true) {
                $agg[$key]['municipal'] += $mat;
                $this->accumulateDependenciaSlice($agg[$key], $regular, $eja, $especial, $complementar, $etapas, true);
            } elseif ($dep === false) {
                $agg[$key]['nao_municipal'] += $mat;
                $this->accumulateDependenciaSlice($agg[$key], $regular, $eja, $especial, $complementar, $etapas, false);
            }
        }
        fclose($fh);

        $importedAt = now();
        $batch = [];
        foreach ($agg as $key => $data) {
            [$ibge] = explode('|', $key, 2);
            $batch[] = [
                'ibge_municipio' => $ibge,
                'ano' => $data['ano'],
                'matriculas_total' => $data['mat'],
                'escolas_contagem' => $data['escolas'],
                'fonte' => 'inep_microdados',
                'matriculas_municipal' => $data['municipal'] > 0 ? $data['municipal'] : null,
                'matriculas_nao_municipal' => $data['nao_municipal'] > 0 ? $data['nao_municipal'] : null,
                'matriculas_regular' => $data['regular'] > 0 ? $data['regular'] : null,
                'matriculas_eja' => $data['eja'] > 0 ? $data['eja'] : null,
                'matriculas_especial' => $data['especial'] > 0 ? $data['especial'] : null,
                'matriculas_complementar' => $data['complementar'] > 0 ? $data['complementar'] : null,
                'matriculas_infantil' => $data['infantil'] > 0 ? $data['infantil'] : null,
                'matriculas_fundamental_1' => $data['fundamental_1'] > 0 ? $data['fundamental_1'] : null,
                'matriculas_fundamental_2' => $data['fundamental_2'] > 0 ? $data['fundamental_2'] : null,
                'matriculas_medio' => $data['medio'] > 0 ? $data['medio'] : null,
                'matriculas_profissional' => $data['profissional'] > 0 ? $data['profissional'] : null,
                'dependencia_breakdown' => $this->dependenciaBreakdownFromAggregate($data),
            ];
        }

        $count = $this->repository->upsertBatch($batch, $importedAt);

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

    /**
     * @return array<string, int>
     */
    private function emptyAggregateRow(int $ano): array
    {
        return [
            'ano' => $ano,
            'mat' => 0,
            'escolas' => 0,
            'municipal' => 0,
            'nao_municipal' => 0,
            'regular' => 0,
            'eja' => 0,
            'especial' => 0,
            'complementar' => 0,
            'infantil' => 0,
            'fundamental_1' => 0,
            'fundamental_2' => 0,
            'medio' => 0,
            'profissional' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $bucket
     * @param  array{infantil: int, fundamental_1: int, fundamental_2: int, medio: int, profissional: int}  $etapas
     */
    private function accumulateDependenciaSlice(
        array &$bucket,
        int $regular,
        int $eja,
        int $especial,
        int $complementar,
        array $etapas,
        bool $municipal,
    ): void {
        $suffix = $municipal ? '_municipal' : '_nao_municipal';
        $bucket['regular'.$suffix] = ($bucket['regular'.$suffix] ?? 0) + $regular;
        $bucket['eja'.$suffix] = ($bucket['eja'.$suffix] ?? 0) + $eja;
        $bucket['especial'.$suffix] = ($bucket['especial'.$suffix] ?? 0) + $especial;
        $bucket['complementar'.$suffix] = ($bucket['complementar'.$suffix] ?? 0) + $complementar;
        $bucket['infantil'.$suffix] = ($bucket['infantil'.$suffix] ?? 0) + $etapas['infantil'];
        $bucket['fundamental_1'.$suffix] = ($bucket['fundamental_1'.$suffix] ?? 0) + $etapas['fundamental_1'];
        $bucket['fundamental_2'.$suffix] = ($bucket['fundamental_2'.$suffix] ?? 0) + $etapas['fundamental_2'];
        $bucket['medio'.$suffix] = ($bucket['medio'.$suffix] ?? 0) + $etapas['medio'];
        $bucket['profissional'.$suffix] = ($bucket['profissional'.$suffix] ?? 0) + $etapas['profissional'];
    }

    /**
     * @param  array<string, int>  $data
     * @return array<string, int>
     */
    private function dependenciaBreakdownFromAggregate(array $data): array
    {
        $map = [
            'regular' => 'matriculas_regular',
            'eja' => 'matriculas_eja',
            'especial' => 'matriculas_especial',
            'complementar' => 'matriculas_complementar',
            'infantil' => 'matriculas_infantil',
            'fundamental_1' => 'matriculas_fundamental_1',
            'fundamental_2' => 'matriculas_fundamental_2',
            'medio' => 'matriculas_medio',
            'profissional' => 'matriculas_profissional',
        ];

        $breakdown = [];
        foreach ($map as $short => $base) {
            foreach (['municipal', 'nao_municipal'] as $slice) {
                $key = $short.'_'.$slice;
                $value = (int) ($data[$key] ?? 0);
                if ($value > 0) {
                    $breakdown[$base.'_'.$slice] = $value;
                }
            }
        }

        return $breakdown;
    }
}
