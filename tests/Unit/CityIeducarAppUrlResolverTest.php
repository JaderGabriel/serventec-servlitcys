<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Ieducar\CityIeducarAppUrlResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CityIeducarAppUrlResolverTest extends TestCase
{
    private CityIeducarAppUrlResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CityIeducarAppUrlResolver;
    }

    #[Test]
    public function usa_url_cadastrada_na_cidade(): void
    {
        $city = new City([
            'name' => 'ITAMARI',
            'uf' => 'BA',
            'ieducar_app_url' => 'https://itamari.exemplo.gov.br/ieducar',
        ]);
        $city->id = 1;

        $this->assertSame('https://itamari.exemplo.gov.br/ieducar', $this->resolver->resolve($city));
    }

    #[Test]
    public function adiciona_https_quando_ausente(): void
    {
        $city = new City([
            'name' => 'Teste',
            'uf' => 'BA',
            'ieducar_app_url' => 'ieducar.exemplo.gov.br',
        ]);

        $this->assertSame('https://ieducar.exemplo.gov.br', $this->resolver->resolve($city));
    }

    #[Test]
    public function fallback_para_mapa_env_por_city_id(): void
    {
        config(['ieducar.app_urls' => ['5' => 'https://saubara.exemplo.gov.br']]);

        $city = new City(['name' => 'SAUBARA', 'uf' => 'BA']);
        $city->id = 5;

        $this->assertSame('https://saubara.exemplo.gov.br', $this->resolver->resolve($city));
    }

    #[Test]
    public function template_com_placeholders(): void
    {
        config([
            'ieducar.app_urls' => [],
            'ieducar.app_url_template' => 'https://{slug}.ieducar.local',
        ]);

        $city = new City(['name' => 'Formosa do Rio Preto', 'uf' => 'BA', 'ibge_municipio' => '2910800']);
        $city->id = 3;

        $this->assertSame('https://formosa-do-rio-preto.ieducar.local', $this->resolver->resolve($city));
    }

    #[Test]
    public function retorna_null_sem_fonte(): void
    {
        config(['ieducar.app_urls' => [], 'ieducar.app_url_template' => '']);

        $city = new City(['name' => 'Sem URL', 'uf' => 'BA']);

        $this->assertNull($this->resolver->resolve($city));
    }
}
