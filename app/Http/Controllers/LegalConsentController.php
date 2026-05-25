<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLegalConsentRequest;
use App\Support\Legal\LegalConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalConsentController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        if (! LegalConsentService::userNeedsConsent($user)) {
            $intended = $request->query('intended');

            return redirect()->to(
                is_string($intended) && $intended !== '' && str_starts_with($intended, '/')
                    ? $intended
                    : $user->homeUrl()
            );
        }

        $brand = config('analytics.pdf_report.brand', []);

        return view('legal.consent', [
            'systemName' => (string) ($brand['system_name'] ?? config('app.name')),
            'status' => LegalConsentService::statusForUser($user),
            'privacyUrl' => route('legal.privacy'),
            'intended' => $request->query('intended'),
        ]);
    }

    public function store(StoreLegalConsentRequest $request, LegalConsentService $consents): RedirectResponse
    {
        $user = $request->user();
        $consents->recordAcceptance(
            $user,
            $request,
            'consent_page',
            privacy: $request->boolean('accept_privacy'),
            cookies: $request->boolean('accept_cookies'),
        );

        $intended = $request->input('intended');

        return redirect()->to(
            is_string($intended) && $intended !== '' && str_starts_with($intended, '/')
                ? $intended
                : $user->homeUrl()
        )->with('success', __('Obrigado. O seu consentimento foi registado para a versão vigente.'));
    }

    /**
     * Aceite rápido via banner (visitantes na welcome ou utilizadores em páginas públicas).
     */
    public function storeGuest(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'accept_privacy' => ['accepted'],
            'accept_cookies' => ['accepted'],
        ]);

        $payload = json_encode([
            'privacy_version' => LegalConsentService::currentPrivacyVersion(),
            'cookies_version' => LegalConsentService::currentCookiesVersion(),
            'accepted_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        $cookie = cookie(
            (string) config('legal.consent_cookie_name', 'servlitcys_legal_consent'),
            $payload,
            (int) config('legal.consent_cookie_days', 365) * 24 * 60,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        );

        if ($request->user() !== null) {
            app(LegalConsentService::class)->recordAcceptance(
                $request->user(),
                $request,
                'banner',
                privacy: true,
                cookies: true,
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true])->withCookie($cookie);
        }

        return back()->withCookie($cookie);
    }
}
