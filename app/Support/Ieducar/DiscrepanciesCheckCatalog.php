<?php

namespace App\Support\Ieducar;

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
     *   vaa_label: string,
     *   aviso: string
     * }
     */
    public static function modalPayload(): array
    {
        $pesos = config('ieducar.discrepancies.peso_por_check', []);
        $vaa = (float) config('ieducar.discrepancies.vaa_referencia_anual', 4500);
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
            'vaa_label' => DiscrepanciesFundingImpact::formatBrl($vaa),
            'aviso' => (string) config('ieducar.discrepancies.aviso_financeiro', ''),
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
                    'Matrículas em turmas AEE (heurística por nome) sem registo em fisica_deficiencia / aluno_deficiencia.'
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
            'escola_sem_geo' => [
                'id' => 'escola_sem_geo',
                'title' => __('Escola sem geolocalização com matrículas'),
                'explanation' => __(
                    'Unidades com matrículas no filtro sem latitude/longitude na tabela escola (quando as colunas existem na base).'
                ),
                'impact' => __(
                    'Afeta mapas, transporte e cruzamentos territoriais; dificulta comprovação de oferta por território.'
                ),
                'correction' => __('Preencher coordenadas na escola ou vincular INEP para geocodificação.'),
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
                'correction' => __('Ajustar situação da matrícula ou encerrar registo conforme realidade.'),
                'severity' => 'warning',
                'vaar_refs' => ['Censo — situação da matrícula', 'INEP'],
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
