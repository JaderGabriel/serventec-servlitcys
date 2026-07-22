<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Export\CampaignCsvExporter;
use App\Services\Clio\Support\ClioUserCopy;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class CampaignCsvExporterTest extends TestCase
{
    #[Test]
    public function mascara_cpf_nis_de_onze_digitos_nas_mensagens(): void
    {
        $exporter = app(CampaignCsvExporter::class);
        $method = new ReflectionMethod(CampaignCsvExporter::class, 'stripPiiHint');

        $out = $method->invoke($exporter, 'Documento 12345678901 na amostra');

        $this->assertSame('Documento [redacted] na amostra', $out);
        $this->assertStringNotContainsString('12345678901', $out);
    }

    #[Test]
    public function copia_de_usuario_explica_acao_por_codigo(): void
    {
        $this->assertStringContainsString('arquivos', mb_strtolower(ClioUserCopy::findingAction('CLIO-COE-TRIADE', 'warning')));
        $this->assertSame(__('Erro'), ClioUserCopy::severityLabel('error'));
        $this->assertNotEmpty(ClioUserCopy::glossary());
    }
}
