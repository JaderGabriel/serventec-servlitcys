<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessClioCampaignIngestJob;
use App\Services\Clio\Ingest\CampaignIngestService;
use App\Services\Clio\Parse\CampaignParseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProcessClioCampaignIngestJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function configura_fila_timeout_e_tries(): void
    {
        config(['clio.queue' => 'clio']);

        $job = new ProcessClioCampaignIngestJob(42, path: '/tmp/x.zip');

        $this->assertSame('clio', $job->queue);
        $this->assertSame(900, $job->timeout);
        $this->assertSame(2, $job->tries);
        $this->assertSame(42, $job->campaignId);
        $this->assertSame('/tmp/x.zip', $job->path);
        $this->assertTrue($job->parse);
    }

    #[Test]
    public function handle_campanha_inexistente_e_noop(): void
    {
        $job = new ProcessClioCampaignIngestJob(999_999, path: '/tmp/missing.zip');

        $job->handle(
            app(CampaignIngestService::class),
            app(CampaignParseService::class),
        );

        $this->assertTrue(true);
    }
}
