<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SaebMunicipioApiTest extends TestCase
{
    /** IBGE Formosa do Rio Preto — BA (contrato GET /api/saeb/municipio/{ibge}). */
    private const IBGE_FORMOSA_RIO_PRETO_BA = '2911105';

    #[Test]
    public function it_returns_formosa_do_rio_preto_ba_via_api_construida(): void
    {
        Storage::fake('public');

        $payload = [
            'meta' => [
                'municipio_ibge' => self::IBGE_FORMOSA_RIO_PRETO_BA,
                'municipio_nome' => 'Formosa do Rio Preto',
                'municipio_uf' => 'BA',
                'fonte' => 'teste automatizado (Storage fake)',
            ],
            'pontos' => [
                [
                    'ano' => 2021,
                    'disciplina' => 'lp',
                    'etapa' => 'efaf',
                    'valor' => 14.0,
                    'status' => 'final',
                    'unidade' => '% proficientes',
                    'city_ids' => [1],
                    'municipio_ibge' => self::IBGE_FORMOSA_RIO_PRETO_BA,
                ],
                [
                    'ano' => 2021,
                    'disciplina' => 'mat',
                    'etapa' => 'efaf',
                    'valor' => 11.5,
                    'status' => 'final',
                    'unidade' => '% proficientes',
                    'city_ids' => [1],
                    'municipio_ibge' => self::IBGE_FORMOSA_RIO_PRETO_BA,
                ],
            ],
        ];

        Storage::disk('public')->put(
            'saeb/municipio/'.self::IBGE_FORMOSA_RIO_PRETO_BA.'.json',
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        );

        $ibge = self::IBGE_FORMOSA_RIO_PRETO_BA;

        $this->getJson("/api/saeb/municipio/{$ibge}")
            ->assertOk()
            ->assertJsonPath('meta.municipio_ibge', $ibge)
            ->assertJsonPath('meta.municipio_nome', 'Formosa do Rio Preto')
            ->assertJsonPath('meta.municipio_uf', 'BA')
            ->assertJsonCount(2, 'pontos');

        $this->getJson("/api/saeb/municipio/{$ibge}.json")
            ->assertOk()
            ->assertJsonPath('meta.municipio_ibge', $ibge);
    }

    #[Test]
    public function it_returns_json_when_municipio_file_exists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('saeb/municipio/1234567.json', json_encode([
            'meta' => ['municipio_ibge' => '1234567'],
            'pontos' => [
                [
                    'ano' => 2021,
                    'disciplina' => 'lp',
                    'etapa' => 'efaf',
                    'valor' => 10,
                    'status' => 'final',
                    'city_ids' => [1],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->getJson('/api/saeb/municipio/1234567')
            ->assertOk()
            ->assertJsonPath('meta.municipio_ibge', '1234567');

        $this->getJson('/api/saeb/municipio/1234567.json')
            ->assertOk();
    }

    #[Test]
    public function it_returns_404_when_route_does_not_match_ibge(): void
    {
        Storage::fake('public');

        $this->getJson('/api/saeb/municipio/123')
            ->assertNotFound();
    }
}
