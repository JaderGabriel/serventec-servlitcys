<?php

namespace App\Http\Controllers;

use App\Http\Requests\FirstAccessProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FirstAccessProfileController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->needsProfileCompletion()) {
            return redirect()->route($request->user()->homeRouteName(), $request->user()->homeRouteParameters());
        }

        return view('profile.first-access', [
            'user' => $request->user(),
        ]);
    }

    public function update(FirstAccessProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill([
            'birth_date' => $request->validated('birth_date'),
            'cpf' => $request->validated('cpf'),
            'phone' => $request->validated('phone'),
            'whatsapp' => $request->validated('whatsapp'),
        ]);
        $user->save();

        return redirect()->route($user->homeRouteName(), $user->homeRouteParameters())
            ->with('success', __('Cadastro complementado. Bem-vindo!'));
    }
}
