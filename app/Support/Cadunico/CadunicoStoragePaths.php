<?php

namespace App\Support\Cadunico;

/**
 * Caminhos de cache/API e descoberta de CSV Cecad em storage.
 */
final class CadunicoStoragePaths
{
    public static function storageRoot(): string
    {
        $rel = trim((string) config('ieducar.cadunico.cecad.storage_path', 'cadunico/cecad'), '/');

        return storage_path('app/'.$rel);
    }

    public static function apiCacheDir(): string
    {
        return storage_path('app/cadunico/api');
    }

    public static function apiCacheFile(string $ibge, int $ano): string
    {
        return self::apiCacheDir().'/'.$ibge.'/'.$ano.'.json';
    }

    public static function uploadDir(): string
    {
        return storage_path('app/cadunico/uploads');
    }

    public static function territorioRoot(): string
    {
        $rel = trim((string) config('ieducar.cadunico.territorio.storage_path', 'cadunico/territorio'), '/');

        return storage_path('app/'.$rel);
    }

    /**
     * CSVs candidatos para município/ano, do mais específico ao mais genérico.
     *
     * @return list<string>
     */
    public static function discoverCsvCandidates(?string $ibge, ?int $ano): array
    {
        $root = self::storageRoot();
        if (! is_dir($root)) {
            return [];
        }

        $candidates = [];
        if ($ibge !== null && $ano !== null) {
            foreach ([
                "{$ibge}_{$ano}.csv",
                "{$ibge}-{$ano}.csv",
                "municipio_{$ibge}_{$ano}.csv",
                "ibge_{$ibge}_{$ano}.csv",
            ] as $name) {
                $path = $root.'/'.$name;
                if (is_readable($path)) {
                    $candidates[] = $path;
                }
            }
        }

        if ($ano !== null) {
            foreach ([
                "nacional_{$ano}.csv",
                "cecad_{$ano}.csv",
                "{$ano}_cecad.csv",
                "brasil_{$ano}.csv",
            ] as $name) {
                $path = $root.'/'.$name;
                if (is_readable($path)) {
                    $candidates[] = $path;
                }
            }
        }

        foreach (glob($root.'/*.csv') ?: [] as $path) {
            if (is_readable($path) && ! in_array($path, $candidates, true)) {
                $candidates[] = $path;
            }
        }

        return $candidates;
    }

    /**
     * @return list<array{path: string, name: string, size: int, modified: string}>
     */
    public static function listStorageCsvFiles(): array
    {
        $root = self::storageRoot();
        if (! is_dir($root)) {
            return [];
        }

        $out = [];
        foreach (glob($root.'/*.csv') ?: [] as $path) {
            if (! is_readable($path)) {
                continue;
            }
            $out[] = [
                'path' => $path,
                'name' => basename($path),
                'size' => (int) filesize($path),
                'modified' => date('d/m/Y H:i', (int) filemtime($path)),
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $out;
    }
}
