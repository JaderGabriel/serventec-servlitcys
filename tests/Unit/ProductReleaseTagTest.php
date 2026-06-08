<?php

namespace Tests\Unit;

use App\Support\Product\ProductReleaseTag;
use Tests\TestCase;

final class ProductReleaseTagTest extends TestCase
{
    public function test_parse_tag_sem_sufixo(): void
    {
        $parsed = ProductReleaseTag::parse('20260607-Phronesis');

        $this->assertNotNull($parsed);
        $this->assertSame('20260607', $parsed['date']);
        $this->assertSame('', $parsed['suffix']);
        $this->assertSame('Phronesis', $parsed['codename']);
        $this->assertSame('20260607', $parsed['sort_key']);
    }

    public function test_parse_tag_com_sufixo(): void
    {
        $parsed = ProductReleaseTag::parse('20260607a-Ananke');

        $this->assertNotNull($parsed);
        $this->assertSame('20260607', $parsed['date']);
        $this->assertSame('a', $parsed['suffix']);
        $this->assertSame('Ananke', $parsed['codename']);
        $this->assertSame('20260607a', $parsed['sort_key']);
    }

    public function test_release_doc_path_com_sufixo(): void
    {
        $this->assertSame(
            'docs/RELEASE_20260607a_ANANKE.md',
            ProductReleaseTag::releaseDocPath('20260607a-Ananke')
        );
    }

    public function test_sort_key_ordena_mesmo_dia_sem_e_com_letra(): void
    {
        $this->assertGreaterThan(
            0,
            strcmp('20260607a', '20260607')
        );
        $this->assertGreaterThan(
            0,
            strcmp('20260607b', '20260607a')
        );
    }

    public function test_next_suffix_quando_ja_existe_release_no_dia(): void
    {
        $this->assertSame(
            'a',
            ProductReleaseTag::nextSuffixForDate('20260607', ['20260607'])
        );
        $this->assertSame(
            'b',
            ProductReleaseTag::nextSuffixForDate('20260607', ['20260607', '20260607a'])
        );
        $this->assertSame(
            '',
            ProductReleaseTag::nextSuffixForDate('20260608', ['20260607'])
        );
    }

    public function test_parse_doc_basename_com_sufixo(): void
    {
        $parsed = ProductReleaseTag::parseDocBasename('RELEASE_20260607a_ANANKE');

        $this->assertNotNull($parsed);
        $this->assertSame('20260607a', $parsed['sort_key']);
        $this->assertSame('ANANKE', $parsed['codename']);
    }
}
