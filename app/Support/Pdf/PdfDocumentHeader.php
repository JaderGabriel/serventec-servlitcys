<?php

namespace App\Support\Pdf;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Metadados do cabeçalho fixo dos PDFs municipais
 * (cidade-UF, IBGE, data de referência, emissão).
 */
final class PdfDocumentHeader
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *   city_uf: string,
     *   ibge: string,
     *   reference: string,
     *   emission: string
     * }
     */
    public static function resolve(array $context): array
    {
        $override = is_array($context['pdf_header'] ?? null) ? $context['pdf_header'] : [];

        $city = self::string($override['city'] ?? null)
            ?? self::string(data_get($context, 'campaign.municipality_name'))
            ?? self::string(data_get($context, 'cover.municipality'))
            ?? self::string(data_get($context, 'data.city_name'))
            ?? '—';

        $uf = self::string($override['uf'] ?? null)
            ?? self::string(data_get($context, 'campaign.uf'))
            ?? self::string(data_get($context, 'cover.uf'))
            ?? self::string(data_get($context, 'data.uf'))
            ?? '';

        $ibge = self::digits($override['ibge'] ?? null)
            ?? self::digits(data_get($context, 'campaign.ibge_municipio'))
            ?? self::digits(data_get($context, 'campaign.city.ibge_municipio'))
            ?? self::digits(data_get($context, 'cover.ibge'))
            ?? self::digits(data_get($context, 'data.ibge'))
            ?? '—';

        $reference = self::string($override['reference'] ?? null)
            ?? self::formatDate(
                data_get($context, 'coverage.reference_date')
                    ?? data_get($context, 'campaign.reference_date')
                    ?? data_get($context, 'data.reference_date')
            )
            ?? self::yearAsReference(
                data_get($context, 'cover.year_value')
                    ?? data_get($context, 'data.year_label')
                    ?? data_get($context, 'data.base_year')
            )
            ?? '—';

        $emission = self::string($override['emission'] ?? null)
            ?? self::string($context['generated_at'] ?? null)
            ?? now()->timezone(config('app.timezone'))->format('d/m/Y H:i');

        $cityUf = $uf !== '' ? $city.'-'.$uf : $city;

        return [
            'city_uf' => $cityUf,
            'ibge' => $ibge !== '' ? $ibge : '—',
            'reference' => $reference,
            'emission' => $emission,
        ];
    }

    private static function string(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('d/m/Y');
        }
        if (! is_scalar($value)) {
            return null;
        }
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private static function digits(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $digits !== '' ? $digits : null;
    }

    private static function formatDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('d/m/Y');
        }
        if (! is_scalar($value)) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) === 1) {
            try {
                return Carbon::parse($raw)->format('d/m/Y');
            } catch (\Throwable) {
                return $raw;
            }
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $raw) === 1) {
            return substr($raw, 0, 10);
        }

        return $raw;
    }

    private static function yearAsReference(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '' || $text === '—') {
            return null;
        }
        if (preg_match('/^\d{4}$/', $text) === 1) {
            return __('Ano :y', ['y' => $text]);
        }

        return $text;
    }
}
