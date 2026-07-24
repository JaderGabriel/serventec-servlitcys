<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessClioCampaignAnalyzeJob;
use App\Services\Clio\Analysis\CampaignAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProcessClioCampaignAnalyzeJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function configura_fila_timeout_e_tries(): void
    {
        config(['clio.queue' => 'clio']);

        $job = new ProcessClioCampaignAnalyzeJob(7, parseFirst: true);

        $this->assertSame('clio', $job->queue);
        $this->assertSame(1200, $job->timeout);
        $this->assertSame(2, $job->tries);
        $this->assertSame(7, $job->campaignId);
        $this->assertTrue($job->parseFirst);
    }

    #[Test]
    public function handle_campanha_inexistente_e_noop(): void
    {
        $job = new ProcessClioCampaignAnalyzeJob(999_999);

        $job->handle(app(CampaignAnalyzer::class));

        $this->assertTrue(true);
    }
}
