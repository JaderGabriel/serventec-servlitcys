<?php

namespace App\Services\Cadunico;

use App\Support\Cadunico\CadunicoStoragePaths;
use App\Support\Http\SafeOutboundUrl;
use Illuminate\Support\Facades\Http;
use ZipArchive;

/**
 * Cache e leitura dos agregados oficiais do Censo 2022 (IBGE FTP) por município.
 */
final class CadunicoIbgeCensoAgregadosCache
{
    /** @var array<string, array<string, list<array{codigo: string, nome: string, tipo: string, populacao: int}>>> */
    private static array $indexCache = [];

    /**
     * @param  (callable(string): void)|null  $log
     * @return list<array{codigo: string, nome: string, tipo: string, populacao: int}>
     */
    public function territoriosForMunicipio(string $ibge, ?callable $log = null): array
    {
        $ibge = str_pad(preg_replace('/\D/', '', $ibge) ?? '', 7, '0', STR_PAD_LEFT);

        $bairroIndex = $this->indexFromZip(
            'bairro_basico',
            'Agregados_por_bairros_basico_BR.csv',
            'bairro',
            $log,
        );
        if (isset($bairroIndex[$ibge]) && $bairroIndex[$ibge] !== []) {
            $this->logStep($log, __('   Malha: bairros (nível preferencial).'));

            return $bairroIndex[$ibge];
        }

        $this->logStep($log, __('   Sem bairros no município; a usar setores censitários.'));

        $setorIndex = $this->indexFromZip(
            'setor_basico',
            'Agregados_por_setores_basico_BR.csv',
            'setor',
            $log,
        );

        return $setorIndex[$ibge] ?? [];
    }

    /**
     * @param  (callable(string): void)|null  $log
     * @return array{ok: bool, path: ?string, message: string}
     */
    private function ensureZip(string $kind, ?callable $log = null): array
    {
        $cfg = config('ieducar.cadunico.territorio.ibge_censo', []);
        $urls = is_array($cfg['zip_urls'] ?? null) ? $cfg['zip_urls'] : [];
        $url = trim((string) ($urls[$kind] ?? ''));
        if ($url === '') {
            return ['ok' => false, 'path' => null, 'message' => __('URL IBGE não configurada (:kind).', ['kind' => $kind])];
        }

        $dir = CadunicoStoragePaths::territorioRoot().'/ibge-cache';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = basename(parse_url($url, PHP_URL_PATH) ?: $kind.'.zip');
        $path = $dir.'/'.$filename;
        $maxAge = max(1, (int) ($cfg['cache_days'] ?? 90)) * 86400;

        if (is_readable($path) && filemtime($path) !== false && (time() - (int) filemtime($path)) < $maxAge) {
            $this->logStep($log, __('   ZIP :kind — cache local (:file).', ['kind' => $kind, 'file' => $filename]));

            return ['ok' => true, 'path' => $path, 'message' => __('Cache IBGE válido.')];
        }

        if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            return ['ok' => false, 'path' => null, 'message' => __('URL IBGE não permitida.')];
        }

        $this->logStep($log, __('   ZIP :kind — a descarregar do FTP IBGE (pode demorar)…', ['kind' => $kind]));

        $timeout = max(30, (int) ($cfg['http_timeout'] ?? 180));
        try {
            $response = Http::timeout($timeout)->withOptions(['verify' => true])->get($url);
        } catch (\Throwable $e) {
            $this->logStep($log, __('   Download falhou: :msg', ['msg' => $e->getMessage()]));

            return ['ok' => false, 'path' => is_readable($path) ? $path : null, 'message' => $e->getMessage()];
        }

        if (! $response->successful()) {
            $this->logStep($log, __('   HTTP :status; a usar cache antigo se existir.', ['status' => (string) $response->status()]));

            return [
                'ok' => is_readable($path),
                'path' => is_readable($path) ? $path : null,
                'message' => __('Download IBGE falhou (HTTP :s).', ['s' => (string) $response->status()]),
            ];
        }

        file_put_contents($path, $response->body());
        $sizeMb = is_readable($path) ? round(filesize($path) / 1048576, 1) : 0;
        $this->logStep($log, __('   ZIP gravado (:file, ~:mb MB).', ['file' => $filename, 'mb' => (string) $sizeMb]));

        return ['ok' => true, 'path' => $path, 'message' => __('ZIP IBGE descarregado.')];
    }

    /**
     * @return array<string, list<array{codigo: string, nome: string, tipo: string, populacao: int}>>
     */
    /**
     * @param  (callable(string): void)|null  $log
     * @return array<string, list<array{codigo: string, nome: string, tipo: string, populacao: int}>>
     */
    private function indexFromZip(string $zipKind, string $innerCsv, string $tipo, ?callable $log = null): array
    {
        $cacheKey = $zipKind.'|'.$innerCsv.'|'.$tipo;
        if (isset(self::$indexCache[$cacheKey])) {
            $this->logStep($log, __('   Índice :tipo já em memória (sessão).', ['tipo' => $tipo]));

            return self::$indexCache[$cacheKey];
        }

        $zipMeta = $this->ensureZip($zipKind, $log);
        $path = $zipMeta['path'] ?? null;
        if (! is_string($path) || ! is_readable($path)) {
            $this->logStep($log, __('   ZIP :kind indisponível.', ['kind' => $zipKind]));

            return self::$indexCache[$cacheKey] = [];
        }

        $this->logStep($log, __('   A indexar CSV :csv dentro do ZIP…', ['csv' => $innerCsv]));

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->logStep($log, __('   Não foi possível abrir o ZIP.'));

            return [];
        }

        $name = $this->resolveInnerName($zip, $innerCsv);
        if ($name === null) {
            $zip->close();

            return self::$indexCache[$cacheKey] = [];
        }

        $stream = $zip->getStream($name);
        if ($stream === false) {
            $zip->close();

            return self::$indexCache[$cacheKey] = [];
        }

        $header = null;
        $map = [];
        /** @var array<string, list<array{codigo: string, nome: string, tipo: string, populacao: int}>> $index */
        $index = [];

        while (($line = fgets($stream)) !== false) {
            $line = $this->normalizeLine($line);
            if ($line === '') {
                continue;
            }
            $cols = str_getcsv($line, ';');
            if ($header === null) {
                $header = array_map(static fn ($c) => mb_strtolower(trim((string) $c, '"')), $cols);
                $map = $this->columnMap($header, $tipo);

                continue;
            }
            if ($map['mun'] === null || $map['codigo'] === null) {
                fclose($stream);
                $zip->close();
                break;
            }
            $mun = $this->cell($cols, $map['mun']);
            if ($mun === null) {
                continue;
            }
            $codigo = $this->cell($cols, $map['codigo']);
            if ($codigo === null || $codigo === '' || $codigo === '.') {
                continue;
            }
            $pop = $this->intCell($cols, $map['pop']);
            if ($pop <= 0) {
                continue;
            }
            $nome = $this->cell($cols, $map['nome'])
                ?? ($tipo === 'setor'
                    ? __('Setor :c', ['c' => substr($codigo, -4)])
                    : $codigo);
            $index[$mun] ??= [];
            $index[$mun][] = [
                'codigo' => $codigo,
                'nome' => $nome,
                'tipo' => $tipo,
                'populacao' => $pop,
            ];
        }

        fclose($stream);
        $zip->close();

        $munCount = count($index);
        $this->logStep($log, __('   Índice :tipo: :mun município(s) no Brasil.', ['tipo' => $tipo, 'mun' => $munCount]));

        return self::$indexCache[$cacheKey] = $index;
    }

    private function resolveInnerName(ZipArchive $zip, string $preferred): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) ($stat['name'] ?? '');
            if (str_ends_with(mb_strtolower($name), '.csv')) {
                if ($name === $preferred || str_contains($name, $preferred)) {
                    return $name;
                }
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) ($stat['name'] ?? '');
            if (str_ends_with(mb_strtolower($name), '.csv')) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $header
     * @return array{mun: ?int, codigo: ?int, nome: ?int, pop: ?int}
     */
    private function columnMap(array $header, string $tipo): array
    {
        $find = static function (array $aliases) use ($header): ?int {
            foreach ($aliases as $a) {
                $k = mb_strtolower($a);
                $idx = array_search($k, $header, true);
                if ($idx !== false) {
                    return (int) $idx;
                }
            }

            return null;
        };

        return [
            'mun' => $find(['cd_mun']),
            'codigo' => $find($tipo === 'bairro' ? ['cd_bairro'] : ['cd_setor']),
            'nome' => $find(['nm_bairro']),
            'pop' => $find(['v0001']),
        ];
    }

    /**
     * @param  list<string|null>  $cols
     */
    private function cell(array $cols, ?int $idx): ?string
    {
        if ($idx === null || ! isset($cols[$idx])) {
            return null;
        }
        $v = trim((string) $cols[$idx], " \t\n\r\0\x0B\"");
        if ($v === '' || $v === '.') {
            return null;
        }

        return $v;
    }

    /**
     * @param  list<string|null>  $cols
     */
    private function intCell(array $cols, ?int $idx): int
    {
        $v = $this->cell($cols, $idx);
        if ($v === null) {
            return 0;
        }

        return (int) round((float) str_replace(',', '.', preg_replace('/[^\d.,-]/', '', $v) ?? '0'));
    }

    private function normalizeLine(string $line): string
    {
        $line = trim($line);
        if ($line === '') {
            return '';
        }
        if (! mb_check_encoding($line, 'UTF-8')) {
            $converted = @mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');
            if (is_string($converted)) {
                return $converted;
            }
        }

        return $line;
    }

    /**
     * @param  (callable(string): void)|null  $log
     */
    private function logStep(?callable $log, string $message): void
    {
        if ($log !== null) {
            $log($message);
        }
    }
}
