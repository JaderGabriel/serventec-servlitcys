<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MailConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, MailConfigService $mailConfig): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'birth_date' => ['required', 'date'],
        ]);

        $mailConfig->applyFromDatabase();

        $user = User::query()->where('email', $request->email)->first();

        if (! $user || ! $user->birth_date) {
            throw ValidationException::withMessages([
                'email' => __('Os dados indicados não conferem.'),
            ]);
        }

        $given = \Carbon\Carbon::parse($request->birth_date)->toDateString();
        $stored = $user->birth_date instanceof \Carbon\CarbonInterface
            ? $user->birth_date->toDateString()
            : (string) $user->birth_date;

        if ($given !== $stored) {
            throw ValidationException::withMessages([
                'email' => __('Os dados indicados não conferem.'),
            ]);
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withInput($request->only('email', 'birth_date'))
                ->withErrors(['email' => __($status)]);
    }
}
