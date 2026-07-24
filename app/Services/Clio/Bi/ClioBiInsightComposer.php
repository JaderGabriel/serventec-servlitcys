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
        $neeScanned = (int) ($snapshot['nee_people_scanned'] ?? 0);
        $neeSemAee = (int) ($snapshot['nee_without_aee'] ?? 0);
        $aeeSemNee = (int) ($snapshot['aee_without_nee'] ?? 0);
        $delta = $snapshot['delta_rede'] ?? null;
        $traRuralPct = $snapshot['tra_rural_pct_active'] ?? null;
        $gapClio = (int) ($snapshot['gap_clio_only'] ?? 0);
        $gapIe = (int) ($snapshot['gap_ieducar_only'] ?? 0);
        $schoolHours = $snapshot['school_time_hours'] ?? null;
        $schoolHasCh = (bool) ($snapshot['school_time_has_ch'] ?? false);
        $schoolAvailable = (bool) ($snapshot['school_time_available'] ?? false);

        if (is_numeric($triade)) {
            $sev = ((float) $triade) >= 90 ? 'info' : (((float) $triade) >= 70 ? 'warning' : 'error');
            $insights[] = [
                'code' => 'TRIAD',
                'severity' => $sev,
                'title' => __('Cobertura da tríade de arquivos'),
                'body' => __('Nas escolas em atividade, :p% têm a tríade completa (alunos, turmas e profissionais). :n unidade(s) ainda incompleta(s) — priorize o envio dos CSV em falta para fechar a Matrícula inicial.', [
                    'p' => $this->pct((float) $triade),
                    'n' => $this->int($incomplete),
                ]),
                'metric_value' => $this->pct((float) $triade).'%',
                'sort' => 10,
            ];
        }

        if ($errors > 0) {
            $insights[] = [
                'code' => 'ERRORS',
                'severity' => 'error',
                'title' => __('Inconsistências a corrigir'),
                'body' => __('Há :n apontamento(s) classificado(s) como erro na coleta. Trate-os antes do fechamento no portal Educacenso — impactam conferência e eventual carga no i-Educar.', [
                    'n' => $this->int($errors),
                ]),
                'metric_value' => $this->int($errors),
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
                    'p' => $this->pct((float) $distPct),
                ]),
                'metric_value' => $this->pct((float) $distPct).'%',
                'sort' => 30,
            ];
        }

        if (is_numeric($density) || $ge40 > 0) {
            $insights[] = [
                'code' => 'DENSITY',
                'severity' => $ge40 > 0 ? 'warning' : 'info',
                'title' => __('Densidade das turmas curriculares'),
                'body' => $ge40 > 0
                    ? __('Média de :m aluno(s) por turma curricular (AEE/AC fora do denominador). :n turma(s) com 40 ou mais alunos — valide composição e capacidade física.', [
                        'm' => is_numeric($density) ? $this->pct((float) $density) : '—',
                        'n' => $this->int($ge40),
                    ])
                    : __('Média de :m aluno(s) por turma curricular (AEE/AC fora do denominador). Nenhuma turma com 40 ou mais alunos no recorte actual.', [
                        'm' => is_numeric($density) ? $this->pct((float) $density) : '—',
                    ]),
                'metric_value' => is_numeric($density) ? $this->pct((float) $density) : null,
                'sort' => 40,
            ];
        }

        if ($semDoc > 0) {
            $insights[] = [
                'code' => 'STAFF',
                'severity' => 'warning',
                'title' => __('Turmas sem profissional vinculado'),
                'body' => __('Há :n turma(s) curricular(es) sem vínculo na Relação de profissionais. Confirme cadastro e vínculo no portal antes do fechamento.', [
                    'n' => $this->int($semDoc),
                ]),
                'metric_value' => $this->int($semDoc),
                'sort' => 50,
            ];
        }

        if ($schoolHasCh && is_numeric($schoolHours)) {
            $insights[] = [
                'code' => 'SCHOOL_TIME',
                'severity' => 'info',
                'title' => __('Tempo escolar semanal dos alunos'),
                'body' => __('Média ponderada da rede: :h h/semana por aluno com carga identificada (coluna de Carga horária ou grade no Turno). Compare os segmentos no quadro de tempo escolar deste relatório.', [
                    'h' => $this->pct((float) $schoolHours),
                ]),
                'metric_value' => $this->pct((float) $schoolHours).'h',
                'sort' => 55,
            ];
        } elseif ($schoolAvailable && ! $schoolHasCh) {
            $insights[] = [
                'code' => 'SCHOOL_TIME',
                'severity' => 'warning',
                'title' => __('Tempo escolar sem carga horária'),
                'body' => __('Há Relações de turmas na coleta, mas sem carga horária legível nem grade no Turno. Peça o preenchimento de «Carga horária semanal» (ou horários no Turno) no export do Educacenso.'),
                'metric_value' => null,
                'sort' => 55,
            ];
        }

        if ($nee > 0 || $neeSemAee > 0 || $aeeSemNee > 0) {
            $pctNee = ($neeScanned > 0 && $nee > 0)
                ? round(100 * $nee / $neeScanned, 1)
                : null;
            $gapHeavy = $nee > 0 && $neeSemAee > 0 && ($neeSemAee / max(1, $nee)) >= 0.4;
            $insights[] = [
                'code' => 'INCLUSION',
                'severity' => ($neeSemAee > 0 || $aeeSemNee > 0) ? 'warning' : 'info',
                'title' => __('Inclusão e AEE'),
                'body' => __('Na Relação de alunos (pessoa única): :nee com marcador NEE, TEA ou AH:scanned. Destas, :sem sem matrícula AEE identificada:gap. Há ainda :aee pessoa(s) em AEE sem tipificação NEE/TEA/AH — revise oferta e declaração das condições.', [
                    'nee' => $this->int($nee),
                    'scanned' => $pctNee !== null
                        ? __(' (:p% das :t pessoas lidas)', [
                            'p' => $this->pct($pctNee),
                            't' => $this->int($neeScanned),
                        ])
                        : '',
                    'sem' => $this->int($neeSemAee),
                    'gap' => $gapHeavy
                        ? __(' — lacuna relevante de AEE')
                        : '',
                    'aee' => $this->int($aeeSemNee),
                ]),
                'metric_value' => $this->int($nee),
                'sort' => 60,
            ];
        }

        if (is_numeric($delta) && (int) $delta !== 0) {
            $insights[] = [
                'code' => 'DELTA',
                'severity' => 'warning',
                'title' => __('Diferença Acompanhamento × Relação de alunos'),
                'body' => __('O total curricular do arquivo geral e as linhas da Relação de alunos diferem em :d. Investigue escolas com delta e matrículas sem turma.', [
                    'd' => ((int) $delta > 0 ? '+' : '').$this->int((int) $delta),
                ]),
                'metric_value' => ((int) $delta > 0 ? '+' : '').$this->int((int) $delta),
                'sort' => 70,
            ];
        }

        if (is_numeric($traRuralPct) && (float) $traRuralPct >= 50) {
            $insights[] = [
                'code' => 'TRANSPORT',
                'severity' => 'info',
                'title' => __('Transporte escolar em escolas rurais'),
                'body' => __('Entre usuários de transporte em escolas ativas, :p% estão em unidades rurais. Planeje rotas e frota com atenção à dispersão territorial.', [
                    'p' => $this->pct((float) $traRuralPct),
                ]),
                'metric_value' => $this->pct((float) $traRuralPct).'%',
                'sort' => 80,
            ];
        }

        if ($gapClio > 0 || $gapIe > 0) {
            $insights[] = [
                'code' => 'GAP',
                'severity' => 'warning',
                'title' => __('Lacuna Clio × i-Educar'),
                'body' => __('Cruzamento: :c escola(s) só na coleta Clio e :i só no i-Educar. Priorize alinhamento de cadastro INEP antes de promover dados.', [
                    'c' => $this->int($gapClio),
                    'i' => $this->int($gapIe),
                ]),
                'metric_value' => $this->int($gapClio).'/'.$this->int($gapIe),
                'sort' => 90,
            ];
        }

        if ($insights === [] && $schoolsActive > 0) {
            $insights[] = [
                'code' => 'READY',
                'severity' => 'info',
                'title' => __('Coleta em condições de leitura gerencial'),
                'body' => __('Com base nos indicadores disponíveis, a rede (:n escola(s) ativa(s)) está pronta para acompanhamento da Matrícula inicial. Continue monitorando tríade e achados.', [
                    'n' => $this->int($schoolsActive),
                ]),
                'metric_value' => null,
                'sort' => 100,
            ];
        }

        usort($insights, static function (array $a, array $b): int {
            $rank = static fn (string $s): int => match ($s) {
                'error' => 0,
                'warning' => 1,
                default => 2,
            };
            $bySev = $rank((string) $a['severity']) <=> $rank((string) $b['severity']);

            return $bySev !== 0 ? $bySev : ($a['sort'] <=> $b['sort']);
        });

        return $insights;
    }

    private function int(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }

    private function pct(float $n): string
    {
        return number_format($n, 1, ',', '.');
    }
}
