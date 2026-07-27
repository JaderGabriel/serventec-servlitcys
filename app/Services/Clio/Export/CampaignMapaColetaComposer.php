<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Analysis\CampaignSchoolTimeComposer;
use App\Services\Clio\Analysis\EtapaLabelOrder;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use App\Services\Clio\Parse\CampaignParseService;
use App\Services\Clio\Parse\CsvReader;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * MAPA de Coleta — tabelas quantitativas enxutas (sem textos diagnósticos).
 * Reutiliza as mesmas fontes/fórmulas dos PDFs Detalhado, Gerencial e Final.
 */
final class CampaignMapaColetaComposer
{
    public function __construct(
        private readonly CampaignParseService $parser,
        private readonly CampaignAnalysisPresenter $presenter,
        private readonly CampaignSchoolTimeComposer $schoolTime,
        private readonly RelationCsvAggregator $aggregator = new RelationCsvAggregator,
        private readonly CsvReader $csv = new CsvReader,
    ) {}

    /**
     * @return array{
     *   coverage: array<string, mixed>,
     *   sections: list<array{key: string, title: string, tables: list<array<string, mixed>>}>
     * }
     */
    public function compose(ClioCampaign $campaign): array
    {
        $campaign->loadMissing([
            'schools.artifacts',
            'artifacts.school',
            'inferences',
            'findings.school',
            'city',
        ]);

        $coverage = $this->parser->coverage($campaign);
        $dashboard = $this->presenter->present(
            $campaign,
            $coverage,
            $campaign->inferences->keyBy('code'),
            $campaign->findings,
        );
        $schoolTime = $this->schoolTime->compose($campaign);

        $sections = array_values(array_filter([
            $this->sectionResumo($dashboard, $coverage),
            $this->sectionEducacaoEspecial($dashboard),
            $this->sectionCorRacaSexo($dashboard),
            $this->sectionEja($dashboard, $schoolTime),
            $this->sectionTransporte($dashboard),
            $this->sectionTempoIntegral($campaign, $dashboard, $schoolTime),
            $this->sectionDistorcao($dashboard),
        ], static fn (?array $section): bool => $section !== null));

        return [
            'coverage' => $coverage,
            'sections' => $sections,
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, mixed>  $coverage
     * @return array{key: string, title: string, tables: list<array<string, mixed>>}
     */
    private function sectionResumo(array $dashboard, array $coverage): array
    {
        $counters = is_array($dashboard['counters'] ?? null) ? $dashboard['counters'] : [];
        $triade = is_array($dashboard['triade'] ?? null) ? $dashboard['triade'] : [];
        $report = is_array($dashboard['report'] ?? null) ? $dashboard['report'] : [];
        $totals = is_array($report['totals'] ?? null) ? $report['totals'] : [];

        $triadePct = $triade['pct'] ?? $coverage['triade_coverage_pct'] ?? null;
        $triadeTone = $this->triadeTone($triadePct);
        $triadeComplete = $this->fmtInt($triade['complete'] ?? $coverage['schools_triade_complete'] ?? 0);
        $triadeValue = is_numeric($triadePct)
            ? $triadeComplete.' ('.$this->fmtPct($triadePct).')'
            : $triadeComplete;

        $rows = [
            [
                'cells' => [__('Escolas ativas'), $this->fmtInt($counters['schools_active'] ?? $coverage['schools_active'] ?? 0)],
                'highlight' => false,
            ],
            [
                'cells' => [
                    __('Tríade completa'),
                    ['text' => $triadeValue, 'tone' => $triadeTone],
                ],
                'highlight' => false,
            ],
            [
                'cells' => [__('Escolas com erros'), $this->fmtInt($counters['schools_with_errors'] ?? 0)],
                'highlight' => ((int) ($counters['schools_with_errors'] ?? 0)) > 0,
            ],
            [
                'cells' => [__('Erros na coleta'), $this->fmtInt($counters['errors'] ?? 0)],
                'highlight' => ((int) ($counters['errors'] ?? 0)) > 0,
            ],
            [
                'cells' => [__('Avisos na coleta'), $this->fmtInt($counters['warnings'] ?? 0)],
                'highlight' => false,
            ],
        ];

        foreach ($totals as $tile) {
            if (! is_array($tile)) {
                continue;
            }
            $rows[] = [
                'cells' => [
                    $this->cleanResumoLabel((string) ($tile['label'] ?? '—')),
                    (string) ($tile['value'] ?? '—'),
                ],
                'highlight' => false,
            ];
        }

        return [
            'key' => 'resumo',
            'title' => __('1. Resumo quantitativo geral'),
            'tables' => [[
                'title' => null,
                'headers' => [__('Indicador'), __('Valor')],
                'rows' => $rows,
            ]],
        ];
    }

    private function cleanResumoLabel(string $label): string
    {
        $trimmed = trim(preg_replace('/\s*\(Acomp\)\s*/iu', ' ', $label) ?? $label);

        return $trimmed !== '' ? $trimmed : $label;
    }

    private function triadeTone(mixed $pct): ?string
    {
        if (! is_numeric($pct)) {
            return null;
        }
        $value = (float) $pct;
        if ($value >= 90.0) {
            return 'emerald';
        }
        if ($value >= 80.0) {
            return 'amber';
        }

        return 'rose';
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @return array{key: string, title: string, tables: list<array<string, mixed>>}|null
     */
    private function sectionEducacaoEspecial(array $dashboard): ?array
    {
        $profile = is_array($dashboard['profile'] ?? null) ? $dashboard['profile'] : [];
        $reportInc = is_array(($dashboard['report']['inclusion'] ?? null)) ? $dashboard['report']['inclusion'] : [];

        if (empty($profile['available']) && empty($reportInc)) {
            return null;
        }

        $resumo = [
            [__('Com marcador NEE/TEA/AH'), $this->fmtInt($profile['nee_flagged'] ?? $reportInc['flagged'] ?? 0)],
            [__('NEE sem AEE'), $this->fmtInt($profile['nee_without_aee'] ?? 0), true],
            [__('AEE sem tipificação NEE'), $this->fmtInt($profile['nee_aee_without_condition'] ?? 0), true],
            [__('Alertas de subnotificação'), $this->fmtInt($profile['underreporting_flagged'] ?? 0)],
        ];

        $tables = [[
            'title' => __('Indicadores'),
            'headers' => [__('Indicador'), __('Qtd.')],
            'rows' => array_map(static function (array $row): array {
                return [
                    'cells' => [$row[0], $row[1]],
                    'highlight' => (bool) ($row[2] ?? false),
                ];
            }, $resumo),
        ]];

        $tipRows = [];
        foreach (array_slice(is_array($profile['by_nee'] ?? null) ? $profile['by_nee'] : [], 0, 12) as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $tipRows[] = [
                'cells' => [
                    (string) ($bar['label'] ?? '—'),
                    $this->fmtInt($bar['count'] ?? 0),
                ],
                'highlight' => false,
            ];
        }
        if ($tipRows !== []) {
            $tables[] = [
                'title' => __('Tipificação'),
                'headers' => [__('Categoria'), __('Alunos')],
                'rows' => $tipRows,
            ];
        }

        return [
            'key' => 'educacao_especial',
            'title' => __('2. Educação especial'),
            'tables' => $tables,
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @return array{key: string, title: string, tables: list<array<string, mixed>>}|null
     */
    private function sectionCorRacaSexo(array $dashboard): ?array
    {
        $profile = is_array($dashboard['profile'] ?? null) ? $dashboard['profile'] : [];
        $corBars = is_array($profile['by_cor_raca'] ?? null) ? $profile['by_cor_raca'] : [];
        $sexoBars = is_array($profile['by_sexo'] ?? null) ? $profile['by_sexo'] : [];
        if ($corBars === [] && $sexoBars === []) {
            return null;
        }

        $tables = [];
        $corRows = [];
        foreach (array_slice($corBars, 0, 12) as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $label = (string) ($bar['label'] ?? '—');
            $lower = mb_strtolower($label);
            $undeclared = str_contains($lower, 'não') || str_contains($lower, 'nao') || str_contains($lower, 'não declar');
            $corRows[] = [
                'cells' => [$label, $this->fmtInt($bar['count'] ?? 0), $this->fmtPct($bar['pct'] ?? null)],
                'highlight' => $undeclared,
            ];
        }
        if ($corRows !== []) {
            $tables[] = [
                'title' => __('Cor/Raça'),
                'headers' => [__('Categoria'), __('Alunos'), __('%')],
                'rows' => $corRows,
            ];
        }

        $sexoRows = [];
        foreach (array_slice($sexoBars, 0, 6) as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $sexoRows[] = [
                'cells' => [
                    (string) ($bar['label'] ?? '—'),
                    $this->fmtInt($bar['count'] ?? 0),
                    $this->fmtPct($bar['pct'] ?? null),
                ],
                'highlight' => false,
            ];
        }
        if ($sexoRows !== []) {
            $tables[] = [
                'title' => __('Sexo'),
                'headers' => [__('Categoria'), __('Alunos'), __('%')],
                'rows' => $sexoRows,
            ];
        }

        return [
            'key' => 'cor_raca_sexo',
            'title' => __('3. Cor/Raça e sexo'),
            'tables' => $tables,
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, mixed>  $schoolTime
     * @return array{key: string, title: string, tables: list<array<string, mixed>>}|null
     */
    private function sectionEja(array $dashboard, array $schoolTime): ?array
    {
        $ejaSeg = null;
        foreach (is_array($schoolTime['segments'] ?? null) ? $schoolTime['segments'] : [] as $seg) {
            if (is_array($seg) && (string) ($seg['key'] ?? '') === 'eja') {
                $ejaSeg = $seg;
                break;
            }
        }

        $report = is_array($dashboard['report'] ?? null) ? $dashboard['report'] : [];
        $etapaBars = is_array($report['matriculas_por_ano'] ?? null) ? $report['matriculas_por_ano'] : [];
        $ejaEtapaRows = [];
        foreach ($etapaBars as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $label = (string) ($bar['label'] ?? '');
            if (preg_match('/\beja\b|jovens e adultos/iu', $label) !== 1) {
                continue;
            }
            $ejaEtapaRows[] = [
                'cells' => [$label, $this->fmtInt($bar['count'] ?? 0)],
                'highlight' => false,
            ];
        }

        if ($ejaSeg === null && $ejaEtapaRows === []) {
            return null;
        }

        $tables = [];
        if ($ejaSeg !== null) {
            $tables[] = [
                'title' => __('Totais EJA'),
                'headers' => [__('Indicador'), __('Valor')],
                'rows' => [
                    ['cells' => [__('Turmas'), $this->fmtInt($ejaSeg['turmas'] ?? 0)], 'highlight' => false],
                    ['cells' => [__('Alunos'), $this->fmtInt($ejaSeg['alunos'] ?? 0)], 'highlight' => false],
                    ['cells' => [__('CH média (h/sem.)'), $this->fmtNum($ejaSeg['ch_media_aluno'] ?? null)], 'highlight' => false],
                ],
            ];

            $lt20Turmas = 0;
            $lt20Alunos = 0;
            $gt30Turmas = 0;
            $gt30Alunos = 0;
            $chRows = [];
            foreach (is_array($ejaSeg['ch_options'] ?? null) ? $ejaSeg['ch_options'] : [] as $opt) {
                if (! is_array($opt)) {
                    continue;
                }
                $hours = (float) ($opt['hours'] ?? 0);
                $turmas = (int) ($opt['turmas'] ?? 0);
                $alunos = (int) ($opt['alunos'] ?? 0);
                $lt20 = $hours < 20.0;
                $gt30 = $hours > 30.0;
                if ($lt20) {
                    $lt20Turmas += $turmas;
                    $lt20Alunos += $alunos;
                }
                if ($gt30) {
                    $gt30Turmas += $turmas;
                    $gt30Alunos += $alunos;
                }
                $chRows[] = [
                    'cells' => [
                        (string) ($opt['label'] ?? $this->fmtNum($hours).' h'),
                        $this->fmtInt($turmas),
                        $this->fmtInt($alunos),
                    ],
                    'highlight' => $lt20 || $gt30,
                ];
            }

            $tables[] = [
                'title' => __('Destaque de carga horária'),
                'headers' => [__('Faixa'), __('Turmas'), __('Alunos')],
                'rows' => [
                    [
                        'cells' => [__('< 20 h/semana'), $this->fmtInt($lt20Turmas), $this->fmtInt($lt20Alunos)],
                        'highlight' => $lt20Turmas > 0,
                    ],
                    [
                        'cells' => [__('> 30 h/semana'), $this->fmtInt($gt30Turmas), $this->fmtInt($gt30Alunos)],
                        'highlight' => $gt30Turmas > 0,
                    ],
                ],
            ];

            if ($chRows !== []) {
                $tables[] = [
                    'title' => __('Turmas EJA por CH'),
                    'headers' => [__('Carga'), __('Turmas'), __('Alunos')],
                    'rows' => $chRows,
                ];
            }
        }

        if ($ejaEtapaRows !== []) {
            $tables[] = [
                'title' => __('Matrículas por etapa EJA'),
                'headers' => [__('Etapa'), __('Alunos')],
                'rows' => $ejaEtapaRows,
            ];
        }

        return [
            'key' => 'eja',
            'title' => __('4. EJA'),
            'tables' => $tables,
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @return array{key: string, title: string, tables: list<array<string, mixed>>}|null
     */
    private function sectionTransporte(array $dashboard): ?array
    {
        $tra = is_array($dashboard['transporte'] ?? null) ? $dashboard['transporte'] : [];
        if (empty($tra['available'])) {
            return null;
        }

        $active = is_array($tra['active'] ?? null) ? $tra['active'] : [];
        $tables = [[
            'title' => __('Uso'),
            'headers' => [__('Indicador'), __('Valor'), __('%')],
            'rows' => [
                [
                    'cells' => [__('Usam transporte (rede)'), $this->fmtInt($tra['flagged'] ?? 0), $this->fmtPct($tra['pct'] ?? null)],
                    'highlight' => false,
                ],
                [
                    'cells' => [__('Usam · escolas ativas'), $this->fmtInt($active['flagged'] ?? 0), $this->fmtPct($active['pct'] ?? null)],
                    'highlight' => false,
                ],
                [
                    'cells' => [__('Pessoas lidas'), $this->fmtInt($tra['scanned'] ?? 0), ''],
                    'highlight' => false,
                ],
            ],
        ]];

        $locSource = is_array($active['by_location_users'] ?? null) && $active['by_location_users'] !== []
            ? $active['by_location_users']
            : (is_array($tra['by_location_users'] ?? null) ? $tra['by_location_users'] : []);
        $locRows = [];
        foreach (array_slice($locSource, 0, 8) as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $label = (string) ($bar['label'] ?? '—');
            $rural = preg_match('/rural/iu', $label) === 1;
            $locRows[] = [
                'cells' => [$label, $this->fmtInt($bar['count'] ?? 0), $this->fmtPct($bar['pct'] ?? null)],
                'highlight' => $rural,
            ];
        }
        if ($locRows !== []) {
            $tables[] = [
                'title' => __('Usuários por localização (destaque rural)'),
                'headers' => [__('Localização'), __('Usuários'), __('%')],
                'rows' => $locRows,
            ];
        }

        $veicSource = is_array($active['by_veiculo'] ?? null) && $active['by_veiculo'] !== []
            ? $active['by_veiculo']
            : (is_array($tra['by_veiculo'] ?? null) ? $tra['by_veiculo'] : []);
        $veicRows = [];
        foreach (array_slice($veicSource, 0, 10) as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $veicRows[] = [
                'cells' => [(string) ($bar['label'] ?? '—'), $this->fmtInt($bar['count'] ?? 0)],
                'highlight' => false,
            ];
        }
        if ($veicRows !== []) {
            $tables[] = [
                'title' => __('Tipo de veículo'),
                'headers' => [__('Categoria'), __('Qtd.')],
                'rows' => $veicRows,
            ];
        }

        return [
            'key' => 'transporte',
            'title' => __('5. Transporte escolar'),
            'tables' => $tables,
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, mixed>  $schoolTime
     * @return array{key: string, title: string, tables: list<array<string, mixed>>}|null
     */
    private function sectionTempoIntegral(ClioCampaign $campaign, array $dashboard, array $schoolTime): ?array
    {
        $wanted = ['infantil', 'fundamental_1', 'fundamental_2', 'medio'];
        $segmentRows = [];
        foreach (is_array($schoolTime['segments'] ?? null) ? $schoolTime['segments'] : [] as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $key = (string) ($seg['key'] ?? '');
            if (! in_array($key, $wanted, true)) {
                continue;
            }
            $ch = $seg['ch_media_aluno'] ?? null;
            $integral = is_numeric($ch) && (float) $ch >= 35.0;
            $segmentRows[] = [
                'cells' => [
                    (string) ($seg['label'] ?? $key),
                    $this->fmtInt($seg['turmas'] ?? 0),
                    $this->fmtInt($seg['alunos'] ?? 0),
                    $this->fmtNum($ch),
                ],
                'highlight' => $integral,
            ];
        }

        $jornada = is_array($dashboard['jornada'] ?? null) ? $dashboard['jornada'] : [];
        $chRows = [];
        foreach (array_slice(is_array($jornada['by_ch_band'] ?? null) ? $jornada['by_ch_band'] : [], 0, 10) as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $label = (string) ($bar['short'] ?? $bar['label'] ?? '—');
            $integral = str_contains(mb_strtolower((string) ($bar['label'] ?? $label)), 'integral')
                || str_contains($label, '35')
                || ((float) ($bar['hours_anchor'] ?? 0) >= 35.0);
            $chRows[] = [
                'cells' => [$label, $this->fmtInt($bar['count'] ?? 0), $this->fmtPct($bar['pct'] ?? null)],
                'highlight' => $integral,
            ];
        }

        $flags = $this->countExtendedFlagsWithoutLegalCh($campaign);
        $infantilEst = (int) ($jornada['infantil_turma_estendida'] ?? 0);
        $fundAee = (int) ($jornada['fund_aee_contraturno'] ?? 0);
        $currAc = (int) ($jornada['curricular_ac'] ?? 0);
        $hasSinais = $infantilEst > 0
            || $fundAee > 0
            || $currAc > 0
            || ($flags['integral_sem_ch_legal'] ?? 0) > 0
            || ($flags['curricular_ac_sem_ch_legal'] ?? 0) > 0;

        if ($segmentRows === [] && $chRows === [] && ! $hasSinais) {
            return null;
        }

        $tables = [];
        if ($segmentRows !== []) {
            $tables[] = [
                'title' => __('Infantil, Fundamental e Médio (destaque CH ≥ 35 h)'),
                'headers' => [__('Segmento'), __('Turmas'), __('Alunos'), __('CH méd. h/sem.')],
                'rows' => $segmentRows,
            ];
        }
        if ($chRows !== []) {
            $tables[] = [
                'title' => __('Turmas por faixa de carga horária'),
                'headers' => [__('Faixa'), __('Turmas'), __('%')],
                'rows' => $chRows,
            ];
        }

        if ($hasSinais) {
            $extra = [];
            if ($infantilEst > 0) {
                $extra[] = [
                    'cells' => [__('Infantil turma estendida'), $this->fmtInt($infantilEst)],
                    'highlight' => true,
                ];
            }
            if ($fundAee > 0) {
                $extra[] = [
                    'cells' => [__('Fund. + AEE contraturno'), $this->fmtInt($fundAee)],
                    'highlight' => false,
                ];
            }
            if ($currAc > 0) {
                $extra[] = [
                    'cells' => [__('Curricular + atividade complementar (pessoas)'), $this->fmtInt($currAc)],
                    'highlight' => false,
                ];
            }
            $extra[] = [
                'cells' => [
                    __('Flag integral / CH ≤ 20 h (turmas)'),
                    $this->fmtInt($flags['integral_sem_ch_legal'] ?? 0),
                ],
                'highlight' => ((int) ($flags['integral_sem_ch_legal'] ?? 0)) > 0,
            ];
            $extra[] = [
                'cells' => [
                    __('Curricular + AC / CH ≤ 20 h (turmas)'),
                    $this->fmtInt($flags['curricular_ac_sem_ch_legal'] ?? 0),
                ],
                'highlight' => ((int) ($flags['curricular_ac_sem_ch_legal'] ?? 0)) > 0,
            ];
            $tables[] = [
                'title' => __('Sinais de jornada estendida'),
                'headers' => [__('Indicador'), __('Qtd.')],
                'rows' => $extra,
            ];
        }

        return [
            'key' => 'tempo_integral',
            'title' => __('6. Tempo integral'),
            'tables' => $tables,
        ];
    }

    /**
     * Turmas com indicação de tempo integral (turno/flag) ou envolvidas em
     * curricular+AC, mas sem carga horária semanal superior a 20 h — corrige
     * leitura de segmento sem lastro de CH legal.
     *
     * @return array{integral_sem_ch_legal: int, curricular_ac_sem_ch_legal: int}
     */
    private function countExtendedFlagsWithoutLegalCh(ClioCampaign $campaign): array
    {
        $disk = (string) config('clio.disk', 'local');
        $profiles = [];
        $tipos = [];

        foreach ($campaign->artifacts as $artifact) {
            if (! $artifact instanceof ClioCampaignArtifact || $artifact->kind !== 'relacao_turma_escola') {
                continue;
            }
            $path = $this->absolutePath($disk, $artifact->storage_path);
            if ($path === null) {
                continue;
            }
            try {
                $data = $this->csv->read($path, 1);
                $agg = $this->aggregator->aggregateTurmas($data['rows'], $this->csv);
            } catch (Throwable) {
                continue;
            }
            foreach (is_array($agg['turma_profiles'] ?? null) ? $agg['turma_profiles'] : [] as $code => $profile) {
                if (! is_array($profile)) {
                    continue;
                }
                $profiles[(string) $code] = $profile;
            }
            foreach ($data['rows'] as $row) {
                $code = trim($this->csv->value($row, 'Código da turma'));
                if ($code === '') {
                    continue;
                }
                $tipos[$code] = trim($this->csv->value($row, 'Tipo de turma'));
            }
        }

        $integralLow = [];
        $currAcTipoLow = [];
        foreach ($profiles as $code => $profile) {
            $ch = isset($profile['ch_hours']) && is_numeric($profile['ch_hours'])
                ? (float) $profile['ch_hours']
                : null;
            if ($ch !== null && $ch > 20.0) {
                continue;
            }
            if (! empty($profile['extended'])) {
                $integralLow[$code] = true;
            }
            $tipo = mb_strtolower((string) ($tipos[$code] ?? ''));
            if (str_contains($tipo, 'curricular') && str_contains($tipo, 'atividade complementar')) {
                $currAcTipoLow[$code] = true;
            }
        }

        $currAcLow = $currAcTipoLow;
        foreach ($campaign->artifacts as $artifact) {
            if (! $artifact instanceof ClioCampaignArtifact || $artifact->kind !== 'relacao_aluno_escola') {
                continue;
            }
            $path = $this->absolutePath($disk, $artifact->storage_path);
            if ($path === null) {
                continue;
            }
            try {
                $data = $this->csv->read($path, 1);
                $pattern = $this->aggregator->aggregateEnrollmentDayPatterns(
                    $data['rows'],
                    $this->csv,
                    $profiles,
                );
            } catch (Throwable) {
                continue;
            }

            // Reconstroi pares curricular+AC a partir das matrículas da escola.
            $byPerson = [];
            foreach ($data['rows'] as $row) {
                $id = trim($this->csv->value($row, 'Identificação única'));
                if ($id === '') {
                    $id = trim($this->csv->value($row, 'Código da Matrícula'));
                }
                if ($id === '') {
                    $id = trim($this->csv->value($row, 'Código da matrícula'));
                }
                if ($id === '') {
                    continue;
                }
                $turma = trim($this->csv->value($row, 'Código da turma'));
                if ($turma === '') {
                    continue;
                }
                $byPerson[$id]['turmas'][$turma] = $turma;
            }

            foreach ($byPerson as $person) {
                $codes = array_values($person['turmas'] ?? []);
                $buckets = [];
                foreach ($codes as $code) {
                    $bucket = (string) ($profiles[$code]['bucket'] ?? '');
                    if ($bucket !== '') {
                        $buckets[$bucket][$code] = true;
                    }
                }
                if (! isset($buckets[RelationCsvAggregator::BUCKET_CURRICULAR], $buckets[RelationCsvAggregator::BUCKET_AC])) {
                    continue;
                }
                foreach (array_keys($buckets[RelationCsvAggregator::BUCKET_CURRICULAR]) as $code) {
                    $ch = isset($profiles[$code]['ch_hours']) && is_numeric($profiles[$code]['ch_hours'])
                        ? (float) $profiles[$code]['ch_hours']
                        : null;
                    if ($ch !== null && $ch > 20.0) {
                        continue;
                    }
                    $currAcLow[$code] = true;
                }
                foreach (array_keys($buckets[RelationCsvAggregator::BUCKET_AC]) as $code) {
                    $ch = isset($profiles[$code]['ch_hours']) && is_numeric($profiles[$code]['ch_hours'])
                        ? (float) $profiles[$code]['ch_hours']
                        : null;
                    if ($ch !== null && $ch > 20.0) {
                        continue;
                    }
                    $currAcLow[$code] = true;
                }
            }

            unset($pattern);
        }

        return [
            'integral_sem_ch_legal' => count($integralLow),
            'curricular_ac_sem_ch_legal' => count($currAcLow),
        ];
    }

    private function absolutePath(string $disk, ?string $storagePath): ?string
    {
        if ($storagePath === null || $storagePath === '') {
            return null;
        }
        try {
            $path = Storage::disk($disk)->path($storagePath);
        } catch (Throwable) {
            return null;
        }

        return is_file($path) && is_readable($path) ? $path : null;
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @return array{key: string, title: string, tables: list<array<string, mixed>>}|null
     */
    private function sectionDistorcao(array $dashboard): ?array
    {
        $stage = is_array($dashboard['stage_metrics'] ?? null) ? $dashboard['stage_metrics'] : [];
        $dis = is_array($stage['distortion'] ?? null) ? $stage['distortion'] : [];
        if (empty($stage['available']) && $dis === []) {
            return null;
        }

        $tables = [[
            'title' => __('Rede'),
            'headers' => [__('Indicador'), __('Valor')],
            'rows' => [
                ['cells' => [__('Distorção rede'), $this->fmtPct($dis['pct'] ?? null)], 'highlight' => is_numeric($dis['pct'] ?? null) && (float) $dis['pct'] >= 20],
                ['cells' => [__('Alunos elegíveis'), $this->fmtInt($dis['eligible'] ?? 0)], 'highlight' => false],
                ['cells' => [__('Com distorção (≥ 2 anos)'), $this->fmtInt($dis['distorcao'] ?? $dis['atraso_2_mais'] ?? 0)], 'highlight' => false],
                ['cells' => [__('Atraso 1 ano'), $this->fmtInt($dis['atraso_1'] ?? 0)], 'highlight' => false],
                ['cells' => [__('Adiantados'), $this->fmtInt($dis['adiantado'] ?? 0)], 'highlight' => false],
            ],
        ]];

        $etapaBars = is_array($dis['by_etapa'] ?? null) ? $dis['by_etapa'] : [];
        $etapaBars = (new EtapaLabelOrder)->sortRowsByEtapaKey(
            array_values(array_filter($etapaBars, static fn ($row): bool => is_array($row))),
            'etapa',
        );

        $rows = [];
        foreach ($etapaBars as $info) {
            $pct = $info['pct'] ?? null;
            $rows[] = [
                'cells' => [
                    (string) ($info['etapa'] ?? '—'),
                    $this->fmtInt($info['eligible'] ?? 0),
                    $this->fmtInt($info['distorcao'] ?? 0),
                    $this->fmtPct($pct),
                    $this->fmtInt($info['atraso_1'] ?? 0),
                    $this->fmtInt($info['adiantado'] ?? 0),
                ],
                'highlight' => is_numeric($pct) && (float) $pct >= 20,
            ];
        }
        if ($rows !== []) {
            $tables[] = [
                'title' => __('Por etapa'),
                'headers' => [
                    __('Etapa'),
                    __('Elegíveis'),
                    __('Distorção'),
                    __('%'),
                    __('Atraso 1 ano'),
                    __('Adiantados'),
                ],
                'rows' => $rows,
            ];
        }

        return [
            'key' => 'distorcao',
            'title' => __('7. Distorção idade-série'),
            'tables' => $tables,
        ];
    }

    private function fmtInt(mixed $n): string
    {
        return number_format((int) ($n ?? 0), 0, ',', '.');
    }

    private function fmtNum(mixed $n): string
    {
        if ($n === null || $n === '' || ! is_numeric($n)) {
            return '—';
        }

        return number_format((float) $n, 1, ',', '.');
    }

    private function fmtPct(mixed $n): string
    {
        if ($n === null || $n === '' || ! is_numeric($n)) {
            return '—';
        }

        return number_format((float) $n, 1, ',', '.').'%';
    }
}
