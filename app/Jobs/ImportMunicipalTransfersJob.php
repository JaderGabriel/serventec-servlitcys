<?php

namespace App\Jobs;

use App\Models\City;
use App\Services\Funding\MunicipalTransferImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportMunicipalTransfersJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public function __construct(
        public int $cityId,
        public int $ano,
    ) {
        $this->timeout = max(60, (int) config('ieducar.funding.transfers.job_timeout', 600));
        $this->onQueue((string) config('ieducar.admin_sync.queue', 'admin-sync'));
        $connection = config('ieducar.admin_sync.connection');
        if ($connection !== null && $connection !== '') {
            $this->onConnection((string) $connection);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(MunicipalTransferImportService $import): array
    {
        $city = City::query()->findOrFail($this->cityId);

        return $import->importForCityYear($city, $this->ano, financeRealtimeRebuild: true);
    }
}
