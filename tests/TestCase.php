<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\DisablesAuthenticatedLegalConsent;

abstract class TestCase extends BaseTestCase
{
    use DisablesAuthenticatedLegalConsent;

    protected function setUp(): void
    {
        parent::setUp();

        // Evita exigir `npm run build` em cada execução de testes (CI/local sem manifest).
        $this->withoutVite();

        // Feature tests não devem ser bloqueados pelo gate de consentimento legal
        // (LegalConsentTest reativa o requisito explicitamente).
        $this->disableAuthenticatedLegalConsentRequirement();
    }
}
