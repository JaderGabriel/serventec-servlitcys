<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MailConfigService;
use App\Support\Cpf;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
            'cpf' => ['required', 'string'],
        ]);

        $mailConfig->applyFromDatabase();

        $user = User::query()->where('email', $request->string('email')->toString())->first();

        $givenCpf = Cpf::normalizeDigits($request->string('cpf')->toString());
        if (! $user || ! $user->birth_date || $user->cpf === null || $user->cpf === '' || ! Cpf::isValidDigits($givenCpf)) {
            throw ValidationException::withMessages([
                'email' => __('Os dados indicados não conferem.'),
            ]);
        }

        $givenBirth = Carbon::parse($request->birth_date)->toDateString();
        $storedBirth = $user->birth_date instanceof CarbonInterface
            ? $user->birth_date->toDateString()
            : (string) $user->birth_date;

        if ($givenBirth !== $storedBirth || $givenCpf !== $user->cpf) {
            throw ValidationException::withMessages([
                'email' => __('Os dados indicados não conferem.'),
            ]);
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withInput($request->only('email', 'birth_date', 'cpf'))
                ->withErrors(['email' => __($status)]);
    }
}
