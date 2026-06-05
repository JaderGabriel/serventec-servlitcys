<?php

namespace App\Support\Analytics;

use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Quadros objetivos de referência FUNDEB para o PDF analítico (portaria, complementação, distribuição).
 */
final class AnalyticsReportFundebReferenceTables
{
    /**
     * @param  array<string, mixed>  $vaafProfile  Saída de FundebVaafProfileBuilder
     * @param  array<string, mixed>  $fundeb  Relatório FundebRepository
     * @return array{
     *   portaria_exercicios: array<string, mixed>,
     *   complementacao_eixos: array<string, mixed>,
     *   distribuicao_legal: array<string, mixed>,
     *   cenarios_previsao: array<string, mixed>,
     *   alertas_fnde: array<string, mixed>
     * }
     */
    public function build(array $vaafProfile, array $fundeb): array
    {
        return [
            'portaria_exercicios' => $this->portariaPorExercicio($vaafProfile),
            'complementacao_eixos' => $this->complementacaoPorExercicio($vaafProfile),
            'distribuicao_legal' => $this->distribuicaoLegal($vaafProfile, $fundeb),
            'cenarios_previsao' => $this->cenariosPrevisao($fundeb),
            'alertas_fnde' => $this->alertasResumo($vaafProfile),
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function portariaPorExercicio(array $profile): array
    {
        $years = is_array($profile['years'] ?? null) ? $profile['years'] : [];
        $rows = [];
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

        foreach ($years as $ano => $block) {
            if (! is_array($block)) {
                continue;
            }
            $rec = is_array($block['receita'] ?? null) ? $block['receita'] : [];
            $mat = is_array($block['matriculas'] ?? null) ? $block['matriculas'] : [];
            $est = is_array($block['vaaf_estimado'] ?? null) ? $block['vaaf_estimado'] : [];
            $prev = is_array($block['previsao_recursos'] ?? null) ? $block['previsao_recursos'] : [];

            $rows[] = [
                (string) ($block['label'] ?? $ano),
                $rec['disponivel'] ?? false
                    ? $fmt((float) ($rec['total'] ?? 0))
                    : '—',
                isset($rec['complementacao_vaaf']) ? $fmt((float) $rec['complementacao_vaaf']) : '—',
                isset($rec['complementacao_vaat']) ? $fmt((float) $rec['complementacao_vaat']) : '—',
                isset($rec['complementacao_vaar']) ? $fmt((float) $rec['complementacao_vaar']) : '—',
                number_format((int) ($mat['usado'] ?? 0), 0, ',', '.'),
                (string) ($mat['fonte_usada'] ?? '—'),
                isset($est['valor']) ? $fmt((float) $est['valor']) : '—',
                isset($prev['base_anual']) ? $fmt((float) $prev['base_anual']) : '—',
                filled($rec['ano_publicacao'] ?? null) ? (string) $rec['ano_publicacao'] : '—',
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a[0], (string) $b[0]));

        return [
            'available' => $rows !== [],
            'title' => __('Quadro de referência — Receita e complementações (Portaria FNDE / CSV oficial)'),
            'subtitle' => __('Valores por exercício conforme anexo «Receita total do Fundeb por ente federado». VAAF estimado = receita ÷ matrículas (i-Educar ou Censo INEP).'),
            'headers' => [
                __('Exercício'),
                __('Receita total'),
                __('Compl. VAAF'),
                __('Compl. VAAT'),
                __('Compl. VAAR'),
                __('Matrículas'),
                __('Fonte mat.'),
                __('VAAF est.'),
                __('Projeção indicativa'),
                __('Publ. FNDE'),
            ],
            'rows' => $rows,
            'note' => __('Fonte primária: CSV Portaria FNDE (gov.br). Complementações são valores previstos de complementação da União por eixo, distintos do VAAF municipal.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function complementacaoPorExercicio(array $profile): array
    {
        $years = is_array($profile['years'] ?? null) ? $profile['years'] : [];
        $rows = [];
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

        foreach ($years as $ano => $block) {
            if (! is_array($block)) {
                continue;
            }
            $rec = is_array($block['receita'] ?? null) ? $block['receita'] : [];
            $db = is_array($block['db_reference'] ?? null) ? $block['db_reference'] : [];
            $est = is_array($block['vaaf_estimado'] ?? null) ? $block['vaaf_estimado'] : [];
            $dbVaat = $db['vaat'] ?? null;
            $dbVaar = $db['complementacao_vaar'] ?? null;

            $rows[] = [
                (string) $ano,
                isset($rec['complementacao_vaaf']) ? $fmt((float) $rec['complementacao_vaaf']) : '—',
                isset($rec['complementacao_vaat']) ? $fmt((float) $rec['complementacao_vaat']) : '—',
                isset($rec['complementacao_vaar']) ? $fmt((float) $rec['complementacao_vaar']) : '—',
                $dbVaat !== null ? $fmt((float) $dbVaat) : '—',
                $dbVaar !== null ? $fmt((float) $dbVaar) : '—',
                isset($est['valor']) ? $fmt((float) $est['valor']) : '—',
                (string) ($db['fonte'] ?? ($est['fonte'] ?? 'portaria_fnde')),
            ];
        }

        sort($rows);

        return [
            'available' => $rows !== [],
            'title' => __('Quadro de referência — Eixos de complementação (VAAF · VAAT · VAAR)'),
            'subtitle' => __('Colunas «Portaria» vêm do CSV FNDE; «BD» da importação municipal (fundeb_municipio_references). VAAR na BD pode ser valor em R$ importado do anexo.'),
            'headers' => [
                __('Exercício'),
                __('Compl. VAAF (portaria)'),
                __('Compl. VAAT (portaria)'),
                __('Compl. VAAR (portaria)'),
                __('VAAT (BD)'),
                __('VAAR (BD)'),
                __('VAAF est.'),
                __('Fonte VAAF'),
            ],
            'rows' => $rows,
            'note' => __('VAAT municipal oficial depende de habilitação do ente na portaria do exercício. Consulte Simec/VAAR para comprovação de condicionalidades.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $fundeb
     * @return array<string, mixed>
     */
    private function distribuicaoLegal(array $profile, array $fundeb): array
    {
        $proj = is_array($fundeb['resource_projection'] ?? null) ? $fundeb['resource_projection'] : [];
        $dist = is_array($proj['distribuicao_legal'] ?? null)
            ? $proj['distribuicao_legal']
            : (is_array($profile['distribuicao_legal'] ?? null) ? $profile['distribuicao_legal'] : []);

        $itens = is_array($dist['itens'] ?? null) ? $dist['itens'] : [];
        $rows = [];
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

        foreach ($itens as $item) {
            if (! is_array($item)) {
                continue;
            }
            $rows[] = [
                (string) ($item['titulo'] ?? ''),
                isset($item['percentual']) ? number_format((float) $item['percentual'], 0, ',', '.').'%' : '—',
                isset($item['valor']) ? $fmt((float) $item['valor']) : (isset($item['valor_planejado']) ? $fmt((float) $item['valor_planejado']) : '—'),
                (string) ($item['descricao'] ?? ''),
            ];
        }

        return [
            'available' => $rows !== [],
            'title' => __('Quadro de referência — Distribuição legal mínima (Lei 14.113/2020)'),
            'subtitle' => (string) ($dist['referencia_legal'] ?? __('Pisos de aplicação sobre a projeção indicativa do exercício do filtro.')),
            'headers' => [__('Destino'), __('% mín./máx.'), __('Valor planejado (R$)'), __('Descrição')],
            'rows' => $rows,
            'note' => (string) ($dist['nota'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $fundeb
     * @return array<string, mixed>
     */
    private function cenariosPrevisao(array $fundeb): array
    {
        $proj = is_array($fundeb['resource_projection'] ?? null) ? $fundeb['resource_projection'] : [];
        $totais = is_array($proj['totais'] ?? null) ? $proj['totais'] : [];
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

        if ($totais === []) {
            return ['available' => false, 'title' => '', 'headers' => [], 'rows' => [], 'note' => ''];
        }

        $rows = [
            [__('Projeção indicativa (matrículas × índice)'), isset($totais['fundeb_base_anual']) ? $fmt((float) $totais['fundeb_base_anual']) : '—'],
            [__('Complementação VAAR (indicativa/importada)'), isset($totais['complementacao_vaar']) ? $fmt((float) $totais['complementacao_vaar']) : '—'],
            [__('Total com complementação'), isset($totais['total_com_complemento']) ? $fmt((float) $totais['total_com_complemento']) : '—'],
            [__('Cenário com risco (cadastro)'), isset($totais['previsao_cenario_risco']) ? $fmt((float) $totais['previsao_cenario_risco']) : '—'],
            [__('Ganho potencial (correções)'), isset($totais['ganho_potencial_anual']) ? $fmt((float) $totais['ganho_potencial_anual']) : '—'],
            [__('Cenário após correções'), isset($totais['previsao_cenario_corrigido']) ? $fmt((float) $totais['previsao_cenario_corrigido']) : '—'],
        ];

        if (isset($totais['fundeb_base_previa_anual']) && $totais['fundeb_base_previa_anual'] !== null) {
            $rows[] = [__('fundeb.semantics.piso_federal_label'), $fmt((float) $totais['fundeb_base_previa_anual'])];
        }

        return [
            'available' => true,
            'title' => __('Quadro de referência — Cenários de projeção indicativa (exercício do filtro)'),
            'subtitle' => (string) ($proj['formula_base'] ?? ''),
            'headers' => [__('Indicador'), __('Valor (R$)')],
            'rows' => $rows,
            'note' => (string) ($proj['aviso'] ?? config('ieducar.fundeb.aviso_previsao', '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function alertasResumo(array $profile): array
    {
        $alerts = is_array($profile['alerts'] ?? null) ? $profile['alerts'] : [];
        $rows = [];

        foreach (array_slice($alerts, 0, 12) as $alert) {
            if (! is_array($alert)) {
                continue;
            }
            $sev = match ($alert['severity'] ?? 'info') {
                'danger' => __('Crítico'),
                'warning' => __('Atenção'),
                default => __('Info'),
            };
            $rows[] = [
                $sev,
                filled($alert['ano'] ?? null) ? (string) $alert['ano'] : '—',
                (string) ($alert['titulo'] ?? ''),
                mb_substr((string) ($alert['mensagem'] ?? ''), 0, 180),
            ];
        }

        return [
            'available' => $rows !== [],
            'title' => __('Alertas — qualidade e publicação FNDE'),
            'subtitle' => __('Inconsistências detectadas automaticamente (defasagem de portaria, matrículas ausentes, placeholder na base, etc.).'),
            'headers' => [__('Grau'), __('Ano'), __('Alerta'), __('Detalhe')],
            'rows' => $rows,
            'note' => __('Corrija alertas críticos antes de usar valores em planejamento orçamentário ou metas de VAAR.'),
        ];
    }

    /**
     * Converte quadros para formato tables[] do ATM scope.
     *
     * @param  array<string, mixed>  $refBundle
     * @return list<array{title: string, headers: list<string>, rows: list<list<string>>}>
     */
    public function asScopeTables(array $refBundle): array
    {
        $out = [];
        foreach (['portaria_exercicios', 'complementacao_eixos', 'cenarios_previsao', 'distribuicao_legal', 'alertas_fnde'] as $key) {
            $block = $refBundle[$key] ?? null;
            if (! is_array($block) || ! ($block['available'] ?? false)) {
                continue;
            }
            $out[] = [
                'title' => (string) ($block['title'] ?? ''),
                'headers' => is_array($block['headers'] ?? null) ? $block['headers'] : [],
                'rows' => is_array($block['rows'] ?? null) ? $block['rows'] : [],
            ];
        }

        return $out;
    }
}
