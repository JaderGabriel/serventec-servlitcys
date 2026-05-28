<?php

namespace Tests\Unit;

use App\Support\Cadunico\CadunicoStoragePaths;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoStoragePathsTest extends TestCase
{
    #[Test]
    public function discover_prioriza_arquivo_por_ibge_e_ano(): void
    {
        $root = CadunicoStoragePaths::storageRoot();
        if (! is_dir($root)) {
            mkdir($root, 0755, true);
        }

        $ibge = '2910800';
        $ano = 2024;
        $specific = $root.'/'.$ibge.'_'.$ano.'.csv';
        $national = $root.'/nacional_'.$ano.'.csv';
        file_put_contents($national, "codigo_ibge;ano\n");
        file_put_contents($specific, "codigo_ibge;ano\n");

        $found = CadunicoStoragePaths::discoverCsvCandidates($ibge, $ano);

        $this->assertNotEmpty($found);
        $this->assertSame($specific, $found[0]);

        @unlink($specific);
        @unlink($national);
    }
}
