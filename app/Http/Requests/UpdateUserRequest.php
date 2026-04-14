<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');
        if (! $target instanceof User) {
            return false;
        }

        return (bool) $this->user()?->can('update', $target);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique(User::class, 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'is_admin' => ['boolean'],
            'is_active' => ['boolean'],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ];
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

        $this->merge([
            'is_admin' => $this->boolean('is_admin'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
