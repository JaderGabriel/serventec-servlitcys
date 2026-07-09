<?php

namespace Tests\Unit;

use App\Support\Product\ProductReleasePublisher;
use App\Support\Product\ProductReleaseTag;
use Tests\TestCase;

final class ProductReleasePublisherTest extends TestCase
{
    public function test_release_notes_path_from_tag(): void
    {
        $publisher = new ProductReleasePublisher;

        $this->assertSame(
            'docs/RELEASE_20260709_CALLIOPE.md',
            $publisher->releaseNotesPath('20260709-Calliope')
        );
    }

    public function test_config_mismatches_detects_drift(): void
    {
        config([
            'documentation.product' => [
                'version' => '7.0.2',
                'release_tag' => '20260706-Hermes',
                'commit_short' => 'pending',
            ],
        ]);

        $publisher = new ProductReleasePublisher;
        $errors = $publisher->configMismatches('20260709-Calliope', '7.0.3');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('version', implode(' ', $errors));
        $this->assertStringContainsString('release_tag', implode(' ', $errors));
    }

    public function test_config_matches_when_aligned(): void
    {
        config([
            'documentation.product' => [
                'version' => '7.0.3',
                'release_tag' => '20260709-Calliope',
                'commit_short' => 'abc1234',
            ],
        ]);

        $publisher = new ProductReleasePublisher;

        $this->assertSame([], $publisher->configMismatches('20260709-Calliope', '7.0.3'));
    }

    public function test_default_title_format(): void
    {
        $publisher = new ProductReleasePublisher;

        $this->assertSame(
            'ServLitcys 7.0.3 — 20260709-Calliope',
            $publisher->defaultReleaseTitle('7.0.3', '20260709-Calliope')
        );
    }

    public function test_product_release_tag_round_trip(): void
    {
        $this->assertTrue(ProductReleaseTag::isValid('20260709-Calliope'));
        $this->assertSame(
            'docs/RELEASE_20260709_CALLIOPE.md',
            ProductReleaseTag::releaseDocPath('20260709-Calliope')
        );
    }
}
