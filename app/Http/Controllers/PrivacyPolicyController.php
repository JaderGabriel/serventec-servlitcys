<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PrivacyPolicyController extends Controller
{
    public function show(): View
    {
        $brand = config('analytics.pdf_report.brand', []);

        return view('legal.privacy', [
            'systemName' => (string) ($brand['system_name'] ?? config('app.name')),
            'serventecName' => (string) ($brand['serventec_name'] ?? 'Serventec Assessoria'),
            'privacyContactEmail' => (string) config('legal.privacy_contact_email', ''),
            'lastUpdated' => (string) config('legal.privacy_last_updated', '2026-05-25'),
        ]);
    }
}
