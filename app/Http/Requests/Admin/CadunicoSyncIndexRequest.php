<?php

namespace App\Http\Requests\Admin;

use App\Authorization\PublicDataHub;
use Illuminate\Foundation\Http\FormRequest;

class CadunicoSyncIndexRequest extends FormRequest
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
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'cadunico_matrix_from' => ['nullable', 'integer', 'min:2000', 'max:'.$maxYear],
            'cadunico_matrix_to' => ['nullable', 'integer', 'min:2000', 'max:'.$maxYear],
        ];
    }
}
