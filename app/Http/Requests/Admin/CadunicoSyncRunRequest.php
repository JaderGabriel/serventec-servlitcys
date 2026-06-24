<?php

namespace App\Http\Requests\Admin;

use App\Authorization\PublicDataHub;
use Illuminate\Foundation\Http\FormRequest;

class CadunicoSyncRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sync', PublicDataHub::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:auto_sync,import_city_year,import_storage_year,import_csv,upload_cecad,upload_territorio,import_all_cities_year,sync_territorio_flow_city,sync_territorio_city,sync_territorio_all',
            'city_id' => 'nullable|integer|exists:cities,id',
            'ano' => 'nullable|integer|min:2000|max:'.((int) date('Y') + 1),
            'csv_file' => 'required_if:action,import_csv,upload_cecad,upload_territorio|file|max:20480',
            'auto_import' => 'sometimes|boolean',
            'all_configured_years' => 'sometimes|boolean',
        ];
    }

    public function action(): string
    {
        return (string) $this->validated('action');
    }

    public function year(): int
    {
        $validated = $this->validated();

        return isset($validated['ano'])
            ? (int) $validated['ano']
            : \App\Services\Cadunico\CadunicoOpenDataImportService::suggestedImportYear();
    }

    public function cityId(): ?int
    {
        $validated = $this->validated();

        return isset($validated['city_id']) ? (int) $validated['city_id'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        return $this->validated();
    }
}
