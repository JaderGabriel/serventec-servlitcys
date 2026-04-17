<?php

namespace App\Services\Inep;

use App\Models\City;
use App\Support\Inep\BrasilUfIbge;
use Illuminate\Support\Collection;

/**
 * Converte CSV de microdados SAEB (INEP / dados abertos) para o formato canónico
 * esperado por {@see SaebCsvPedagogicalImportService}.
 */
final class SaebMicrodadosCsvStreamConverter
{
    /**
     * Escolhe o ficheiro .csv mais provável dentro de um directório extraído (ZIP INEP).
     */
    public function pickBestCsv(string $extractDir): ?string
    {
        $minScore = max(3, (int) config('ieducar.saeb.microdados_csv_min_score', 6));
        $best = null;
        $bestScore = -1;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'csv') {
                continue;
            }
            $path = $file->getPathname();
            $score = $this->scoreCsvHeader($path);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $path;
            }
        }

        if ($best === null || $bestScore < $minScore) {
            return null;
        }

        return $best;
    }

    /**
     * Filtra por municípios cadastrados e grava CSV canónico (streaming).
     *
     * @param  array<string, true>  $allowedIbge
     * @param  Collection<int, City>  $cities
     * @return array{rows: int, skipped: int, warnings: list<string>}
     */
    public function streamToCanonicalCsv(string $inputPath, string $outputPath, array $allowedIbge, Collection $cities): array
    {
        $warnings = [];
        $allowedUf = $this->allowedUfCodesFromCities($cities);

        $in = fopen($inputPath, 'rb');
        if ($in === false) {
            throw new \RuntimeException(__('Não foi possível abrir o CSV de microdados.'));
        }

        $first = fgets($in);
        if ($first === false) {
            fclose($in);

            return ['rows' => 0, 'skipped' => 0, 'warnings' => [__('CSV vazio.')]];
        }

        if (str_starts_with($first, "\xEF\xBB\xBF")) {
            $first = substr($first, 3);
        }

        $delimiter = $this->detectDelimiter($first);
        $headers = str_getcsv($first, $delimiter);
        $norm = [];
        foreach ($headers as $i => $h) {
            $norm[$i] = $this->normalizeHeaderName((string) $h);
        }

        $spec = $this->resolveColumnSpec($norm);

        if ($spec['ibge'] === null || ($spec['year'] === null && $spec['year_fallback'] === null)) {
            fclose($in);

            return [
                'rows' => 0,
                'skipped' => 0,
                'warnings' => [__('Cabeçalho não reconhecido (IBGE ou ano). Ajuste IEDUCAR_SAEB_MICRODADOS_COLUMN_MAP ou use CSV manual.')],
            ];
        }

        $out = fopen($outputPath, 'wb');
        if ($out === false) {
            fclose($in);
            throw new \RuntimeException(__('Não foi possível gravar o CSV canónico.'));
        }

        fwrite($out, "municipio_ibge;ano_aplicacao;disciplina;etapa;valor;status;inep_escola\n");

        $maxLines = max(1000, min(50_000_000, (int) config('ieducar.saeb.microdados_max_rows', 5_000_000)));
        $rows = 0;
        $skipped = 0;
        $linesRead = 0;

        while (($line = fgets($in)) !== false) {
            $linesRead++;
            if ($linesRead > $maxLines) {
                break;
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = str_getcsv($line, $delimiter);
            if ($row === []) {
                continue;
            }

            if ($spec['uf'] !== null && $allowedUf !== []) {
                $ufVal = $this->cell($row, $spec['uf']);
                $ufNorm = $this->normalizeUfCell((string) $ufVal);
                if ($ufNorm !== null && ! isset($allowedUf[$ufNorm])) {
                    $skipped++;

                    continue;
                }
            }

            $ibgeRaw = $this->cell($row, $spec['ibge']);
            $ibge = $this->normalizeIbge((string) $ibgeRaw);
            if ($ibge === null || ! isset($allowedIbge[$ibge])) {
                $skipped++;

                continue;
            }

            $year = $this->parseYear($row, $spec);
            if ($year === null || $year < 1995 || $year > 2100) {
                $skipped++;

                continue;
            }

            $status = $this->inferStatus($row, $spec);
            $inep = $this->parseInep($row, $spec);

            $etapaRaw = $spec['etapa'] !== null ? (string) $this->cell($row, $spec['etapa']) : '';
            $etapa = $this->normalizeEtapa($etapaRaw);

            $emitted = 0;

            if ($spec['lp_wide'] !== null && $spec['mat_wide'] !== null) {
                $vLp = $this->cell($row, $spec['lp_wide']);
                $vMat = $this->cell($row, $spec['mat_wide']);
                if (is_numeric($vLp)) {
                    fwrite($out, $this->canonicalLine($ibge, $year, 'lp', $etapa, (float) $vLp, $status, $inep));
                    $emitted++;
                }
                if (is_numeric($vMat)) {
                    fwrite($out, $this->canonicalLine($ibge, $year, 'mat', $etapa, (float) $vMat, $status, $inep));
                    $emitted++;
                }
            }

            if ($emitted === 0 && $spec['disc'] !== null && $spec['val'] !== null) {
                $discRaw = (string) $this->cell($row, $spec['disc']);
                $valRaw = $this->cell($row, $spec['val']);
                if (! is_numeric($valRaw)) {
                    $skipped++;

                    continue;
                }
                $disc = $this->normalizeDisciplina($discRaw);
                fwrite($out, $this->canonicalLine($ibge, $year, $disc, $etapa, (float) $valRaw, $status, $inep));
                $emitted++;
            }

            if ($emitted === 0) {
                $skipped++;

                continue;
            }

            $rows += $emitted;
        }

        fclose($in);
        fclose($out);

        if ($rows === 0) {
            $warnings[] = __('Nenhuma linha gerada: verifique colunas de valor (LP/MAT ou disciplina+percentual) ou amplie os municípios cadastrados.');
        }

        return ['rows' => $rows, 'skipped' => $skipped, 'warnings' => $warnings];
    }

    private function scoreCsvHeader(string $path): int
    {
        $in = fopen($path, 'rb');
        if ($in === false) {
            return 0;
        }
        $line = fgets($in);
        fclose($in);
        if ($line === false) {
            return 0;
        }
        if (str_starts_with($line, "\xEF\xBB\xBF")) {
            $line = substr($line, 3);
        }
        $delimiter = $this->detectDelimiter($line);
        $headers = str_getcsv($line, $delimiter);
        $score = 0;
        foreach ($headers as $h) {
            $u = strtoupper($this->normalizeHeaderName((string) $h));
            if (str_contains($u, 'MUNICIPIO') || str_contains($u, 'IBGE')) {
                $score += 3;
            }
            if (str_contains($u, 'ANO') || str_contains($u, 'SAEB')) {
                $score += 2;
            }
            if (str_contains($u, 'ESCOLA') || str_contains($u, 'INEP')) {
                $score += 2;
            }
            if (str_contains($u, 'PROF') || str_contains($u, 'DISC') || str_contains($u, 'LP') || str_contains($u, 'MAT')) {
                $score += 1;
            }
        }

        return $score;
    }

    /**
     * @param  array<int, string>  $norm  índice => cabeçalho normalizado
     * @return array{
     *   ibge: ?int,
     *   year: ?int,
     *   year_fallback: ?int,
     *   uf: ?int,
     *   disc: ?int,
     *   val: ?int,
     *   etapa: ?int,
     *   lp_wide: ?int,
     *   mat_wide: ?int,
     *   inep: ?int,
     *   status_pre: ?int,
     *   status_tp: ?int
     * }
     */
    private function resolveColumnSpec(array $norm): array
    {
        $map = config('ieducar.saeb.microdados_column_map');
        $map = is_array($map) ? $map : [];

        $pick = function (string $key, array $defaults) use ($map, $norm): ?int {
            $candidates = isset($map[$key]) && is_array($map[$key])
                ? array_merge($defaults, array_map('strval', $map[$key]))
                : $defaults;
            foreach ($norm as $i => $h) {
                foreach ($candidates as $c) {
                    $c = strtoupper(trim((string) $c));
                    if ($c !== '' && $h === $c) {
                        return $i;
                    }
                }
            }

            return null;
        };

        $ibge = $pick('ibge', [
            'CO_MUNICIPIO', 'CO_MUNICIPIO_IBGE', 'CO_IBGE', 'CO_CODIGO_IBGE', 'IBGE_MUNICIPIO', 'CO_CODIGO_MUNICIPIO',
        ]);

        $year = $pick('year', ['NU_ANO_SAEB', 'ANO_SAEB', 'ANO_REFERENCIA', 'ANO_APLICACAO', 'ANO']);
        $yearFallback = $pick('year_alt', ['NU_ANO']);

        $uf = $pick('uf', ['CO_UF', 'SG_UF', 'UF']);

        $disc = $pick('disciplina', ['SG_DISCIPLINA', 'DISCIPLINA', 'CO_DISCIPLINA', 'NM_DISCIPLINA']);
        $val = $pick('valor', [
            'PC_PROFICIENTES', 'VL_PROFICIENCIA', 'NU_MEDIA', 'MEDIA', 'VL_MEDIA', 'NOTA',
        ]);

        $lpWide = $pick('lp_wide', ['PC_PROFICIENTES_LP', 'VL_PROFICIENTES_LP', 'MEDIA_LP', 'NU_MEDIA_LP']);
        $matWide = $pick('mat_wide', ['PC_PROFICIENTES_MAT', 'VL_PROFICIENTES_MAT', 'MEDIA_MAT', 'NU_MEDIA_MAT']);

        $etapa = $pick('etapa', ['CO_ETAPA', 'DS_ETAPA', 'ETAPA', 'NM_ETAPA', 'TP_ETAPA']);

        $inep = $pick('inep_escola', [
            'CO_ESCOLA', 'ID_ESCOLA', 'INEP', 'CODIGO_ESCOLA', 'CO_CODIGO_ESCOLA', 'INEP_ESCOLA',
        ]);

        $statusPre = $pick('preliminar_flag', ['IN_PRELIMINAR', 'FL_PRELIMINAR', 'PRELIMINAR']);
        $statusTp = $pick('tipo_resultado', ['TP_RESULTADO', 'TP_STATUS', 'NATUREZA']);

        return [
            'ibge' => $ibge,
            'year' => $year,
            'year_fallback' => $yearFallback,
            'uf' => $uf,
            'disc' => $disc,
            'val' => $val,
            'etapa' => $etapa,
            'lp_wide' => $lpWide,
            'mat_wide' => $matWide,
            'inep' => $inep,
            'status_pre' => $statusPre,
            'status_tp' => $statusTp,
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function parseYear(array $row, array $spec): ?int
    {
        $y = $spec['year'] !== null ? $this->cell($row, $spec['year']) : null;
        if ($y !== null && is_numeric($y)) {
            return (int) $y;
        }
        $y2 = $spec['year_fallback'] !== null ? $this->cell($row, $spec['year_fallback']) : null;
        if ($y2 !== null && is_numeric($y2)) {
            return (int) $y2;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function inferStatus(array $row, array $spec): string
    {
        if ($spec['status_pre'] !== null) {
            $v = $this->cell($row, $spec['status_pre']);
            if ($v === '1' || strtoupper((string) $v) === 'S' || strtolower((string) $v) === 'true') {
                return 'preliminar';
            }
        }
        if ($spec['status_tp'] !== null) {
            $v = strtolower((string) $this->cell($row, $spec['status_tp']));
            if (str_contains($v, 'prelim')) {
                return 'preliminar';
            }
        }

        return 'final';
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function parseInep(array $row, array $spec): string
    {
        if ($spec['inep'] === null) {
            return '';
        }
        $v = $this->cell($row, $spec['inep']);
        if ($v === null || $v === '') {
            return '';
        }
        $d = preg_replace('/\D/', '', (string) $v) ?? '';

        return $d;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function cell(array $row, ?int $idx): ?string
    {
        if ($idx === null) {
            return null;
        }

        return isset($row[$idx]) ? trim((string) $row[$idx]) : null;
    }

    private function canonicalLine(string $ibge, int $year, string $disc, string $etapa, float $valor, string $status, string $inep): string
    {
        $valorStr = str_replace('.', ',', (string) round($valor, 6));

        return $ibge.';'.$year.';'.$disc.';'.$etapa.';'.$valorStr.';'.$status.';'.$inep."\n";
    }

    private function normalizeHeaderName(string $h): string
    {
        $h = trim($h);
        $h = str_replace([' ', '-', '.'], '_', $h);

        return strtoupper($h);
    }

    private function detectDelimiter(string $firstLine): string
    {
        $semi = substr_count($firstLine, ';');
        $comma = substr_count($firstLine, ',');

        return $semi >= $comma ? ';' : ',';
    }

    private function normalizeIbge(string $raw): ?string
    {
        $d = preg_replace('/\D/', '', $raw) ?? '';
        if ($d === '') {
            return null;
        }
        if (strlen($d) < 7) {
            $d = str_pad($d, 7, '0', STR_PAD_LEFT);
        }
        if (strlen($d) !== 7) {
            return null;
        }

        return $d;
    }

    private function normalizeDisciplina(string $raw): string
    {
        $s = strtolower(trim($raw));
        if ($s === '' || str_contains($s, 'port') || $s === 'lp' || $s === 'lingua_portuguesa' || $s === '1') {
            return 'lp';
        }
        if (str_contains($s, 'mat') || $s === '2') {
            return 'mat';
        }

        return $s !== '' ? $s : 'lp';
    }

    private function normalizeEtapa(string $raw): string
    {
        $s = strtolower(trim($raw));
        if ($s === '') {
            return 'geral';
        }
        if (str_contains($s, 'inicia') || str_contains($s, 'efi') || str_contains($s, 'ef_i')) {
            return 'efi';
        }
        if (str_contains($s, 'finais') || str_contains($s, 'efaf') || str_contains($s, 'ef_ii')) {
            return 'efaf';
        }
        if (str_contains($s, 'médio') || str_contains($s, 'medio') || $s === 'em') {
            return 'em';
        }
        if (str_contains($s, 'infantil')) {
            return 'ei';
        }
        if (ctype_digit($s)) {
            $n = (int) $s;
            if ($n === 1) {
                return 'efi';
            }
            if ($n === 2) {
                return 'efaf';
            }
            if ($n === 3) {
                return 'em';
            }
        }

        return 'geral';
    }

    private function normalizeUfCell(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (strlen($raw) === 2 && ctype_alpha($raw)) {
            $uf = strtoupper($raw);

            return BrasilUfIbge::UF_PARA_PREFIXO_IBGE[$uf] ?? null;
        }
        $d = preg_replace('/\D/', '', $raw) ?? '';

        return strlen($d) >= 2 ? substr($d, 0, 2) : null;
    }

    /**
     * @param  Collection<int, City>  $cities
     * @return array<string, true>
     */
    private function allowedUfCodesFromCities(Collection $cities): array
    {
        $out = [];
        foreach ($cities as $city) {
            $uf = strtoupper(trim((string) ($city->uf ?? '')));
            if ($uf !== '' && isset(BrasilUfIbge::UF_PARA_PREFIXO_IBGE[$uf])) {
                $out[BrasilUfIbge::UF_PARA_PREFIXO_IBGE[$uf]] = true;
            }
        }

        return $out;
    }
}
