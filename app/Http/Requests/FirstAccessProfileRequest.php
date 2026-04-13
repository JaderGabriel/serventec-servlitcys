<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Cpf;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FirstAccessProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->needsProfileCompletion();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'birth_date' => ['required', 'date', 'before:today'],
            'cpf' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $d = Cpf::normalizeDigits(is_string($value) ? $value : '');
                    if ($d === '' || ! Cpf::isValidDigits($d)) {
                        $fail(__('Informe um CPF válido.'));
                    }
                },
                Rule::unique(User::class, 'cpf')->ignore($this->user()->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $cpf = $this->input('cpf');
        if (is_string($cpf)) {
            $this->merge(['cpf' => Cpf::normalizeDigits($cpf)]);
        }
    }
}
