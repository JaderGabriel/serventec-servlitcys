<?php

namespace App\Support\Analytics;

use App\Models\City;
use App\Support\Dashboard\AnalyticsTabCatalog;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Conteúdo executivo da capa do PDF (Prefeitura / Secretaria de Educação).
 */
final class AnalyticsReportCoverPresentation
{
    /**
     * @param  array<string, mixed>  $cover
     * @param  array<string, mixed>  $health
     * @param  array<string, mixed>  $overview
     * @param  array<string, mixed>  $disc
     * @return array<string, mixed>
     */
    public static function enrich(
        array $cover,
        City $city,
        ?IeducarFilterState $filters,
        array $health = [],
        array $overview = [],
        array $disc = [],
    ): array {
        $kpis = is_array($overview['kpis'] ?? null) ? $overview['kpis'] : [];
        $discSummary = is_array($disc['summary'] ?? null) ? $disc['summary'] : [];
        $healthSummary = is_array($health['summary'] ?? null) ? $health['summary'] : [];

        $matriculas = (int) ($kpis['matriculas'] ?? $healthSummary['total_matriculas'] ?? $disc['total_matriculas'] ?? 0);
        $escolas = (int) ($kpis['escolas'] ?? 0);
        $turmas = (int) ($kpis['turmas'] ?? 0);

        $score = $health['compliance_score'] ?? null;
        $score = is_numeric($score) ? (int) $score : null;

        $perda = (float) ($discSummary['perda_estimada_anual'] ?? $healthSummary['perda_estimada_anual'] ?? 0);
        $ganho = (float) ($discSummary['ganho_potencial_anual'] ?? $healthSummary['ganho_potencial_anual'] ?? 0);

        $cover['report_title'] = __('A educação no município de');
        $cover['report_title_municipality_upper'] = mb_strtoupper(trim((string) $city->name), 'UTF-8');
        $cover['report_subtitle'] = __('Relatório educacional municipal · modelo integrado SERVLITCYS');
        $cover['version_line'] = __('Versão gerada em :date', ['date' => now()->format('d.m.Y')]);
        $cover['audience_line'] = __('Documento institucional para Prefeitura Municipal e Secretaria de Educação');
        $cover['gestao_lead'] = self::executiveLead($city, $filters, $matriculas, $score, $health);
        $cover['headline_kpis'] = self::headlineKpis($matriculas, $escolas, $turmas, $score, $health, $perda, $ganho);
        $cover['systemic_dimensions'] = self::systemicDimensions();
        $cover['cultural_pillars'] = self::culturalPillars();
        $cover['executive_summary'] = self::executiveSummary($health, $discSummary, $matriculas, $perda, $ganho);
        $cover['confidentiality_note'] = __(
            'Uso restrito à gestão municipal. Valores financeiros são estimativas indicativas (VAAF × pesos de cadastro) e não substituem repasses oficiais do FNDE, Simec ou Tesouro Transparente.'
        );

        return $cover;
    }

    private static function executiveLead(
        City $city,
        ?IeducarFilterState $filters,
        int $matriculas,
        ?int $score,
        array $health,
    ): string {
        $municipio = trim((string) $city->name);
        $ano = $filters !== null && $filters->hasYearSelected()
            ? ($filters->isAllSchoolYears() ? __('todos os anos letivos') : __('ano letivo :ano', ['ano' => $filters->ano_letivo]))
            : __('recorte analítico');

        if ($matriculas <= 0) {
            return __(
                'Leitura consolidada da rede municipal de :município (:ano), articulando cadastro escolar, equidade, desempenho e sustentabilidade dos repasses públicos.',
                ['município' => $municipio, 'ano' => $ano]
            );
        }

        $conformidade = $score !== null
            ? __('índice de conformidade :s/100 (:l)', [
                's' => (string) $score,
                'l' => (string) ($health['compliance_label'] ?? ''),
            ])
            : __('indicadores de cadastro e financiamento');

        return __(
            'Síntese para tomada de decisão em :município (:ano): :n matrículas no filtro, com :conf — alinhada às dimensões sistêmicas da educação pública e aos compromissos culturais de equidade e transparência.',
            [
                'município' => $municipio,
                'ano' => $ano,
                'n' => number_format($matriculas, 0, ',', '.'),
                'conf' => $conformidade,
            ]
        );
    }

    /**
     * @return list<array{label: string, value: string, tone: string}>
     */
    private static function headlineKpis(
        int $matriculas,
        int $escolas,
        int $turmas,
        ?int $score,
        array $health,
        float $perda,
        float $ganho,
    ): array {
        $out = [];

        $out[] = [
            'label' => __('Matrículas (filtro)'),
            'value' => $matriculas > 0 ? number_format($matriculas, 0, ',', '.') : '—',
            'tone' => $matriculas > 0 ? 'primary' : 'muted',
        ];

        $out[] = [
            'label' => __('Unidades · turmas'),
            'value' => $escolas > 0 || $turmas > 0
                ? number_format($escolas, 0, ',', '.').' · '.number_format($turmas, 0, ',', '.')
                : '—',
            'tone' => 'neutral',
        ];

        if ($score !== null) {
            $status = (string) ($health['compliance_status'] ?? 'neutral');
            $out[] = [
                'label' => __('Conformidade cadastro'),
                'value' => $score.'/100',
                'tone' => match ($status) {
                    'success' => 'success',
                    'warning' => 'warning',
                    'danger' => 'danger',
                    default => 'neutral',
                },
            ];
        }

        if ($perda > 0 || $ganho > 0) {
            $out[] = [
                'label' => __('Impacto cadastro (est./ano)'),
                'value' => ($perda > 0 ? '−'.DiscrepanciesFundingImpact::formatBrl($perda) : '')
                    .($perda > 0 && $ganho > 0 ? ' · ' : '')
                    .($ganho > 0 ? '+'.DiscrepanciesFundingImpact::formatBrl($ganho) : ''),
                'tone' => $perda > $ganho ? 'warning' : 'neutral',
            ];
        }

        return array_slice($out, 0, 4);
    }

    /**
     * @return list<array{step: string, title: string, hint: string, topics: list<string>}>
     */
    private static function systemicDimensions(): array
    {
        $presentation = AnalyticsTabCatalog::groupPresentation();
        $hints = AnalyticsTabCatalog::tabHints();
        $groups = AnalyticsTabCatalog::groups();

        $out = [];
        foreach ($groups as $group) {
            $id = (string) ($group['id'] ?? '');
            $meta = $presentation[$id] ?? [];
            $topics = [];
            foreach ($group['tabs'] ?? [] as $tabId) {
                $tabId = (string) $tabId;
                $hint = $hints[$tabId] ?? '';
                if ($hint !== '') {
                    $topics[] = $hint;
                }
            }

            $out[] = [
                'step' => (string) ($meta['step'] ?? ''),
                'title' => (string) ($group['label'] ?? ''),
                'hint' => (string) ($meta['hint'] ?? ''),
                'topics' => array_slice($topics, 0, 4),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{title: string, text: string}>
     */
    private static function culturalPillars(): array
    {
        return [
            [
                'title' => __('Equidade e inclusão'),
                'text' => __('Matrículas NEE, recurso de prova e VAAR-inclusão — garantia de acesso e participação na rede municipal.'),
            ],
            [
                'title' => __('Qualidade e permanência'),
                'text' => __('Desempenho, frequência e fluxo escolar — acompanhamento da aprendizagem e redução de abandono e evasão.'),
            ],
            [
                'title' => __('Transparência e Censo'),
                'text' => __('Cadastro fiel ao INEP, georreferenciação e exportação Censo — base para políticas públicas e prestação de contas.'),
            ],
            [
                'title' => __('Sustentabilidade financeira'),
                'text' => __('FUNDEB (VAAF/VAAR), programas complementares e impacto de inconsistências — planejamento orçamentário da educação.'),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function executiveSummary(array $health, array $discSummary, int $matriculas, float $perda, float $ganho): array
    {
        $lines = [];

        if (filled($health['intro'] ?? null)) {
            $lines[] = (string) $health['intro'];
        }

        if ($matriculas > 0) {
            $lines[] = __(
                'No recorte analisado, a rede municipal contabiliza :n matrícula(s) ativas nos filtros aplicados — referência para oferta, financiamento e indicadores pedagógicos deste relatório.',
                ['n' => number_format($matriculas, 0, ',', '.')]
            );
        }

        $pend = (int) ($discSummary['com_problema'] ?? $health['summary']['pendencias_cadastro'] ?? 0);
        if ($pend > 0) {
            $lines[] = __(
                'Foram identificadas :p ocorrência(s) de cadastro com impacto indicativo em repasses (VAAF municipal nos pesos). Priorize correção antes de prazos do Censo e condicionalidades VAAR.',
                ['p' => number_format($pend, 0, ',', '.')]
            );
        } elseif ($matriculas > 0) {
            $lines[] = __('Não há pendências críticas agregadas em Discrepâncias no filtro atual — mantenha o ritmo de atualização no i-Educar.');
        }

        if ($perda > 0 || $ganho > 0) {
            $lines[] = __(
                'Estimativa financeira indicativa no ano: perda :perda · ganho potencial :ganho (não é valor oficial de repasse).',
                [
                    'perda' => $perda > 0 ? DiscrepanciesFundingImpact::formatBrl($perda) : '—',
                    'ganho' => $ganho > 0 ? DiscrepanciesFundingImpact::formatBrl($ganho) : '—',
                ]
            );
        }

        $lines[] = __('As seções seguintes detalham cadastro, pedagógico, financiamento e comparativos — leitura recomendada para equipe técnica e gestores.');

        return array_values(array_filter($lines, static fn (string $s): bool => trim($s) !== ''));
    }
}
