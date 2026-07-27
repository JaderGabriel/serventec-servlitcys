<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Export\CampaignMapaColetaComposer;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class CampaignMapaColetaComposerTest extends TestCase
{
    #[Test]
    public function sections_seguem_ordem_quantitativa(): void
    {
        $composer = app(CampaignMapaColetaComposer::class);
        $method = new ReflectionMethod(CampaignMapaColetaComposer::class, 'sectionResumo');

        $section = $method->invoke($composer, [
            'counters' => [
                'schools_active' => 12,
                'schools_with_errors' => 2,
                'errors' => 5,
                'warnings' => 3,
            ],
            'triade' => ['complete' => 10, 'pct' => 83.3],
            'report' => [
                'totals' => [
                    ['label' => 'Turmas', 'value' => '40'],
                    ['label' => 'Alunos matriculados', 'value' => '800'],
                ],
            ],
        ], [
            'schools_active' => 12,
            'triade_coverage_pct' => 83.3,
        ]);

        $this->assertSame('resumo', $section['key']);
        $this->assertStringContainsString('Resumo quantitativo', (string) ($section['title'] ?? ''));
        $this->assertSame([__('Indicador'), __('Valor')], $section['tables'][0]['headers']);
        $triade = $section['tables'][0]['rows'][1];
        $this->assertSame(__('Tríade completa'), $triade['cells'][0]);
        $this->assertIsArray($triade['cells'][1]);
        $this->assertSame('10 (83,3%)', $triade['cells'][1]['text']);
        $this->assertSame('amber', $triade['cells'][1]['tone']);
    }

    #[Test]
    public function resumo_remove_acomp_do_label_curricular(): void
    {
        $composer = app(CampaignMapaColetaComposer::class);
        $method = new ReflectionMethod(CampaignMapaColetaComposer::class, 'sectionResumo');

        $section = $method->invoke($composer, [
            'counters' => [],
            'triade' => ['complete' => 9, 'pct' => 95.0],
            'report' => [
                'totals' => [
                    ['label' => 'Curricular (Acomp)', 'value' => '1.200'],
                ],
            ],
        ], []);

        $labels = collect($section['tables'][0]['rows'])
            ->map(static fn (array $row): string => (string) ($row['cells'][0] ?? ''))
            ->all();
        $this->assertContains('Curricular', $labels);
        $this->assertNotContains('Curricular (Acomp)', $labels);
        $this->assertSame('emerald', $section['tables'][0]['rows'][1]['cells'][1]['tone']);
    }

    #[Test]
    public function eja_destaca_ch_abaixo_de_20_e_acima_de_30(): void
    {
        $composer = app(CampaignMapaColetaComposer::class);
        $method = new ReflectionMethod(CampaignMapaColetaComposer::class, 'sectionEja');

        $section = $method->invoke($composer, [
            'report' => ['matriculas_por_ano' => []],
        ], [
            'segments' => [[
                'key' => 'eja',
                'label' => 'EJA',
                'turmas' => 5,
                'alunos' => 80,
                'ch_media_aluno' => 22.0,
                'ch_options' => [
                    ['hours' => 15.0, 'label' => '15 h/semana', 'turmas' => 2, 'alunos' => 20],
                    ['hours' => 22.0, 'label' => '22 h/semana', 'turmas' => 2, 'alunos' => 40],
                    ['hours' => 32.0, 'label' => '32 h/semana', 'turmas' => 1, 'alunos' => 20],
                ],
            ]],
        ]);

        $this->assertNotNull($section);
        $this->assertSame('eja', $section['key']);

        $destaque = collect($section['tables'])->firstWhere('title', __('Destaque de carga horária'));
        $this->assertIsArray($destaque);
        $this->assertSame(['< 20 h/semana', '2', '20'], $destaque['rows'][0]['cells']);
        $this->assertTrue($destaque['rows'][0]['highlight']);
        $this->assertSame(['> 30 h/semana', '1', '20'], $destaque['rows'][1]['cells']);
        $this->assertTrue($destaque['rows'][1]['highlight']);
    }

    #[Test]
    public function transporte_destaca_zona_rural(): void
    {
        $composer = app(CampaignMapaColetaComposer::class);
        $method = new ReflectionMethod(CampaignMapaColetaComposer::class, 'sectionTransporte');

        $section = $method->invoke($composer, [
            'transporte' => [
                'available' => true,
                'flagged' => 100,
                'pct' => 25.0,
                'scanned' => 400,
                'active' => [
                    'flagged' => 90,
                    'pct' => 30.0,
                    'by_location_users' => [
                        ['label' => 'Urbana', 'count' => 40, 'pct' => 44.4],
                        ['label' => 'Rural', 'count' => 50, 'pct' => 55.6],
                    ],
                    'by_veiculo' => [
                        ['label' => 'Ônibus', 'count' => 70],
                    ],
                ],
            ],
        ]);

        $this->assertNotNull($section);
        $loc = collect($section['tables'])->first(
            static fn (array $t): bool => str_contains((string) ($t['title'] ?? ''), 'localização')
                || str_contains((string) ($t['title'] ?? ''), 'Localização')
                || str_contains((string) ($t['title'] ?? ''), 'rural'),
        );
        $this->assertIsArray($loc);
        $rural = collect($loc['rows'])->first(
            static fn (array $r): bool => ($r['cells'][0] ?? '') === 'Rural',
        );
        $this->assertTrue($rural['highlight'] ?? false);
    }
}
