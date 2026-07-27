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
        ], []);

        $this->assertSame('resumo', $section['key']);
        $this->assertStringContainsString('Exposição das matrículas', (string) ($section['title'] ?? ''));
        $qualidade = collect($section['tables'])->firstWhere('title', __('Qualidade da coleta'));
        $this->assertIsArray($qualidade);
        $this->assertSame([__('Indicador'), __('Valor')], $qualidade['headers']);
        $triade = $qualidade['rows'][1];
        $this->assertSame(__('Tríade completa'), $triade['cells'][0]);
        $this->assertIsArray($triade['cells'][1]);
        $this->assertSame('10 (83,3%)', $triade['cells'][1]['text']);
        $this->assertSame('amber', $triade['cells'][1]['tone']);
    }

    #[Test]
    public function resumo_abre_com_exposicao_e_omite_totais_de_matricula_duplicados(): void
    {
        $composer = app(CampaignMapaColetaComposer::class);
        $method = new ReflectionMethod(CampaignMapaColetaComposer::class, 'sectionResumo');

        $matrix = [
            'available' => true,
            'year' => 2025,
            'infantil' => [
                'title' => 'Educação infantil',
                'columns' => [
                    ['key' => 'creche_parcial', 'label' => 'Creche Parcial'],
                ],
                'rows' => [
                    'regular' => 'Regular',
                    'especial' => 'Especial',
                ],
                'values' => [
                    'creche_parcial' => [
                        'Urbana' => ['regular' => 10, 'especial' => 1],
                        'Rural' => ['regular' => 2, 'especial' => 0],
                    ],
                ],
            ],
            'fundamental' => [
                'title' => 'Educação fundamental',
                'columns' => [
                    ['key' => 'ai_parcial', 'label' => 'Fundamental I · Parcial'],
                ],
                'rows' => [
                    'regular' => 'Regular',
                    'especial' => 'Especial',
                ],
                'values' => [
                    'ai_parcial' => [
                        'Urbana' => ['regular' => 40, 'especial' => 0],
                        'Rural' => ['regular' => 5, 'especial' => 0],
                    ],
                ],
            ],
            'eja' => [
                'title' => 'EJA presencial fundamental',
                'columns' => [
                    ['key' => 'eja', 'label' => 'EJA Presencial Fundamental'],
                ],
                'rows' => [
                    'regular' => 'Regular',
                    'especial' => 'Especial',
                ],
                'values' => [
                    'eja' => [
                        'Urbana' => ['regular' => 8, 'especial' => 0],
                        'Rural' => ['regular' => 1, 'especial' => 0],
                    ],
                ],
            ],
            'geral' => [
                'title' => 'Análise geral',
                'columns' => [
                    ['key' => 'geral', 'label' => 'GERAL'],
                    ['key' => 'especial', 'label' => 'Educação Especial'],
                ],
                'values' => [
                    'geral' => 66,
                    'especial' => 1,
                ],
            ],
        ];

        $section = $method->invoke($composer, [
            'counters' => [],
            'triade' => ['complete' => 9, 'pct' => 95.0],
            'report' => [
                'totals' => [
                    ['label' => 'Turmas', 'value' => '40'],
                    ['label' => 'Alunos matriculados', 'value' => '800'],
                    ['label' => 'Curricular (Acomp)', 'value' => '1.200'],
                    ['label' => 'AEE', 'value' => '15'],
                    ['label' => 'Ativ. complementar', 'value' => '30'],
                    ['label' => 'Diferenças a revisar', 'value' => '2'],
                ],
            ],
        ], [], $matrix);

        $this->assertStringContainsString('Exposição das matrículas', (string) ($section['tables'][0]['title'] ?? ''));
        $this->assertSame(['Regular', '10 / 2'], $section['tables'][0]['rows'][0]['cells']);

        $qualidade = collect($section['tables'])->firstWhere('title', __('Qualidade da coleta'));
        $this->assertIsArray($qualidade);
        $labels = collect($qualidade['rows'])
            ->map(static fn (array $row): string => (string) ($row['cells'][0] ?? ''))
            ->all();
        $this->assertContains('Turmas', $labels);
        $this->assertContains('Diferenças a revisar', $labels);
        $this->assertNotContains('Curricular', $labels);
        $this->assertNotContains('Alunos matriculados', $labels);
        $this->assertNotContains('AEE', $labels);
        $this->assertNotContains('Ativ. complementar', $labels);
        $this->assertSame('emerald', $qualidade['rows'][1]['cells'][1]['tone']);
    }

    #[Test]
    public function eja_destaca_ch_abaixo_de_20_e_acima_de_30_sem_tabela_de_etapa(): void
    {
        $composer = app(CampaignMapaColetaComposer::class);
        $method = new ReflectionMethod(CampaignMapaColetaComposer::class, 'sectionEja');

        $section = $method->invoke($composer, [
            'report' => [
                'matriculas_por_ano' => [
                    ['label' => 'EJA - Anos iniciais', 'count' => 12],
                ],
            ],
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

        $etapa = collect($section['tables'])->firstWhere('title', __('Matrículas por etapa EJA'));
        $this->assertNull($etapa);
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
