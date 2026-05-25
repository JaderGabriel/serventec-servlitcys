<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLegalConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'accept_privacy' => ['accepted'],
            'accept_cookies' => ['accepted'],
            'intended' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accept_privacy.accepted' => __('Deve aceitar a política de privacidade vigente.'),
            'accept_cookies.accepted' => __('Deve aceitar o uso de cookies essenciais.'),
        ];
    }
}
