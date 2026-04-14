<?php

namespace App\Services\Inep;

use App\Models\InepCensoEscolaGeoAgg;
use App\Support\InepMicrodadosEscolasCsv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Localização administrativa agregada (Censo Escolar / microdados_ed_basica) por código INEP.
 * Usado no modal quando o cadastro i-Educar não traz endereço.
 */
class InepCensoEscolaGeoAggService
{
    /**
     * @param  list<int>  $inepCodes
     * @return array<int, array{
     *   nu_ano_censo: ?int,
     *   no_municipio: ?string,
     *   sg_uf: ?string,
     *   no_uf: ?string,
     *   no_regiao: ?string,
     *   tp_localizacao: ?int,
     *   localizacao_label: ?string,
     *   resumo: string
     * }>
     */
    public function lookupByInepCodes(array $inepCodes): array
    {
        if ($inepCodes === [] || ! Schema::hasTable((new InepCensoEscolaGeoAgg)->getTable())) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($c) => is_numeric($c) ? (int) $c : 0,
            $inepCodes
        ), static fn (int $v) => $v > 0)));

        if ($ids === []) {
            return [];
        }

        $rows = InepCensoEscolaGeoAgg::query()->whereIn('inep_code', $ids)->get();
        $out = [];
        foreach ($rows as $row) {
            $inep = (int) $row->inep_code;
            $locLabel = $this->localizacaoLabel($row->tp_localizacao);
            $out[$inep] = [
                'nu_ano_censo' => $row->nu_ano_censo,
                'no_municipio' => $row->no_municipio,
                'sg_uf' => $row->sg_uf,
                'no_uf' => $row->no_uf,
                'no_regiao' => $row->no_regiao,
                'tp_localizacao' => $row->tp_localizacao,
                'localizacao_label' => $locLabel,
                'resumo' => $this->formatResumo($row->no_municipio, $row->sg_uf, $row->no_regiao, $locLabel, $row->nu_ano_censo),
            ];
        }

        return $out;
    }

    /**
     * Lê o CSV de microdados (escola), trunca a tabela e reimporta índice agregado.
     * Pode demorar vários minutos em ficheiros nacionais completos.
     */
    public function indexFromMicrodadosCsv(string $absolutePath): int
    {
        if (! is_readable($absolutePath) || ! Schema::hasTable((new InepCensoEscolaGeoAgg)->getTable())) {
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
        $inepIdx = InepMicrodadosEscolasCsv::inepColumnIndex($map);
        if ($inepIdx === null) {
            fclose($fh);

            return 0;
        }

        $idx = static function (array $map, array $names): ?int {
            foreach ($names as $n) {
                $k = mb_strtolower($n);
                if (isset($map[$k])) {
                    return $map[$k];
                }
            }

            return null;
        };

        $iAno = $idx($map, ['nu_ano_censo']);
        $iMun = $idx($map, ['no_municipio']);
        $iUfS = $idx($map, ['sg_uf']);
        $iUfN = $idx($map, ['no_uf']);
        $iReg = $idx($map, ['no_regiao']);
        $iLoc = $idx($map, ['tp_localizacao']);

        DB::table((new InepCensoEscolaGeoAgg)->getTable())->truncate();

        $batch = [];
        $totalIndexed = 0;
        $flush = function () use (&$batch, &$totalIndexed): void {
            if ($batch === []) {
                return;
            }
            DB::table((new InepCensoEscolaGeoAgg)->getTable())->upsert(
                $batch,
                ['inep_code'],
                ['nu_ano_censo', 'no_municipio', 'sg_uf', 'no_uf', 'no_regiao', 'tp_localizacao', 'updated_at']
            );
            $totalIndexed += count($batch);
            $batch = [];
        };

        $now = now();
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $inep = InepMicrodadosEscolasCsv::parseInepCode($row[$inepIdx] ?? '');
            if ($inep <= 0) {
                continue;
            }

            $mun = $iMun !== null ? $this->trimStr($row[$iMun] ?? null) : null;
            $sgUf = $iUfS !== null ? $this->trimStr($row[$iUfS] ?? null) : null;
            if ($sgUf !== null && mb_strlen($sgUf) > 2) {
                $sgUf = mb_strtoupper(mb_substr($sgUf, 0, 2));
            }
            $noUf = $iUfN !== null ? $this->trimStr($row[$iUfN] ?? null) : null;
            $reg = $iReg !== null ? $this->trimStr($row[$iReg] ?? null) : null;
            $ano = null;
            if ($iAno !== null) {
                $rawAno = trim((string) ($row[$iAno] ?? ''));
                $ano = ctype_digit($rawAno) ? (int) $rawAno : null;
            }
            $tpLoc = null;
            if ($iLoc !== null && isset($row[$iLoc]) && is_numeric($row[$iLoc])) {
                $tpLoc = (int) $row[$iLoc];
            }

            $batch[] = [
                'inep_code' => $inep,
                'nu_ano_censo' => $ano,
                'no_municipio' => $mun,
                'sg_uf' => $sgUf,
                'no_uf' => $noUf,
                'no_regiao' => $reg,
                'tp_localizacao' => $tpLoc,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 800) {
                $flush();
            }
        }
        fclose($fh);
        $flush();

        Log::info('INEP censo geo agg indexados', ['rows' => $totalIndexed, 'file' => $absolutePath]);

        return $totalIndexed;
    }

    private function trimStr(mixed $v): ?string
    {
        $s = trim((string) $v);
        if ($s === '' || $s === 'null') {
            return null;
        }

        return $s;
    }

    private function localizacaoLabel(?int $tp): ?string
    {
        if ($tp === null) {
            return null;
        }

        return match ($tp) {
            1 => __('Urbana'),
            2 => __('Rural'),
            default => null,
        };
    }

    private function formatResumo(?string $mun, ?string $sgUf, ?string $reg, ?string $locLabel, ?int $ano): string
    {
        $parts = [];
        if ($mun !== null && $mun !== '') {
            $parts[] = $mun;
        }
        if ($sgUf !== null && $sgUf !== '') {
            $parts[] = $sgUf;
        }
        $head = implode(' — ', array_filter($parts));
        $tail = [];
        if ($reg !== null && $reg !== '') {
            $tail[] = __('Região').': '.$reg;
        }
        if ($locLabel !== null && $locLabel !== '') {
            $tail[] = __('Localização').': '.$locLabel;
        }
        if ($ano !== null && $ano > 0) {
            $tail[] = __('Ano Censo').': '.$ano;
        }
        $t = $head;
        if ($tail !== []) {
            $t .= ($t !== '' ? '. ' : '').implode('. ', $tail);
        }

        return $t !== '' ? $t : '';
    }
}
