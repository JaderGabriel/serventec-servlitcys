<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\Fundeb\FundebMatriculasByYearService;
use App\Services\Fundeb\FundebOpenDataImportService;
use Illuminate\Console\Command;

class FundebDiagnoseMatriculasCommand extends Command
{
    protected $signature = 'fundeb:diagnose-matriculas
                            {city? : ID da cidade (vazio = todas com IBGE)}
                            {--anos= : Anos separados por vírgula (default: perfil planejamento)}';

    protected $description = 'Diagnóstico de matrículas i-Educar e Censo INEP por município/ano (base para VAAF FNDE)';

    public function handle(FundebMatriculasByYearService $matriculas): int
    {
        $years = $this->parseYears();
        $cityId = $this->argument('city');

        $query = City::query()->whereNotNull('ibge_municipio')->orderBy('name');
        if ($cityId !== null && $cityId !== '') {
            $query->where('id', (int) $cityId);
        }

        $cities = $query->get();
        if ($cities->isEmpty()) {
            $this->warn(__('Nenhuma cidade com IBGE encontrada.'));

            return self::FAILURE;
        }

        $this->info(__('Anos: :anos', ['anos' => implode(', ', $years)]));
        $this->newLine();

        foreach ($cities as $city) {
            $this->line('<fg=cyan>'.str_pad($city->name.' ('.$city->uf.')', 40).'</> IBGE '.$city->ibge_municipio);
            $rows = $matriculas->forCityYears($city, $years);
            foreach ($years as $ano) {
                $r = $rows[$ano] ?? null;
                if ($r === null) {
                    continue;
                }
                $this->line(sprintf(
                    '  %d: i-Educar=%s | Censo=%s | usado=%s [%s]',
                    $ano,
                    number_format($r['ieducar'], 0, ',', '.'),
                    $r['censo'] !== null ? number_format($r['censo'], 0, ',', '.') : '—',
                    number_format($r['usado'], 0, ',', '.'),
                    $r['fonte_usada'],
                ));
            }
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function parseYears(): array
    {
        $raw = trim((string) $this->option('anos'));
        if ($raw !== '') {
            return FundebOpenDataImportService::normalizeYearList(
                array_map('intval', explode(',', $raw)),
            );
        }

        return FundebOpenDataImportService::yearsForPlanningProfile();
    }
}
