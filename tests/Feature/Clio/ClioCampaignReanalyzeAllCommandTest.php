<?php

namespace Tests\Feature\Clio;

use App\Jobs\ProcessClioCampaignAnalyzeJob;
use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Analysis\CampaignAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClioCampaignReanalyzeAllCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Extensão pdo_sqlite necessária para RefreshDatabase neste ambiente.');
        }

        parent::setUp();
        config(['clio.enabled' => true]);
    }

    #[Test]
    public function dry_run_lists_campaigns_without_calling_analyzer(): void
    {
        ClioCampaign::query()->create([
            'municipality_name' => 'Alpha',
            'uf' => 'BA',
            'year' => 2026,
            'status' => ClioCampaign::STATUS_ANALYZED,
            'stage' => ClioCampaign::STAGE_1,
            'profile' => ClioCampaign::PROFILE_ANALYSIS_ONLY,
        ]);
        ClioCampaign::query()->create([
            'municipality_name' => 'Beta',
            'uf' => 'BA',
            'year' => 2025,
            'status' => ClioCampaign::STATUS_ANALYZED,
            'stage' => ClioCampaign::STAGE_1,
            'profile' => ClioCampaign::PROFILE_ANALYSIS_ONLY,
        ]);

        $this->mock(CampaignAnalyzer::class, function ($mock): void {
            $mock->shouldReceive('analyze')->never();
        });

        $this->artisan('clio:campaign-reanalyze-all', ['--year' => 2026, '--dry-run' => true])
            ->expectsOutputToContain('Alpha')
            ->doesntExpectOutputToContain('Beta')
            ->assertSuccessful();
    }

    #[Test]
    public function sync_mode_analyzes_each_campaign(): void
    {
        $a = ClioCampaign::query()->create([
            'municipality_name' => 'Alpha',
            'uf' => 'BA',
            'year' => 2026,
            'status' => ClioCampaign::STATUS_ANALYZED,
            'stage' => ClioCampaign::STAGE_1,
            'profile' => ClioCampaign::PROFILE_ANALYSIS_ONLY,
        ]);
        $b = ClioCampaign::query()->create([
            'municipality_name' => 'Beta',
            'uf' => 'BA',
            'year' => 2026,
            'status' => ClioCampaign::STATUS_PARSED,
            'stage' => ClioCampaign::STAGE_1,
            'profile' => ClioCampaign::PROFILE_ANALYSIS_ONLY,
        ]);

        $ids = [$a->id, $b->id];
        $this->mock(CampaignAnalyzer::class, function ($mock) use ($ids): void {
            $mock->shouldReceive('analyze')
                ->twice()
                ->withArgs(function (ClioCampaign $campaign, bool $parseFirst) use ($ids): bool {
                    return in_array($campaign->id, $ids, true) && $parseFirst === false;
                })
                ->andReturn([
                    'inferences' => 1,
                    'findings' => 0,
                    'coverage' => [],
                ]);
        });

        $this->artisan('clio:campaign-reanalyze-all', ['--year' => 2026, '--skip-parse' => true])
            ->assertSuccessful();
    }

    #[Test]
    public function queue_mode_dispatches_jobs(): void
    {
        Queue::fake();

        $campaign = ClioCampaign::query()->create([
            'municipality_name' => 'Alpha',
            'uf' => 'BA',
            'year' => 2026,
            'status' => ClioCampaign::STATUS_ANALYZED,
            'stage' => ClioCampaign::STAGE_1,
            'profile' => ClioCampaign::PROFILE_ANALYSIS_ONLY,
        ]);

        $this->mock(CampaignAnalyzer::class, function ($mock): void {
            $mock->shouldReceive('analyze')->never();
        });

        $this->artisan('clio:campaign-reanalyze-all', ['--queue' => true])
            ->assertSuccessful();

        Queue::assertPushed(ProcessClioCampaignAnalyzeJob::class, function (ProcessClioCampaignAnalyzeJob $job) use ($campaign): bool {
            return $job->campaignId === $campaign->id && $job->parseFirst === true;
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
