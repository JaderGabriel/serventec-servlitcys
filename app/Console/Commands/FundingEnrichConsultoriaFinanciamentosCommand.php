<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\Funding\ConsultoriaFinanciamentosEnrichmentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('funding:enrich-consultoria-financiamentos {--ano=} {--city=} {--cities=} {--dry-run} {--skip-import} {--skip-warm}')]
#[Description('Enriquece Finanças → Financiamentos além do FUNDEB para municípios com consultoria activa')]
class FundingEnrichConsultoriaFinanciamentosCommand extends Command
{
    public function handle(ConsultoriaFinanciamentosEnrichmentService $enrichment): int
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
        $skipImport = (bool) $this->option('skip-import');
        $skipWarm = (bool) $this->option('skip-warm');

        if ($skipImport && $skipWarm) {
            $this->error(__('Não combine --skip-import e --skip-warm (nada a fazer).'));

            return self::FAILURE;
        }

        $this->info(__('Finanças → Financiamentos — enriquecimento complementar (sem extratos FUNDEB)'));
        $this->line(__('Ano: :ano', ['ano' => $year]));

        $result = $enrichment->enrich($year, $cityIds, $dryRun, $skipImport, $skipWarm);

        if ($result['cities'] === 0) {
            $this->warn(__('Nenhum município de consultoria encontrado (activo + i-Educar + IBGE).'));

            return self::SUCCESS;
        }

        $this->line(__('Municípios: :n', ['n' => $result['cities']]));

        $table = [];
        foreach ($result['results'] as $row) {
            $import = $row['import'];
            $warm = $row['warm'];
            $importTxt = is_array($import)
                ? (string) ($import['message'] ?? json_encode($import, JSON_UNESCAPED_UNICODE))
                : (string) $import;
            $warmTxt = is_array($warm)
                ? (
                    isset($warm['queries_ok'])
                        ? __('consultas :ok/:total', [
                            'ok' => $warm['queries_ok'],
                            'total' => $warm['queries_total'],
                        ])
                        : (string) ($warm['message'] ?? json_encode($warm, JSON_UNESCAPED_UNICODE))
                )
                : (string) $warm;

            $table[] = [
                $row['city_id'],
                $row['city'].'/'.$row['uf'],
                $row['ibge'] ?? '—',
                mb_strimwidth($importTxt, 0, 56, '…'),
                mb_strimwidth($warmTxt, 0, 40, '…'),
                ($row['ok'] ?? true) ? 'ok' : 'falha',
            ];
        }

        $this->table(
            [__('ID'), __('Município'), __('IBGE'), __('Importação'), __('Cache público'), __('Estado')],
            $table,
        );

        if ($dryRun) {
            $this->comment(__('Dry-run: nenhuma alteração. Remova --dry-run para executar.'));

            return self::SUCCESS;
        }

        $this->info(__('Linhas complementares gravadas: :n', ['n' => $result['imported_rows']]));
        $this->info(__('Caches aquecidos: :n', ['n' => $result['warmed']]));
        if ($result['failed'] > 0) {
            $this->warn(__('Falhas: :n', ['n' => $result['failed']]));

            return self::FAILURE;
        }

        $this->comment(__('Abra Consultoria → Finanças → Financiamentos para ver PNAE/PNATE/PDDE e consultas públicas.'));

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
