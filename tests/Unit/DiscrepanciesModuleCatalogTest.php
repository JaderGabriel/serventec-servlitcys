<?php

namespace Tests\Unit;

use App\Support\Ieducar\ConsultoriaOperationalSignals;
use App\Support\Ieducar\DiscrepanciesCheckCatalog;
use App\Support\Ieducar\DiscrepanciesModuleCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DiscrepanciesModuleCatalogTest extends TestCase
{
    #[Test]
    public function modulos_referenciam_rotinas_do_catalogo_ou_operacionais(): void
    {
        $definitions = DiscrepanciesCheckCatalog::definitions();
        $operational = ConsultoriaOperationalSignals::operationalMeta();

        foreach (DiscrepanciesModuleCatalog::modules() as $module) {
            foreach ($module['routine_ids'] as $routineId) {
                $this->assertTrue(
                    isset($definitions[$routineId]) || isset($operational[$routineId]),
                    sprintf('Rotina «%s» do módulo «%s» sem metadados no catálogo.', $routineId, $module['id'] ?? ''),
                );
            }
        }
    }
}
