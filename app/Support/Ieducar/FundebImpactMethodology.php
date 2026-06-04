<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Support\Dashboard\IeducarFilterState;

/**
 * Metodologia unificada para perda/ganho indicativo — VAAF, ponderações, portarias e base legal FUNDEB.
 */
final class FundebImpactMethodology
{
    /**
     * Painel completo para abas de consultoria (Discrepâncias, Diagnóstico, FUNDEB).
     *
     * @return array<string, mixed>
     */
    public static function panel(?City $city = null, ?IeducarFilterState $filters = null): array
    {
        $met = DiscrepanciesFundingImpact::metodologiaResumo($city, $filters);
        $calc = FundebMunicipalReferenceResolver::vaafParaCalculo($city, $filters);
        $ref = DiscrepanciesFundingImpact::resolveReference($city, $filters);
        $funding = DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters);

        return array_merge($met, [
            'vaaf_calculo' => [
                'valor' => (float) $calc['vaaf'],
                'valor_label' => DiscrepanciesFundingImpact::formatBrl((float) $calc['vaaf']),
                'origem' => (string) $calc['origem'],
                'fonte_label' => (string) $calc['fonte_label'],
                'ano' => $calc['ano'] ?? $ref['ano'] ?? null,
                'rotulo' => FundebReferenceDisplay::rotuloVaafCurto($funding),
            ],
            'ponderacoes' => self::ponderacoesVigentes(),
            'pilares' => DiscrepanciesFundingImpact::fundingPillars(),
            'distribuicao_legal' => self::distribuicaoLegalResumo(),
            'portarias' => self::portariasOficiais($city, $filters),
            'fontes_dados' => self::fontesDadosResumo(),
            'formula_impacto' => __('Perda ou ganho indicativo = ocorrências × (VAAF de referência × peso da rotina). Ponderações alinhadas ao eixo FUNDEB/VAAR/Censo em config/ieducar.php.'),
        ]);
    }

    /**
     * Versão compacta para faixa de impacto no topo das abas.
     *
     * @param  array<string, mixed>  $municipalityContext
     * @return array<string, mixed>|null
     */
    public static function compactFromContext(?array $municipalityContext): ?array
    {
        if ($municipalityContext === null) {
            return null;
        }

        $funding = is_array($municipalityContext['funding_reference'] ?? null)
            ? $municipalityContext['funding_reference']
            : null;

        if ($funding === null) {
            return null;
        }

        return [
            'rotulo_vaaf' => FundebReferenceDisplay::rotuloVaafCurto($funding),
            'vaa_label' => (string) ($funding['vaa_label'] ?? ''),
            'vaa_fonte_label' => (string) ($funding['vaa_fonte_label'] ?? ''),
            'vaa_ano' => $funding['vaa_ano'] ?? null,
            'aviso' => DiscrepanciesFundingImpact::avisoGeral(),
            'formula_curta' => __('Ocorrências × VAAF (:vaa) × peso por tipo de discrepância', [
                'vaa' => (string) ($funding['vaa_label'] ?? ''),
            ]),
            'distribuicao_referencia' => (string) (self::distribuicaoLegalResumo()['referencia'] ?? ''),
            'referencias_legais' => FundebReferenceDisplay::referenciasLegaisLinha(),
        ];
    }

    /**
     * @return list<array{check_id: string, label: string, peso: float}>
     */
    public static function ponderacoesVigentes(): array
    {
        $pesos = config('ieducar.discrepancies.peso_por_check', []);
        if (! is_array($pesos)) {
            return [];
        }

        $defs = DiscrepanciesCheckCatalog::definitions();
        $out = [];
        foreach ($pesos as $id => $peso) {
            $def = $defs[$id] ?? null;
            $out[] = [
                'check_id' => (string) $id,
                'label' => is_array($def) ? (string) ($def['title'] ?? $id) : (string) $id,
                'peso' => max(0.0, (float) $peso),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['peso'] <=> $a['peso']);

        return $out;
    }

    /**
     * @return array{referencia: string, nota: string, pisos: list<array{titulo: string, percentual: float}>}
     */
    public static function distribuicaoLegalResumo(): array
    {
        $cfg = config('ieducar.fundeb.distribuicao_legal', []);
        if (! is_array($cfg)) {
            return ['referencia' => '', 'nota' => '', 'pisos' => []];
        }

        $pisos = [];
        foreach ($cfg['pisos'] ?? [] as $piso) {
            if (! is_array($piso)) {
                continue;
            }
            $pisos[] = [
                'titulo' => (string) ($piso['titulo'] ?? ''),
                'percentual' => (float) ($piso['percentual_minimo'] ?? $piso['percentual_maximo'] ?? $piso['percentual'] ?? 0),
            ];
        }

        return [
            'referencia' => (string) ($cfg['referencia'] ?? 'Lei nº 14.113/2020'),
            'nota' => (string) ($cfg['nota'] ?? ''),
            'pisos' => $pisos,
        ];
    }

    /**
     * @return list<array{label: string, url: string, ano?: int|null, tipo: string}>
     */
    public static function portariasOficiais(?City $city = null, ?IeducarFilterState $filters = null): array
    {
        $links = [];
        $ano = $filters !== null ? $filters->yearFilterValue() : null;
        if ($ano <= 0) {
            $ano = FundebOpenDataImportService::suggestedImportYear();
        }

        $csvByYear = config('ieducar.fundeb.open_data.fnde_receita_csv_by_year', []);
        if (is_array($csvByYear)) {
            foreach ($csvByYear as $y => $url) {
                if (! is_string($url) || $url === '') {
                    continue;
                }
                $links[] = [
                    'label' => __('Portaria FNDE — receita total por ente (:ano)', ['ano' => (string) $y]),
                    'url' => $url,
                    'ano' => (int) $y,
                    'tipo' => 'portaria_receita',
                ];
            }
        }

        $ref = $city !== null ? FundebMunicipalReferenceResolver::resolve($city, $filters) : [];
        $municipal = is_array($ref['municipal'] ?? null) ? $ref['municipal'] : null;
        if ($municipal !== null && filled($municipal['url_portaria'] ?? null)) {
            $links[] = [
                'label' => __('Portaria/registro importado (município)'),
                'url' => (string) $municipal['url_portaria'],
                'ano' => $municipal['ano'] ?? $ano,
                'tipo' => 'importada',
            ];
        }

        $static = [
            ['label' => __('Consultas oficiais FUNDEB (FNDE)'), 'url' => 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas', 'ano' => null, 'tipo' => 'fnde'],
            ['label' => __('Lei nº 14.113/2020 — FUNDEB'), 'url' => 'http://www.planalto.gov.br/ccivil_03/_ato2019-2022/2020/lei/L14113.htm', 'ano' => null, 'tipo' => 'lei'],
        ];

        return array_values(array_merge($links, $static));
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    public static function fontesDadosResumo(): array
    {
        return [
            ['key' => 'ckan', 'label' => __('FNDE dados abertos (CKAN)'), 'url' => (string) config('ieducar.fundeb.open_data.ckan_base_url', 'https://www.fnde.gov.br/dadosabertos')],
            ['key' => 'tesouro', 'label' => __('Tesouro Transparente — repasses observados'), 'url' => 'https://www.tesourotransparente.gov.br/'],
            ['key' => 'portal', 'label' => __('Portal da Transparência'), 'url' => 'https://portaldatransparencia.gov.br/'],
            ['key' => 'ieducar', 'label' => __('Matrículas activas (i-Educar)'), 'url' => ''],
        ];
    }
}
