<?php

namespace Tests\Unit;

use App\Support\Http\UserAgentSummary;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserAgentSummaryTest extends TestCase
{
    #[Test]
    public function resume_chrome_no_windows(): void
    {
        $summary = app(UserAgentSummary::class)->short(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        );

        $this->assertSame('Chrome 120 · Windows', $summary);
    }

    #[Test]
    public function resume_firefox_no_linux(): void
    {
        $summary = app(UserAgentSummary::class)->short(
            'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0',
        );

        $this->assertSame('Firefox 128 · Linux', $summary);
    }
}
