<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\Cadunico\CadunicoPortalBeneficiosSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cadunico:sync-beneficios-portal {--city=} {--cities=} {--meses=} {--programas=} {--dry-run}')]
#[Description('CUN-04 — importa agregados PBF/NBF/BPC (Portal) por IBGE para o card Escolarização')]
class CadunicoSyncBeneficiosPortalCommand extends Command
{
    public function handle(CadunicoPortalBeneficiosSyncService $sync): int
    {
        $cityIds = $this->resolveCityIds();
        if ($cityIds === false) {
            return self::FAILURE;
        }

        $meses = $this->option('meses');
        $months = $meses !== null && $meses !== '' ? (int) $meses : null;
        $programas = trim((string) $this->option('programas'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info(__('CadÚnico — benefícios Portal (PBF / Novo Bolsa Família / BPC)'));
        if ($months !== null) {
            $this->line(__('Janela: :n mês(es)', ['n' => $months]));
        }
        if ($programas !== '') {
            $this->line(__('Programas: :p', ['p' => $programas]));
        }

        $result = $sync->sync(
            $cityIds,
            $programas !== '' ? $programas : null,
            $months,
            $dryRun,
        );

        if ($result['skipped'] ?? false) {
            $this->warn($result['message']);

            return self::SUCCESS;
        }

        if (($result['cities'] ?? 0) === 0) {
            $this->warn($result['message']);

            return self::SUCCESS;
        }

        $table = [];
        foreach ($result['by_city'] as $row) {
            $table[] = [
                $row['city_id'] ?? '—',
                ($row['city'] ?? '—').'/'.($row['uf'] ?? '—'),
                $row['ibge'] ?? '—',
                (int) ($row['rows'] ?? 0),
                (int) ($row['fetched'] ?? 0),
            ];
        }

        $this->table(
            [__('ID'), __('Município'), __('IBGE'), __('Linhas'), __('Itens API')],
            $table,
        );

        if ($dryRun) {
            $this->comment($result['message']);
            $this->comment(__('Dry-run: nenhuma alteração. Remova --dry-run para gravar.'));

            return self::SUCCESS;
        }

        if (! ($result['success'] ?? false)) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);
        $this->comment(__('Aparece como callouts no card Escolarização (Analytics → CadÚnico).'));

        return self::SUCCESS;
    }

    /**
     * @return list<int>|null|false
     */
    private function resolveCityIds(): array|null|false
    {
        $city = $this->option('city');
        $cities = $this->option('cities');

        if ($city === null && $cities === null) {
            return null;
        }

        $ids = [];
        if ($city !== null && $city !== '') {
            $ids[] = (int) $city;
        }
        if ($cities !== null && $cities !== '') {
            foreach (explode(',', (string) $cities) as $part) {
                $part = trim($part);
                if ($part !== '' && ctype_digit($part)) {
                    $ids[] = (int) $part;
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            $this->error(__('Indique --city= ou --cities= com IDs válidos, ou omita ambos para todas as consultorias.'));

            return false;
        }

        $found = City::query()->whereIn('id', $ids)->pluck('id')->all();
        $missing = array_diff($ids, array_map('intval', $found));
        if ($missing !== []) {
            $this->error(__('Cidade(s) inexistente(s): :ids', ['ids' => implode(', ', $missing)]));

            return false;
        }

        return $ids;
    }
}
