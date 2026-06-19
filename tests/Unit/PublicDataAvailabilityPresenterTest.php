<?php

namespace Tests\Unit;

use App\Support\Admin\PublicDataAvailabilityPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PublicDataAvailabilityPresenterTest extends TestCase
{
    #[Test]
    public function summary_flags_news_and_attention(): void
    {
        $report = [
            'has_news' => true,
            'news_count' => 1,
            'findings' => [
                ['status' => 'new_available'],
                ['status' => 'attention'],
                ['status' => 'unchanged'],
            ],
        ];

        $summary = PublicDataAvailabilityPresenter::summary($report);

        $this->assertSame(1, $summary['news']);
        $this->assertSame(1, $summary['attention']);
        $this->assertSame(1, $summary['ok']);
        $this->assertSame('warning', $summary['variant']);
    }

    #[Test]
    public function enrich_report_adds_ui_and_anchor(): void
    {
        $enriched = PublicDataAvailabilityPresenter::enrichReport([
            'has_news' => false,
            'news_count' => 0,
            'findings' => [
                [
                    'source_id' => 'fundeb_fnde',
                    'source_title' => 'FUNDEB',
                    'status' => 'unchanged',
                    'headline' => 'OK',
                ],
            ],
        ]);

        $this->assertSame('#source-fundeb_fnde', $enriched['findings'][0]['source_anchor']);
        $this->assertSame('ok', $enriched['findings'][0]['ui']['level']);
        $this->assertArrayHasKey('summary', $enriched);
    }
}
