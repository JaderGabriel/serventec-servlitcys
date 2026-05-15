<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesManagedUserAttributes;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class StoreUserRequest extends FormRequest
{
    use ValidatesManagedUserAttributes;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge($this->managedUserAttributeRules(), [
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
    }
}
