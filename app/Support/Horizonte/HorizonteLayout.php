<?php

namespace App\Support\Horizonte;

use Illuminate\Http\Request;

/** Detecção de dispositivo e preferência de layout Horizonte (desktop vs mão). */
final class HorizonteLayout
{
    public const PREFERENCE_AUTO = 'auto';

    public const PREFERENCE_MOBILE = 'mobile';

    public const PREFERENCE_DESKTOP = 'desktop';

    public const COOKIE_NAME = 'horizonte_layout_preference';

    public static function normalizePreference(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, [self::PREFERENCE_MOBILE, self::PREFERENCE_DESKTOP, self::PREFERENCE_AUTO], true)
            ? $value
            : self::PREFERENCE_AUTO;
    }

    /** mobile | tablet | desktop — indicação inicial antes do JavaScript. */
    public static function deviceHint(Request $request): string
    {
        $ua = strtolower((string) $request->userAgent());

        if ($ua === '') {
            return 'unknown';
        }

        if (preg_match('/ipad|tablet|playbook|silk|(android(?!.*mobile))/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/mobile|iphone|ipod|android|blackberry|iemobile|opera mini|webos/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    public static function initialPreference(Request $request): string
    {
        $fromQuery = self::normalizePreference($request->query('layout'));
        if ($fromQuery !== self::PREFERENCE_AUTO) {
            return $fromQuery;
        }

        return self::normalizePreference($request->cookie(self::COOKIE_NAME));
    }

    public static function suggestsMobileLayout(Request $request): bool
    {
        $hint = self::deviceHint($request);

        return in_array($hint, ['mobile', 'tablet'], true);
    }
}
