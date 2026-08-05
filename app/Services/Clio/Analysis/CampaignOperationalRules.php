<?php

namespace App\Services\Clio\Analysis;

/**
 * Regras operacionais Clio (filtros Educacenso) — escolas aptas, jornada e alertas.
 *
 * Congeladas em docs/ROADMAP_CLIO_EXCEL_FILTROS.md (CLI-XLS-00):
 * - Aptas = em atividade ∧ (Municipal ∨ (Privada filantrópica ∧ parceria Municipal))
 * - Parcial &lt; 35 h · Integral ≥ 35 h (ou turno integral)
 * - EJA: alertar CH &lt; 20 h
 * - AC: elegível a proxy de integral se CH ≥ 15 h; alertar se CH &lt; 15 h
 * - PNATE: Sim ∧ escola Urbana ∧ residência Urbana → fora; senão elegível
 */
final class CampaignOperationalRules
{
    public const INTEGRAL_MIN_HOURS = 35.0;

    public const EJA_ALERT_BELOW_HOURS = 20.0;

    public const AC_INTEGRAL_PROXY_MIN_HOURS = 15.0;

    /**
     * Escola apta à coleta operacional (dependência + meta do Acomp).
     * Sem dependência no Acomp: não restringe (compatibilidade com fichas incompletas).
     *
     * @param  array<string, mixed>|null  $meta
     */
    public static function isSchoolApta(?string $dependency, ?array $meta = null): bool
    {
        $meta = is_array($meta) ? $meta : [];
        $location = self::normalizeLocation((string) ($meta['location'] ?? ''));
        if ($location === 'indefinida') {
            return false;
        }

        $dep = self::normalizeToken((string) $dependency);
        if ($dep === '') {
            return true;
        }

        if (self::isMunicipalDependency($dep)) {
            return true;
        }

        if (! self::isPrivateDependency($dep)) {
            return false;
        }

        $category = self::normalizeToken((string) ($meta['private_category'] ?? $meta['categoria_privada'] ?? ''));
        $partnership = self::normalizeToken((string) (
            $meta['partnership_authority']
            ?? $meta['poder_publico_parceria']
            ?? ''
        ));

        return self::isFilantropicaCategory($category)
            && self::isMunicipalPartnership($partnership);
    }

    /**
     * Em atividade e apta (municipal ∪ filantrópica parceira).
     *
     * @param  array<string, mixed>|null  $meta
     */
    public static function isOperationallyEligible(
        ?string $functioningStatus,
        ?string $dependency,
        ?array $meta = null,
    ): bool {
        if (CampaignAnalysisPresenter::isInactiveFunctioning($functioningStatus)) {
            return false;
        }

        return self::isSchoolApta($dependency, $meta);
    }

    public static function isIntegralHours(?float $chHours, string $turnoRaw = ''): bool
    {
        $t = mb_strtolower(trim($turnoRaw));
        if ($t !== '' && preg_match('/integral|tempo\s*integral|estendid|manh[aã].*tarde|tarde.*manh[aã]/u', $t) === 1) {
            return true;
        }

        return $chHours !== null && $chHours >= self::INTEGRAL_MIN_HOURS;
    }

    public static function isPartialHours(?float $chHours, string $turnoRaw = ''): bool
    {
        if (self::isIntegralHours($chHours, $turnoRaw)) {
            return false;
        }

        return $chHours !== null;
    }

    public static function isAcEligibleForIntegralProxy(?float $chHours): bool
    {
        return $chHours !== null && $chHours >= self::AC_INTEGRAL_PROXY_MIN_HOURS;
    }

    public static function isAcBelowFloor(?float $chHours): bool
    {
        return $chHours !== null && $chHours < self::AC_INTEGRAL_PROXY_MIN_HOURS;
    }

    public static function isEjaLowHours(?float $chHours): bool
    {
        return $chHours !== null && $chHours < self::EJA_ALERT_BELOW_HOURS;
    }

    /**
     * PNATE: transporte Sim; exclusão urbano–urbano só com coluna de residência presente.
     *
     * @return 'sem_transporte'|'excluido_urbano_urbano'|'elegivel'
     */
    public static function classifyPnate(
        bool $usesTransport,
        string $schoolLocation,
        ?string $residenceLocation,
        bool $residenceColumnPresent,
    ): string {
        if (! $usesTransport) {
            return 'sem_transporte';
        }

        $school = self::normalizeLocation($schoolLocation);
        if ($residenceColumnPresent) {
            $residence = self::normalizeLocation((string) $residenceLocation);
            if ($school === 'urbana' && $residence === 'urbana') {
                return 'excluido_urbano_urbano';
            }
        }

        return 'elegivel';
    }

    public static function normalizeLocation(string $raw): string
    {
        $s = self::normalizeToken($raw);
        if ($s === '') {
            return '';
        }
        if (str_contains($s, 'rural')) {
            return 'rural';
        }
        if (str_contains($s, 'urban')) {
            return 'urbana';
        }

        return 'indefinida';
    }

    public static function normalizeToken(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        $s = strtr($s, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        return preg_replace('/\s+/', ' ', $s) ?? $s;
    }

    private static function isMunicipalDependency(string $dep): bool
    {
        return str_contains($dep, 'municipal') && ! str_contains($dep, 'privada');
    }

    private static function isPrivateDependency(string $dep): bool
    {
        return str_contains($dep, 'privada') || str_contains($dep, 'particular');
    }

    private static function isFilantropicaCategory(string $category): bool
    {
        return str_contains($category, 'filantrop');
    }

    private static function isMunicipalPartnership(string $partnership): bool
    {
        return str_contains($partnership, 'municipal');
    }
}
