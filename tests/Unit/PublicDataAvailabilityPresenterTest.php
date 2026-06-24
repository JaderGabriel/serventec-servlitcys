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
            'attention_count' => 1,
            'aligned_count' => 1,
            'findings' => [
                ['status' => 'new_available'],
                ['status' => 'attention'],
                ['status' => 'unchanged'],
            ],
        ];

        $summary = PublicDataAvailabilityPresenter::summary($report);

        $this->assertSame(1, $summary['news']);
        $this->assertSame(1, $summary['attention']);
        $this->assertSame(1, $summary['aligned']);
        $this->assertSame('warning', $summary['variant']);
        $this->assertStringContainsString('1', $summary['headline']);
    }

    #[Test]
    public function summary_attention_only_does_not_call_it_news(): void
    {
        $report = [
            'attention_count' => 1,
            'aligned_count' => 4,
            'findings' => [
                ['status' => 'attention'],
                ['status' => 'unchanged'],
                ['status' => 'unchanged'],
                ['status' => 'unchanged'],
                ['status' => 'unchanged'],
            ],
        ];

        $summary = PublicDataAvailabilityPresenter::summary($report);

        $this->assertSame(0, $summary['news']);
        $this->assertSame(1, $summary['attention']);
        $this->assertSame(4, $summary['aligned']);
        $this->assertStringContainsString('atenção', mb_strtolower($summary['headline']));
        $this->assertNotNull($summary['subline']);
    }

    #[Test]
    public function notification_title_uses_attention_not_news_when_only_attention(): void
    {
        $report = [
            'attention_count' => 1,
            'aligned_count' => 4,
            'findings' => [
                ['status' => 'attention', 'source_title' => 'Censo', 'headline' => 'Verificar microdados'],
                ['status' => 'unchanged', 'source_title' => 'FUNDEB', 'headline' => 'OK'],
            ],
        ];

        $title = PublicDataAvailabilityPresenter::notificationTitle($report);

        $this->assertStringContainsString('atenção', mb_strtolower($title));
        $this->assertStringNotContainsString('novidade', mb_strtolower($title));
    }

    #[Test]
    public function notification_body_groups_action_and_aligned(): void
    {
        $report = [
            'attention_count' => 1,
            'aligned_count' => 1,
            'findings' => [
                [
                    'status' => 'attention',
                    'source_title' => 'Censo INEP',
                    'headline' => 'Indexado até 2024',
                    'detail' => 'Verifique microdados',
                    'routine_cli' => 'php artisan app:import-inep',
                ],
                [
                    'status' => 'unchanged',
                    'source_title' => 'FUNDEB',
                    'headline' => 'Sem exercício novo',
                ],
            ],
        ];

        $body = PublicDataAvailabilityPresenter::notificationBody($report);

        $this->assertStringContainsString('REQUER ACÇÃO', $body);
        $this->assertStringContainsString('SEM ALTERAÇÃO', $body);
        $this->assertStringContainsString('Censo INEP', $body);
        $this->assertStringContainsString('FUNDEB', $body);
        $this->assertStringContainsString('Sem exercício novo', $body);
    }

    #[Test]
    public function group_findings_splits_by_status_group(): void
    {
        $groups = PublicDataAvailabilityPresenter::groupFindings([
            ['status' => 'new_available'],
            ['status' => 'unchanged'],
            ['status' => 'not_configured'],
        ]);

        $this->assertCount(2, $groups['action']);
        $this->assertCount(1, $groups['aligned']);
    }

    #[Test]
    public function enrich_report_adds_ui_groups_and_counts(): void
    {
        $enriched = PublicDataAvailabilityPresenter::enrichReport([
            'has_news' => false,
            'news_count' => 0,
            'attention_count' => 0,
            'aligned_count' => 1,
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
        $this->assertArrayHasKey('groups', $enriched);
        $this->assertArrayHasKey('counts', $enriched);
        $this->assertSame(1, $enriched['counts']['aligned_count']);
    }

    #[Test]
    public function flash_message_describes_attention_separately_from_news(): void
    {
        $message = PublicDataAvailabilityPresenter::flashMessage([
            'attention_count' => 1,
            'aligned_count' => 4,
            'findings' => [
                ['status' => 'attention'],
                ['status' => 'unchanged'],
                ['status' => 'unchanged'],
                ['status' => 'unchanged'],
                ['status' => 'unchanged'],
            ],
        ]);

        $this->assertStringContainsString('atenção', mb_strtolower($message));
        $this->assertStringNotContainsString('novidade', mb_strtolower($message));
    }
}
