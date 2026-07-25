<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\Funding\ConsultoriaEmendasEnrichmentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('funding:enrich-consultoria-emendas {--ano=} {--city=} {--cities=} {--dry-run}')]
#[Description('Importa emendas parlamentares de educação (Portal) para municípios com consultoria activa')]
class FundingEnrichConsultoriaEmendasCommand extends Command
{
    public function handle(ConsultoriaEmendasEnrichmentService $enrichment): int
    {
        $year = (int) ($this->option('ano') ?: date('Y'));
        if ($year < 2000 || $year > 2100) {
            $this->error(__('Indique --ano= válido (ex.: 2025).'));

            return self::FAILURE;
        }

        $cityIds = $this->resolveCityIds();
        if ($cityIds === false) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info(__('Finanças → Emendas educação (Portal da Transparência)'));
        $this->line(__('Ano: :ano', ['ano' => $year]));

        $result = $enrichment->enrich($year, $cityIds, $dryRun);

        if ($result['cities'] === 0) {
            $this->warn($result['message']);

            return self::SUCCESS;
        }

        $this->line(__('Municípios: :n · Catálogo Portal: :c', [
            'n' => $result['cities'],
            'c' => $result['catalog_rows'],
        ]));

        $table = [];
        foreach ($result['by_city'] as $row) {
            $table[] = [
                $row['city_id'] ?? '—',
                ($row['city'] ?? '—').'/'.($row['uf'] ?? '—'),
                $row['ibge'] ?? '—',
                (int) ($row['rows'] ?? 0),
            ];
        }

        $this->table(
            [__('ID'), __('Município'), __('IBGE'), __('Emendas')],
            $table,
        );

        if ($dryRun) {
            $this->comment($result['message']);
            $this->comment(__('Dry-run: nenhuma alteração. Remova --dry-run para gravar.'));

            return self::SUCCESS;
        }

        if (! $result['success']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);
        $this->line(__('Documentos Portal pedidos: :n', ['n' => $result['documentos_fetched']]));
        $this->comment(__('Próximo: UI Finanças → Emendas (FIN-08 A3).'));

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
