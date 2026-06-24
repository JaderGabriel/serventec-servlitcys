<?php

namespace App\Http\Requests\Admin;

use App\Authorization\PublicDataHub;
use Illuminate\Foundation\Http\FormRequest;

class PublicDataImportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PublicDataHub::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hub' => ['nullable', 'string', 'max:32'],
        ];
    }
}
