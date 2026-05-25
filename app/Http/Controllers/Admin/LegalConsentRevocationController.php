<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevokeLegalConsentRequest;
use App\Models\User;
use App\Support\Legal\LegalDocumentService;
use Illuminate\Http\RedirectResponse;

class LegalConsentRevocationController extends Controller
{
    public function revokeUser(
        RevokeLegalConsentRequest $request,
        User $user,
        LegalDocumentService $documents,
    ): RedirectResponse {
        $documents->revokeUser(
            $user,
            $request->user(),
            $request,
            $request->revokePrivacy(),
            $request->revokeCookies(),
        );

        return back()->with(
            'status',
            __('Aceite de :name revogado. O utilizador deverá confirmar novamente em /consentimento.', ['name' => $user->name])
        );
    }

    public function revokeAll(
        RevokeLegalConsentRequest $request,
        LegalDocumentService $documents,
    ): RedirectResponse {
        $count = $documents->revokeAllActiveUsers(
            $request->revokePrivacy(),
            $request->revokeCookies(),
            $request->user(),
            $request,
        );

        return back()->with(
            'status',
            __('Aceites revogados para :n utilizador(es) activos.', ['n' => $count])
        );
    }
}
