<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Repositories\InepCensoMunicipioMatriculaRepository;
use App\Support\Analytics\AnalyticsReportAtmCatalog;
use App\Support\Analytics\AnalyticsReportBibliography;
use App\Support\Analytics\AnalyticsReportQrCodeBuilder;
use App\Support\Dashboard\IeducarFilterState;

/**
 * Monta secções do relatório ATM com dados disponíveis e registo técnico de lacunas.
 */
final class AnalyticsReportSectionScopeAssembler
{
    public function __construct(
        private InepCensoMunicipioMatriculaRepository $censoMunicipio,
    ) {}

    /**
     * @param  array<string, mixed>  $bundle  Payload de AnalyticsFullReportAssembler
     * @return array{
     *   sections: list<array<string, mixed>>,
     *   gaps: list<array{section: string, code: string, detail: string}>,
     *   bibliography: array<string, mixed>,
     *   publication: array<string, mixed>
     * }
     */
    public function assemble(
        City $city,
        IeducarFilterState $filters,
        array $bundle,
        ?int $exportId = null,
        ?string $publicId = null,
    ): array {
        $gaps = [];
        $sections = [];

        $bibExport = new \App\Models\AnalyticsReportExport([
            'public_id' => $publicId ?? AnalyticsReportBibliography::generatePublicId(),
            'filters' => $filters->toQueryParamsWithCity((int) $city->id),
            'completed_at' => now(),
        ]);
        $bibliography = AnalyticsReportBibliography::forExport($bibExport, $city);

        $publicUrl = self::publicationUrl($publicId, $exportId, $bundle);

        foreach (AnalyticsReportAtmCatalog::sections() as $def) {
            $built = $this->buildSection($def, $city, $filters, $bundle, $gaps, $bibliography);
            $sections[] = array_merge($def, $built);
        }

        $qrDataUri = AnalyticsReportQrCodeBuilder::forUrl($publicUrl);

        return [
            'sections' => $sections,
            'gaps' => $gaps,
            'bibliography' => $bibliography,
            'publication' => [
                'public_url' => $publicUrl,
                'qr_data_uri' => $qrDataUri,
                'export_id' => $exportId,
            ],
            'table_of_contents' => AnalyticsReportAtmCatalog::tableOfContents(),
        ];
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>  $bundle
     * @param  list<array{section: string, code: string, detail: string}>  $gaps
     * @return array{available: bool, kpis: list<array<string, mixed>>, tables: list<array<string, mixed>>, narrative: string, notes: list<string>}
     */
    private function buildSection(
        array $def,
        City $city,
        IeducarFilterState $filters,
        array $bundle,
        array &$gaps,
        array $bibliography = [],
    ): array {
        $id = (string) $def['id'];
        $kpis = [];
        $tables = [];
        $notes = [];
        $available = true;

        $overview = is_array($bundle['overview'] ?? null) ? $bundle['overview'] : [];
        $enrollment = is_array($bundle['enrollment'] ?? null) ? $bundle['enrollment'] : [];
        $fundeb = is_array($bundle['fundeb'] ?? null) ? $bundle['fundeb'] : [];
        $other = is_array($bundle['other_funding'] ?? null) ? $bundle['other_funding'] : [];
        $health = is_array($bundle['health'] ?? null) ? $bundle['health'] : [];
        $disc = is_array($bundle['discrepancies'] ?? null) ? $bundle['discrepancies'] : [];
        $work = is_array($bundle['work_done'] ?? null) ? $bundle['work_done'] : [];
        $performance = is_array($bundle['performance'] ?? null) ? $bundle['performance'] : [];
        $inclusion = is_array($bundle['inclusion'] ?? null) ? $bundle['inclusion'] : [];
        $network = is_array($bundle['network'] ?? null) ? $bundle['network'] : [];
        $schoolMap = is_array($bundle['school_units_map'] ?? null) ? $bundle['school_units_map'] : [];
        $comparatives = is_array($bundle['comparatives'] ?? null) ? $bundle['comparatives'] : [];

        match ($id) {
            'indicadores_educacionais' => $this->scopeIndicadores($city, $filters, $overview, $comparatives, $kpis, $tables, $gaps, $notes),
            'rede_municipal' => $this->scopeRedeMunicipal($enrollment, $network, $overview, $kpis, $tables, $gaps, $notes),
            'redes_publicas' => $this->scopeRedesPublicas($city, $filters, $overview, $kpis, $tables, $gaps, $notes),
            'fundeb' => $this->scopeFundeb($fundeb, $health, $kpis, $tables, $gaps, $notes),
            'salario_educacao' => $this->scopeSalarioEducacao($other, $kpis, $tables, $gaps, $notes),
            'programas_universais' => $this->scopeProgramasUniversais($other, $kpis, $tables, $gaps, $notes),
            'educacao_infantil' => $this->scopeEducacaoInfantil($enrollment, $overview, $kpis, $tables, $gaps, $notes),
            'inclusao_equidade' => $this->scopeInclusao($inclusion, $health, $kpis, $tables, $gaps, $notes),
            'desempenho_aprendizagem' => $this->scopeDesempenho($performance, $kpis, $tables, $gaps, $notes),
            'cadastro_censo' => $this->scopeCadastroCenso($disc, $work, $health, $kpis, $tables, $gaps, $notes),
            'territorio_rede' => $this->scopeTerritorio($schoolMap, $kpis, $tables, $gaps, $notes),
            'publicacao_digital' => $this->scopePublicacao($bundle, $kpis, $notes),
            default => $available = false,
        };

        if ($kpis === [] && $tables === [] && $id !== 'publicacao_digital') {
            $available = false;
            $this->gap($gaps, $id, 'no_data', __('Nenhum indicador calculável no recorte actual (verifique ano letivo, ligação i-Educar e sincronizações).'));
        }

        return [
            'available' => $available,
            'kpis' => $kpis,
            'tables' => $tables,
            'narrative' => (string) ($def['intro'] ?? ''),
            'notes' => $notes,
        ];
    }

    /**
     * @param  list<array{section: string, code: string, detail: string}>  $gaps
     */
    private function gap(array &$gaps, string $section, string $code, string $detail): void
    {
        $gaps[] = ['section' => $section, 'code' => $code, 'detail' => $detail];
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array{section: string, code: string, detail: string}>  $gaps
     * @param  list<string>  $notes
     */
    private function scopeIndicadores(
        City $city,
        IeducarFilterState $filters,
        array $overview,
        array $comparatives,
        array &$kpis,
        array &$tables,
        array &$gaps,
        array &$notes,
    ): void {
        $ok = is_array($overview['kpis'] ?? null) ? $overview['kpis'] : [];
        if ($ok !== []) {
            $kpis[] = ['label' => __('Escolas (recorte)'), 'value' => $this->fmtInt($ok['escolas'] ?? null)];
            $kpis[] = ['label' => __('Turmas'), 'value' => $this->fmtInt($ok['turmas'] ?? null)];
            $kpis[] = ['label' => __('Matrículas activas'), 'value' => $this->fmtInt($ok['matriculas'] ?? null)];
        }

        $ano = $this->resolveCensoYear($filters);
        $censo = $this->censoMunicipio->findForCityYear($city, $ano);
        if ($censo !== null) {
            $kpis[] = ['label' => __('Matrículas Censo (:ano)', ['ano' => $ano]), 'value' => $this->fmtInt($censo->matriculas_total)];
            $notes[] = __('Fonte: microdados INEP indexados (inep_censo_municipio_matriculas).');
        } else {
            $this->gap($gaps, 'indicadores_educacionais', 'censo_municipio_missing', __('Matrículas por município no Censo não indexadas. Execute a sincronização massiva (fase funding_censo_matriculas) ou importe microdados.'));
        }

        $this->gap($gaps, 'indicadores_educacionais', 'ibge_socio_missing', __('PIB per capita, IDH e Gini comparativos (município × região × UF × Brasil) não estão modelados na SERVLITCYS — requer integração IBGE/IPEA dedicada.'));

        $munState = is_array($comparatives['municipal_vs_state_enriched'] ?? null)
            ? $comparatives['municipal_vs_state_enriched']
            : [];
        if ($munState !== []) {
            $rows = [];
            foreach (array_slice($munState, 0, 8) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = [
                    'indicador' => $row['label'] ?? $row['metric'] ?? '—',
                    'municipio' => $row['municipal_value'] ?? $row['value'] ?? '—',
                    'referencia' => $row['state_value'] ?? $row['reference'] ?? '—',
                ];
            }
            if ($rows !== []) {
                $tables[] = [
                    'title' => __('Comparativo municipal × UF'),
                    'headers' => [__('Indicador'), __('Município'), __('Referência')],
                    'rows' => $rows,
                ];
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array{section: string, code: string, detail: string}>  $gaps
     * @param  list<string>  $notes
     */
    private function scopeRedeMunicipal(
        array $enrollment,
        array $network,
        array $overview,
        array &$kpis,
        array &$tables,
        array &$gaps,
        array &$notes,
    ): void {
        $dist = is_array($enrollment['distorcao'] ?? null) ? $enrollment['distorcao'] : null;
        if ($dist !== null) {
            $kpis[] = ['label' => __('Distorção idade-série'), 'value' => ($dist['pct'] ?? '—').'%'];
            $kpis[] = ['label' => __('Com distorção'), 'value' => $this->fmtInt($dist['com'] ?? 0)];
            $notes[] = __('Mecanismo: :m', ['m' => (string) ($dist['metodo'] ?? $dist['fonte'] ?? 'automático')]);
        } else {
            $this->gap($gaps, 'rede_municipal', 'distorcao_unavailable', (string) ($enrollment['distorcao_cartao_motivo'] ?? __('Distorção indisponível — falta nascimento, série ou ano letivo na base i-Educar.')));
        }

        $fluxo = is_array($enrollment['fluxo_taxas'] ?? null) ? $enrollment['fluxo_taxas'] : null;
        if ($fluxo !== null && (int) ($fluxo['total'] ?? 0) > 0) {
            $kpis[] = ['label' => __('Abandono (INEP 11)'), 'value' => $this->fmtInt($fluxo['abandono_q'] ?? 0)];
            $kpis[] = ['label' => __('Remanejamento (16)'), 'value' => $this->fmtInt($fluxo['remanejamento_q'] ?? 0)];
        }

        $nk = is_array($network['kpis'] ?? null) ? $network['kpis'] : [];
        if ($nk !== []) {
            $kpis[] = ['label' => __('Taxa ociosidade'), 'value' => isset($nk['taxa_ociosidade_pct']) ? $nk['taxa_ociosidade_pct'].'%' : '—'];
        }

        $mecanismos = is_array($enrollment['distorcao_mecanismos'] ?? null) ? $enrollment['distorcao_mecanismos'] : [];
        if (count($mecanismos) > 0) {
            $rows = [];
            foreach (array_slice($mecanismos, 0, 6) as $m) {
                if (! is_array($m) || empty($m['disponivel'])) {
                    continue;
                }
                $rows[] = [
                    'mecanismo' => $m['label'] ?? $m['id'] ?? '—',
                    'com' => $this->fmtInt($m['com'] ?? 0),
                    'total' => $this->fmtInt($m['total'] ?? 0),
                    'taxa' => isset($m['pct']) ? $m['pct'].'%' : '—',
                ];
            }
            if ($rows !== []) {
                $tables[] = [
                    'title' => __('Mecanismos de apuração da distorção'),
                    'headers' => [__('Mecanismo'), __('Com distorção'), __('Total'), __('Taxa')],
                    'rows' => $rows,
                ];
            }
        }

        $this->gap($gaps, 'rede_municipal', 'ideb_series_missing', __('Série histórica IDEB por etapa (gráficos ATM) não importada — configure SAEB/IDEB municipal ou use o Portal IDEB no painel web.'));
        $this->gap($gaps, 'rede_municipal', 'infra_censo_missing', __('Percentual de escolas com infraestrutura (água, energia, laboratório) exige microdados Censo por escola — não agregado neste relatório.'));
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array{section: string, code: string, detail: string}>  $gaps
     * @param  list<string>  $notes
     */
    private function scopeRedesPublicas(
        City $city,
        IeducarFilterState $filters,
        array $overview,
        array &$kpis,
        array &$tables,
        array &$gaps,
        array &$notes,
    ): void {
        $chart = null;
        foreach (is_array($overview['charts'] ?? null) ? $overview['charts'] : [] as $c) {
            if (is_array($c) && str_contains(mb_strtolower((string) ($c['title'] ?? '')), 'rede')) {
                $chart = $c;
                break;
            }
        }

        if ($chart !== null && isset($chart['labels'], $chart['datasets'][0]['data'])) {
            $labels = $chart['labels'];
            $data = $chart['datasets'][0]['data'];
            $total = array_sum(array_map('intval', $data));
            $rows = [];
            foreach ($labels as $i => $label) {
                $v = (int) ($data[$i] ?? 0);
                $pct = $total > 0 ? round(100 * $v / $total, 1) : null;
                $rows[] = ['rede' => (string) $label, 'matriculas' => $this->fmtInt($v), 'percentual' => $pct !== null ? $pct.'%' : '—'];
                $kpis[] = ['label' => (string) $label, 'value' => $this->fmtInt($v)];
            }
            $tables[] = [
                'title' => __('Matrículas por rede de ensino'),
                'headers' => [__('Rede'), __('Matrículas'), __('%')],
                'rows' => $rows,
            ];
            $notes[] = __('Fonte: i-Educar (recorte de matrículas activas).');
        } else {
            $this->gap($gaps, 'redes_publicas', 'network_breakdown_missing', __('Gráfico de participação por rede (municipal/estadual/privada) indisponível — verifique coluna de dependência administrativa ou importe Censo por rede.'));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array{section: string, code: string, detail: string}>  $gaps
     * @param  list<string>  $notes
     */
    private function scopeFundeb(array $fundeb, array $health, array &$kpis, array &$tables, array &$gaps, array &$notes): void
    {
        $proj = is_array($fundeb['resource_projection'] ?? null) ? $fundeb['resource_projection'] : [];
        if (($proj['totais']['fundeb_base_anual'] ?? null) !== null) {
            $kpis[] = ['label' => __('Previsão base Fundeb'), 'value' => 'R$ '.number_format((float) $proj['totais']['fundeb_base_anual'], 2, ',', '.')];
        }
        if (isset($proj['matriculas'])) {
            $kpis[] = ['label' => __('Matrículas (base cálculo)'), 'value' => $this->fmtInt($proj['matriculas'])];
        }

        $vaaf = is_array($health['vaaf_comparacao'] ?? null) ? $health['vaaf_comparacao'] : [];
        if ($vaaf !== []) {
            foreach (['real', 'previa'] as $key) {
                $row = $vaaf[$key] ?? null;
                if (is_array($row)) {
                    $kpis[] = ['label' => (string) ($row['label'] ?? $key), 'value' => (string) ($row['value'] ?? '—')];
                }
            }
        }

        if ($kpis === []) {
            $this->gap($gaps, 'fundeb', 'fundeb_projection_missing', __('Projeção FUNDEB indisponível — configure VAAF municipal, matrículas no filtro e referências FNDE.'));
        }

        $notes[] = __('Valores indicativos; repasses oficiais no Simec/FNDE.');
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array{section: string, code: string, detail: string}>  $gaps
     * @param  list<string>  $notes
     */
    private function scopeSalarioEducacao(array $other, array &$kpis, array &$tables, array &$gaps, array &$notes): void
    {
        $found = false;
        foreach (is_array($other['programs'] ?? null) ? $other['programs'] : [] as $prog) {
            if (! is_array($prog)) {
                continue;
            }
            $titulo = mb_strtolower((string) ($prog['titulo'] ?? ''));
            if (! str_contains($titulo, 'salário') && ! str_contains($titulo, 'salario')) {
                continue;
            }
            $found = true;
            foreach (is_array($prog['kpis'] ?? null) ? $prog['kpis'] : [] as $kpi) {
                if (is_array($kpi)) {
                    $kpis[] = ['label' => (string) ($kpi['label'] ?? ''), 'value' => (string) ($kpi['value'] ?? $kpi['status_label'] ?? '—')];
                }
            }
        }

        if (! $found) {
            $this->gap($gaps, 'salario_educacao', 'salario_educacao_not_tracked', __('Repasses Salário-Educação não estão segregados no módulo de financiamentos — requer série Tesouro/FNDE por programa.'));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array{section: string, code: string, detail: string}>  $gaps
     * @param  list<string>  $notes
     */
    private function scopeProgramasUniversais(array $other, array &$kpis, array &$tables, array &$gaps, array &$notes): void
    {
        $programs = is_array($other['programs'] ?? null) ? $other['programs'] : [];
        if ($programs === []) {
            $this->gap($gaps, 'programas_universais', 'programs_empty', __('Nenhum programa complementar carregado para o município/ano.'));

            return;
        }

        $rows = [];
        foreach (array_slice($programs, 0, 12) as $prog) {
            if (! is_array($prog)) {
                continue;
            }
            $rows[] = [
                'programa' => $prog['titulo'] ?? '—',
                'situacao' => $prog['status_label'] ?? (is_array($prog['kpis'][0] ?? null) ? ($prog['kpis'][0]['value'] ?? '—') : '—'),
            ];
        }
        $tables[] = [
            'title' => __('Programas monitorados'),
            'headers' => [__('Programa'), __('Situação no recorte')],
            'rows' => $rows,
        ];
        $notes[] = __('Programas federais específicos do modelo MEC (Pé-de-Meia, ENEC, PartiuIF, etc.) são descritos textualmente; dados operacionais dependem de API MEC não integrada.');
        $this->gap($gaps, 'programas_universais', 'mec_programs_api', __('Catálogo completo de programas ATM (20+ políticas) sem API federal — apenas programas com repasse/snapshot na SERVLITCYS.');
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array{section: string, code: string, detail: string}>  $gaps
     * @param  list<string>  $notes
     */
    private function scopeEducacaoInfantil(array $enrollment, array $overview, array &$kpis, array &$tables, array &$gaps, array &$notes): void
    {
        $this->gap($gaps, 'educacao_infantil', 'ei_censo_etapa', __('Tabela Censo por etapa (creche/pré com escolas, professores, alunos) não extraída — microdados por etapa não indexados; use matrículas i-Educar por série Educacenso no painel.'));
        $this->gap($gaps, 'educacao_infantil', 'ei_programs', __('EI Manutenção e Conaquei — sem integração MEC; apenas narrativa de política.'));

        foreach (is_array($enrollment['charts'] ?? null) ? $enrollment['charts'] : [] as $chart) {
            if (! is_array($chart)) {
                continue;
            }
            $t = mb_strtolower((string) ($chart['title'] ?? ''));
            if (str_contains($t, 'série') || str_contains($t, 'serie') || str_contains($t, 'educacenso')) {
                $notes[] = __('Gráfico no anexo: :t', ['t' => (string) ($chart['title'] ?? '')]);
                break;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array{section: string, code: string, detail: string}>  $gaps
     * @param  list<string>  $notes
     */
    private function scopeInclusao(array $inclusion, array $health, array &$kpis, array &$tables, array &$gaps, array &$notes): void
    {
        $recurso = is_array($inclusion['recurso_prova'] ?? null) ? $inclusion['recurso_prova'] : [];
        if ($recurso !== []) {
            $kpis[] = ['label' => __('Matrículas NEE'), 'value' => $this->fmtInt($recurso['total_nee'] ?? data_get($inclusion, 'total_matriculas', 0))];
            $kpis[] = ['label' => __('Sem recurso em prova'), 'value' => $this->fmtInt($recurso['sem_nee'] ?? 0)];
        } else {
            $this->gap($gaps, 'inclusao_equidade', 'nee_data_missing', __('Bloco NEE/recurso em prova indisponível no recorte.'));
        }

        $this->gap($gaps, 'inclusao_equidade', 'pneei_pnee', __('PNEEI e políticas MEC específicas sem API — VAAR coberto na secção FUNDEB quando houver complementação.'));
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array{section: string, code: string, detail: string}>  $gaps
     * @param  list<string>  $notes
     */
    private function scopeDesempenho(array $performance, array &$kpis, array &$tables, array &$gaps, array &$notes): void
    {
        $saeb = is_array($performance['saeb_series'] ?? null) ? $performance['saeb_series'] : [];
        $summary = is_array($saeb['summary'] ?? null) ? $saeb['summary'] : [];
        if ($summary !== []) {
            foreach (array_slice($summary, 0, 6) as $key => $val) {
                if (is_scalar($val) && filled($val)) {
                    $kpis[] = ['label' => ucfirst(str_replace('_', ' ', (string) $key)), 'value' => (string) $val];
                }
            }
            $notes[] = (string) ($saeb['source_hint'] ?? __('Fonte SAEB configurada.'));
        } else {
            $this->gap($gaps, 'desempenho_aprendizagem', 'saeb_missing', (string) ($performance['message'] ?? __('SAEB/IDEB não configurados — importe microdados ou defina IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE.')));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array{section: string, code: string, detail: string}>  $gaps
     * @param  list<string>  $notes
     */
    private function scopeCadastroCenso(array $disc, array $work, array $health, array &$kpis, array &$tables, array &$gaps, array &$notes): void
    {
        $ds = is_array($disc['summary'] ?? null) ? $disc['summary'] : [];
        $kpis[] = ['label' => __('Índice conformidade'), 'value' => isset($health['compliance_score']) ? (int) $health['compliance_score'].'/100' : '—'];
        $kpis[] = ['label' => __('Ocorrências discrepâncias'), 'value' => $this->fmtInt($ds['com_problema'] ?? 0)];
        $kpis[] = ['label' => __('Perda estimada/ano'), 'value' => 'R$ '.number_format((float) ($ds['perda_estimada_anual'] ?? 0), 2, ',', '.')];

        $censo = is_array($work['censo'] ?? null) ? $work['censo'] : [];
        if ($censo['available'] ?? false) {
            $kpis[] = ['label' => __('Censo pendentes'), 'value' => $this->fmtInt(data_get($censo, 'summary.pendentes', 0))];
        } else {
            $this->gap($gaps, 'cadastro_censo', 'censo_export_status', __('Estado de exportação Censo não disponível na base i-Educar para este município.'));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array{section: string, code: string, detail: string}>  $gaps
     * @param  list<string>  $notes
     */
    private function scopeTerritorio(array $schoolMap, array &$kpis, array &$tables, array &$gaps, array &$notes): void
    {
        if ($schoolMap['available'] ?? false) {
            $stats = is_array($schoolMap['stats'] ?? null) ? $schoolMap['stats'] : [];
            $kpis[] = ['label' => __('Escolas no mapa'), 'value' => $this->fmtInt($stats['schools'] ?? 0)];
            $kpis[] = ['label' => __('Matrículas (mapa)'), 'value' => $this->fmtInt($stats['matriculas_total'] ?? 0)];
            $notes[] = (string) ($schoolMap['caption'] ?? '');
        } else {
            $this->gap($gaps, 'territorio_rede', 'map_unavailable', (string) ($schoolMap['geo_note'] ?? __('Mapa indisponível — falta georreferenciação ou matrículas no filtro.')));
        }
    }

    /**
     * @param  array<string, mixed>  $bibliography
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<string>  $notes
     */
    private function scopePublicacao(array $bibliography, array &$kpis, array &$notes): void
    {
        if ($bibliography !== []) {
            $kpis[] = ['label' => __('Identificador'), 'value' => (string) ($bibliography['public_id'] ?? '—')];
            $kpis[] = ['label' => __('Emissão'), 'value' => (string) ($bibliography['issued_at'] ?? '—')];
        }
        $notes[] = __('Leia o QR code com o telemóvel para abrir a página de verificação e download do PDF.');
    }

    /**
     * @param  array<string, mixed>  $bundle
     */
    private static function publicationUrl(?string $publicId, ?int $exportId, array $bundle): string
    {
        if ($publicId !== null && $publicId !== '') {
            try {
                return route('analytics.report.public', ['publicId' => $publicId]);
            } catch (\Throwable) {
            }
        }

        $query = is_array($bundle['filters_query'] ?? null) ? $bundle['filters_query'] : [];

        return url('/dashboard/analytics'.($query !== [] ? '?'.http_build_query($query) : ''));
    }

    private function resolveCensoYear(IeducarFilterState $filters): int
    {
        if ($filters->hasYearSelected() && ! $filters->isAllSchoolYears()) {
            return (int) $filters->ano_letivo;
        }

        return (int) date('Y');
    }

    private function fmtInt(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }

        return number_format((int) $v, 0, ',', '.');
    }
}
