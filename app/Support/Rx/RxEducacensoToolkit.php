<?php

namespace App\Support\Rx;

/**
 * Conteúdo orientativo do Educacenso para o painel RX (regras e calendário por exercício).
 */
final class RxEducacensoToolkit
{
    /**
     * @return array<string, mixed>
     */
    public static function forYear(?int $year = null): array
    {
        $calendar = RxCensoCalendar::forYear($year);
        $ano = is_array($calendar) ? (int) ($calendar['ano'] ?? $year ?? date('Y')) : ($year ?? (int) date('Y'));

        return [
            'ano' => $ano,
            'calendar' => self::calendarMilestones($calendar),
            'calendar_legend' => self::calendarLegend(),
            'stage1_required' => self::stage1RequiredData(),
            'rectification' => self::rectificationRules($calendar),
            'stage2_preview' => self::stage2Preview($calendar),
            'sources' => self::sources($calendar),
        ];
    }

    /**
     * @return list<array{kind: string, label: string, hint: string}>
     */
    public static function calendarLegend(): array
    {
        return [
            [
                'kind' => 'reference',
                'label' => __('Data-base'),
                'hint' => __('Situação da rede na data oficial do Censo (Dia Nacional do Censo Escolar).'),
            ],
            [
                'kind' => 'collect',
                'label' => __('Coleta'),
                'hint' => __('Envio das informações no Educacenso ou exportação a partir do i-Educar.'),
            ],
            [
                'kind' => 'publication',
                'label' => __('Publicação no DOU'),
                'hint' => __('Dados preliminares publicados; em seguida abre a conferência na rede.'),
            ],
            [
                'kind' => 'rectification',
                'label' => __('Correção'),
                'hint' => __('30 dias para conferir, confirmar e ajustar o que foi declarado na 1ª etapa.'),
            ],
            [
                'kind' => 'fundeb',
                'label' => __('Homologação FUNDEB'),
                'hint' => __('Envio dos dados finais ao FNDE para coeficientes de distribuição.'),
            ],
            [
                'kind' => 'stage2',
                'label' => __('Situação do aluno'),
                'hint' => __('Aprovação, reprovação, abandono e transferência ao fim do ano letivo.'),
            ],
        ];
    }

    /**
     * @return array{kind: string, label: string, hint: string}
     */
    private static function legendEntryForKind(string $kind): array
    {
        foreach (self::calendarLegend() as $entry) {
            if (($entry['kind'] ?? '') === $kind) {
                return $entry;
            }
        }

        return ['kind' => $kind, 'label' => '', 'hint' => ''];
    }

    /**
     * @param  array<string, mixed>|null  $calendar
     * @return list<array{
     *   key: string,
     *   kind: string,
     *   kind_label: string,
     *   label: string,
     *   date: string,
     *   date_end: ?string,
     *   date_label: string,
     *   date_short: string,
     *   label_short: string,
     *   icon: string,
     *   note: string
     * }>
     */
    private static function calendarMilestones(?array $calendar): array
    {
        if (! is_array($calendar)) {
            return [];
        }

        $s1 = is_array($calendar['stage1'] ?? null) ? $calendar['stage1'] : [];
        $s2 = is_array($calendar['stage2'] ?? null) ? $calendar['stage2'] : [];
        $ref = (string) ($calendar['reference_date'] ?? '');

        $milestones = [];

        if ($ref !== '') {
            $milestones[] = self::milestone(
                'reference',
                __('Data-base do Censo (Dia Nacional do Censo Escolar)'),
                $ref,
                RxCensoCalendar::formatDate($ref),
                __('Escolas, turmas, matrículas e profissionais devem refletir a situação nesta data.'),
            );
        }

        if (filled($s1['collect_start'] ?? null) && filled($s1['collect_end'] ?? null)) {
            $start = (string) $s1['collect_start'];
            $end = (string) $s1['collect_end'];
            $milestones[] = self::milestone(
                'stage1_collect',
                (string) ($s1['label'] ?? __('1ª etapa — Matrícula inicial')),
                $start,
                RxCensoCalendar::formatDate($start).' — '.RxCensoCalendar::formatDate($end),
                __('Período de envio no Educacenso ou migração via exportação do i-Educar.'),
                $end,
            );
        }

        if (filled($s1['prelim_dou'] ?? null)) {
            $dou = (string) $s1['prelim_dou'];
            $milestones[] = self::milestone(
                'prelim_dou',
                __('Publicação preliminar (DOU)'),
                $dou,
                RxCensoCalendar::formatDate($dou),
                __('Publicação dos dados preliminares no Diário Oficial da União.'),
            );
        }

        if (filled($s1['rectification_end'] ?? null)) {
            $rectEnd = (string) $s1['rectification_end'];
            $milestones[] = self::milestone(
                'rectification',
                __('Correção da 1ª etapa'),
                $rectEnd,
                __('Janela de 30 dias (até :data)', ['data' => RxCensoCalendar::formatDate($rectEnd)]),
                __('Ajustes com base nos registros administrativos e acadêmicos da escola.'),
            );
        }

        if (filled($s1['fundeb_send'] ?? null)) {
            $fundeb = (string) $s1['fundeb_send'];
            $milestones[] = self::milestone(
                'fundeb_send',
                __('Envio homologado ao FNDE (Fundeb)'),
                $fundeb,
                RxCensoCalendar::formatDate($fundeb),
                __('Dados homologados enviados ao FNDE para o cálculo do FUNDEB.'),
            );
        }

        if (filled($s2['collect_start'] ?? null) && filled($s2['collect_end'] ?? null)) {
            $start = (string) $s2['collect_start'];
            $end = (string) $s2['collect_end'];
            $milestones[] = self::milestone(
                'stage2_collect',
                (string) ($s2['label'] ?? __('2ª etapa — Situação do aluno')),
                $start,
                RxCensoCalendar::formatDate($start).' — '.RxCensoCalendar::formatDate($end),
                __('Rendimento e movimento escolar dos alunos declarados na 1ª etapa.'),
                $end,
            );
        }

        return $milestones;
    }

    /**
     * @return array{
     *   key: string,
     *   kind: string,
     *   label: string,
     *   date: string,
     *   date_end: ?string,
     *   date_label: string,
     *   date_short: string,
     *   label_short: string,
     *   icon: string,
     *   note: string
     * }
     */
    private static function milestone(
        string $key,
        string $label,
        string $date,
        string $dateLabel,
        string $note,
        ?string $dateEnd = null,
    ): array {
        $dateShort = $dateEnd !== null
            ? RxCensoCalendar::formatDate($date).' — '.RxCensoCalendar::formatDate($dateEnd)
            : RxCensoCalendar::formatDate($date);

        $kind = self::milestoneKind($key);
        $legend = self::legendEntryForKind($kind);

        return [
            'key' => $key,
            'kind' => $kind,
            'kind_label' => (string) ($legend['label'] ?? ''),
            'label' => $label,
            'label_short' => self::milestoneShortLabel($key),
            'icon' => self::milestoneIcon($key),
            'date' => $date,
            'date_end' => $dateEnd,
            'date_label' => $dateLabel,
            'date_short' => $dateShort,
            'note' => $note,
        ];
    }

    private static function milestoneShortLabel(string $key): string
    {
        return match ($key) {
            'reference' => __('Data-base'),
            'stage1_collect' => __('Matrícula inicial'),
            'prelim_dou' => __('Publicação DOU'),
            'rectification' => __('Correção'),
            'fundeb_send' => __('Homologação FUNDEB'),
            'stage2_collect' => __('Situação do aluno'),
            default => '',
        };
    }

    private static function milestoneIcon(string $key): string
    {
        return match ($key) {
            'reference' => 'map-pin',
            'stage1_collect' => 'clipboard-document-list',
            'prelim_dou' => 'document-text',
            'rectification' => 'arrow-path',
            'fundeb_send' => 'banknotes',
            'stage2_collect' => 'academic-cap',
            default => 'signal',
        };
    }

    private static function milestoneKind(string $key): string
    {
        return match ($key) {
            'reference' => 'reference',
            'stage1_collect' => 'collect',
            'prelim_dou' => 'publication',
            'rectification' => 'rectification',
            'fundeb_send' => 'fundeb',
            'stage2_collect' => 'stage2',
            default => 'neutral',
        };
    }

    /**
     * Marco ativo no calendário conforme a fase do banner RX.
     */
    public static function activeMilestoneKey(?string $deadlinePhase): ?string
    {
        return match ($deadlinePhase) {
            'before_reference' => 'reference',
            'stage1_collect' => 'stage1_collect',
            'awaiting_rectification' => 'prelim_dou',
            'stage1_rectification' => 'rectification',
            'between_stages' => 'fundeb_send',
            'stage2_collect' => 'stage2_collect',
            default => null,
        };
    }

    /**
     * @return list<array{title: string, items: list<string>}>
     */
    private static function stage1RequiredData(): array
    {
        return [
            [
                'title' => __('Estabelecimento de ensino'),
                'items' => [
                    __('Identificação, endereço, dependências administrativas e situação de funcionamento.'),
                    __('Infraestrutura e equipamentos (salas, acessibilidade, biblioteca, laboratórios, internet).'),
                    __('Oferta de atividades complementares, alimentação escolar e transporte, quando houver.'),
                ],
            ],
            [
                'title' => __('Turmas e organização escolar'),
                'items' => [
                    __('Turmas abertas na data-base, com etapa/modalidade, turno e tipo de atendimento.'),
                    __('Vínculo correto entre turma, escola e ano letivo no i-Educar antes da exportação.'),
                ],
            ],
            [
                'title' => __('Alunos e matrículas'),
                'items' => [
                    __('Matrícula inicial de cada aluno na data-base (vínculo ativo, série/etapa).'),
                    __('Dados cadastrais do aluno: nome, data de nascimento, filiação, documentos e deficiência/NNE.'),
                    __('Endereço residencial e vínculo com a unidade — base para programas e indicadores territoriais.'),
                ],
            ],
            [
                'title' => __('Profissionais escolares'),
                'items' => [
                    __('Gestores, docentes e demais profissionais em exercício na data-base.'),
                    __('Função, carga horária, formação e vínculo empregatício conforme layout do Educacenso.'),
                ],
            ],
            [
                'title' => __('Responsabilidades na declaração'),
                'items' => [
                    __('Diretores: preenchimento no Educacenso ou exportação a partir do sistema da escola.'),
                    __('Secretarias municipais/estaduais: validação, consolidação e apoio às unidades da rede.'),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $calendar
     * @return array{title: string, intro: string, items: list<string>, warnings: list<string>}
     */
    private static function rectificationRules(?array $calendar): array
    {
        $prelim = is_array($calendar) && is_array($calendar['stage1'] ?? null)
            ? RxCensoCalendar::formatDate((string) ($calendar['stage1']['prelim_dou'] ?? ''))
            : __('após publicação no DOU');

        return [
            'title' => __('Correção da 1ª etapa'),
            'intro' => __('Depois da publicação preliminar no DOU (:data), o Educacenso fica aberto por 30 dias para conferência, confirmação e correção dos dados da coleta inicial.', [
                'data' => $prelim,
            ]),
            'items' => [
                __('Ajustes em escola, turmas, matrículas e profissionais declarados na 1ª etapa.'),
                __('Correção de inconsistências apontadas pelo Inep ou identificadas pela rede.'),
                __('Confirmação de matrículas duplicadas no módulo «Confirmação de Matrícula» (relatórios de duplicidade).'),
                __('Matrículas não confirmadas podem ser desconsideradas nos resultados finais.'),
            ],
            'warnings' => [
                __('A correção da 1ª etapa não substitui a 2ª etapa (Situação do aluno) — rendimento e movimento têm calendário próprio.'),
                __('Use os registros administrativos e acadêmicos da escola; a data-base da 1ª etapa permanece a do Censo.'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $calendar
     * @return array{title: string, intro: string, items: list<string>}|null
     */
    private static function stage2Preview(?array $calendar): ?array
    {
        if (! is_array($calendar) || ! is_array($calendar['stage2'] ?? null)) {
            return null;
        }

        $s2 = $calendar['stage2'];
        if (! filled($s2['collect_start'] ?? null)) {
            return null;
        }

        return [
            'title' => (string) ($s2['label'] ?? __('2ª etapa — Situação do aluno')),
            'intro' => __('Coleta prevista de :inicio a :fim. Somente para alunos já declarados na 1ª etapa.', [
                'inicio' => RxCensoCalendar::formatDate((string) $s2['collect_start']),
                'fim' => RxCensoCalendar::formatDate((string) ($s2['collect_end'] ?? '')),
            ]),
            'items' => [
                __('Situação final do ano letivo: aprovado, reprovado, em exame ou progressão continuada.'),
                __('Movimento escolar: transferência, abandono, falecimento ou conclusão.'),
                __('Após a coleta, há período de conferência e correção específico da 2ª etapa.'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $calendar
     * @return list<array{label: string, url: string}>
     */
    private static function sources(?array $calendar): array
    {
        $links = [
            [
                'label' => __('Perguntas frequentes — Censo Escolar (Inep)'),
                'url' => 'https://www.gov.br/inep/pt-br/acesso-a-informacao/perguntas-frequentes/censo-escolar',
            ],
            [
                'label' => __('Sistema Educacenso'),
                'url' => 'https://www.gov.br/inep/pt-br/acesso-a-informacao/dados-abertos/microdados/censo-escolar',
            ],
        ];

        if (is_array($calendar) && filled($calendar['source_url'] ?? null)) {
            array_unshift($links, [
                'label' => __('Cronograma oficial :ano (Inep)', ['ano' => (string) ($calendar['ano'] ?? '')]),
                'url' => (string) $calendar['source_url'],
            ]);
        }

        if (is_array($calendar) && filled($calendar['portaria'] ?? null)) {
            $links[] = [
                'label' => (string) $calendar['portaria'],
                'url' => (string) ($calendar['source_url'] ?? 'https://www.gov.br/inep/pt-br/areas-de-atuacao/pesquisas-estatisticas-e-indicadores/censo-escolar'),
            ];
        }

        return $links;
    }
}
