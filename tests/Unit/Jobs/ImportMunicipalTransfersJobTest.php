<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ImportMunicipalTransfersJob;
use App\Services\Funding\MunicipalTransferImportService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ImportMunicipalTransfersJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function configura_fila_e_timeout(): void
    {
        config([
            'ieducar.admin_sync.queue' => 'admin-sync',
            'ieducar.funding.transfers.job_timeout' => 600,
        ]);

        $job = new ImportMunicipalTransfersJob(1, 2024);

        $this->assertSame('admin-sync', $job->queue);
        $this->assertSame(600, $job->timeout);
        $this->assertSame(1, $job->cityId);
        $this->assertSame(2024, $job->ano);
    }

    #[Test]
    public function handle_cidade_inexistente_lanca_model_not_found(): void
    {
        $job = new ImportMunicipalTransfersJob(999_999, 2024);

        $this->expectException(ModelNotFoundException::class);

        $job->handle(app(MunicipalTransferImportService::class));
    }
}
