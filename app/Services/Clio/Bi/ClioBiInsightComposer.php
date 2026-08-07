<?php

namespace App\Services\Clio\Bi;

/**
 * Textos profissionais para gestores educacionais (zero PII).
 * Alinhados às métricas do Excel de filtros operacionais (CLI-XLS).
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
        $neeWithK = (int) ($snapshot['nee_with_k_rows'] ?? 0);
        $neeWithL = (int) ($snapshot['nee_with_l_rows'] ?? 0);
        $neeLSemK = (int) ($snapshot['nee_l_without_k'] ?? 0);
        $schoolsAptas = (int) ($snapshot['schools_aptas'] ?? $schoolsActive);
        $schoolsFora = (int) ($snapshot['schools_fora'] ?? 0);
        $pnateElegivel = (int) ($snapshot['pnate_elegivel'] ?? 0);
        $pnateExcluido = (int) ($snapshot['pnate_excluido'] ?? 0);
        $pnateSem = (int) ($snapshot['pnate_sem_transporte'] ?? 0);
        $pnateHasRes = (bool) ($snapshot['pnate_has_residence'] ?? false);
        $tempoPleno = (int) ($snapshot['tempo_integral_pleno'] ?? 0);
        $tempoProxy = (int) ($snapshot['tempo_integral_proxy'] ?? 0);
        $turmasParcial = (int) ($snapshot['turmas_parcial'] ?? 0);
        $turmasIntegral = (int) ($snapshot['turmas_integral'] ?? 0);
        $alertAc = (int) ($snapshot['alert_ac_lt15'] ?? 0);
        $alertEja = (int) ($snapshot['alert_eja_lt20'] ?? 0);
        $delta = $snapshot['delta_rede'] ?? null;
        $traRuralPct = $snapshot['tra_rural_pct_active'] ?? null;
        $gapClio = (int) ($snapshot['gap_clio_only'] ?? 0);
        $gapIe = (int) ($snapshot['gap_ieducar_only'] ?? 0);
        $schoolHours = $snapshot['school_time_hours'] ?? null;
        $schoolHasCh = (bool) ($snapshot['school_time_has_ch'] ?? false);
        $schoolAvailable = (bool) ($snapshot['school_time_available'] ?? false);
        $withoutCor = (int) ($snapshot['without_cor'] ?? 0);
        $demScanned = (int) ($snapshot['dem_scanned'] ?? 0);
        $alunosSemTurma = (int) ($snapshot['alunos_sem_turma'] ?? 0);

        if ($schoolsAptas > 0 || $schoolsFora > 0) {
            $insights[] = [
                'code' => 'APTAS',
                'severity' => $schoolsFora > 0 ? 'info' : 'info',
                'title' => __('Escolas aptas (filtros operacionais)'),
                'body' => __('No Excel de filtros: :a escola(s) apta(s) no arquivo geral (municipal ou filantrópica com parceria municipal, em atividade, localização válida). :f unidade(s) ficam fora do escopo operacional — veja a aba 11-Fora do escopo.', [
                    'a' => $this->int($schoolsAptas),
                    'f' => $this->int($schoolsFora),
                ]),
                'metric_value' => $this->int($schoolsAptas),
                'sort' => 5,
            ];
        }

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

        if ($alunosSemTurma > 0) {
            $insights[] = [
                'code' => 'ENTURMACAO',
                'severity' => 'warning',
                'title' => __('Ausência de enturmação'),
                'body' => __('Há :n matrícula(s) na Relação de alunos sem Código da turma. Vincule cada aluno a uma turma antes do fechamento no Educacenso.', [
                    'n' => $this->int($alunosSemTurma),
                ]),
                'metric_value' => $this->int($alunosSemTurma),
                'sort' => 25,
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

        if ($withoutCor > 0) {
            $pctCor = $demScanned > 0 ? round(100 * $withoutCor / $demScanned, 1) : null;
            $insights[] = [
                'code' => 'COR_RACA',
                'severity' => ($pctCor !== null && $pctCor >= 20) ? 'warning' : 'info',
                'title' => __('Cor/Raça não declarada'),
                'body' => __('Como na aba 05-Demografia do Excel de filtros: :n aluno(s) com Cor/Raça vazia ou «Não declarado»:pct. Complete no Educacenso para o perfil demográfico e as leituras de equidade ficarem confiáveis.', [
                    'n' => $this->int($withoutCor),
                    'pct' => $pctCor !== null
                        ? __(' (:p% das :t pessoas lidas)', [
                            'p' => $this->pct($pctCor),
                            't' => $this->int($demScanned),
                        ])
                        : '',
                ]),
                'metric_value' => $this->int($withoutCor),
                'sort' => 35,
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

        if ($turmasParcial > 0 || $turmasIntegral > 0) {
            $insights[] = [
                'code' => 'JORNADA_TURMAS',
                'severity' => 'info',
                'title' => __('Turmas parcial × integral'),
                'body' => __('No Excel de filtros (abas 03/04): :p turma(s) parcial(is) (&lt; 35 h) e :i integral(is) (≥ 35 h ou turno integral). EJA e AEE ficam fora do tempo integral canónico.', [
                    'p' => $this->int($turmasParcial),
                    'i' => $this->int($turmasIntegral),
                ]),
                'metric_value' => $this->int($turmasIntegral),
                'sort' => 52,
            ];
        }

        if ($tempoPleno > 0 || $tempoProxy > 0) {
            $insights[] = [
                'code' => 'TEMPO_INTEGRAL',
                'severity' => 'info',
                'title' => __('Tempo integral (filtros)'),
                'body' => __('Aba 09-Tempo integral: :pleno aluno(s) em turmas plenas (≥ 35 h) e :proxy em AC ≥ 15 h (proxy de contraturno). O proxy não substitui CH curricular+AC ≥ 35 por pessoa.', [
                    'pleno' => $this->int($tempoPleno),
                    'proxy' => $this->int($tempoProxy),
                ]),
                'metric_value' => $this->int($tempoPleno),
                'sort' => 54,
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

        if ($alertAc > 0 || $alertEja > 0) {
            $insights[] = [
                'code' => 'ALERTAS_FILTROS',
                'severity' => 'warning',
                'title' => __('Alertas operacionais de carga horária'),
                'body' => __('Como na aba 10-Alertas do Excel: :ac turma(s) de AC com CH &lt; 15 h e :eja turma(s) de EJA com CH &lt; 20 h. Ajuste a oferta ou documente a exceção antes do fechamento.', [
                    'ac' => $this->int($alertAc),
                    'eja' => $this->int($alertEja),
                ]),
                'metric_value' => $this->int($alertAc + $alertEja),
                'sort' => 56,
            ];
        }

        if ($nee > 0 || $neeSemAee > 0 || $aeeSemNee > 0 || $neeWithK > 0 || $neeWithL > 0) {
            $pctNee = ($neeScanned > 0 && $nee > 0)
                ? round(100 * $nee / $neeScanned, 1)
                : null;
            $gapHeavy = $nee > 0 && $neeSemAee > 0 && ($neeSemAee / max(1, $nee)) >= 0.4;
            $insights[] = [
                'code' => 'INCLUSION',
                'severity' => ($neeSemAee > 0 || $aeeSemNee > 0) ? 'warning' : 'info',
                'title' => __('Inclusão e AEE (filtros)'),
                'body' => __('Alinhado à aba 06-NEE-TRS: :nee pessoa(s) com marcador NEE/TEA/AH:scanned. Contador do Excel — deficiência/transtorno sem turma AEE: :sem:gap. Há ainda :aee pessoa(s) em AEE sem tipificação. Linhas K=:k · L=:l.', [
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
                    'k' => $this->int($neeWithK),
                    'l' => $this->int($neeWithL),
                ]),
                'metric_value' => $this->int($neeSemAee),
                'sort' => 60,
            ];
        }

        if ($neeLSemK > 0) {
            $insights[] = [
                'code' => 'NEE_L_SEM_K',
                'severity' => 'warning',
                'title' => __('Transtorno sem deficiência tipificada'),
                'body' => __('No Excel de filtros (06-NEE-TRS): :n linha(s) com transtorno (L) preenchido e deficiência/NEE (K) vazio. Confira tipificação no Educacenso — a listagem completa está na mesma aba.', [
                    'n' => $this->int($neeLSemK),
                ]),
                'metric_value' => $this->int($neeLSemK),
                'sort' => 62,
            ];
        }

        if ($pnateElegivel > 0 || $pnateExcluido > 0 || $pnateSem > 0) {
            $insights[] = [
                'code' => 'PNATE',
                'severity' => $pnateExcluido > 0 ? 'warning' : 'info',
                'title' => __('PNATE (filtros)'),
                'body' => $pnateHasRes
                    ? __('Aba 07-PNATE: :e elegível(is), :x excluído(s) urbano–urbano (transporte Sim + escola Urbana + residência Urbana) e :s sem transporte. Use a listagem do Excel para conferência.', [
                        'e' => $this->int($pnateElegivel),
                        'x' => $this->int($pnateExcluido),
                        's' => $this->int($pnateSem),
                    ])
                    : __('Aba 07-PNATE: :e elegível(is) e :s sem transporte. Sem coluna de residência — a exclusão urbano–urbano não foi aplicada.', [
                        'e' => $this->int($pnateElegivel),
                        's' => $this->int($pnateSem),
                    ]),
                'metric_value' => $this->int($pnateElegivel),
                'sort' => 65,
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
