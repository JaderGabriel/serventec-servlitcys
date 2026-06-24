<?php

namespace App\Http\Requests;

use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Foundation\Http\FormRequest;

class AnalyticsFilterRequest extends FormRequest
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
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'ano_letivo' => ['nullable', 'string', 'max:8'],
            'escola_id' => ['nullable', 'string', 'max:32'],
            'curso_id' => ['nullable', 'string', 'max:32'],
            'turno_id' => ['nullable', 'string', 'max:32'],
            'inclusion_scope' => ['nullable', 'string', 'in:nee,inconsistencias,all'],
            'inclusion_somente_nee' => ['sometimes', 'boolean'],
            'inclusion_somente_inconsistencias' => ['sometimes', 'boolean'],
            'tab' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function filters(): IeducarFilterState
    {
        return IeducarFilterState::fromRequest($this);
    }
}
