<?php

namespace App\Support\Http;

/**
 * Resumo legível do User-Agent para listagens administrativas.
 */
final class UserAgentSummary
{
    public function short(?string $userAgent): ?string
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return null;
        }

        $browser = $this->browser($ua);
        $os = $this->operatingSystem($ua);

        if ($browser !== null && $os !== null) {
            return $browser.' · '.$os;
        }

        return $browser ?? $os ?? mb_substr($ua, 0, 72).(mb_strlen($ua) > 72 ? '…' : '');
    }

    private function browser(string $ua): ?string
    {
        if (preg_match('/Edg(?:A|iOS)?\/([\d.]+)/i', $ua, $m) === 1) {
            return 'Microsoft Edge '.(int) $m[1];
        }
        if (preg_match('/OPR\/([\d.]+)/i', $ua, $m) === 1 || preg_match('/Opera\/([\d.]+)/i', $ua, $m) === 1) {
            return 'Opera '.(int) $m[1];
        }
        if (preg_match('/Firefox\/([\d.]+)/i', $ua, $m) === 1) {
            return 'Firefox '.(int) $m[1];
        }
        if (preg_match('/Chrome\/([\d.]+)/i', $ua, $m) === 1) {
            return 'Chrome '.(int) $m[1];
        }
        if (preg_match('/Version\/([\d.]+).*Safari/i', $ua, $m) === 1) {
            return 'Safari '.(int) $m[1];
        }

        return null;
    }

    private function operatingSystem(string $ua): ?string
    {
        if (stripos($ua, 'Android') !== false) {
            return 'Android';
        }
        if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            return 'iOS';
        }
        if (preg_match('/Windows NT 10/i', $ua) === 1) {
            return 'Windows';
        }
        if (preg_match('/Windows NT/i', $ua) === 1) {
            return 'Windows';
        }
        if (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) {
            return 'macOS';
        }
        if (stripos($ua, 'Linux') !== false) {
            return 'Linux';
        }

        return null;
    }
}
