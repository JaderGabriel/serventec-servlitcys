<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Console\Command;

class FundebImportReferencesCommand extends Command
{
    protected $signature = 'fundeb:import-references
                            {path : Caminho do CSV (ibge;ano;vaaf;vaat;complementacao_vaar;fonte;notas)}
                            {--delimiter=; : Separador de colunas}';

    protected $description = 'Importa VAAF/VAAT/complementação VAAR por município (IBGE) e ano';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_readable($path)) {
            $this->error(__('Ficheiro não encontrado: :path', ['path' => $path]));

            return self::FAILURE;
        }

        $delimiter = (string) $this->option('delimiter');
        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error(__('Não foi possível abrir o ficheiro.'));

            return self::FAILURE;
        }

        $header = null;
        $imported = 0;
        $skipped = 0;
        $lineNo = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNo++;
            if ($row === [null] || $row === []) {
                continue;
            }

            if ($header === null) {
                $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $row);

                continue;
            }

            $assoc = [];
            foreach ($header as $i => $key) {
                $assoc[$key] = $row[$i] ?? '';
            }

            $ibge = preg_replace('/\D/', '', (string) ($assoc['ibge_municipio'] ?? $assoc['ibge'] ?? ''));
            $ano = (int) preg_replace('/\D/', '', (string) ($assoc['ano'] ?? $assoc['ano_letivo'] ?? '0'));
            $vaaf = $this->parseMoney($assoc['vaaf'] ?? $assoc['vaa'] ?? '');

            if (strlen($ibge) !== 7 || $ano <= 0 || $vaaf === null || $vaaf <= 0) {
                $skipped++;

                continue;
            }

            $cityId = City::query()
                ->where('ibge_municipio', $ibge)
                ->value('id');

            FundebMunicipioReference::query()->updateOrCreate(
                ['ibge_municipio' => $ibge, 'ano' => $ano],
                [
                    'city_id' => $cityId,
                    'vaaf' => $vaaf,
                    'vaat' => $this->parseMoney($assoc['vaat'] ?? null),
                    'complementacao_vaar' => $this->parseMoney($assoc['complementacao_vaar'] ?? $assoc['vaar'] ?? null),
                    'fonte' => trim((string) ($assoc['fonte'] ?? 'import_csv')) ?: 'import_csv',
                    'notas' => trim((string) ($assoc['notas'] ?? '')) ?: null,
                    'imported_at' => now(),
                ],
            );
            $imported++;
        }

        fclose($handle);

        $this->info(__('Importados: :n linha(s); ignorados: :s.', ['n' => $imported, 's' => $skipped]));

        return self::SUCCESS;
    }

    private function parseMoney(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $s = trim((string) $raw);
        $s = str_replace(['R$', ' '], '', $s);
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }
}
