<?php

namespace App\Http\Requests\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Auth\UserCityAccess;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

trait ValidatesManagedUserAttributes
{
    /**
     * @return list<string>
     */
    protected function allowedRoleValues(?User $target = null): array
    {
        /** @var User $actor */
        $actor = $this->user();

        return UserRole::assignableValuesFor($actor->role(), $target?->role());
    }

    /**
     * @return array<string, mixed>
     */
    protected function managedUserAttributeRules(?User $target = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique(User::class, 'username')->ignore($target?->id)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($target?->id)],
            'role' => ['required', 'string', Rule::in($this->allowedRoleValues($target))],
            'city_ids' => ['nullable', 'array'],
            'city_ids.*' => ['integer', 'exists:cities,id'],
        ];

        if ($this->input('role') === UserRole::Municipal->value) {
            $rules['city_ids'] = ['required', 'array', 'min:1'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('role') !== UserRole::Municipal->value) {
                return;
            }

            /** @var User|null $actor */
            $actor = $this->user();
            if ($actor === null) {
                return;
            }

            $rawIds = $this->input('city_ids', []);
            $rawIds = is_array($rawIds) ? array_map('intval', $rawIds) : [];
            $resolved = UserCityAccess::sanitizeCityIdsForActor($actor, $rawIds);

            if ($resolved === []) {
                $validator->errors()->add(
                    'city_ids',
                    __('Seleccione pelo menos um município válido para o perfil Municipal.')
                );

                return;
            }

            if (count(array_diff($rawIds, $resolved)) > 0) {
                $validator->errors()->add(
                    'city_ids',
                    __('Não pode atribuir municípios fora do seu âmbito.')
                );
            }
        });
    }

    public function resolvedRole(): UserRole
    {
        return UserRole::from((string) $this->validated('role'));
    }

    /**
     * @return list<int>
     */
    public function resolvedCityIds(): array
    {
        /** @var User $actor */
        $actor = $this->user();
        $ids = $this->validated('city_ids') ?? [];

        return UserCityAccess::sanitizeCityIdsForActor($actor, is_array($ids) ? $ids : []);
    }
}
