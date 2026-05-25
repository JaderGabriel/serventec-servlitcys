<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RevokeLegalConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'revoke_privacy' => ['sometimes', 'boolean'],
            'revoke_cookies' => ['sometimes', 'boolean'],
        ];
    }

    public function revokePrivacy(): bool
    {
        return $this->boolean('revoke_privacy', true);
    }

    public function revokeCookies(): bool
    {
        return $this->boolean('revoke_cookies', true);
    }
}
