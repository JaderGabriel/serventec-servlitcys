<?php

namespace App\Services\Inep;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Extrai RAR de planilhas INEP e localiza ficheiros XLSX/XLSB.
 */
final class SaebPlanilhaInepArchive
{
    /**
     * @return list<string> caminhos absolutos de planilhas encontradas
     */
    public function extractRarAndFindSpreadsheets(string $rarPath, string $extractDir): array
    {
        if (! is_file($rarPath)) {
            throw new \RuntimeException(__('Ficheiro RAR inexistente: :path', ['path' => $rarPath]));
        }

        if (is_dir($extractDir)) {
            File::deleteDirectory($extractDir);
        }
        if (! mkdir($extractDir, 0755, true) && ! is_dir($extractDir)) {
            throw new \RuntimeException(__('Não foi possível criar pasta de extracção.'));
        }

        $this->extractRar($rarPath, $extractDir);

        return $this->findSpreadsheetFiles($extractDir);
    }

    /**
     * @return list<string>
     */
    public function findSpreadsheetFiles(string $directory): array
    {
        $found = [];
        if (! is_dir($directory)) {
            return [];
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['xlsx', 'xlsb', 'xls'], true)) {
                continue;
            }
            $path = $file->getPathname();
            $name = strtolower($file->getFilename());
            if (
                str_contains($name, 'erro')
                || str_contains($name, 'intervalo')
                || str_contains($name, 'alfabetiz')
            ) {
                continue;
            }
            $priority = 2;
            if (str_contains($name, 'brasil_estados_municipios') || str_contains($name, 'resultados_saeb')) {
                $priority = 0;
            } elseif (str_contains($name, 'municip')) {
                $priority = 1;
            }
            $found[] = ['priority' => $priority, 'path' => $path];
        }

        usort($found, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return array_values(array_map(static fn (array $x): string => $x['path'], $found));
    }

    public function pickBestSpreadsheet(array $candidates): ?string
    {
        return $candidates[0] ?? null;
    }

    private function extractRar(string $rarPath, string $extractDir): void
    {
        $unrar = $this->findBinary(['unrar', 'rar']);
        if ($unrar !== null) {
            $process = new Process([$unrar, 'x', '-o+', $rarPath, $extractDir]);
            $process->setTimeout(600);
            $process->run();
            if ($process->isSuccessful()) {
                return;
            }
        }

        $sevenZip = $this->findBinary(['7z', '7za']);
        if ($sevenZip !== null) {
            $process = new Process([$sevenZip, 'x', $rarPath, '-o'.$extractDir, '-y']);
            $process->setTimeout(600);
            $process->run();
            if ($process->isSuccessful()) {
                return;
            }
        }

        throw new \RuntimeException(__(
            'Não foi possível extrair o RAR. Instale «unrar» ou «p7zip» no servidor (pacotes unrar / p7zip-full).'
        ));
    }

    /**
     * @param  list<string>  $names
     */
    private function findBinary(array $names): ?string
    {
        foreach ($names as $name) {
            $path = trim((string) shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null') ?? '');
            if ($path !== '' && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}
