<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesManagedUserAttributes;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class UpdateUserRequest extends FormRequest
{
    use ValidatesManagedUserAttributes;

    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        $rules = array_merge($this->managedUserAttributeRules($target), [
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        if ($this->user()?->isAdmin()) {
            $rules['is_active'] = ['boolean'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $pwd = $this->input('password');
        if ($pwd === null || (is_string($pwd) && trim($pwd) === '')) {
            $this->merge([
                'password' => null,
                'password_confirmation' => null,
            ]);
        }

        if ($this->user()?->isAdmin()) {
            $this->merge([
                'is_active' => $this->boolean('is_active'),
            ]);
        }
    }
}
