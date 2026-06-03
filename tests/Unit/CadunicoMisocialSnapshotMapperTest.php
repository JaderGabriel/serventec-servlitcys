<?php

namespace Tests\Unit;

use App\Services\Cadunico\CadunicoMisocialSnapshotMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoMisocialSnapshotMapperTest extends TestCase
{
    #[Test]
    public function mapeia_faixas_etarias_misocial(): void
    {
        $doc = [
            'codigo_ibge' => '2910800',
            'anomes_s' => '202412',
            'cadun_qtd_pessoas_cadastradas_i' => 50000,
            'cadun_qtd_familias_cadastradas_i' => 12000,
            'qtd_pes_pbf_idade_0_e_4_sexo_feminino_i' => 100,
            'qtd_pes_pbf_idade_0_e_4_sexo_masculino_i' => 90,
            'qtd_pes_cad_nao_pbf_idade_5_a_6_sexo_feminino_i' => 50,
            'qtd_pes_cad_nao_pbf_idade_5_a_6_sexo_masculino_i' => 45,
            'qtd_pes_pbf_idade_7_a_15_sexo_feminino_i' => 200,
            'qtd_pes_pbf_idade_7_a_15_sexo_masculino_i' => 190,
            'qtd_pes_cad_nao_pbf_idade_16_a_17_sexo_feminino_i' => 30,
            'qtd_pes_cad_nao_pbf_idade_16_a_17_sexo_masculino_i' => 28,
        ];

        $payload = CadunicoMisocialSnapshotMapper::toSnapshotPayload($doc, 2024);

        $this->assertNotNull($payload);
        $this->assertSame('sagi_misocial', $payload['fonte']);
        $this->assertSame(50000, $payload['pessoas_cadastradas']);
        $this->assertGreaterThan(0, $payload['populacao_escolar_estimada']);
        $this->assertGreaterThan(0, $payload['criancas_4_5']);
        $this->assertGreaterThan(0, $payload['criancas_15_17']);
    }

    #[Test]
    public function usa_fallback_igd_quando_faixas_vazias(): void
    {
        $doc = [
            'anomes_s' => '202312',
            'cadun_qtd_pessoas_cadastradas_i' => 1000,
            'igd_pbf_qtd_total_criancas_adolescentes_pbf_i' => 400,
        ];

        $payload = CadunicoMisocialSnapshotMapper::toSnapshotPayload($doc, 2023);

        $this->assertNotNull($payload);
        $this->assertSame(400, $payload['populacao_escolar_estimada']);
    }
}
