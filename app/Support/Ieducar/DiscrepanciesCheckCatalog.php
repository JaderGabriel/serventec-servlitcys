<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;

/**
 * Metadados pedagógicos e de financiamento (FUNDEB / VAAR / Censo) por tipo de discrepância.
 */
final class DiscrepanciesCheckCatalog
{
    /**
     * Conteúdo do modal «Condições que implicam perda de recursos» (sem consulta à base).
     *
     * @return array{
     *   conditions: list<array<string, mixed>>,
     *   pillars: list<array<string, mixed>>,
     *   complementary_programs: list<array<string, mixed>>,
     *   public_repasses: list<array<string, mixed>>,
     *   vaa_label: string,
     *   aviso: string
     * }
     */
    public static function modalPayload(?City $city = null, ?IeducarFilterState $filters = null): array
    {
        $pesos = config('ieducar.discrepancies.peso_por_check', []);
        $ref = DiscrepanciesFundingImpact::resolveReference($city, $filters);
        $calc = FundebMunicipalReferenceResolver::vaafParaCalculo($city, $filters);
        $vaa = (float) $calc['vaaf'];
        $conditions = [];
        foreach (self::definitions() as $id => $def) {
            $peso = 1.0;
            if (is_array($pesos) && isset($pesos[$id])) {
                $peso = max(0.0, (float) $pesos[$id]);
            }
            $conditions[] = array_merge($def, [
                'peso' => $peso,
                'peso_label' => number_format($peso, 2, ',', '.'),
                'impacto_financeiro' => __(
                    'Cada ocorrência corrigível representa, em média, até :valor de ganho potencial indicativo (VAAF × peso :p).',
                    [
                        'valor' => DiscrepanciesFundingImpact::formatBrl($vaa * $peso),
                        'p' => number_format($peso, 2, ',', '.'),
                    ]
                ),
            ]);
        }

        return [
            'conditions' => $conditions,
            'pillars' => DiscrepanciesFundingImpact::fundingPillars(),
            'complementary_programs' => self::complementaryProgramsForModal(),
            'public_repasses' => self::publicRepassesForModal(),
            'vaa_label' => DiscrepanciesFundingImpact::formatBrl($vaa),
            'vaa_fonte_label' => (string) ($calc['fonte_label'] ?? $ref['fonte_label'] ?? ''),
            'vaa_ano' => $ref['ano'] ?? null,
            'aviso' => (string) config('ieducar.discrepancies.aviso_financeiro', ''),
        ];
    }

    /**
     * Programas complementares ao FUNDEB (PNAE, PNATE, PDDE, etc.) — texto pedagógico do modal.
     *
     * @return list<array<string, mixed>>
     */
    public static function complementaryProgramsForModal(): array
    {
        $extras = self::complementaryProgramPedagogy();
        $config = config('ieducar.other_funding.programs', []);
        if (! is_array($config)) {
            return [];
        }

        $out = [];
        foreach ($config as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = (string) ($item['id'] ?? '');
            $extra = is_array($extras[$id] ?? null) ? $extras[$id] : [];
            $out[] = [
                'id' => $id,
                'titulo' => (string) ($item['titulo'] ?? $id),
                'descricao' => (string) ($item['descricao'] ?? ''),
                'explanation' => (string) ($extra['explanation'] ?? $item['descricao'] ?? ''),
                'impact' => (string) ($extra['impact'] ?? ''),
                'correction' => (string) ($extra['correction'] ?? ''),
                'cadastro_ligacao' => (string) ($extra['cadastro_ligacao'] ?? ''),
                'repasse_fonte' => (string) ($extra['repasse_fonte'] ?? 'FNDE'),
                'fnde_url' => (string) ($item['fnde_url'] ?? ''),
                'related_checks' => is_array($extra['related_checks'] ?? null) ? $extra['related_checks'] : [],
            ];
        }

        return $out;
    }

    /**
     * Repasses e consultas públicas referenciadas no painel (informativo).
     *
     * @return list<array<string, mixed>>
     */
    public static function publicRepassesForModal(): array
    {
        return [
            [
                'id' => 'fnde-fundeb',
                'titulo' => __('FUNDEB — financiamento básico'),
                'descricao' => __(
                    'Principal fonte por aluno/ano (VAAF). Depende de matrículas válidas no Censo. Complementação VAAR/VAAT exige condicionalidades (inclusão, equidade, indicadores).'
                ),
                'onde' => __('Abas FUNDEB, Serventec, Discrepâncias; import FNDE no admin.'),
            ],
            [
                'id' => 'tesouro',
                'titulo' => __('Tesouro Transparente — transferências'),
                'descricao' => __(
                    'Repasses constitucionais e obrigatórios da União ao município (podem incluir parcelas de educação). O painel amostra registros filtrados por IBGE — não substitui o portal oficial.'
                ),
                'onde' => __('Aba Financiamentos — consulta automática CKAN.'),
            ],
            [
                'id' => 'portal-transparencia',
                'titulo' => __('Portal da Transparência — despesas federais'),
                'descricao' => __(
                    'Despesas e transferências com órgãos federais no município. Requer chave de API. Útil para cruzar execução com programas FNDE (PNAE, PNATE, etc.).'
                ),
                'onde' => __('Aba Financiamentos — requer PORTAL_TRANSPARENCIA_API_KEY.'),
            ],
            [
                'id' => 'simec',
                'titulo' => __('Simec / VAAR — comprovação'),
                'descricao' => __(
                    'Não há API pública: gestor municipal comprova condicionalidades no Simec. Cadastro incompleto no i-Educar antecipa diligências e risco de perda de complementação.'
                ),
                'onde' => __('Links na aba Serventec e Financiamentos; sem leitura automática.'),
            ],
        ];
    }

    /**
     * @return array<string, array{explanation: string, impact: string, correction: string, cadastro_ligacao: string, repasse_fonte: string, related_checks: list<string>}>
     */
    private static function complementaryProgramPedagogy(): array
    {
        return [
            'pnate' => [
                'explanation' => __(
                    'O PNATE financia transporte escolar. O FNDE e o Censo usam o cadastro de utilização de transporte nas matrículas e a oferta de rotas/turmas. Campos vazios ou incoerentes impedem planeamento e podem gerar glosa na prestação de contas.'
                ),
                'impact' => __(
                    'Subnotificação de alunos elegíveis → subdimensionamento de repasse de transporte; inconsistência com Censo pode bloquear contagem de alunos transportados.'
                ),
                'correction' => __(
                    'Preencher transporte escolar nas matrículas; validar rotas e frota no processo municipal; alinhar com exportação Educacenso.'
                ),
                'cadastro_ligacao' => __('Matrícula: transporte_escolar, uso_transporte_escolar (quando existirem na base).'),
                'repasse_fonte' => 'FNDE / PNATE',
                'related_checks' => ['escola_sem_geo', 'matricula_situacao_invalida'],
            ],
            'pnae' => [
                'explanation' => __(
                    'O PNAE financia alimentação escolar. A elegibilidade depende de matrículas activas e, em muitos municípios, de campos de alimentação ou tipo de atendimento no i-Educar e no Censo.'
                ),
                'impact' => __(
                    'Alunos sem vínculo de alimentação no cadastro podem ficar fora do planeamento de merenda; risco de questionamento em auditoria do FNDE e custos municipais não reembolsados.'
                ),
                'correction' => __(
                    'Atualizar campos de alimentação/atendimento nas matrículas; conferir cardápio e prestação de contas no FNDE (fora do i-Educar).'
                ),
                'cadastro_ligacao' => __('Matrícula: alimentacao_escolar, tipo_atendimento (detecção automática por coluna).'),
                'repasse_fonte' => 'FNDE / PNAE',
                'related_checks' => ['sem_data_nascimento', 'matricula_duplicada'],
            ],
            'pdde' => [
                'explanation' => __(
                    'O PDDE repassa recursos diretamente às escolas (custeio e capital). Exige escola com código INEP válido, situação activa e matrículas consistentes no Censo.'
                ),
                'impact' => __(
                    'Escola sem INEP ou com matrículas inválidas pode não receber PDDE ou ter prestação de contas rejeitada; afeta autonomia financeira da unidade.'
                ),
                'correction' => __(
                    'Regularizar INEP e situação da escola; corrigir matrículas antes do fecho do Censo; prestação de contas no Simec/FNDE.'
                ),
                'cadastro_ligacao' => __('Escola INEP + matrículas activas (rotinas escola_sem_inep, matricula_situacao_invalida).'),
                'repasse_fonte' => 'FNDE / PDDE',
                'related_checks' => ['escola_sem_inep', 'escola_inativa_matricula', 'matricula_duplicada'],
            ],
            'pdde-qualidade' => [
                'explanation' => __(
                    'O PDDE Qualidade complementa o PDDE para acções prioritárias de qualidade. A comprovação passa pelo Simec/FNDE e pressupõe o mesmo cadastro escolar/matrícula fiável do PDDE base.'
                ),
                'impact' => __(
                    'Indicadores e planos de acção incoerentes com o cadastro municipal podem impedir liberação ou gerar devolução de recursos.'
                ),
                'correction' => __(
                    'Alinhar plano da escola ao cadastro i-Educar; corrigir pendências de INEP e matrícula antes de solicitar recursos.'
                ),
                'cadastro_ligacao' => __('Mesmas exigências do PDDE + indicadores pedagógicos (aba Desempenho quando disponível).'),
                'repasse_fonte' => 'FNDE / PDDE Qualidade',
                'related_checks' => ['escola_sem_inep', 'distorcao_idade_serie'],
            ],
            'salario-educacao' => [
                'explanation' => __(
                    'O Salário-educação financia a educação básica via contribuição social. Não depende de campos específicos no i-Educar, mas o volume de matrículas válidas no Censo influencia a distribuição entre entes.'
                ),
                'impact' => __(
                    'Matrículas não declaradas ou duplicadas no Censo reduzem a base de distribuição per capita e a credibilidade da rede perante o FNDE.'
                ),
                'correction' => __(
                    'Priorizar cadastro completo e Censo exportado; corrigir duplicidades e situação «em curso» das matrículas.'
                ),
                'cadastro_ligacao' => __('Todas as rotinas de matrícula válida no Censo (FUNDEB base).'),
                'repasse_fonte' => __('Contribuição social / FNDE'),
                'related_checks' => ['matricula_duplicada', 'sem_raca', 'sem_sexo'],
            ],
        ];
    }

    /**
     * @return array<string, array{
     *   id: string,
     *   title: string,
     *   explanation: string,
     *   impact: string,
     *   correction: string,
     *   severity: string,
     *   vaar_refs: list<string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            'sem_raca' => [
                'id' => 'sem_raca',
                'title' => __('Matrículas sem cor/raça declarada'),
                'explanation' => __(
                    'O Censo Escolar e indicadores de equidade (VAAR/FUNDEB) exigem cor/raça no cadastro do aluno. Matrículas sem vínculo em fisica_raca ou pessoa ficam como «não declarado» e podem subestimar a rede em políticas afirmativas.'
                ),
                'impact' => __(
                    'Perda indicativa no eixo equidade do VAAR e risco de glosa na validação do Educacenso; matrícula pode não compor indicadores de inclusão e equidade.'
                ),
                'correction' => __('Atualizar cor/raça no cadastro (fisica_raca / pessoa) e reexportar ao Censo.'),
                'severity' => 'danger',
                'vaar_refs' => ['Censo — cor/raça', 'VAAR — equidade'],
            ],
            'sem_sexo' => [
                'id' => 'sem_sexo',
                'title' => __('Matrículas sem sexo declarado'),
                'explanation' => __(
                    'O Educacenso exige sexo do aluno. Cadastro em pessoa ou fisica sem preenchimento impede exportação completa da matrícula.'
                ),
                'impact' => __(
                    'Matrícula incompleta no Censo pode ser rejeitada ou excluída da contagem oficial, reduzindo o universo FUNDEB da rede.'
                ),
                'correction' => __('Preencher sexo em pessoa/fisica no i-Educar.'),
                'severity' => 'warning',
                'vaar_refs' => ['Censo — sexo', 'VAAR — equidade'],
            ],
            'sem_data_nascimento' => [
                'id' => 'sem_data_nascimento',
                'title' => __('Matrículas sem data de nascimento'),
                'explanation' => __(
                    'Data de nascimento é obrigatória no Censo e base para distorção idade/série, transporte e PNAE. Ausência indica cadastro incompleto.'
                ),
                'impact' => __(
                    'Impede cálculos de idade-série e pode bloquear validação da matrícula no Educacenso; afeta planeamento de custos por faixa etária.'
                ),
                'correction' => __('Registrar data de nascimento em fisica/pessoa.'),
                'severity' => 'danger',
                'vaar_refs' => ['Censo — identificação', 'FUNDEB — matrícula válida'],
            ],
            'nee_sem_aee' => [
                'id' => 'nee_sem_aee',
                'title' => __('NEE cadastrado sem turma AEE identificada'),
                'explanation' => __(
                    'Alunos com deficiência/NEE na base, sem matrícula em turma cujo nome ou curso sugira AEE (palavras-chave em config/ieducar.php).'
                ),
                'impact' => __(
                    'Subnotificação de atendimento especializado — condicionalidade VAAR de inclusão e comprovação de oferta de AEE.'
                ),
                'correction' => __('Matricular em turma AEE ou documentar atendimento integrado; ajustar cadastro NEE se incorreto.'),
                'severity' => 'warning',
                'vaar_refs' => ['VAAR — inclusão', 'VAAR — educação especial'],
            ],
            'aee_sem_nee' => [
                'id' => 'aee_sem_nee',
                'title' => __('Turma AEE sem cadastro de NEE no aluno'),
                'explanation' => __(
                    'Matrículas em turmas AEE (heurística por nome) sem registro em fisica_deficiencia / aluno_deficiencia.'
                ),
                'impact' => __(
                    'Inconsistência Censo (turma × deficiência) e risco de questionamento em auditoria de educação especial.'
                ),
                'correction' => __('Registrar NEE no aluno ou corrigir nomenclatura da turma.'),
                'severity' => 'warning',
                'vaar_refs' => ['VAAR — inclusão', 'Censo — deficiência'],
            ],
            'nee_subnotificacao' => [
                'id' => 'nee_subnotificacao',
                'title' => __('Possível subnotificação de NEE na rede'),
                'explanation' => __(
                    'A proporção de matrículas com NEE cadastrado está abaixo do patamar de referência configurável (benchmark nacional/municipal). Pode indicar subnotificação no Censo e perda de peso no VAAR de inclusão.'
                ),
                'impact' => __(
                    'Subnotificação de NEE reduz comprovação de políticas de inclusão e pode limitar condicionalidades do VAAR ligadas à educação especial.'
                ),
                'correction' => __('Revisar cadastro de deficiências, busca ativa e atualização antes da exportação ao Censo.'),
                'severity' => 'danger',
                'vaar_refs' => ['VAAR — inclusão', 'FUNDEB — complementação VAAR'],
            ],
            'escola_sem_inep' => [
                'id' => 'escola_sem_inep',
                'title' => __('Escola com matrículas e sem código INEP'),
                'explanation' => __(
                    'Unidades com alunos matriculados sem código INEP em escola ou educacenso_cod_escola.'
                ),
                'impact' => __(
                    'Unidade fora de cruzamentos SAEB/IDEB e repasses por escola; alto impacto em indicadores VAAR.'
                ),
                'correction' => __('Preencher cod_escola_inep ou coluna INEP na escola.'),
                'severity' => 'danger',
                'vaar_refs' => ['VAAR — indicadores INEP', 'Censo — escola'],
            ],
            'escola_inativa_matricula' => [
                'id' => 'escola_inativa_matricula',
                'title' => __('Escola inativa com matrículas ativas'),
                'explanation' => __(
                    'Escola marcada inativa na base, mas com matrículas ativas no filtro.'
                ),
                'impact' => __(
                    'Distorce contagem de rede e repasses; Censo pode rejeitar vínculo com unidade inativa.'
                ),
                'correction' => __('Reativar escola, transferir ou encerrar matrículas.'),
                'severity' => 'danger',
                'vaar_refs' => ['Censo — situação da escola', 'FUNDEB — matrícula'],
            ],
            'recurso_prova_sem_nee' => [
                'id' => 'recurso_prova_sem_nee',
                'title' => __('Recurso de prova INEP sem cadastro de NEE'),
                'explanation' => __(
                    'Matrículas com recurso de prova registado (aba Recursos prova INEP / tabela detectada na base) sem vínculo em fisica_deficiencia ou aluno_deficiencia. Ex.: óculos ou tempo adicional na prova sem caracterização de deficiência — pode ser legítimo ou omissão no Censo; requer revisão pedagógica.'
                ),
                'impact' => __(
                    'Inconsistência entre apoio declarado para avaliações INEP e educação especial no cadastro; risco em validação do Educacenso e no eixo inclusão do VAAR.'
                ),
                'correction' => __('Confirmar se o aluno tem NEE e registar deficiência, ou remover recurso se não aplicável; alinhar antes da exportação ao Censo.'),
                'severity' => 'warning',
                'vaar_refs' => ['Censo — recursos de avaliação', 'VAAR — inclusão'],
            ],
            'nee_sem_recurso_prova' => [
                'id' => 'nee_sem_recurso_prova',
                'title' => __('NEE cadastrado sem recurso de prova INEP'),
                'explanation' => __(
                    'Alunos com deficiência/NEE no cadastro mas sem recurso de prova registado, quando a rede exige coerência Censo (IEDUCAR_INCLUSION_RECURSO_EXIGIR_COM_NEE=true).'
                ),
                'impact' => __(
                    'Possível omissão de recursos necessários na avaliação INEP; pode afetar prestação de apoio e consistência do Educacenso.'
                ),
                'correction' => __('Registar recursos de prova na ficha do aluno ou rever cadastro NEE se incorreto.'),
                'severity' => 'warning',
                'vaar_refs' => ['Censo — recursos de avaliação', 'VAAR — inclusão'],
            ],
            'recurso_prova_incompativel' => [
                'id' => 'recurso_prova_incompativel',
                'title' => __('Recurso de prova incompatível com deficiência cadastrada'),
                'explanation' => __(
                    'Combinação de tipo de recurso e deficiência que não corresponde às regras configuradas (ex.: recurso de surdez sem deficiência auditiva no catálogo).'
                ),
                'impact' => __(
                    'Sinal de erro de cadastro ou troca de vínculos; pode gerar questionamento em auditoria do Censo.'
                ),
                'correction' => __('Ajustar recurso de prova ou tipo de deficiência para refletir o perfil do aluno.'),
                'severity' => 'warning',
                'vaar_refs' => ['Censo — recursos de avaliação', 'Censo — deficiência'],
            ],
            'escola_sem_geo' => [
                'id' => 'escola_sem_geo',
                'title' => __('Escola sem posição no mapa'),
                'explanation' => __(
                    'Unidades no âmbito do filtro que não obtêm marcador no mapa de Cadastro → Unidades (lat/lng i-Educar, cache school_unit_geos ou consulta INEP em tempo real). O total de escolas coincide com escolas_no_escopo − total_com_coordenadas do painel de Unidades.'
                ),
                'impact' => __(
                    'Afeta mapas, transporte e cruzamentos territoriais; dificulta comprovação de oferta por território e alinha com a distribuição geográfica do painel.'
                ),
                'correction' => __('Preencher coordenadas na escola, sincronizar INEP (Admin → Geo) ou importar coordenadas oficiais para school_unit_geos.'),
                'severity' => 'warning',
                'vaar_refs' => ['Censo — localização', 'Programas — transporte'],
            ],
            'matricula_duplicada' => [
                'id' => 'matricula_duplicada',
                'title' => __('Aluno com mais de uma matrícula ativa'),
                'explanation' => __(
                    'Mesmo aluno com duas ou mais matrículas ativas no filtro (turmas/escolas distintas). Situação típica de erro de transferência ou duplicidade no Censo.'
                ),
                'impact' => __(
                    'Inflaciona matrícula e pode gerar dupla contagem no Educacenso até regularização — impacto direto no FUNDEB.'
                ),
                'correction' => __('Encerrar matrícula duplicada ou concluir transferência no i-Educar.'),
                'severity' => 'danger',
                'vaar_refs' => ['Censo — matrícula única', 'FUNDEB — contagem'],
            ],
            'matricula_situacao_invalida' => [
                'id' => 'matricula_situacao_invalida',
                'title' => __('Matrícula fora da situação «em curso» (INEP)'),
                'explanation' => __(
                    'Matrículas contabilizadas como ativas no filtro, mas com situação pedagógica diferente de «em curso» (código INEP 1 ou equivalente configurado).'
                ),
                'impact' => __(
                    'Matrícula não deve compor Censo como cursando; risco de glosa e distorção de indicadores de fluxo.'
                ),
                'correction' => __('Ajustar situação da matrícula ou encerrar registro conforme realidade.'),
                'severity' => 'warning',
                'vaar_refs' => ['Censo — situação da matrícula', 'INEP'],
            ],
            'matricula_censo_vs_ieducar' => [
                'id' => 'matricula_censo_vs_ieducar',
                'title' => __('analytics.discrepancies.censo_vs_ieducar_title'),
                'explanation' => __('analytics.discrepancies.censo_vs_ieducar_explanation'),
                'impact' => __('analytics.discrepancies.censo_vs_ieducar_impact'),
                'correction' => __('analytics.discrepancies.censo_vs_ieducar_correction'),
                'severity' => 'danger',
                'vaar_refs' => ['Censo — matrícula', 'FUNDEB — contagem'],
            ],
            'distorcao_idade_serie' => [
                'id' => 'distorcao_idade_serie',
                'title' => __('Distorção idade/série (critério INEP)'),
                'explanation' => __(
                    'Matrículas em que a idade do aluno excede a idade máxima esperada para a série/ano (critério automático já usado na aba Matrículas).'
                ),
                'impact' => __(
                    'Não exclui matrícula do FUNDEB automaticamente, mas sinaliza inconsistência cadastral e fluxo escolar para Censo e gestão.'
                ),
                'correction' => __('Rever série, data de nascimento e histórico escolar; regularizar matrícula.'),
                'severity' => 'warning',
                'vaar_refs' => ['Censo — série/idade', 'Gestão pedagógica'],
            ],
        ];
    }
}
