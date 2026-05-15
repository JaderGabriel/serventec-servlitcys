<?php

namespace App\Observers;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Services\Fundeb\FundebImportProgress;
use Illuminate\Support\Facades\Log;

final class CityFundebSyncObserver
{
    public function saved(City $city): void
    {
        if (! (bool) config('ieducar.fundeb.open_data.sync_on_city_save', true)) {
            return;
        }

        if (! $city->wasChanged('ibge_municipio')) {
            return;
        }

        if (FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio) === null) {
            return;
        }

        $cityId = (int) $city->id;
        $years = FundebOpenDataImportService::yearsForNewCitySync();

        dispatch(static function () use ($cityId, $years): void {
            $progress = new FundebImportProgress(static function (string $level, string $message): void {
                Log::info('[fundeb:city-save] '.$message, ['level' => $level]);
            });
            $progress->info(__('Cadastro cidade #:id — importação automática anos :anos', [
                'id' => (string) $cityId,
                'anos' => implode(', ', array_map('strval', $years)),
            ]));
            app(FundebOpenDataImportService::class)->importBulkForYears($years, false, [$cityId], $progress);
        })->afterResponse();
    }
}
