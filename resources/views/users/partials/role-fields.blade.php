@props([
    'creatableRoles' => [],
    'assignableCities' => collect(),
    'selectedRole' => null,
    'selectedCityIds' => [],
    'actor',
    'showActiveToggle' => false,
    'isActive' => true,
])

@php
    use App\Enums\UserRole;
    $roleValue = old('role', $selectedRole instanceof UserRole ? $selectedRole->value : (string) $selectedRole);
    $cityIds = old('city_ids', $selectedCityIds);
@endphp

<div x-data="{ role: @js($roleValue) }" class="space-y-4">
    <div>
        <x-input-label for="role" :value="__('Perfil')" />
        <select id="role" name="role" x-model="role" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
            @foreach ($creatableRoles as $r)
                <option value="{{ $r->value }}">{{ $r->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('role')" class="mt-2" />
    </div>

    @if ($assignableCities->isNotEmpty())
        <div x-show="role === '{{ UserRole::Municipal->value }}'" x-cloak class="space-y-2">
            <x-input-label :value="__('Municípios vinculados')" />
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('O perfil Municipal só vê análises dos municípios seleccionados.') }}</p>
            <div class="max-h-48 overflow-y-auto rounded-md border border-gray-200 dark:border-gray-600 p-3 space-y-2">
                @foreach ($assignableCities as $city)
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="city_ids[]" value="{{ $city->id }}" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600"
                            @checked(in_array($city->id, $cityIds, false))>
                        <span>{{ $city->name }}@if (filled($city->uf)) ({{ $city->uf }})@endif</span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('city_ids')" class="mt-2" />
            <x-input-error :messages="$errors->get('city_ids.*')" class="mt-2" />
        </div>
    @endif

    @if ($showActiveToggle && $actor->isAdmin())
        <div class="flex items-center gap-2">
            <input id="is_active" type="checkbox" name="is_active" value="1" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500"
                {{ old('is_active', $isActive) ? 'checked' : '' }}>
            <x-input-label for="is_active" :value="__('Conta ativa')" class="!mb-0" />
        </div>
        <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
    @endif
</div>
