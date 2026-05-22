<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Fundeb\FundebFndeEstadoVaafService;
use App\Services\Fundeb\FundebFndePublicationAlerts;
use App\Services\Fundeb\FundebFndeReceitaCsvService;
use App\Services\Fundeb\FundebMatriculasByYearService;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Fundeb\FundebReferenceSource;
use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Perfil VAAF/receita FUNDEB por município: ano corrente de planejamento + anos futuros configurados.
 */
final class FundebVaafProfileBuilder
{
    public function __construct(
        private FundebFndeReceitaCsvService $fndeReceita,
        private FundebFndeEstadoVaafService $fndeEstadoVaaf,
        private FundebMatriculasByYearService $matriculas,
        private FundebFndePublicationAlerts $alerts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(
        City $city,
        ?IeducarFilterState $filters = null,
        ?int $matriculasFiltroAtual = null,
        ?array $discrepanciesData = null,
        ?array $enrollmentData = null,
    ): array {
        $years = FundebOpenDataImportService::yearsForPlanningProfile();
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        $matByYear = $this->matriculas->forCityYears($city, $years);
        $yearBlocks = [];

        foreach ($years as $ano) {
            $yearBlocks[$ano] = $this->buildYearBlock($city, $ibge, $ano, $matByYear[$ano] ?? null);
        }

        $anchorAno = $filters !== null && $filters->hasYearSelected() && ! $filters->isAllSchoolYears()
            ? (int) $filters->ano_letivo
            : FundebOpenDataImportService::suggestedImportYear();

        $matAnchor = $matriculasFiltroAtual ?? (int) (($matByYear[$anchorAno]['usado'] ?? 0));
        $refAnchor = DiscrepanciesFundingImpact::resolveReference($city, $filters);

        $projection = $matAnchor > 0
            ? FundebResourceProjection::build(
                $matAnchor,
                __('Ano letivo :year', ['year' => (string) $anchorAno]),
                is_array($enrollmentData) ? $enrollmentData : [],
                $discrepanciesData,
                $city,
                $filters,
                $refAnchor,
            )
            : FundebResourceProjection::build(0, '', [], $discrepanciesData, $city, $filters, $refAnchor);

        $alertList = $this->alerts->evaluate($yearBlocks);

        return [
            'ibge' => $ibge,
            'city_name' => $city->name,
            'uf' => $city->uf,
            'planning_years' => $years,
            'anchor_ano' => $anchorAno,
            'years' => $yearBlocks,
            'ponderacoes_discrepancias' => config('ieducar.discrepancies.peso_por_check', []),
            'distribuicao_legal' => $projection['distribuicao_legal'] ?? [],
            'previsao_ano_corrente' => $projection,
            'portarias' => $this->collectPortariaLinks($yearBlocks),
            'alerts' => $alertList,
            'alerts_count' => [
                'danger' => count(array_filter($alertList, static fn (array $a): bool => ($a['severity'] ?? '') === 'danger')),
                'warning' => count(array_filter($alertList, static fn (array $a): bool => ($a['severity'] ?? '') === 'warning')),
                'info' => count(array_filter($alertList, static fn (array $a): bool => ($a['severity'] ?? '') === 'info')),
            ],
            'fontes' => $this->fontesResumo(),
        ];
    }

    /**
     * @param  ?array{ano: int, ieducar: int, censo: ?int, usado: int, fonte_usada: string}  $matRow
     * @return array<string, mixed>
     */
    private function buildYearBlock(City $city, ?string $ibge, int $ano, ?array $matRow): array
    {
        $filters = new IeducarFilterState((string) $ano, null, null, null);
        $resolver = FundebMunicipalReferenceResolver::resolve($city, $filters);
        $db = $ibge !== null
            ? FundebMunicipioReference::query()->where('ibge_municipio', $ibge)->where('ano', $ano)->first()
            : null;

        $receitaRow = $ibge !== null ? $this->fndeReceita->rowForIbge($ibge, $ano) : null;
        $uf = strtoupper(trim((string) $city->uf));
        $estadoRow = strlen($uf) === 2 ? $this->fndeEstadoVaaf->rowForUf($uf, $ano) : null;
        $refEstadual = is_array($resolver['referencia_estadual'] ?? null) ? $resolver['referencia_estadual'] : null;
        $matUsado = (int) ($matRow['usado'] ?? 0);
        $totalReceita = $receitaRow !== null ? (float) $receitaRow['total_receita'] : null;
        $vaafEst = $totalReceita !== null && $matUsado > 0
            ? $this->fndeReceita->estimateVaafFromReceitaAndMatriculas($totalReceita, $matUsado)
            : null;

        $min = (float) config('ieducar.fundeb.open_data.vaaf_estimate_min', 2500);
        $max = (float) config('ieducar.fundeb.open_data.vaaf_estimate_max', 18000);
        $rawVaaf = $totalReceita !== null && $matUsado > 0 ? round($totalReceita / $matUsado, 2) : null;

        $previsaoBase = $matUsado > 0 && $vaafEst !== null
            ? round($matUsado * $vaafEst, 2)
            : ($matUsado > 0 && (float) ($resolver['vaaf'] ?? 0) > 0
                ? round($matUsado * (float) $resolver['vaaf'], 2)
                : null);

        $distCfg = config('ieducar.fundeb.distribuicao_legal', []);
        $distribuicao = $previsaoBase !== null && $previsaoBase > 0
            ? $this->legalSlice($previsaoBase, is_array($distCfg) ? $distCfg : [])
            : [];

        return [
            'ano' => $ano,
            'label' => $this->yearLabel($ano),
            'resolver' => $resolver,
            'db_reference' => $db !== null ? [
                'vaaf' => (float) $db->vaaf,
                'vaat' => $db->vaat !== null ? (float) $db->vaat : null,
                'complementacao_vaar' => $db->complementacao_vaar !== null ? (float) $db->complementacao_vaar : null,
                'fonte' => (string) $db->fonte,
                'tipo_valor' => $db->tipo_valor,
                'placeholder' => FundebReferenceSource::isPlaceholder($db->fonte),
                'imported_at' => $db->imported_at?->format('Y-m-d H:i'),
            ] : null,
            'receita' => [
                'disponivel' => $receitaRow !== null,
                'total' => $totalReceita,
                'complementacao_vaaf' => $receitaRow['complementacao_vaaf'] ?? null,
                'complementacao_vaat' => $receitaRow['complementacao_vaat'] ?? null,
                'complementacao_vaar' => $receitaRow['complementacao_vaar'] ?? null,
                'ano_publicacao' => $receitaRow['ano_publicacao'] ?? null,
                'csv_url' => $receitaRow['csv_url'] ?? null,
                'entidade' => $receitaRow['entidade'] ?? null,
            ],
            'matriculas' => $matRow ?? [
                'ano' => $ano,
                'ieducar' => 0,
                'censo' => null,
                'usado' => 0,
                'fonte_usada' => 'indisponivel',
            ],
            'vaaf_estimado' => [
                'valor' => $vaafEst,
                'bruto' => $rawVaaf,
                'fora_limites' => $rawVaaf !== null && ($rawVaaf < $min || $rawVaaf > $max),
                'fonte' => FundebReferenceSource::FONTE_FNDE_RECEITA_IEDUCAR,
            ],
            'referencia_estadual' => [
                'disponivel' => $estadoRow !== null || $refEstadual !== null,
                'vaaf' => $estadoRow !== null
                    ? (float) $estadoRow['vaaf']
                    : ($refEstadual !== null ? (float) ($refEstadual['vaaf'] ?? 0) : null),
                'total_receita' => $estadoRow['total_receita_vaaf'] ?? null,
                'complementacao_vaaf' => $estadoRow['complementacao_vaaf'] ?? null,
                'ano_publicacao' => $estadoRow['ano_publicacao'] ?? ($refEstadual['ano'] ?? null),
                'pdf_url' => $estadoRow['pdf_url'] ?? null,
                'uf' => $uf !== '' ? $uf : null,
                'fonte_label' => $refEstadual['fonte_label'] ?? null,
            ],
            'previsao_recursos' => [
                'base_anual' => $previsaoBase,
                'formula' => $previsaoBase !== null && $vaafEst !== null
                    ? __(':mat × :vaaf (estimativa portaria ÷ matrículas)', [
                        'mat' => number_format($matUsado, 0, ',', '.'),
                        'vaaf' => number_format($vaafEst, 2, ',', '.'),
                    ])
                    : null,
            ],
            'distribuicao_planejada' => $distribuicao,
        ];
    }

    private function yearLabel(int $ano): string
    {
        $cy = (int) date('Y');
        if ($ano === $cy) {
            return __('Exercício corrente (:ano)', ['ano' => (string) $ano]);
        }
        if ($ano === $cy + 1) {
            return __('Próximo exercício (:ano) — planejamento', ['ano' => (string) $ano]);
        }
        if ($ano > $cy) {
            return __(':ano (futuro)', ['ano' => (string) $ano]);
        }

        return (string) $ano;
    }

    /**
     * @param  array<string, mixed>  $distCfg
     * @return list<array<string, mixed>>
     */
    private function legalSlice(float $base, array $distCfg): array
    {
        $pisos = is_array($distCfg['pisos'] ?? null) ? $distCfg['pisos'] : [];
        $itens = [];
        foreach ($pisos as $piso) {
            if (! is_array($piso)) {
                continue;
            }
            $pct = (float) ($piso['percentual_minimo'] ?? $piso['percentual_maximo'] ?? 0);
            $valor = $pct > 0 ? round($base * ($pct / 100), 2) : null;
            $itens[] = [
                'id' => $piso['id'] ?? '',
                'titulo' => $piso['titulo'] ?? '',
                'percentual' => $pct,
                'valor_planejado' => $valor,
                'descricao' => $piso['descricao'] ?? '',
            ];
        }

        return $itens;
    }

    /**
     * @param  array<int, array<string, mixed>>  $yearBlocks
     * @return list<array{ano: int, url: string, publicacao: ?int}>
     */
    private function collectPortariaLinks(array $yearBlocks): array
    {
        $links = [];
        foreach ($yearBlocks as $ano => $block) {
            $url = $block['receita']['csv_url'] ?? null;
            if (! is_string($url) || $url === '') {
                continue;
            }
            $links[] = [
                'ano' => (int) $ano,
                'url' => $url,
                'publicacao' => $block['receita']['ano_publicacao'] ?? null,
            ];
        }

        return $links;
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    private function fontesResumo(): array
    {
        return [
            ['key' => 'fnde_portaria', 'label' => __('Portaria FNDE — CSV receita total por ente'), 'url' => 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas'],
            ['key' => 'fnde_estado_vaaf', 'label' => __('Consultas FNDE — VAAF estimado por UF/DF (PDF)'), 'url' => 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas'],
            ['key' => 'ckan', 'label' => __('FNDE dados abertos (CKAN)'), 'url' => (string) config('ieducar.fundeb.open_data.ckan_base_url', 'https://www.fnde.gov.br/dadosabertos')],
            ['key' => 'ieducar', 'label' => __('Matrículas activas (i-Educar)'), 'url' => ''],
            ['key' => 'censo', 'label' => __('Censo INEP (agregado municipal)'), 'url' => 'https://www.gov.br/inep/pt-br/areas-de-atuacao/pesquisas-estatisticas-e-indicadores/censo-escolar/resultado'],
        ];
    }
}
