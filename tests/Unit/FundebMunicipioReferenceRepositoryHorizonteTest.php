<?php

namespace Tests\Unit;

use App\Models\FundebMunicipioReference;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebMunicipioReferenceRepositoryHorizonteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function upsert_horizonte_receita_sets_vaaf_zero_on_create(): void
    {
        $repo = app(FundebMunicipioReferenceRepository::class);

        $ref = $repo->upsertHorizontePortariaReceita('1200013', 2025, null, [
            'receita_total' => 21_064_393.14,
            'complementacao_vaat' => 2_690_196.52,
            'fonte' => 'fnde_portaria_receita_horizonte',
            'url_portaria' => 'https://example.test/receita.csv',
        ]);

        $this->assertSame(0.0, (float) $ref->vaaf);
        $this->assertSame(21_064_393.14, (float) $ref->receita_total);
        $this->assertDatabaseHas('fundeb_municipio_references', [
            'ibge_municipio' => '1200013',
            'ano' => 2025,
            'vaaf' => 0,
        ]);
    }

    #[Test]
    public function upsert_horizonte_receita_does_not_overwrite_existing_vaaf(): void
    {
        FundebMunicipioReference::query()->create([
            'ibge_municipio' => '1200013',
            'ano' => 2025,
            'vaaf' => 4500.50,
            'fonte' => 'api_fnde',
            'receita_total' => 1_000_000,
            'imported_at' => now(),
        ]);

        $repo = app(FundebMunicipioReferenceRepository::class);
        $ref = $repo->upsertHorizontePortariaReceita('1200013', 2025, null, [
            'receita_total' => 21_064_393.14,
            'fonte' => 'fnde_portaria_receita_horizonte',
        ]);

        $this->assertSame(4500.50, (float) $ref->vaaf);
        $this->assertSame(21_064_393.14, (float) $ref->receita_total);
    }
}
