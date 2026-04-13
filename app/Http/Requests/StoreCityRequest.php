<?php

namespace App\Http\Requests;

use App\Models\City;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', City::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('uf')) {
            $this->merge(['uf' => strtoupper((string) $this->input('uf'))]);
        }

        $driver = strtolower((string) $this->input('db_driver', City::DRIVER_MYSQL));
        if (! in_array($driver, [City::DRIVER_MYSQL, City::DRIVER_PGSQL], true)) {
            $driver = City::DRIVER_MYSQL;
        }
        $this->merge(['db_driver' => $driver]);

        if ($this->input('db_port') === null || $this->input('db_port') === '') {
            $this->merge(['db_port' => $driver === City::DRIVER_PGSQL ? 5432 : 3306]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);

        if ($this->has('ieducar_schema')) {
            $s = trim((string) $this->input('ieducar_schema'));
            $this->merge(['ieducar_schema' => $s === '' ? null : $s]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cities', 'name')->where(fn ($q) => $q->where('uf', (string) $this->input('uf'))),
            ],
            'uf' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'country' => ['nullable', 'string', 'max:100'],
            'db_driver' => ['required', 'string', Rule::in([City::DRIVER_MYSQL, City::DRIVER_PGSQL])],
            'ieducar_schema' => ['nullable', 'string', 'max:63', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('Já existe uma cidade com este nome neste estado.'),
        ];
    }
}
