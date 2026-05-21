<?php

namespace Tests\Unit;

use App\Services\AdminSync\AdminSyncTaskProgress;
use Tests\TestCase;

class AdminSyncTaskProgressTest extends TestCase
{
    public function test_format_includes_levels_and_explain_prefix(): void
    {
        $progress = new AdminSyncTaskProgress;
        $progress->info('Início');
        $progress->explain('Descrição do passo');
        $progress->step(1, 3, 'Download');
        $progress->detail('HTTP 200');

        $text = $progress->formatForDisplay();

        $this->assertStringContainsString('[info] Início', $text);
        $this->assertStringContainsString('[nota] → Descrição do passo', $text);
        $this->assertStringContainsString('[passo] Passo 1/3', $text);
        $this->assertStringContainsString('[detalhe] HTTP 200', $text);
    }
}
