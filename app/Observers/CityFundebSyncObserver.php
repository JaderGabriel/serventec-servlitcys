<?php

namespace App\Observers;

use App\Enums\AdminSyncDomain;
use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\AdminSync\AdminSyncQueueService;
use App\Services\Fundeb\FundebOpenDataImportService;

final class CityFundebSyncObserver
{
    public function __construct(
        private AdminSyncQueueService $syncQueue,
    ) {}

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

        $years = FundebOpenDataImportService::yearsForNewCitySync();

        $this->syncQueue->dispatch(
            AdminSyncDomain::Fundeb,
            'new_city_auto',
            __('FUNDEB automático — nova cidade :name', ['name' => $city->name]),
            [
                'city_id' => (int) $city->id,
                'years' => $years,
            ],
            (int) $city->id,
        );
    }
}
