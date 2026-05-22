<?php

namespace App\Support\Analytics;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * QR code em data-URI para embutir no PDF (DomPDF) e na página pública.
 */
final class AnalyticsReportQrCodeBuilder
{
    public static function forUrl(string $url, int $size = 180): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $pixels = max(80, min(400, $size));
        $api = 'https://api.qrserver.com/v1/create-qr-code/?'.http_build_query([
            'size' => $pixels.'x'.$pixels,
            'data' => $url,
            'format' => 'png',
            'margin' => 2,
        ]);

        try {
            $response = Http::timeout(8)->get($api);
            if (! $response->successful()) {
                return self::placeholderSvgDataUri($url);
            }

            $body = $response->body();
            if ($body === '') {
                return self::placeholderSvgDataUri($url);
            }

            return 'data:image/png;base64,'.base64_encode($body);
        } catch (\Throwable $e) {
            Log::debug('analytics.report.qr_failed', ['message' => $e->getMessage()]);

            return self::placeholderSvgDataUri($url);
        }
    }

    private static function placeholderSvgDataUri(string $url): string
    {
        $safe = htmlspecialchars(mb_substr($url, 0, 120), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="180" viewBox="0 0 180 180">'
            .'<rect width="180" height="180" fill="#f1f5f9" stroke="#94a3b8"/>'
            .'<text x="90" y="88" text-anchor="middle" font-size="11" fill="#475569">QR</text>'
            .'<text x="90" y="108" text-anchor="middle" font-size="7" fill="#64748b">'.$safe.'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
