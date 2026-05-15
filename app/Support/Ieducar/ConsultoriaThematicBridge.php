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
        int $totalMat
    ): array {
        $blocks = [];

        $blocks[] = self::blockInclusao($inclusion, $disc, $totalMat);
        $blocks[] = self::blockEquidade($inclusion, $disc, $totalMat);
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

        $neeCheck = self::findDiscCheck($disc, 'nee_sem_aee');
        if ($neeCheck !== null) {
            $items[] = __('Discrepância alinhada: :t — :n ocorrência(s).', [
                't' => (string) ($neeCheck['title'] ?? ''),
                'n' => number_format((int) ($neeCheck['total'] ?? 0)),
            ]);
            $status = 'danger';
        }

        $sub = self::findDiscCheck($disc, 'nee_subnotificacao');
        if ($sub !== null) {
            $items[] = __('Subnotificação NEE (rotina discrepâncias): :n caso(s) estimado(s).', [
                'n' => number_format((int) ($sub['total'] ?? 0)),
            ]);
            $status = 'danger';
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

        foreach (['sem_raca', 'sem_sexo'] as $cid) {
            $c = self::findDiscCheck($disc, $cid);
            if ($c !== null) {
                $items[] = __(':t: :n ocorrência(s) (:pct% da rede).', [
                    't' => (string) ($c['title'] ?? ''),
                    'n' => number_format((int) ($c['total'] ?? 0)),
                    'pct' => number_format((float) ($c['pct_rede'] ?? 0), 1, ',', '.'),
                ]);
                $status = (string) ($c['severity'] ?? '') === 'danger' ? 'danger' : 'warning';
            }
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

        $criticos = ['escola_sem_inep', 'escola_inativa_matricula', 'matricula_duplicada'];
        foreach ($criticos as $cid) {
            $c = self::findDiscCheck($disc, $cid);
            if ($c !== null) {
                $items[] = __('Crítico — :t: :n ocorrência(s).', [
                    't' => (string) ($c['title'] ?? ''),
                    'n' => number_format((int) ($c['total'] ?? 0)),
                ]);
                $status = 'danger';
            }
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
