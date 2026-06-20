<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PublishLegalDocumentRequest;
use App\Models\LegalDocumentVersion;
use App\Support\Legal\LegalDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LegalDocumentAdminController extends Controller
{
    public function index(LegalDocumentService $documents): View
    {
        return view('admin.legal-documents.index', [
            'privacy' => $documents->currentPrivacy(),
            'cookies' => $documents->currentCookies(),
            'privacyHistory' => $documents->history(LegalDocumentVersion::TYPE_PRIVACY, 10),
            'cookiesHistory' => $documents->history(LegalDocumentVersion::TYPE_COOKIES, 10),
            'privacyVersionConfig' => LegalDocumentVersion::query()->where('document_type', LegalDocumentVersion::TYPE_PRIVACY)->exists()
                ? null
                : config('legal.privacy_version'),
        ]);
    }

    public function edit(string $type, LegalDocumentService $documents): View
    {
        $documentType = $this->resolveType($type);
        $current = $documents->current($documentType);

        $body = old('body_markdown', $current?->body_markdown ?? $documents->defaultBody($documentType));
        $version = old('version', $current?->version ?? $documents->suggestNextVersion($documentType, true));
        $title = old('title', $current?->title ?? ($documentType === LegalDocumentVersion::TYPE_COOKIES
            ? __('Política de cookies essenciais')
            : __('Política de privacidade')));

        $preview = $documents->renderBody(new LegalDocumentVersion([
            'body_markdown' => $body,
        ]));

        return view('admin.legal-documents.edit', [
            'documentType' => $documentType,
            'typeLabel' => $this->typeLabel($documentType),
            'current' => $current,
            'title' => $title,
            'bodyMarkdown' => $body,
            'suggestedVersion' => $documents->suggestNextVersion(
                $documentType,
                $current === null || $documents->hashBody($body) !== $current->content_hash
            ),
            'version' => $version,
            'previewHtml' => $preview,
            'history' => $documents->history($documentType, 12),
            'contentChanged' => $current === null || $documents->hashBody($body) !== $current->content_hash,
        ]);
    }

    public function publish(
        PublishLegalDocumentRequest $request,
        string $type,
        LegalDocumentService $documents,
    ): RedirectResponse {
        $documentType = $this->resolveType($type);

        try {
            $result = $documents->publish(
                $request->user(),
                $documentType,
                (string) $request->input('title', ''),
                (string) $request->input('body_markdown'),
                (string) $request->input('version'),
                $request->forceReconsent(),
            );
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        $message = __('Versão :v publicada.', ['v' => $result['document']->version]);
        if ($result['reconsent_count'] > 0) {
            $message .= ' '.__(
                ':n usuário(s) terão de aceitar novamente na próxima visita.',
                ['n' => $result['reconsent_count']]
            );
        }

        return redirect()
            ->route('admin.legal-documents.edit', ['type' => $documentType === LegalDocumentVersion::TYPE_COOKIES ? 'cookies' : 'privacy'])
            ->with('status', $message);
    }

    private function resolveType(string $type): string
    {
        return $type === 'cookies'
            ? LegalDocumentVersion::TYPE_COOKIES
            : LegalDocumentVersion::TYPE_PRIVACY;
    }

    private function typeLabel(string $documentType): string
    {
        return $documentType === LegalDocumentVersion::TYPE_COOKIES
            ? __('Política de cookies')
            : __('Política de privacidade');
    }
}
