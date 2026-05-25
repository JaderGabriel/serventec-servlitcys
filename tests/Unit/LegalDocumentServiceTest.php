<?php

namespace Tests\Unit;

use App\Support\Legal\LegalDocumentService;
use App\Support\Legal\LegalMarkdownRenderer;
use Tests\TestCase;

final class LegalDocumentServiceTest extends TestCase
{
    public function test_hash_detecta_alteracao_de_conteudo(): void
    {
        $service = new LegalDocumentService(new LegalMarkdownRenderer);

        $a = $service->hashBody('## Um');
        $b = $service->hashBody('## Dois');

        $this->assertNotSame($a, $b);
    }
}
