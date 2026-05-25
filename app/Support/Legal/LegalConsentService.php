<?php

namespace App\Support\Legal;

use App\Models\LegalConsentLog;
use App\Models\LegalDocumentVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class LegalConsentService
{
    public static function currentPrivacyVersion(): string
    {
        return app(LegalDocumentService::class)->versionForType(LegalDocumentVersion::TYPE_PRIVACY);
    }

    public static function currentCookiesVersion(): string
    {
        return app(LegalDocumentService::class)->versionForType(LegalDocumentVersion::TYPE_COOKIES);
    }

    public static function fallbackPrivacyVersion(): string
    {
        return (string) config('legal.privacy_version', '2026-05-25');
    }

    public static function fallbackCookiesVersion(): string
    {
        return (string) config('legal.cookies_version', '2026-05-25');
    }

    public static function consentRequiredForUser(?User $user): bool
    {
        if (! filter_var(config('legal.require_authenticated_consent', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return $user !== null && $user->is_active;
    }

    public static function userNeedsConsent(User $user): bool
    {
        if (! self::consentRequiredForUser($user)) {
            return false;
        }

        $privacy = self::currentPrivacyVersion();
        $cookies = self::currentCookiesVersion();

        return $user->privacy_policy_version_accepted !== $privacy
            || $user->cookies_consent_version !== $cookies;
    }

    /**
     * @return array{
     *   privacy_version: string,
     *   cookies_version: string,
     *   privacy_accepted: bool,
     *   cookies_accepted: bool,
     *   needs_consent: bool
     * }
     */
    public static function statusForUser(?User $user): array
    {
        $privacy = self::currentPrivacyVersion();
        $cookies = self::currentCookiesVersion();

        if ($user === null) {
            return [
                'privacy_version' => $privacy,
                'cookies_version' => $cookies,
                'privacy_accepted' => false,
                'cookies_accepted' => false,
                'needs_consent' => true,
            ];
        }

        $privacyOk = $user->privacy_policy_version_accepted === $privacy;
        $cookiesOk = $user->cookies_consent_version === $cookies;

        return [
            'privacy_version' => $privacy,
            'cookies_version' => $cookies,
            'privacy_accepted' => $privacyOk,
            'cookies_accepted' => $cookiesOk,
            'needs_consent' => self::userNeedsConsent($user),
        ];
    }

    public function recordAcceptance(
        User $user,
        Request $request,
        string $source = 'consent_page',
        bool $privacy = true,
        bool $cookies = true,
    ): void {
        $privacyVersion = self::currentPrivacyVersion();
        $cookiesVersion = self::currentCookiesVersion();
        $now = Carbon::now();

        if ($privacy) {
            $user->privacy_policy_version_accepted = $privacyVersion;
            $user->privacy_policy_accepted_at = $now;
        }

        if ($cookies) {
            $user->cookies_consent_version = $cookiesVersion;
            $user->cookies_consent_accepted_at = $now;
        }

        $user->save();

        $type = match (true) {
            $privacy && $cookies => LegalConsentLog::TYPE_BOTH,
            $privacy => LegalConsentLog::TYPE_PRIVACY,
            default => LegalConsentLog::TYPE_COOKIES,
        };

        LegalConsentLog::query()->create([
            'user_id' => $user->id,
            'consent_type' => $type,
            'privacy_version' => $privacy ? $privacyVersion : null,
            'cookies_version' => $cookies ? $cookiesVersion : null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'source' => $source,
            'accepted_at' => $now,
        ]);
    }

    /**
     * @return array{pending_privacy: int, pending_cookies: int, pending_any: int, total_users: int}
     */
    public function adminSummary(): array
    {
        $privacy = self::currentPrivacyVersion();
        $cookies = self::currentCookiesVersion();

        $active = User::query()->where('is_active', true);
        $total = (clone $active)->count();

        $pendingPrivacy = (clone $active)
            ->where(function ($q) use ($privacy): void {
                $q->whereNull('privacy_policy_version_accepted')
                    ->orWhere('privacy_policy_version_accepted', '!=', $privacy);
            })
            ->count();

        $pendingCookies = (clone $active)
            ->where(function ($q) use ($cookies): void {
                $q->whereNull('cookies_consent_version')
                    ->orWhere('cookies_consent_version', '!=', $cookies);
            })
            ->count();

        $pendingAny = (clone $active)
            ->where(function ($q) use ($privacy, $cookies): void {
                $q->whereNull('privacy_policy_version_accepted')
                    ->orWhere('privacy_policy_version_accepted', '!=', $privacy)
                    ->orWhereNull('cookies_consent_version')
                    ->orWhere('cookies_consent_version', '!=', $cookies);
            })
            ->count();

        return [
            'pending_privacy' => $pendingPrivacy,
            'pending_cookies' => $pendingCookies,
            'pending_any' => $pendingAny,
            'total_users' => $total,
        ];
    }
}
