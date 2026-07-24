<?php

namespace App\Services\Clio\Bi;

/**
 * Textos profissionais para gestores educacionais (zero PII).
 */
final class ClioBiInsightComposer
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array{code: string, severity: string, title: string, body: string, metric_value: ?string, sort: int}>
     */
    public function compose(array $snapshot): array
    {
        $insights = [];
        $triade = $snapshot['triade_pct'] ?? null;
        $schoolsActive = (int) ($snapshot['schools_active'] ?? 0);
        $incomplete = (int) ($snapshot['schools_incomplete_triad'] ?? 0);
        $errors = (int) ($snapshot['findings_errors'] ?? 0);
        $distPct = $snapshot['distortion_pct'] ?? null;
        $density = $snapshot['density_avg'] ?? null;
        $ge40 = (int) ($snapshot['turmas_ge_40'] ?? 0);
        $semDoc = (int) ($snapshot['turmas_sem_docente'] ?? 0);
        $nee = (int) ($snapshot['nee_people'] ?? 0);
        $neeSemAee = (int) ($snapshot['nee_without_aee'] ?? 0);
        $aeeSemNee = (int) ($snapshot['aee_without_nee'] ?? 0);
        $delta = $snapshot['delta_rede'] ?? null;
        $traRuralPct = $snapshot['tra_rural_pct_active'] ?? null;
        $gapClio = (int) ($snapshot['gap_clio_only'] ?? 0);
        $gapIe = (int) ($snapshot['gap_ieducar_only'] ?? 0);

        if (is_numeric($triade)) {
            $sev = ((float) $triade) >= 90 ? 'info' : (((float) $triade) >= 70 ? 'warning' : 'error');
            $insights[] = [
                'code' => 'TRIAD',
                'severity' => $sev,
                'title' => __('Cobertura da tríade de arquivos'),
                'body' => __('Nas escolas em atividade, :p% têm a tríade completa (alunos, turmas e profissionais). :n unidade(s) ainda incompleta(s) — priorize o envio dos CSV em falta para fechar a Matrícula inicial.', [
                    'p' => number_format((float) $triade, 1, ',', '.'),
                    'n' => $incomplete,
                ]),
                'metric_value' => number_format((float) $triade, 1, ',', '.').'%',
                'sort' => 10,
            ];
        }

        if ($errors > 0) {
            $insights[] = [
                'code' => 'ERRORS',
                'severity' => 'error',
                'title' => __('Inconsistências a corrigir'),
                'body' => __('Há :n apontamento(s) classificado(s) como erro na coleta. Trate-os antes do fechamento no portal Educacenso — impactam conferência e eventual carga no i-Educar.', [
                    'n' => $errors,
                ]),
                'metric_value' => (string) $errors,
                'sort' => 20,
            ];
        }

        if (is_numeric($distPct)) {
            $sev = ((float) $distPct) >= 20 ? 'warning' : 'info';
            $insights[] = [
                'code' => 'DISTORTION',
                'severity' => $sev,
                'title' => __('Distorção idade-série (estimativa)'),
                'body' => __('Estimativa alinhada ao critério INEP (≥ margem de anos acima da idade esperada em 31/03) no escopo EF/EM: :p%. Use a tabela por etapa para priorizar fluxos de progressão e recuperação.', [
                    'p' => number_format((float) $distPct, 1, ',', '.'),
                ]),
                'metric_value' => number_format((float) $distPct, 1, ',', '.').'%',
                'sort' => 30,
            ];
        }

        if (is_numeric($density) || $ge40 > 0) {
            $insights[] = [
                'code' => 'DENSITY',
                'severity' => $ge40 > 0 ? 'warning' : 'info',
                'title' => __('Densidade das turmas curriculares'),
                'body' => __('Média de :m aluno(s) por turma curricular (AEE/AC fora do denominador). :n turma(s) com 40 ou mais alunos — valide composição e capacidade física.', [
                    'm' => is_numeric($density) ? number_format((float) $density, 1, ',', '.') : '—',
                    'n' => $ge40,
                ]),
                'metric_value' => is_numeric($density) ? number_format((float) $density, 1, ',', '.') : null,
                'sort' => 40,
            ];
        }

        if ($semDoc > 0) {
            $insights[] = [
                'code' => 'STAFF',
                'severity' => 'warning',
                'title' => __('Turmas sem profissional vinculado'),
                'body' => __('Há :n turma(s) curricular(es) sem vínculo na Relação de profissionais. Confirme cadastro e vínculo no portal antes do fechamento.', [
                    'n' => $semDoc,
                ]),
                'metric_value' => (string) $semDoc,
                'sort' => 50,
            ];
        }

        if ($nee > 0 || $neeSemAee > 0 || $aeeSemNee > 0) {
            $insights[] = [
                'code' => 'INCLUSION',
                'severity' => ($neeSemAee > 0 || $aeeSemNee > 0) ? 'warning' : 'info',
                'title' => __('Inclusão e atendimento educacional especializado'),
                'body' => __('Contagem por pessoa: :nee com marcador NEE/TEA/AH. Destes, :sem sem matrícula AEE identificada. Há ainda :aee matrícula(s) AEE sem condição tipificada — revise tipificação e oferta de AEE.', [
                    'nee' => $nee,
                    'sem' => $neeSemAee,
                    'aee' => $aeeSemNee,
                ]),
                'metric_value' => (string) $nee,
                'sort' => 60,
            ];
        }

        if (is_numeric($delta) && (int) $delta !== 0) {
            $insights[] = [
                'code' => 'DELTA',
                'severity' => 'warning',
                'title' => __('Diferença Acompanhamento × Relação de alunos'),
                'body' => __('O total curricular do arquivo geral e as linhas da Relação de alunos diferem em :d. Investigue escolas com delta e matrículas sem turma.', [
                    'd' => ((int) $delta > 0 ? '+' : '').(int) $delta,
                ]),
                'metric_value' => ((int) $delta > 0 ? '+' : '').(string) (int) $delta,
                'sort' => 70,
            ];
        }

        if (is_numeric($traRuralPct) && (float) $traRuralPct >= 50) {
            $insights[] = [
                'code' => 'TRANSPORT',
                'severity' => 'info',
                'title' => __('Transporte escolar em escolas rurais'),
                'body' => __('Entre usuários de transporte em escolas ativas, :p% estão em unidades rurais. Planeje rotas e frota com atenção à dispersão territorial.', [
                    'p' => number_format((float) $traRuralPct, 1, ',', '.'),
                ]),
                'metric_value' => number_format((float) $traRuralPct, 1, ',', '.').'%',
                'sort' => 80,
            ];
        }

        if ($gapClio > 0 || $gapIe > 0) {
            $insights[] = [
                'code' => 'GAP',
                'severity' => 'warning',
                'title' => __('Lacuna Clio × i-Educar'),
                'body' => __('Cruzamento: :c escola(s) só na coleta Clio e :i só no i-Educar. Priorize alinhamento de cadastro INEP antes de promover dados.', [
                    'c' => $gapClio,
                    'i' => $gapIe,
                ]),
                'metric_value' => $gapClio.'/'.$gapIe,
                'sort' => 90,
            ];
        }

        if ($insights === [] && $schoolsActive > 0) {
            $insights[] = [
                'code' => 'READY',
                'severity' => 'info',
                'title' => __('Coleta em condições de leitura gerencial'),
                'body' => __('Com base nos indicadores disponíveis, a rede (:n escola(s) ativa(s)) está pronta para acompanhamento da Matrícula inicial. Continue monitorando tríade e achados.', [
                    'n' => $schoolsActive,
                ]),
                'metric_value' => null,
                'sort' => 100,
            ];
        }

        usort($insights, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return $insights;
    }
}
