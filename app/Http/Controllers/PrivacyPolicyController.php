<?php

namespace App\Http\Controllers;

use App\Support\Legal\LegalConsentService;
use App\Support\Legal\LegalDocumentService;
use Illuminate\View\View;

class PrivacyPolicyController extends Controller
{
    public function show(LegalDocumentService $documents): View
    {
        $brand = config('analytics.pdf_report.brand', []);
        $published = $documents->currentPrivacy();

        return view('legal.privacy', [
            'systemName' => (string) ($brand['system_name'] ?? config('app.name')),
            'serventecName' => (string) ($brand['serventec_name'] ?? 'Serventec Assessoria'),
            'privacyContactEmail' => (string) config('legal.privacy_contact_email', ''),
            'lastUpdated' => $published?->published_at?->format('Y-m-d')
                ?? (string) config('legal.privacy_last_updated', '2026-05-25'),
            'privacyVersion' => LegalConsentService::currentPrivacyVersion(),
            'documentTitle' => $published?->title ?? __('Política de privacidade'),
            'documentHtml' => $documents->privacyHtml(),
        ]);
    }
}
