<?php

namespace App\Support\Ieducar;

/**
 * Ponte temática entre abas (i-Educar vs. fontes públicas externas) para Diagnóstico e consultoria.
 */
final class ConsultoriaThematicBridge
{
    /**
     * @param  array<string, mixed>  $inclusion
     * @param  array<string, mixed>  $fundeb
     * @param  array<string, mixed>  $performance
     * @param  array<string, mixed>  $disc
     * @return list<array<string, mixed>>
     */
    public static function buildBlocks(
        array $inclusion,
        array $fundeb,
        array $performance,
        array $disc,
        int $totalMat,
        ?array $networkKpis = null,
    ): array {
        $blocks = [];

        $blocks[] = self::blockInclusao($inclusion, $disc, $totalMat);
        $blocks[] = self::blockEquidade($inclusion, $disc, $totalMat);
        $blocks[] = self::blockRedeOferta($disc, $networkKpis);
        $blocks[] = self::blockRecursosPublicos($disc, $fundeb);
        $blocks[] = self::blockIndicadoresExternos($performance);

        return array_values(array_filter($blocks));
    }

    /**
     * @param  array<string, mixed>  $inclusion
     * @param  array<string, mixed>  $disc
     * @return array<string, mixed>
     */
    private static function blockInclusao(array $inclusion, array $disc, int $totalMat): array
    {
        $fonte = 'ieducar';
        $items = [];
        $status = 'success';

        $aee = is_array($inclusion['aee_cross'] ?? null) ? $inclusion['aee_cross'] : null;
        if ($aee !== null) {
            $neeTotal = (int) ($aee['nee_matriculas_total'] ?? 0);
            $emAee = (int) ($aee['matriculas_em_turmas_aee'] ?? 0);
            $semAee = max(0, $neeTotal - $emAee);
            $items[] = __('NEE na base (cruzamento AEE): :n matrícula(s); em turma AEE identificada: :a; sem AEE: :s.', [
                'n' => number_format($neeTotal),
                'a' => number_format($emAee),
                's' => number_format($semAee),
            ]);
            if ($semAee > 0) {
                $status = 'warning';
            }
        }

        $gauges = is_array($inclusion['gauges'] ?? null) ? $inclusion['gauges'] : [];
        if ($gauges !== []) {
            foreach ($gauges as $g) {
                $chart = is_array($g['chart'] ?? null) ? $g['chart'] : [];
                $title = (string) ($chart['title'] ?? '');
                $pct = self::gaugePercentFromChart($chart);
                if ($title !== '' && $pct !== null) {
                    $items[] = __(':titulo: :pct% das matrículas no filtro.', [
                        'titulo' => $title,
                        'pct' => number_format($pct, 1, ',', '.'),
                    ]);
                }
            }
        } elseif ($totalMat > 0) {
            $items[] = __('Medidores de educação especial não disponíveis nesta base (tabelas de deficiência).');
            $status = 'neutral';
        }

        foreach (['nee_sem_aee', 'nee_subnotificacao', 'recurso_prova_sem_nee', 'nee_sem_recurso_prova', 'recurso_prova_incompativel'] as $cid) {
            $item = self::findDiscItem($disc, $cid);
            if ($item === null || ! ($item['has_issue'] ?? false)) {
                continue;
            }
            $items[] = self::formatDiscItemLine($item);
            $status = self::mergeStatus($status, self::itemStatus($item));
        }

        return [
            'id' => 'inclusao',
            'titulo' => __('Educação especial e inclusão'),
            'fonte' => $fonte,
            'fonte_label' => __('Base i-Educar (cadastro / turmas AEE)'),
            'status' => $status,
            'items' => $items,
            'tab_link' => 'inclusion',
        ];
    }

    /**
     * @param  array<string, mixed>  $inclusion
     * @param  array<string, mixed>  $disc
     * @return array<string, mixed>
     */
    private static function blockEquidade(array $inclusion, array $disc, int $totalMat): array
    {
        $items = [];
        $status = 'success';

        if ($totalMat > 0) {
            $items[] = __('Denominador comum (matrículas ativas no filtro): :n.', ['n' => number_format($totalMat)]);
        }

        $eq = (string) ($inclusion['equidade_fonte'] ?? '');
        if ($eq === 'serie') {
            $items[] = __('Gráfico de equidade por série disponível na aba Inclusão (mesmo filtro).');
        }

        foreach (['sem_raca', 'sem_sexo', 'sem_data_nascimento'] as $cid) {
            $item = self::findDiscItem($disc, $cid);
            if ($item === null) {
                continue;
            }
            if (($item['availability'] ?? '') === 'unavailable') {
                $items[] = __(':t — rotina indisponível nesta base.', ['t' => (string) ($item['title'] ?? '')]);
                $status = self::mergeStatus($status, 'neutral');

                continue;
            }
            if (self::isNoDataDimension($item)) {
                $items[] = __(':t — sem dados no filtro para analisar (não equivale a «sem pendência»).', ['t' => (string) ($item['title'] ?? '')]);
                $status = self::mergeStatus($status, 'neutral');

                continue;
            }
            if (! ($item['has_issue'] ?? false)) {
                continue;
            }
            $items[] = self::formatDiscItemLine($item, true);
            $status = self::mergeStatus($status, self::itemStatus($item));
        }

        $racaChart = $inclusion['chart_raca_por_escola_stacked'] ?? null;
        if (is_array($racaChart)) {
            $items[] = __('Distribuição cor/raça por escola na aba Inclusão (i-Educar).');
        }

        return [
            'id' => 'equidade',
            'titulo' => __('Equidade (Censo / VAAR)'),
            'fonte' => 'ieducar',
            'fonte_label' => __('Base i-Educar'),
            'status' => $status,
            'items' => $items,
            'tab_link' => 'inclusion',
        ];
    }

    /**
     * @param  array<string, mixed>  $disc
     * @param  array<string, mixed>|null  $networkKpis
     * @return array<string, mixed>
     */
    private static function blockRedeOferta(array $disc, ?array $networkKpis): array
    {
        $items = [];
        $status = 'success';

        if (is_array($networkKpis)) {
            $cap = (int) ($networkKpis['capacidade_total'] ?? 0);
            $mat = (int) ($networkKpis['matriculas'] ?? 0);
            $vagas = (int) ($networkKpis['vagas_ociosas'] ?? 0);
            $taxa = $networkKpis['taxa_ociosidade_pct'] ?? null;
            if ($cap > 0) {
                $items[] = __('Capacidade nas turmas: :c · Matrículas: :m · Vagas ociosas: :v.', [
                    'c' => number_format($cap),
                    'm' => number_format($mat),
                    'v' => number_format($vagas),
                ]);
            }
            if ($taxa !== null) {
                $items[] = __('Taxa de ociosidade: :p%.', ['p' => number_format((float) $taxa, 1, ',', '.')]);
            }
        } else {
            $items[] = __('Indicadores de vagas por turma não calculados nesta base (capacidade / ligação matrícula↔turma).');
            $status = 'neutral';
        }

        foreach (['rede_vagas_ociosas', 'escola_sem_geo', 'escola_sem_inep', 'escola_inativa_matricula'] as $cid) {
            $item = self::findDiscItem($disc, $cid);
            if ($item === null) {
                continue;
            }
            if (($item['availability'] ?? '') === 'unavailable') {
                continue;
            }
            if (self::isNoDataDimension($item)) {
                $items[] = __(':t — sem dados no filtro para analisar.', ['t' => (string) ($item['title'] ?? '')]);
                $status = self::mergeStatus($status, 'neutral');

                continue;
            }
            if (! ($item['has_issue'] ?? false)) {
                continue;
            }
            $items[] = self::formatDiscItemLine($item, true);
            $status = self::mergeStatus($status, self::itemStatus($item));
        }

        return [
            'id' => 'rede-oferta',
            'titulo' => __('Rede, unidades escolares e oferta'),
            'fonte' => 'ieducar',
            'fonte_label' => __('Base i-Educar (turmas, escolas, capacidade)'),
            'status' => $status,
            'items' => $items,
            'tab_link' => 'network',
        ];
    }

    /**
     * @param  array<string, mixed>  $disc
     * @param  array<string, mixed>  $fundeb
     * @return array<string, mixed>
     */
    private static function blockRecursosPublicos(array $disc, array $fundeb): array
    {
        $items = [];
        $status = 'success';
        $summary = is_array($disc['summary'] ?? null) ? $disc['summary'] : [];

        if (($summary['perda_estimada_anual'] ?? 0) > 0) {
            $items[] = __('Perda estimada indicativa (discrepâncias): :v/ano.', [
                'v' => DiscrepanciesFundingImpact::formatBrl((float) $summary['perda_estimada_anual']),
            ]);
            $status = 'warning';
        }
        if (($summary['ganho_potencial_anual'] ?? 0) > 0) {
            $items[] = __('Ganho potencial após correções: :v/ano.', [
                'v' => DiscrepanciesFundingImpact::formatBrl((float) $summary['ganho_potencial_anual']),
            ]);
        }

        foreach (['escola_sem_inep', 'escola_inativa_matricula', 'matricula_duplicada', 'sem_raca'] as $cid) {
            $item = self::findDiscItem($disc, $cid);
            if ($item === null || ! ($item['has_issue'] ?? false)) {
                continue;
            }
            $items[] = __('Crítico — :line', ['line' => self::formatDiscItemLine($item)]);
            $status = 'danger';
        }

        $mods = is_array($fundeb['modules'] ?? null) ? $fundeb['modules'] : [];
        $alertas = 0;
        foreach ($mods as $m) {
            if (in_array((string) ($m['status'] ?? ''), ['danger', 'warning'], true)) {
                $alertas++;
            }
        }
        if ($alertas > 0) {
            $items[] = __('Roteiro FUNDEB/VAAR: :n módulo(s) com alerta na aba FUNDEB.', ['n' => number_format($alertas)]);
            if ($status !== 'danger') {
                $status = 'warning';
            }
        }

        $items[] = __('Extração oficial: use a secção «Fontes públicas» no Diagnóstico ou FUNDEB (FNDE, Tesouro, Simec, INEP).');

        return [
            'id' => 'recursos',
            'titulo' => __('Recursos públicos (FUNDEB / VAAR / Censo)'),
            'fonte' => 'ieducar',
            'fonte_label' => __('i-Educar + referência FNDE (estimativa configurável)'),
            'status' => $status,
            'items' => $items,
            'tab_link' => 'discrepancies',
        ];
    }

    /**
     * @param  array<string, mixed>  $performance
     * @return array<string, mixed>
     */
    private static function blockIndicadoresExternos(array $performance): array
    {
        $items = [];
        $status = 'neutral';
        $fonte = 'inep_publico';
        $fonteLabel = __('Dados públicos INEP (quando sincronizados no painel)');

        $saeb = is_array($performance['saeb_series'] ?? null) ? $performance['saeb_series'] : [];
        $hasSaeb = ! empty($saeb['summary']) || ! empty($saeb['charts']) || ! empty($saeb['school_table']);
        if ($hasSaeb) {
            $items[] = __('SAEB / série pedagógica disponível na aba Desempenho (fonte pública importada ou modelo pedagógico).');
            $status = 'success';
        } else {
            $items[] = __('SAEB/IDEB: importe dados pedagógicos em Admin → Sincronizações ou consulte o Portal INEP.');
        }

        $inep = $performance['inep_panel'] ?? null;
        if (is_array($inep) && ! empty($inep)) {
            $items[] = __('Painel INEP presente na aba Desempenho.');
        }

        return [
            'id' => 'externos',
            'titulo' => __('Indicadores externos (aprendizagem)'),
            'fonte' => $fonte,
            'fonte_label' => $fonteLabel,
            'status' => $status,
            'items' => $items,
            'tab_link' => 'performance',
        ];
    }

    /**
     * @param  array<string, mixed>  $disc
     * @return ?array<string, mixed>
     */
    private static function findDiscItem(array $disc, string $id): ?array
    {
        foreach ($disc['dimensions'] ?? [] as $d) {
            if (is_array($d) && ($d['id'] ?? '') === $id) {
                return $d;
            }
        }

        return self::findDiscCheck($disc, $id);
    }

    /**
     * @param  array<string, mixed>  $disc
     * @return ?array<string, mixed>
     */
    private static function findDiscCheck(array $disc, string $id): ?array
    {
        foreach ($disc['checks'] ?? [] as $c) {
            if (is_array($c) && ($c['id'] ?? '') === $id) {
                return $c;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function formatDiscItemLine(array $item, bool $withFinance = false): string
    {
        $line = __(':t: :n ocorrência(s)', [
            't' => (string) ($item['title'] ?? ''),
            'n' => number_format((int) ($item['total'] ?? 0)),
        ]);
        $pct = $item['pct_rede'] ?? null;
        if ($pct !== null) {
            $line .= ' ('.number_format((float) $pct, 1, ',', '.').'% '.__('da rede').')';
        }
        if ($withFinance) {
            $perda = (float) ($item['perda_estimada_anual'] ?? $item['ganho_potencial_anual'] ?? 0);
            if ($perda > 0) {
                $line .= ' · '.__('perda est.').' '.DiscrepanciesFundingImpact::formatBrl($perda);
            }
        }

        return $line;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function itemStatus(array $item): string
    {
        $st = (string) ($item['status'] ?? '');
        if (($item['availability'] ?? '') === 'unavailable' || $st === 'unavailable') {
            return 'neutral';
        }
        if (($item['availability'] ?? '') === 'no_data' || $st === 'no_data') {
            return 'neutral';
        }
        if (! ($item['has_issue'] ?? false)) {
            return 'success';
        }

        return match ($st !== '' ? $st : (string) ($item['severity'] ?? 'warning')) {
            'danger' => 'danger',
            default => 'warning',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function isNoDataDimension(array $item): bool
    {
        return ($item['availability'] ?? '') === 'no_data'
            || ($item['status'] ?? '') === 'no_data';
    }

    private static function mergeStatus(string $current, string $next): string
    {
        $order = ['success' => 0, 'neutral' => 1, 'warning' => 2, 'danger' => 3];

        return ($order[$next] ?? 0) > ($order[$current] ?? 0) ? $next : $current;
    }

    /**
     * @param  array<string, mixed>  $chart
     */
    private static function gaugePercentFromChart(array $chart): ?float
    {
        $data = $chart['datasets'][0]['data'] ?? null;
        if (! is_array($data) || ! isset($data[0])) {
            return null;
        }

        return (float) $data[0];
    }
}
