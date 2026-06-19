<?php

namespace App\Support\Admin;

/**
 * Metadados de apresentação para o relatório de verificação de fontes oficiais.
 */
final class PublicDataAvailabilityPresenter
{
    /**
     * @return array{label: string, level: string, variant: string, icon: string}
     */
    public static function statusMeta(string $status): array
    {
        return match ($status) {
            'new_available' => [
                'label' => __('Novidade'),
                'level' => 'ok',
                'variant' => 'success',
                'icon' => 'sparkles',
            ],
            'attention' => [
                'label' => __('Atenção'),
                'level' => 'partial',
                'variant' => 'warning',
                'icon' => 'exclamation-triangle',
            ],
            'unreachable' => [
                'label' => __('Indisponível'),
                'level' => 'warn',
                'variant' => 'danger',
                'icon' => 'x-circle',
            ],
            'not_configured' => [
                'label' => __('Não configurado'),
                'level' => 'neutral',
                'variant' => 'info',
                'icon' => 'cog-6-tooth',
            ],
            default => [
                'label' => __('Sem novidade'),
                'level' => 'ok',
                'variant' => 'success',
                'icon' => 'check-circle',
            ],
        };
    }

    /**
     * @param  array{has_news?: bool, news_count?: int, findings?: list<array<string, mixed>>}  $report
     * @return array{
     *     headline: string,
     *     variant: string,
     *     news: int,
     *     attention: int,
     *     ok: int,
     *     total: int
     * }
     */
    public static function summary(array $report): array
    {
        $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
        $news = 0;
        $attention = 0;
        $ok = 0;

        foreach ($findings as $finding) {
            $status = (string) ($finding['status'] ?? '');
            if ($status === 'new_available') {
                $news++;
            } elseif (in_array($status, ['attention', 'unreachable', 'not_configured'], true)) {
                $attention++;
            } else {
                $ok++;
            }
        }

        $total = count($findings);
        $hasNews = (bool) ($report['has_news'] ?? false);

        if ($hasNews) {
            $headline = trans_choice(
                ':n fonte com novidade detectada.|:n fontes com novidades detectadas.',
                max(1, (int) ($report['news_count'] ?? $news)),
                ['n' => (int) ($report['news_count'] ?? $news)],
            );
            $variant = 'warning';
        } elseif ($attention > 0) {
            $headline = trans_choice(
                ':n fonte requer atenção (sem novidade automática).|:n fontes requerem atenção (sem novidade automática).',
                $attention,
                ['n' => $attention],
            );
            $variant = 'warning';
        } else {
            $headline = __('Todas as fontes verificadas estão alinhadas com a cobertura local.');
            $variant = 'success';
        }

        return [
            'headline' => $headline,
            'variant' => $variant,
            'news' => $news,
            'attention' => $attention,
            'ok' => $ok,
            'total' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public static function enrichReport(array $report): array
    {
        $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
        $enriched = [];

        foreach ($findings as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            $status = (string) ($finding['status'] ?? '');
            $enriched[] = array_merge($finding, [
                'ui' => self::statusMeta($status),
                'source_anchor' => '#source-'.($finding['source_id'] ?? ''),
            ]);
        }

        $report['findings'] = $enriched;
        $report['summary'] = self::summary($report);

        return $report;
    }
}
