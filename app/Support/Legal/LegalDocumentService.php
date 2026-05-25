<?php

namespace App\Support\Legal;

use App\Models\LegalConsentLog;
use App\Models\LegalDocumentVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class LegalDocumentService
{
    public function __construct(
        private readonly LegalMarkdownRenderer $renderer,
    ) {}

    public function currentPrivacy(): ?LegalDocumentVersion
    {
        return $this->current(LegalDocumentVersion::TYPE_PRIVACY);
    }

    public function currentCookies(): ?LegalDocumentVersion
    {
        return $this->current(LegalDocumentVersion::TYPE_COOKIES);
    }

    public function current(string $documentType): ?LegalDocumentVersion
    {
        return LegalDocumentVersion::query()
            ->currentForType($documentType)
            ->first();
    }

    public function versionForType(string $documentType): string
    {
        $doc = $this->current($documentType);

        if ($doc !== null) {
            return $doc->version;
        }

        return match ($documentType) {
            LegalDocumentVersion::TYPE_COOKIES => LegalConsentService::fallbackCookiesVersion(),
            default => LegalConsentService::fallbackPrivacyVersion(),
        };
    }

    public function privacyHtml(): ?string
    {
        $doc = $this->currentPrivacy();

        return $doc !== null ? $this->renderBody($doc) : null;
    }

    public function cookiesHtml(): ?string
    {
        $doc = $this->currentCookies();

        return $doc !== null ? $this->renderBody($doc) : null;
    }

    public function renderBody(LegalDocumentVersion $document): string
    {
        return $this->renderer->toHtml($document->body_markdown);
    }

    /**
     * @return array{body: string, version: string, changed: bool, current: ?LegalDocumentVersion}
     */
    public function editorState(string $documentType, string $draftBody, ?string $draftVersion): array
    {
        $current = $this->current($documentType);
        $normalized = $this->normalizeBody($draftBody);
        $hash = $this->hashBody($normalized);
        $changed = $current === null || $current->content_hash !== $hash;

        return [
            'body' => $normalized,
            'version' => $draftVersion !== null && $draftVersion !== ''
                ? $this->sanitizeVersion($draftVersion)
                : $this->suggestNextVersion($documentType, $changed),
            'changed' => $changed,
            'current' => $current,
        ];
    }

    public function suggestNextVersion(string $documentType, bool $contentChanged): string
    {
        $today = now()->format('Y-m-d');
        $latest = LegalDocumentVersion::query()
            ->where('document_type', $documentType)
            ->where('version', 'like', $today.'%')
            ->orderByDesc('version')
            ->value('version');

        if ($latest === null) {
            return $today;
        }

        if (! $contentChanged && $this->current($documentType)?->version === $latest) {
            return $latest;
        }

        if (! str_starts_with((string) $latest, $today)) {
            return $today;
        }

        if (preg_match('/^'.preg_quote($today, '/').'\.(\d+)$/', (string) $latest, $m)) {
            return $today.'.'.((int) $m[1] + 1);
        }

        return $today.'.1';
    }

    /**
     * @return array{document: LegalDocumentVersion, reconsent_count: int}
     */
    public function publish(
        User $admin,
        string $documentType,
        string $title,
        string $bodyMarkdown,
        string $version,
        bool $forceReconsent,
    ): array {
        $normalized = $this->normalizeBody($bodyMarkdown);
        $hash = $this->hashBody($normalized);
        $version = $this->sanitizeVersion($version);

        $current = $this->current($documentType);
        if ($current !== null && $current->content_hash === $hash && $current->version === $version) {
            throw new \InvalidArgumentException(__('Não há alteração em relação à versão vigente.'));
        }

        if (LegalDocumentVersion::query()->where('document_type', $documentType)->where('version', $version)->exists()) {
            throw new \InvalidArgumentException(__('Já existe um documento com esta versão.'));
        }

        return DB::transaction(function () use ($admin, $documentType, $title, $normalized, $hash, $version, $forceReconsent): array {
            LegalDocumentVersion::query()
                ->where('document_type', $documentType)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $document = LegalDocumentVersion::query()->create([
                'document_type' => $documentType,
                'version' => $version,
                'title' => trim($title) !== '' ? trim($title) : $this->defaultTitle($documentType),
                'body_markdown' => $normalized,
                'content_hash' => $hash,
                'is_current' => true,
                'published_at' => Carbon::now(),
                'published_by' => $admin->id,
            ]);

            $reconsentCount = 0;
            if ($forceReconsent) {
                $reconsentCount = $this->revokeAllActiveUsers(
                    privacy: $documentType === LegalDocumentVersion::TYPE_PRIVACY,
                    cookies: $documentType === LegalDocumentVersion::TYPE_COOKIES,
                    admin: $admin,
                    source: 'admin_publish_'.$documentType,
                );
            }

            return ['document' => $document, 'reconsent_count' => $reconsentCount];
        });
    }

    public function revokeUser(
        User $target,
        User $admin,
        Request $request,
        bool $privacy = true,
        bool $cookies = true,
    ): void {
        if (! $privacy && ! $cookies) {
            return;
        }

        if ($privacy) {
            $target->privacy_policy_version_accepted = null;
            $target->privacy_policy_accepted_at = null;
        }

        if ($cookies) {
            $target->cookies_consent_version = null;
            $target->cookies_consent_accepted_at = null;
        }

        $target->save();

        LegalConsentLog::query()->create([
            'user_id' => $target->id,
            'consent_type' => match (true) {
                $privacy && $cookies => LegalConsentLog::TYPE_REVOKED_BOTH,
                $privacy => LegalConsentLog::TYPE_REVOKED_PRIVACY,
                default => LegalConsentLog::TYPE_REVOKED_COOKIES,
            },
            'privacy_version' => $privacy ? $this->versionForType(LegalDocumentVersion::TYPE_PRIVACY) : null,
            'cookies_version' => $cookies ? $this->versionForType(LegalDocumentVersion::TYPE_COOKIES) : null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'source' => 'admin_revoke_user',
            'accepted_at' => Carbon::now(),
        ]);
    }

    public function revokeAllActiveUsers(
        bool $privacy,
        bool $cookies,
        User $admin,
        Request|string $source = 'admin_revoke_all',
    ): int {
        if (! $privacy && ! $cookies) {
            return 0;
        }

        $ip = $source instanceof Request ? $source->ip() : null;
        $ua = $source instanceof Request ? mb_substr((string) $source->userAgent(), 0, 500) : null;
        $sourceKey = $source instanceof Request ? 'admin_revoke_all' : (string) $source;

        $users = User::query()->where('is_active', true)->get();
        $count = 0;

        foreach ($users as $user) {
            $hadPrivacy = filled($user->privacy_policy_version_accepted);
            $hadCookies = filled($user->cookies_consent_version);

            if ($privacy) {
                $user->privacy_policy_version_accepted = null;
                $user->privacy_policy_accepted_at = null;
            }
            if ($cookies) {
                $user->cookies_consent_version = null;
                $user->cookies_consent_accepted_at = null;
            }

            if (($privacy && $hadPrivacy) || ($cookies && $hadCookies)) {
                $user->save();
                $count++;

                LegalConsentLog::query()->create([
                    'user_id' => $user->id,
                    'consent_type' => match (true) {
                        $privacy && $cookies => LegalConsentLog::TYPE_REVOKED_BOTH,
                        $privacy => LegalConsentLog::TYPE_REVOKED_PRIVACY,
                        default => LegalConsentLog::TYPE_REVOKED_COOKIES,
                    },
                    'privacy_version' => $privacy ? $this->versionForType(LegalDocumentVersion::TYPE_PRIVACY) : null,
                    'cookies_version' => $cookies ? $this->versionForType(LegalDocumentVersion::TYPE_COOKIES) : null,
                    'ip_address' => $ip,
                    'user_agent' => $ua,
                    'source' => $sourceKey,
                    'accepted_at' => Carbon::now(),
                ]);
            }
        }

        return $count;
    }

    public function defaultBody(string $documentType): string
    {
        $path = match ($documentType) {
            LegalDocumentVersion::TYPE_COOKIES => resource_path('legal/default-cookies.md'),
            default => resource_path('legal/default-privacy.md'),
        };

        if (! File::isFile($path)) {
            return '';
        }

        $body = File::get($path);
        $brand = config('analytics.pdf_report.brand', []);

        return str_replace(
            ['{{app}}', '{{serventec}}', '{{contact_block}}'],
            [
                (string) ($brand['system_name'] ?? config('app.name')),
                (string) ($brand['serventec_name'] ?? 'Serventec Assessoria'),
                $this->defaultContactBlock(),
            ],
            $body
        );
    }

    public function defaultContactBlock(): string
    {
        $email = (string) config('legal.privacy_contact_email', '');
        if ($email !== '') {
            return __('Encarregado / canal de privacidade: :email', ['email' => $email]);
        }

        return __('Para exercer direitos ou esclarecer dúvidas sobre privacidade, contacte a Serventec pelos canais oficiais de atendimento ou o administrador da sua conta na plataforma.');
    }

    /**
     * @return list<LegalDocumentVersion>
     */
    public function history(string $documentType, int $limit = 15): array
    {
        return LegalDocumentVersion::query()
            ->where('document_type', $documentType)
            ->with('publisher:id,name')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function hashBody(string $body): string
    {
        return hash('sha256', $this->normalizeBody($body));
    }

    public function normalizeBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));

        return $body;
    }

    public function sanitizeVersion(string $version): string
    {
        $version = trim($version);
        $version = preg_replace('/[^0-9A-Za-z.\-_]/', '', $version) ?? '';

        return Str::limit($version, 32, '');
    }

    private function defaultTitle(string $documentType): string
    {
        return match ($documentType) {
            LegalDocumentVersion::TYPE_COOKIES => __('Política de cookies essenciais'),
            default => __('Política de privacidade'),
        };
    }
}
