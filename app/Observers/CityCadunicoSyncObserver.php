<?php

namespace App\Observers;

use App\Enums\AdminSyncDomain;
use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\AdminSync\AdminSyncQueueService;
use App\Services\Cadunico\CadunicoOpenDataImportService;

final class CityCadunicoSyncObserver
{
    public function __construct(
        private AdminSyncQueueService $syncQueue,
    ) {}

    public function saved(City $city): void
    {
        if (! filter_var(config('ieducar.cadunico.auto_sync.sync_on_city_save', true), FILTER_VALIDATE_BOOL)) {
            return;
        }

        if (! filter_var(config('ieducar.cadunico.enabled', true), FILTER_VALIDATE_BOOL)) {
            return;
        }

        if (! $city->wasChanged('ibge_municipio')) {
            return;
        }

        if (FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio) === null) {
            return;
        }

        $ano = CadunicoOpenDataImportService::suggestedImportYear();

        $this->syncQueue->dispatch(
            AdminSyncDomain::Cadastro,
            'import_city_year',
            __('CadÚnico automático — :name', ['name' => $city->name]),
            ['city_id' => (int) $city->id, 'ano' => $ano],
            (int) $city->id,
        );
    }
}
