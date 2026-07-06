<?php

namespace App\Services\Inep;

use Symfony\Component\Process\Process;

/**
 * Garante leitura de XLSX; converte XLSB via LibreOffice ou Python (pyxlsb).
 */
final class SaebPlanilhaInepSpreadsheetResolver
{
    /**
     * Devolve caminho legível pelo PhpSpreadsheet (XLSX/XLS).
     *
     * @throws \RuntimeException
     */
    public function resolveReadablePath(string $spreadsheetPath): string
    {
        $ext = strtolower(pathinfo($spreadsheetPath, PATHINFO_EXTENSION));
        if ($ext !== 'xlsb') {
            return $spreadsheetPath;
        }

        $xlsxPath = preg_replace('/\.xlsb$/i', '.xlsx', $spreadsheetPath) ?? $spreadsheetPath.'_converted.xlsx';
        if (is_file($xlsxPath) && filemtime($xlsxPath) >= filemtime($spreadsheetPath)) {
            return $xlsxPath;
        }

        if ($this->convertWithLibreOffice($spreadsheetPath, dirname($spreadsheetPath)) && is_file($xlsxPath)) {
            return $xlsxPath;
        }

        if ($this->convertWithPython($spreadsheetPath, $xlsxPath) && is_file($xlsxPath)) {
            return $xlsxPath;
        }

        throw new \RuntimeException(__(
            'Arquivo XLSB (SAEB 2023): instale LibreOffice (libreoffice) no servidor ou Python com pyxlsb+openpyxl para conversão automática.'
        ));
    }

    private function convertWithLibreOffice(string $xlsbPath, string $outDir): bool
    {
        foreach (['libreoffice', 'soffice'] as $bin) {
            $path = $this->findBinary($bin);
            if ($path === null) {
                continue;
            }

            $process = new Process([
                $path,
                '--headless',
                '--convert-to',
                'xlsx',
                '--outdir',
                $outDir,
                $xlsbPath,
            ]);
            $process->setTimeout(600);
            $process->run();

            if ($process->isSuccessful()) {
                return true;
            }
        }

        return false;
    }

    private function convertWithPython(string $xlsbPath, string $xlsxPath): bool
    {
        $python = $this->findBinary('python3') ?? $this->findBinary('python');
        if ($python === null) {
            return false;
        }

        $script = <<<'PY'
import sys
from pyxlsb import open_workbook
from openpyxl import Workbook

src, dst = sys.argv[1], sys.argv[2]
wb_out = Workbook()
wb_out.remove(wb_out.active)

with open_workbook(src) as wb:
    for name in wb.sheets:
        ws_out = wb_out.create_sheet(title=name[:31])
        with wb.get_sheet(name) as sh:
            for r_idx, row in enumerate(sh.rows(), start=1):
                for c_idx, cell in enumerate(row, start=1):
                    ws_out.cell(row=r_idx, column=c_idx, value=cell.v)

wb_out.save(dst)
PY;

        $process = new Process([$python, '-c', $script, $xlsbPath, $xlsxPath]);
        $process->setTimeout(900);
        $process->run();

        return $process->isSuccessful() && is_file($xlsxPath);
    }

    private function findBinary(string $name): ?string
    {
        $path = trim((string) shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null') ?? '');

        return ($path !== '' && is_executable($path)) ? $path : null;
    }
}
