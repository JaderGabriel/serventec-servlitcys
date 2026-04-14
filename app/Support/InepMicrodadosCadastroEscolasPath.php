<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Resolve o caminho absoluto do CSV de microdados INEP `MICRODADOS_CADASTRO_ESCOLAS_*`.
 *
 * O ficheiro deve estar no disco `public` do Laravel (`storage/app/public`), servido via
 * `php artisan storage:link` como `public/storage/...` quando necessário.
 *
 * Se o caminho configurado contiver `*`, usa-se `glob` e escolhe-se o ficheiro cujo nome
 * contém o ano mais recente (ex.: 2024 vs 2023).
 */
final class InepMicrodadosCadastroEscolasPath
{
    public static function resolve(?string $configured): ?string
    {
        $configured = trim((string) $configured);
        if ($configured === '') {
            return null;
        }

        if (str_starts_with($configured, '/')) {
            return is_readable($configured) ? $configured : null;
        }

        if (str_starts_with($configured, 'app/') && ! str_starts_with($configured, 'app/public/')) {
            $p = storage_path($configured);

            return is_readable($p) ? $p : null;
        }

        if (str_starts_with($configured, 'app/public/')) {
            $configured = substr($configured, strlen('app/public/'));
        }

        $disk = Storage::disk('public');

        if (str_contains($configured, '*')) {
            $pattern = $disk->path($configured);
            $matches = glob($pattern) ?: [];
            if ($matches === []) {
                return null;
            }
            usort($matches, fn (string $a, string $b): int => self::yearFromFilename($b) <=> self::yearFromFilename($a));

            return is_readable($matches[0]) ? $matches[0] : null;
        }

        $path = $disk->path($configured);

        return is_readable($path) ? $path : null;
    }

    private static function yearFromFilename(string $path): int
    {
        $base = basename($path);
        if (preg_match('/(\d{4})/', $base, $m)) {
            return (int) $m[1];
        }

        return 0;
    }
}
