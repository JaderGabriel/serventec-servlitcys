<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalConsentLog;
use App\Models\User;
use App\Support\Legal\LegalConsentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalConsentReportController extends Controller
{
    public function index(Request $request, LegalConsentService $consents): View
    {
        $privacyVersion = LegalConsentService::currentPrivacyVersion();
        $cookiesVersion = LegalConsentService::currentCookiesVersion();
        $summary = $consents->adminSummary();

        $usersQuery = User::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->query('filter') === 'pending') {
            $usersQuery->where(function ($q) use ($privacyVersion, $cookiesVersion): void {
                $q->whereNull('privacy_policy_version_accepted')
                    ->orWhere('privacy_policy_version_accepted', '!=', $privacyVersion)
                    ->orWhereNull('cookies_consent_version')
                    ->orWhere('cookies_consent_version', '!=', $cookiesVersion);
            });
        }

        $users = $usersQuery
            ->paginate(25)
            ->withQueryString();

        $logs = LegalConsentLog::query()
            ->with('user:id,name,email,username')
            ->latest('accepted_at')
            ->limit(50)
            ->get();

        return view('admin.legal-consents.index', [
            'summary' => $summary,
            'privacyVersion' => $privacyVersion,
            'cookiesVersion' => $cookiesVersion,
            'users' => $users,
            'logs' => $logs,
            'filter' => (string) $request->query('filter', ''),
        ]);
    }
}
