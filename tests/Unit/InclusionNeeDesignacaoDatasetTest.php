<?php

namespace Tests\Unit;

use App\Support\Ieducar\InclusionDashboardQueries;
use App\Support\Ieducar\InclusionEducacensoCatalog;
use App\Support\Ieducar\InclusionNeeDesignacaoDataset;
use Tests\TestCase;

final class InclusionNeeDesignacaoDatasetTest extends TestCase
{
    public function test_grupo_totais_somam_catalogo_positivo_com_mesma_classificacao(): void
    {
        $dataset = [
            'footnote' => 'test',
            'uses_fisica' => true,
            'matriculas_nee' => 10,
            'catalog' => [
                [
                    'label' => 'TEA — cadastro i-Educar',
                    'value' => 5.0,
                    'kind' => 'ieducar',
                    'norm' => 'tea',
                    'grupo' => 'sindrome',
                ],
                [
                    'label' => 'Baixa visão — INEP/Censo',
                    'value' => 3.0,
                    'kind' => 'inep',
                    'norm' => 'baixa visao',
                    'grupo' => 'deficiencia',
                ],
                [
                    'label' => 'Surdez — INEP/Censo',
                    'value' => 0.0,
                    'kind' => 'inep',
                    'norm' => 'surdez',
                    'grupo' => 'deficiencia',
                ],
            ],
            'grupos' => [
                'deficiencias' => 3,
                'sindromes_tea' => 5,
                'ne_altas_habilidades' => 0,
            ],
        ];

        $chart = InclusionNeeDesignacaoDataset::chartGrupo($dataset, 100);
        $this->assertNotNull($chart);
        $this->assertSame([3.0, 5.0, 0.0], $chart['datasets'][0]['data']);

        $ativo = InclusionNeeDesignacaoDataset::chartCatalogo($dataset, 100, false);
        $this->assertNotNull($ativo);
        $this->assertCount(2, $ativo['labels']);

        $completo = InclusionNeeDesignacaoDataset::chartCatalogo($dataset, 100, true);
        $this->assertNotNull($completo);
        $this->assertCount(3, $completo['labels']);

        $detalhe = InclusionNeeDesignacaoDataset::detalhePorCategoria($dataset);
        $this->assertSame(3, $detalhe['totais_por_secao']['deficiencias']);
        $this->assertSame(5, $detalhe['totais_por_secao']['sindromes_tea']);
    }

    public function test_resolve_catalog_norm_aplica_alias_configuravel(): void
    {
        config(['ieducar.inclusion.deficiencia_label_aliases' => [
            'TEA' => 'Transtorno do espectro autista',
        ]]);

        $norm = InclusionEducacensoCatalog::resolveCatalogNorm('TEA');
        $this->assertSame(
            InclusionEducacensoCatalog::normalizeLabel('Transtorno do espectro autista'),
            $norm
        );
    }

    public function test_grupo_inclui_sem_designacao_no_bloco_deficiencias(): void
    {
        $catalog = [
            ['label' => 'Baixa visão', 'value' => 10.0, 'kind' => 'inep', 'norm' => 'baixa visao', 'grupo' => 'deficiencia'],
            ['label' => 'Sem designação', 'value' => 706.0, 'kind' => 'ieducar', 'norm' => '__sem_designacao__', 'grupo' => 'deficiencia'],
        ];
        $method = new \ReflectionMethod(InclusionNeeDesignacaoDataset::class, 'aggregateGruposFromCatalog');
        $method->setAccessible(true);
        $grupos = $method->invoke(null, $catalog);

        $this->assertSame(716, $grupos['deficiencias']);
        $this->assertSame(0, $grupos['sindromes_tea']);

        $dataset = [
            'footnote' => 'test',
            'uses_fisica' => true,
            'matriculas_nee' => 716,
            'catalog' => $catalog,
            'grupos' => $grupos,
        ];
        $chart = InclusionNeeDesignacaoDataset::chartGrupo($dataset, 1000);
        $this->assertNotNull($chart);
        $this->assertSame([716.0, 0.0, 0.0], $chart['datasets'][0]['data']);
    }

    public function test_append_sem_designacao_quando_total_nee_excede_soma_barras(): void
    {
        $catalog = [
            ['label' => 'Baixa visão — INEP/Censo', 'value' => 10.0, 'kind' => 'inep', 'norm' => 'baixa visao', 'grupo' => 'deficiencia'],
        ];
        $method = new \ReflectionMethod(InclusionNeeDesignacaoDataset::class, 'appendSemDesignacaoCatalogoRow');
        $method->setAccessible(true);
        $out = $method->invoke(null, $catalog, 716);

        $this->assertCount(2, $out);
        $this->assertSame('__sem_designacao__', $out[1]['norm']);
        $this->assertSame(706.0, $out[1]['value']);
    }

    public function test_classificar_designacao_grupo_alinha_com_palavras_chave(): void
    {
        $this->assertSame('sindrome', InclusionDashboardQueries::classificarDesignacaoNeeGrupo('Transtorno do espectro autista'));
        $this->assertSame('ne', InclusionDashboardQueries::classificarDesignacaoNeeGrupo('Altas habilidades'));
        $this->assertSame('deficiencia', InclusionDashboardQueries::classificarDesignacaoNeeGrupo('Baixa visão'));
    }
}
