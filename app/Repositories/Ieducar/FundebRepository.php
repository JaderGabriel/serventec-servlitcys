<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Dashboard\PublicDataSourcesCatalog;
use App\Support\Ieducar\FundebResourceProjection;

/**
 * Relatório temático alinhado às condicionalidades do FUNDEB / VAAR (referência pedagógica).
 * A comprovação formal de cumprimento ocorre no módulo Simec (MEC); aqui cruzamos dados do i-Educar quando existirem.
 */
class FundebRepository
{
    /**
     * @param  array<string, mixed>  $overviewData
     * @param  array<string, mixed>  $enrollmentData
     * @param  array<string, mixed>  $performanceData
     * @param  array<string, mixed>  $attendanceData
     * @param  array<string, mixed>  $inclusionData
     * @param  array<string, mixed>  $networkData
     * @return array{
     *   year_label: string,
     *   city_name: string,
     *   intro: string,
     *   footnote: string,
     *   modules: list<array{
     *     id: string,
     *     title: string,
     *     reference: string,
     *     explanation: string,
     *     situacao: string,
     *     status: 'success'|'warning'|'neutral'|'danger',
     *     evidencias: list<string>,
     *     gaps: list<string>
     *   }>
     * }
     */
    public function buildReport(
        City $city,
        IeducarFilterState $filters,
        array $overviewData,
        array $enrollmentData,
        array $performanceData,
        array $attendanceData,
        array $inclusionData,
        array $networkData,
        ?array $discrepanciesData = null,
    ): array {
        $yearLabel = $this->yearLabel($filters);
        $matTotal = (int) data_get($overviewData, 'kpis.matriculas', data_get($enrollmentData, 'kpis.matriculas', 0));

        return [
            'year_label' => $yearLabel,
            'city_name' => $city->name,
            'resource_projection' => FundebResourceProjection::build(
                $matTotal,
                $yearLabel,
                $enrollmentData,
                $discrepanciesData,
                $city,
                $filters,
            ),
            'intro' => __(
                'O FUNDEB financia a manutenção e o desenvolvimento da educação básica. O MEC acompanha condicionalidades ligadas ao Valor-Aluno-Ano-Resultado (VAAR), com registro e documentação no Sistema Simec. Este painel não substitui o módulo oficial: organiza um roteiro por «módulos» temáticos, explica o que costuma ser exigido e cruza, quando possível, indicadores da base i-Educar da cidade (respeitando os filtros actuais).'
            ),
            'footnote' => __(
                'Condicionalidade IV (ICMS) aplica-se às redes estaduais na comprovação específica; municípios e o Distrito Federal seguem outras regras do VAAR. Dúvidas sobre prazos e documentos: canal oficial do MEC / Simec.'
            ),
            'public_data_sources' => PublicDataSourcesCatalog::build($city, 'financeiro'),
            'modules' => [
                $this->moduleGestaoDemocratica(),
                $this->moduleBncc(),
                $this->moduleInepSaeb($performanceData, $matTotal),
                $this->moduleInclusao($inclusionData, $matTotal),
                $this->moduleEiEfOferta($enrollmentData, $networkData, $matTotal),
                $this->moduleFrequenciaFluxo($attendanceData, $enrollmentData, $performanceData),
                $this->moduleTransporteAlimentacao(),
                $this->moduleTransparenciaFundeb(),
            ],
        ];
    }

    private function yearLabel(IeducarFilterState $filters): string
    {
        if (! $filters->hasYearSelected()) {
            return '';
        }
        if ($filters->isAllSchoolYears()) {
            return __('Todos os anos (consolidado no filtro)');
        }

        return __('Ano letivo :year', ['year' => (string) $filters->ano_letivo]);
    }

    /**
     * @return array{id: string, title: string, reference: string, explanation: string, situacao: string, status: 'success'|'warning'|'neutral'|'danger', evidencias: list<string>, gaps: list<string>}
     */
    private function moduleGestaoDemocratica(): array
    {
        return [
            'id' => 'gestao-democratica',
            'title' => __('Gestão democrática e participação'),
            'reference' => __('Condicionalidade I (referência VAAR — participação e governança)'),
            'explanation' => __(
                'A participação de famílias e da comunidade escolar em conselhos e instâncias colegiadas é requisito recorrente nas condicionalidades. A comprovação costuma envolver atas, composição dos conselhos e publicidade das decisões, conforme orientações do MEC no ciclo em vigor.'
            ),
            'situacao' => __(
                'O i-Educar costuma não centralizar o arquivo completo de conselhos escolares ou do Conselho Municipal de Educação; esse rastreio permanece no âmbito administrativo e no Simec.'
            ),
            'status' => 'neutral',
            'evidencias' => [],
            'gaps' => [
                __('Confirmar no Simec o envio e a validação dos documentos do ciclo do VAAR para gestão democrática.'),
                __('Manter no protocolo municipal atas atualizadas e composição dos conselhos, alinhadas à legislação local.'),
            ],
        ];
    }

    /**
     * @return array{id: string, title: string, reference: string, explanation: string, situacao: string, status: 'success'|'warning'|'neutral'|'danger', evidencias: list<string>, gaps: list<string>}
     */
    private function moduleBncc(): array
    {
        return [
            'id' => 'bncc',
            'title' => __('BNCC e organização curricular'),
            'reference' => __('Condicionalidade V (referência — Base Nacional Comum Curricular)'),
            'explanation' => __(
                'A rede deve demonstrar alinhamento curricular com a BNCC e o planejamento docente. Em muitos ciclos essa condicionalidade exige declarações, planos ou evidências de formação continuada vinculados ao currículo em vigor.'
            ),
            'situacao' => __(
                'Indicadores de matrícula ou desempenho no i-Educar não comprovam, por si, o cumprimento integral da BNCC; servem apenas como contexto pedagógico.'
            ),
            'status' => 'neutral',
            'evidencias' => [],
            'gaps' => [
                __('Arquivar e enviar ao Simec as evidências pedidas no edital/orientação do VAAR para o ano de referência.'),
                __('Garantir que escolas e equipes tenham planos de aula e currículos referenciados à BNCC, auditáveis localmente.'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $performanceData
     */
    private function moduleInepSaeb(array $performanceData, int $matTotal): array
    {
        $hasKpis = ! empty($performanceData['kpis']) && is_array($performanceData['kpis']);
        $hasCharts = ! empty($performanceData['charts']);
        $hasRows = ! empty($performanceData['rows']);
        $err = (string) ($performanceData['error'] ?? '');

        $evidencias = [];
        if ($matTotal > 0) {
            $evidencias[] = __('Total de matrículas ativas no filtro: :n.', ['n' => number_format($matTotal, 0, ',', '.')]);
        }
        if ($hasKpis || $hasCharts || $hasRows) {
            $evidencias[] = __('Existem indicadores de situação de matrícula / desempenho calculados a partir do i-Educar (aprovados, reprovações, distorções, etc., conforme configurado).');
        }

        $status = 'neutral';
        $situacao = __(
            'As condicionalidades ligadas ao INEP costumam envolver SAEB, IDEB e metas do PNE; o painel de Desempenho usa apenas campos de situação da matrícula na base local — não o resultado nacional do SAEB.'
        );
        if ($err !== '') {
            $status = 'danger';
            $situacao = __('Não foi possível carregar indicadores de desempenho na base: verifique a configuração das tabelas de matrícula e situação.');
        } elseif ($hasKpis || $hasCharts) {
            $status = 'warning';
            $situacao = __(
                'Há dados locais de situação escolar no i-Educar; comparar com as metas e indicadores exigidos pelo INEP no ciclo do VAAR.'
            );
        }

        $gaps = [
            __('Cruzar os indicadores oficiais (IDEB, SAEB, metas do PNE) com o que foi declarado no Simec.'),
            __('Se o município não publicar notas do SAEB para a rede própria, registrar justificativa e plano de melhoria conforme regras do MEC.'),
        ];

        return [
            'id' => 'inep-saeb',
            'title' => __('Indicadores educacionais e metas (INEP)'),
            'reference' => __('Condicionalidade II (referência — indicadores e avaliações externas)'),
            'explanation' => __(
                'O acompanhamento de resultados de aprendizagem e de indicadores agregados faz parte do conjunto de condicionalidades analisadas pelo INEP e relacionadas ao desempenho da rede.'
            ),
            'situacao' => $situacao,
            'status' => $status,
            'evidencias' => $evidencias,
            'gaps' => $gaps,
        ];
    }

    /**
     * @param  array<string, mixed>  $inclusionData
     */
    private function moduleInclusao(array $inclusionData, int $matTotal): array
    {
        $gauges = $inclusionData['gauges'] ?? [];
        $charts = $inclusionData['charts'] ?? [];
        $err = (string) ($inclusionData['error'] ?? '');

        $evidencias = [];
        foreach ($gauges as $g) {
            $chart = $g['chart'] ?? [];
            $title = $chart['title'] ?? ($g['caption'] ?? '');
            if (is_string($title) && $title !== '') {
                $evidencias[] = __('Medidor disponível: :t.', ['t' => $title]);
            }
        }
        if ($matTotal > 0 && $evidencias === []) {
            $evidencias[] = __('Matrículas no filtro: :n (base para percentagens de inclusão).', ['n' => number_format($matTotal, 0, ',', '.')]);
        }

        $status = 'neutral';
        $situacao = __(
            'Sem medidores de educação especial na base filtrada, não é possível avaliar aqui percentagens de atendimento.'
        );
        if ($err !== '') {
            $status = 'danger';
            $situacao = __('Erro ao ler dados de inclusão na base; ajuste tabelas de deficiência / aluno conforme config/ieducar.php.');
        } elseif ($gauges !== []) {
            $status = 'success';
            $situacao = __(
                'Há medidores de educação especial (deficiências, síndromes, altas habilidades ou SQL personalizado). Compare com as metas de equidade e políticas municipais.'
            );
        } elseif ($charts !== []) {
            $status = 'warning';
            $situacao = __('Há gráficos de equidade (sexo, série, raça/cor), mas não medidores específicos de educação especial.');
        }

        return [
            'id' => 'inclusao-pnee',
            'title' => __('Inclusão e educação especial'),
            'reference' => __('Políticas de educação inclusiva e direitos da pessoa com deficiência (referência ao arcabouço legal e ao VAAR)'),
            'explanation' => __(
                'O FUNDEB condiciona apoio a políticas que garantam matrícula, permanência e suporte pedagógico aos públicos da educação especial e inclusiva, em articulação com a rede de saúde e assistência quando aplicável.'
            ),
            'situacao' => $situacao,
            'status' => $status,
            'evidencias' => $evidencias,
            'gaps' => [
                __('Verificar no Simec o atendimento às exigências documentais sobre educação especial no ciclo vigente.'),
                __('Se os percentuais na base forem baixos, analisar fluxo de identificação, AEE e formação de professores (registros fora do i-Educar).'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $enrollmentData
     * @param  array<string, mixed>  $networkData
     */
    private function moduleEiEfOferta(array $enrollmentData, array $networkData, int $matTotal): array
    {
        $kpis = $networkData['kpis'] ?? null;
        $chartsE = $enrollmentData['charts'] ?? [];
        $vagasOciosa = is_array($kpis) ? (float) ($kpis['taxa_ociosidade_pct'] ?? 0) : null;

        $evidencias = [];
        if ($matTotal > 0) {
            $evidencias[] = __('Matrículas ativas (filtro): :n.', ['n' => number_format($matTotal, 0, ',', '.')]);
        }
        if (is_array($kpis) && isset($kpis['vagas_ociosas'], $kpis['capacidade_total'])) {
            $evidencias[] = __(
                'Capacidade declarada nas turmas: :cap; vagas ociosas: :v.',
                [
                    'cap' => number_format((int) $kpis['capacidade_total'], 0, ',', '.'),
                    'v' => number_format((int) $kpis['vagas_ociosas'], 0, ',', '.'),
                ]
            );
        }
        $temDistorcao = false;
        foreach ($chartsE as $c) {
            $t = strtolower((string) ($c['title'] ?? ''));
            if (str_contains($t, 'distor') || str_contains($t, 'idade')) {
                $temDistorcao = true;
                break;
            }
        }
        if ($temDistorcao) {
            $evidencias[] = __('Gráfico de distorção idade/série ou equivalente disponível na aba Matrículas — use para planos de universalização e remediação.');
        }

        $status = 'warning';
        if ($matTotal <= 0) {
            $status = 'danger';
        } elseif (is_array($kpis) && $vagasOciosa !== null && $vagasOciosa > 25) {
            $status = 'warning';
        } elseif ($matTotal > 0 && is_array($kpis)) {
            $status = 'success';
        }

        $situacao = __(
            'A oferta de vagas na educação infantil e no ensino fundamental e a redução da distorção idade/série são eixos centrais das metas de acesso; os números abaixo vêm da base i-Educar filtrada.'
        );
        if ($matTotal <= 0) {
            $situacao = __('Sem matrículas ativas no filtro — alargue o ano ou remova filtros para ver a rede completa.');
        }

        return [
            'id' => 'ei-ef-oferta',
            'title' => __('Educação Infantil, Ensino Fundamental e oferta de vagas'),
            'reference' => __('Metas de acesso e organização da rede (PNE / universalização — referência)'),
            'explanation' => __(
                'As condicionalidades do FUNDEB dialogam com a expansão de vagas, especialmente na educação infantil, e com a redução de exclusão e abandono. A rede municipal deve demonstrar esforço compatível com o financiamento recebido.'
            ),
            'situacao' => $situacao,
            'status' => $status,
            'evidencias' => $evidencias,
            'gaps' => [
                __('Priorizar matrícula na faixa etária adequada e acompanhar distorção idade/série (Censo/INEP).'),
                __('Se a taxa de ociosidade for alta, revisar turnos, transporte ou remanejamento de professores para equilibrar oferta e demanda.'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $attendanceData
     * @param  array<string, mixed>  $enrollmentData
     * @param  array<string, mixed>  $performanceData
     */
    private function moduleFrequenciaFluxo(array $attendanceData, array $enrollmentData, array $performanceData): array
    {
        $msg = (string) ($attendanceData['message'] ?? '');
        $chart = $attendanceData['chart'] ?? null;
        $charts = $attendanceData['charts'] ?? [];
        $rows = $attendanceData['rows'] ?? [];
        $hasSignal = ($chart !== null && $chart !== []) || (is_array($charts) && $charts !== []) || (is_array($rows) && $rows !== []);

        $temDistorcao = false;
        foreach (($enrollmentData['charts'] ?? []) as $c) {
            $t = strtolower((string) ($c['title'] ?? ''));
            if (str_contains($t, 'distor')) {
                $temDistorcao = true;
                break;
            }
        }
        $temPerformance = ! empty($performanceData['charts']) || ! empty($performanceData['kpis']);

        $evidencias = [];
        if ($hasSignal) {
            $evidencias[] = __('Registros de frequência (faltas) agregados no período do filtro, quando a tabela existe na base.');
        }
        if ($temDistorcao) {
            $evidencias[] = __('Indicador de distorção idade/série na aba Matrículas apoia análise de fluxo e reprovação.');
        }
        if ($temPerformance) {
            $evidencias[] = __('Situações de matrícula na aba Desempenho ajudam a identificar reprovações e movimentações.');
        }

        $status = 'neutral';
        $situacao = __(
            'Frequência escolar e permanência são monitoradas localmente; o VAAR pode pedir ações quando há índices críticos de abandono ou infrequência.'
        );

        if (str_contains(mb_strtolower($msg), 'falta') || str_contains(mb_strtolower($msg), 'tabela')) {
            $status = 'danger';
            $situacao = __('A base não expõe registros utilizáveis de faltas (falta_aluno) ou colunas não coincidem com config/ieducar.php.');
        } elseif ($hasSignal) {
            $status = 'success';
            $situacao = __('Há série temporal ou agregado de faltas na base filtrada — use para planos de recuperação de frequência.');
        } elseif ($temPerformance || $temDistorcao) {
            $status = 'warning';
            $situacao = __('Há indícios de fluxo (distorção ou situação de matrícula), mas não gráfico de faltas completo.');
        }

        $gaps = [
            __('Implementar ou corrigir o registro diário de frequência no i-Educar para monitorar infrequência e abandono.'),
            __('Articular com equipe pedagógica planos de recuperação alinhados ao que for exigido no Simec no ciclo do VAAR.'),
        ];

        return [
            'id' => 'frequencia-fluxo',
            'title' => __('Frequência escolar e fluxo (permanência)'),
            'reference' => __('Permanência e combate ao abandono (referência às condicionalidades pedagógicas)'),
            'explanation' => __(
                'A rede deve demonstrar esforços para reduzir abandono e infrequência, com políticas claras de acompanhamento. O financiamento pode estar vinculado a metas de fluxo em alguns instrumentos de planejamento.'
            ),
            'situacao' => $situacao,
            'status' => $status,
            'evidencias' => $evidencias,
            'gaps' => $gaps,
        ];
    }

    /**
     * @return array{id: string, title: string, reference: string, explanation: string, situacao: string, status: 'success'|'warning'|'neutral'|'danger', evidencias: list<string>, gaps: list<string>}
     */
    private function moduleTransporteAlimentacao(): array
    {
        return [
            'id' => 'transporte-alimentacao',
            'title' => __('Transporte escolar e alimentação escolar'),
            'reference' => __('Programas complementares (PDDE, PNATE, roteiros locais — referência)'),
            'explanation' => __(
                'O transporte do estudante e a alimentação escolar são áreas frequentemente vinculadas a programas federais e a prestação de contas próprias. Os dados operacionais (rotas, cardápios, quantidades servidas) costumam ficar fora do núcleo acadêmico do i-Educar.'
            ),
            'situacao' => __(
                'Este painel não lê automaticamente rotas de transporte nem merenda; a comprovação segue normas do FNDE e do município.'
            ),
            'status' => 'neutral',
            'evidencias' => [],
            'gaps' => [
                __('Conferir no Simec e no Portal FNDE os relatórios exigidos para transporte e alimentação no exercício financeiro correspondente.'),
                __('Garantir documentação de contratos, fretes, quilometragem ou prestação de serviço de alimentação conforme auditorias locais.'),
            ],
        ];
    }

    /**
     * @return array{id: string, title: string, reference: string, explanation: string, situacao: string, status: 'success'|'warning'|'neutral'|'danger', evidencias: list<string>, gaps: list<string>}
     */
    private function moduleTransparenciaFundeb(): array
    {
        return [
            'id' => 'transparencia-fundeb',
            'title' => __('Transparência e gestão dos recursos do FUNDEB'),
            'reference' => __('Planejamento, prestação de contas e publicidade (referência às regras do FUNDEB)'),
            'explanation' => __(
                'Estados e municípios devem aplicar os recursos conforme a legislação, com transparência e controle social. O VAAR reforça vínculos entre resultados educacionais e repasses.'
            ),
            'situacao' => __(
                'Dados financeiros do FUNDEB não são tratados neste painel; consulte o Tesouro Transparente, o portal do FNDE e os relatórios do tribunal de contas local.'
            ),
            'status' => 'neutral',
            'evidencias' => [],
            'gaps' => [
                __('Publicar de forma clara a aplicação dos recursos por programa e escola, quando exigido pela lei de responsabilidade fiscal e normas do fundo.'),
                __('Manter alinhamento entre metas pedagógicas (este painel) e o plano plurianual de educação.'),
            ],
        ];
    }
}
