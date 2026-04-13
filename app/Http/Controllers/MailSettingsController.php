<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMailSettingsRequest;
use App\Models\MailSetting;
use App\Services\MailConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MailSettingsController extends Controller
{
    public function edit(): View
    {
        return view('settings.mail', [
            'settings' => MailSetting::query()->first(),
        ]);
    }

    public function update(StoreMailSettingsRequest $request, MailConfigService $mailConfig): RedirectResponse
    {
        $payload = $request->validated();

        if (($payload['smtp_password'] ?? '') === '') {
            unset($payload['smtp_password']);
        }

        $model = MailSetting::query()->firstOrNew([]);
        $model->fill($payload);
        $model->save();

        $mailConfig->applyFromDatabase();

        return redirect()->route('settings.mail.edit')->with('status', __('Configurações de e-mail salvas.'));
    }
}
