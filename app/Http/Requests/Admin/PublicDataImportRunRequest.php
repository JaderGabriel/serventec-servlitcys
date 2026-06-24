<?php

namespace App\Http\Requests\Admin;

use App\Authorization\PublicDataHub;
use App\Services\Fundeb\FundebImportMode;
use Illuminate\Foundation\Http\FormRequest;

class PublicDataImportRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', PublicDataHub::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxYear = (int) date('Y') + 1;

        return [
            'source_id' => ['required', 'string', 'max:64'],
            'action_key' => ['required', 'string', 'max:64'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'ano' => ['nullable', 'integer', 'min:2000', 'max:'.$maxYear],
            'ano_from' => ['nullable', 'integer', 'min:2000', 'max:'.$maxYear],
            'ano_to' => ['nullable', 'integer', 'min:2000', 'max:'.$maxYear],
            'use_nearest_year' => ['sometimes', 'boolean'],
            'import_mode' => ['sometimes', 'string', 'in:'.FundebImportMode::REPLACE.','.FundebImportMode::UPDATE],
            'include_cached_years' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        return $this->validated();
    }
}
