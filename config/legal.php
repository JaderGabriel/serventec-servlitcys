<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Política de privacidade e cookies (LGPD)
    |--------------------------------------------------------------------------
    |
    | privacy_version / cookies_version: altere quando o texto ou o tratamento
    | mudar — utilizadores com versão antiga serão solicitados a aceitar de novo.
    |
    */

    'privacy_last_updated' => env('LEGAL_PRIVACY_LAST_UPDATED', '2026-05-25'),

    'privacy_version' => env('LEGAL_PRIVACY_VERSION', env('LEGAL_PRIVACY_LAST_UPDATED', '2026-05-25')),

    'cookies_version' => env('LEGAL_COOKIES_VERSION', env('LEGAL_PRIVACY_VERSION', env('LEGAL_PRIVACY_LAST_UPDATED', '2026-05-25'))),

    'privacy_contact_email' => env('LEGAL_PRIVACY_CONTACT_EMAIL', ''),

    'require_authenticated_consent' => filter_var(env('LEGAL_REQUIRE_AUTHENTICATED_CONSENT', true), FILTER_VALIDATE_BOOLEAN),

    'consent_cookie_name' => env('LEGAL_CONSENT_COOKIE_NAME', 'servlitcys_legal_consent'),

    'consent_cookie_days' => max(1, (int) env('LEGAL_CONSENT_COOKIE_DAYS', 365)),

];
