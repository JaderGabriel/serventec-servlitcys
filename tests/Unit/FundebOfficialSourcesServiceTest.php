<?php

namespace Tests\Unit;

use App\Services\Fundeb\FundebOfficialSourcesService;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Support\Fundeb\FundebFndePortariaCatalog;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

final class FundebOfficialSourcesServiceTest extends TestCase
{
    public function test_probe_csv_url_usa_get_com_user_agent_em_vez_de_head(): void
    {
        Http::fake([
            '*' => Http::response("CODIGO IBGE;UF;TOTAL\n2915700;BA;1000\n", 206, [
                'Content-Type' => 'text/csv',
            ]),
        ]);

        $service = new FundebOfficialSourcesService(app(FundebOpenDataImportService::class));
        $url = FundebFndePortariaCatalog::receitaCsvUrl(2026);
        $this->assertNotNull($url);

        $probe = new ReflectionMethod(FundebOfficialSourcesService::class, 'probeCsvUrl');
        $probe->setAccessible(true);
        /** @var array{ok: bool, message: string} $result */
        $result = $probe->invoke($service, $url);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('206', $result['message']);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && $request->hasHeader('User-Agent', 'Servlitcys-FUNDEB/1.0')
                && $request->hasHeader('Range', 'bytes=0-1023');
        });
    }
}
