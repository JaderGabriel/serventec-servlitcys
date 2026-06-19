<?php

namespace Tests\Unit;

use App\Support\Admin\PublicDataImportCatalog;
use Tests\TestCase;

final class PublicDataImportCatalogRoutineHintTest extends TestCase
{
    public function test_routine_hint_returns_hub_and_cli(): void
    {
        $hint = PublicDataImportCatalog::routineHint('fundeb_fnde');

        $this->assertStringContainsString('admin', $hint['hub_url']);
        $this->assertNotNull($hint['cli']);
    }
}
