<?php

namespace Tests\Concerns;

/**
 * Desativa o middleware de consentimento autenticado nos testes Feature/Unit
 * que não exercitam o fluxo legal (evita redirect em massa para /consentimento).
 */
trait DisablesAuthenticatedLegalConsent
{
    protected function disableAuthenticatedLegalConsentRequirement(): void
    {
        config(['legal.require_authenticated_consent' => false]);
    }

    protected function enableAuthenticatedLegalConsentRequirement(): void
    {
        config(['legal.require_authenticated_consent' => true]);
    }
}
