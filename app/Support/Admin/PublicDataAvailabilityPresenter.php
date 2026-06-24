<?php

namespace App\Support\Admin;

/**
 * Metadados de apresentação para o relatório de verificação de fontes oficiais.
 */
final class PublicDataAvailabilityPresenter
{
    private const ACTION_STATUSES = ['new_available', 'attention', 'unreachable', 'not_configured'];

    /**
     * @return array{label: string, level: string, variant: string, icon: string, group: string}
     */
    public static function statusMeta(string $status): array
    {
        return match ($status) {
            'new_available' => [
                'label' => __('Novidade'),
                'level' => 'ok',
                'variant' => 'success',
                'icon' => 'sparkles',
                'group' => 'action',
            ],
            'attention' => [
                'label' => __('Atenção'),
                'level' => 'partial',
                'variant' => 'warning',
                'icon' => 'exclamation-triangle',
                'group' => 'action',
            ],
            'unreachable' => [
                'label' => __('Indisponível'),
                'level' => 'warn',
                'variant' => 'danger',
                'icon' => 'x-circle',
                'group' => 'action',
            ],
            'not_configured' => [
                'label' => __('Não configurado'),
                'level' => 'neutral',
                'variant' => 'info',
                'icon' => 'cog-6-tooth',
                'group' => 'action',
            ],
            'unchanged' => [
                'label' => __('Alinhado'),
                'level' => 'ok',
                'variant' => 'success',
                'icon' => 'check-circle',
                'group' => 'aligned',
            ],
            default => [
                'label' => __('Sem novidade'),
                'level' => 'ok',
                'variant' => 'success',
                'icon' => 'check-circle',
                'group' => 'aligned',
            ],
        };
    }

    /**
     * @param  array{findings?: list<array<string, mixed>>, new_count?: int, attention_count?: int, aligned_count?: int}  $report
     * @return array{new: int, attention: int, aligned: int, action: int, total: int}
     */
    public static function counts(array $report): array
    {
        $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
        $new = 0;
        $attention = 0;
        $aligned = 0;

        foreach ($findings as $finding) {
            $status = (string) ($finding['status'] ?? '');
            if ($status === 'new_available') {
                $new++;
            } elseif (in_array($status, ['attention', 'unreachable', 'not_configured'], true)) {
                $attention++;
            } else {
                $aligned++;
            }
        }

        return [
            'new' => isset($report['new_count']) ? (int) $report['new_count'] : $new,
            'attention' => isset($report['attention_count']) ? (int) $report['attention_count'] : $attention,
            'aligned' => isset($report['aligned_count']) ? (int) $report['aligned_count'] : $aligned,
            'action' => isset($report['action_count'])
                ? (int) $report['action_count']
                : $new + $attention,
            'total' => count($findings),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return array{action: list<array<string, mixed>>, aligned: list<array<string, mixed>>}
     */
    public static function groupFindings(array $findings): array
    {
        $action = [];
        $aligned = [];

        foreach ($findings as $finding) {
            if (! is_array($finding)) {
                continue;
            }
            $group = self::statusMeta((string) ($finding['status'] ?? ''))['group'];
            if ($group === 'action') {
                $action[] = $finding;
            } else {
                $aligned[] = $finding;
            }
        }

        return [
            'action' => $action,
            'aligned' => $aligned,
        ];
    }

    /**
     * @param  array{has_news?: bool, news_count?: int, attention_count?: int, findings?: list<array<string, mixed>>}  $report
     * @return array{
     *     headline: string,
     *     subline: ?string,
     *     variant: string,
     *     news: int,
     *     attention: int,
     *     aligned: int,
     *     ok: int,
     *     total: int
     * }
     */
    public static function summary(array $report): array
    {
        $counts = self::counts($report);
        $subline = null;

        if ($counts['new'] > 0 && $counts['attention'] > 0) {
            $headline = __(':news publicação(ões) nova(s) e :att fonte(s) com atenção.', [
                'news' => $counts['new'],
                'att' => $counts['attention'],
            ]);
            $variant = 'warning';
        } elseif ($counts['new'] > 0) {
            $headline = trans_choice(
                ':n publicação nova detectada na fonte oficial.|:n publicações novas detectadas nas fontes oficiais.',
                max(1, $counts['new']),
                ['n' => $counts['new']],
            );
            $variant = 'warning';
        } elseif ($counts['attention'] > 0) {
            $headline = trans_choice(
                ':n fonte requer atenção (lacuna ou configuração).|:n fontes requerem atenção (lacunas ou configuração).',
                $counts['attention'],
                ['n' => $counts['attention']],
            );
            $variant = 'warning';
        } else {
            $headline = trans_choice(
                'Todas as :n fontes verificadas estão alinhadas com a cobertura local.|Todas as :n fontes verificadas estão alinhadas com a cobertura local.',
                max(1, $counts['aligned']),
                ['n' => max(1, $counts['aligned'])],
            );
            $variant = 'success';
        }

        if ($counts['action'] > 0 && $counts['aligned'] > 0) {
            $subline = trans_choice(
                ':aligned fonte permanece sem alteração.|:aligned fontes permanecem sem alteração.',
                $counts['aligned'],
                ['aligned' => $counts['aligned']],
            );
        }

        return [
            'headline' => $headline,
            'subline' => $subline,
            'variant' => $variant,
            'news' => $counts['new'],
            'attention' => $counts['attention'],
            'aligned' => $counts['aligned'],
            'ok' => $counts['aligned'],
            'total' => $counts['total'],
        ];
    }

    /**
     * @param  array{has_news?: bool, news_count?: int, attention_count?: int, findings?: list<array<string, mixed>>}  $report
     */
    public static function notificationTitle(array $report): string
    {
        $counts = self::counts($report);

        if ($counts['action'] === 0) {
            return __('Dados públicos: verificação diária — tudo alinhado');
        }

        if ($counts['new'] > 0 && $counts['attention'] > 0) {
            return __('Dados públicos: :news novidade(s) e :att atenção(ões)', [
                'news' => $counts['new'],
                'att' => $counts['attention'],
            ]);
        }

        if ($counts['new'] > 0) {
            return trans_choice(
                'Dados públicos: :n publicação nova detectada|Dados públicos: :n publicações novas detectadas',
                max(1, $counts['new']),
                ['n' => $counts['new']],
            );
        }

        return trans_choice(
            'Dados públicos: :n fonte requer atenção|Dados públicos: :n fontes requerem atenção',
            max(1, $counts['attention']),
            ['n' => $counts['attention']],
        );
    }

    /**
     * @param  array{findings?: list<array<string, mixed>>}  $report
     */
    public static function notificationBody(array $report): string
    {
        $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
        $groups = self::groupFindings($findings);
        $counts = self::counts($report);
        $lines = [];

        if ($counts['action'] > 0) {
            $lines[] = trans_choice(
                'Verificação concluída — :action fonte com acção sugerida:|Verificação concluída — :action fontes com acção sugerida:',
                $counts['action'],
                ['action' => $counts['action']],
            );
            if ($counts['aligned'] > 0) {
                $lines[0] .= ' '.trans_choice(
                    ':aligned já alinhada.|:aligned já alinhadas.',
                    $counts['aligned'],
                    ['aligned' => $counts['aligned']],
                );
            }
        } else {
            $lines[] = __('Verificação concluída — nenhuma publicação nova nem lacuna detectada.');
        }

        if ($groups['action'] !== []) {
            $lines[] = '';
            $lines[] = '▶ '.__('REQUER ACÇÃO');
            foreach ($groups['action'] as $finding) {
                $lines[] = '';
                $lines[] = self::notificationFindingBlock($finding, detailed: true);
            }
        }

        if ($groups['aligned'] !== []) {
            $lines[] = '';
            $lines[] = '✓ '.__('SEM ALTERAÇÃO').' ('.count($groups['aligned']).')';
            foreach ($groups['aligned'] as $finding) {
                $lines[] = self::notificationFindingBlock($finding, detailed: false);
            }
        }

        $lines[] = '';
        $lines[] = __('Ver detalhes no hub: :url', [
            'url' => route('admin.public-data.index').'#verificacao-oficial',
        ]);

        return mb_substr(implode("\n", $lines), 0, 3500);
    }

    /**
     * @param  array{has_news?: bool, news_count?: int, attention_count?: int}  $result
     */
    public static function flashMessage(array $result): string
    {
        $counts = self::counts($result);

        if ($counts['action'] === 0) {
            return __('Verificação concluída — todas as fontes alinhadas (:n verificadas).', [
                'n' => $counts['total'],
            ]);
        }

        if ($counts['new'] > 0 && $counts['attention'] > 0) {
            return __('Verificação concluída — :news novidade(s), :att atenção(ões), :aligned alinhada(s).', [
                'news' => $counts['new'],
                'att' => $counts['attention'],
                'aligned' => $counts['aligned'],
            ]);
        }

        if ($counts['new'] > 0) {
            return trans_choice(
                'Verificação concluída — :n publicação nova detectada.|Verificação concluída — :n publicações novas detectadas.',
                max(1, $counts['new']),
                ['n' => $counts['new']],
            );
        }

        return trans_choice(
            'Verificação concluída — :n fonte requer atenção.|Verificação concluída — :n fontes requerem atenção.',
            max(1, $counts['attention']),
            ['n' => $counts['attention']],
        );
    }

    /**
     * @param  array<string, mixed>  $finding
     */
    private static function notificationFindingBlock(array $finding, bool $detailed): string
    {
        $title = (string) ($finding['source_title'] ?? $finding['source_id'] ?? '');
        $status = (string) ($finding['status'] ?? '');
        $badge = self::statusMeta($status)['label'];
        $headline = (string) ($finding['headline'] ?? '');

        if (! $detailed) {
            $compact = $headline !== '' ? $title.' — '.$headline : $title;

            return '  • '.$compact;
        }

        $lines = [
            '• '.$title.' — '.$badge,
            '  '.$headline,
        ];

        if (filled($finding['detail'] ?? null)) {
            $lines[] = '  '.(string) $finding['detail'];
        }

        $routineCli = $finding['routine_cli'] ?? null;
        if (is_string($routineCli) && $routineCli !== '') {
            $lines[] = '  '.__('Rotina: :cmd', ['cmd' => $routineCli]);
        } elseif (filled($finding['routine_label'] ?? null)) {
            $lines[] = '  '.__('Rotina: :label (hub Dados públicos)', [
                'label' => (string) $finding['routine_label'],
            ]);
        }

        return implode("\n", $lines);
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
            $meta = self::statusMeta($status);
            $enriched[] = array_merge($finding, [
                'ui' => $meta,
                'status_group' => $meta['group'],
                'source_anchor' => '#source-'.($finding['source_id'] ?? ''),
            ]);
        }

        $report['findings'] = $enriched;
        $report['summary'] = self::summary($report);
        $report['groups'] = self::groupFindings($enriched);
        $report['counts'] = [
            'new_count' => self::counts($report)['new'],
            'attention_count' => self::counts($report)['attention'],
            'aligned_count' => self::counts($report)['aligned'],
            'action_count' => self::counts($report)['action'],
        ];

        return $report;
    }
}
