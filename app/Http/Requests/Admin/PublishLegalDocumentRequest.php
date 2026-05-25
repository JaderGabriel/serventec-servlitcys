<?php

namespace App\Http\Requests\Admin;

use App\Models\LegalDocumentVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishLegalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = (string) $this->route('type', LegalDocumentVersion::TYPE_PRIVACY);

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'body_markdown' => ['required', 'string', 'min:20'],
            'version' => ['required', 'string', 'max:32', 'regex:/^[0-9A-Za-z.\-_]+$/'],
            'force_reconsent' => ['sometimes', 'boolean'],
            'document_type' => ['sometimes', Rule::in([
                LegalDocumentVersion::TYPE_PRIVACY,
                LegalDocumentVersion::TYPE_COOKIES,
            ])],
        ];
    }

    public function documentType(): string
    {
        $type = (string) $this->route('type', $this->input('document_type', LegalDocumentVersion::TYPE_PRIVACY));

        return in_array($type, [LegalDocumentVersion::TYPE_PRIVACY, LegalDocumentVersion::TYPE_COOKIES], true)
            ? $type
            : LegalDocumentVersion::TYPE_PRIVACY;
    }

    public function forceReconsent(): bool
    {
        return $this->boolean('force_reconsent', true);
    }
}
