<?php

namespace App\Observers;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Fundeb\FundebOpenDataImportService;

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
        $years = FundebOpenDataImportService::configuredSyncYears();

        dispatch(static function () use ($cityId, $years): void {
            app(FundebOpenDataImportService::class)->importBulkForYears($years, false, $cityId);
        })->afterResponse();
    }
}
