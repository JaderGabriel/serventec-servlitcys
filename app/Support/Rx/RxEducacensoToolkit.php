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
     * @return list<array{kind: string, label: string}>
     */
    public static function calendarLegend(): array
    {
        return [
            ['kind' => 'reference', 'label' => __('Data de referência')],
            ['kind' => 'collect', 'label' => __('Coleta')],
            ['kind' => 'publication', 'label' => __('Publicação DOU')],
            ['kind' => 'rectification', 'label' => __('Retificação')],
            ['kind' => 'fundeb', 'label' => __('Homologação Fundeb')],
            ['kind' => 'stage2', 'label' => __('2ª etapa')],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $calendar
     * @return list<array{
     *   key: string,
     *   kind: string,
     *   label: string,
     *   date: string,
     *   date_end: ?string,
     *   date_label: string,
     *   date_short: string,
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
                __('Data de referência (Dia Nacional do Censo)'),
                $ref,
                RxCensoCalendar::formatDate($ref),
                __('Situação das escolas, turmas, matrículas e profissionais deve refletir esta data.'),
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
                __('Coleta no Educacenso ou migração via exportação do i-Educar.'),
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
                __('Após esta data abre a janela de conferência e retificação.'),
            );
        }

        if (filled($s1['rectification_end'] ?? null)) {
            $rectEnd = (string) $s1['rectification_end'];
            $milestones[] = self::milestone(
                'rectification',
                __('Retificação da 1ª etapa'),
                $rectEnd,
                __('30 dias após o DOU').' ('.RxCensoCalendar::formatDate($rectEnd).')',
                __('Correções com base nos registros administrativos e acadêmicos.'),
            );
        }

        if (filled($s1['fundeb_send'] ?? null)) {
            $fundeb = (string) $s1['fundeb_send'];
            $milestones[] = self::milestone(
                'fundeb_send',
                __('Envio homologado ao FNDE (Fundeb)'),
                $fundeb,
                RxCensoCalendar::formatDate($fundeb),
                __('Dados finais para coeficientes de distribuição do Fundeb.'),
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

        return [
            'key' => $key,
            'kind' => self::milestoneKind($key),
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
            'reference' => __('Referência'),
            'stage1_collect' => __('1ª etapa'),
            'prelim_dou' => __('DOU preliminar'),
            'rectification' => __('Retificação'),
            'fundeb_send' => __('Homologação Fundeb'),
            'stage2_collect' => __('2ª etapa'),
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
     * Marco activo no calendário conforme a fase do banner RX.
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
                    __('Turmas abertas na data de referência, com etapa/modalidade, turno e tipo de atendimento.'),
                    __('Vínculo correto entre turma, escola e ano letivo no i-Educar antes da exportação.'),
                ],
            ],
            [
                'title' => __('Alunos e matrículas'),
                'items' => [
                    __('Matrícula inicial de cada estudante na data de referência (vínculo ativo, série/etapa).'),
                    __('Dados cadastrais do aluno: nome, data de nascimento, filiação, documentos e deficiência/NNE.'),
                    __('Endereço residencial e vínculo com a unidade — base para programas e indicadores territoriais.'),
                ],
            ],
            [
                'title' => __('Profissionais escolares'),
                'items' => [
                    __('Gestores, docentes e demais profissionais em exercício na data de referência.'),
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
            'title' => __('Retificação da 1ª etapa'),
            'intro' => __('Após a publicação preliminar no DOU (:data), o Educacenso reabre por 30 dias para conferência, ratificação e correção dos dados declarados na coleta inicial.', [
                'data' => $prelim,
            ]),
            'items' => [
                __('Ajustes em escola, turmas, matrículas e profissionais declarados na 1ª etapa.'),
                __('Correção de inconsistências apontadas pelo Inep ou identificadas pela rede.'),
                __('Confirmação de matrículas duplicadas no módulo «Confirmação de Matrícula» (relatórios de duplicidade).'),
                __('Matrículas não confirmadas podem ser desconsideradas nos resultados finais.'),
            ],
            'warnings' => [
                __('A retificação não substitui a 2ª etapa (Situação do aluno) — rendimento e movimento têm calendário próprio.'),
                __('Use registros administrativos e acadêmicos da escola; a data de referência da 1ª etapa permanece a do Censo.'),
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
            'intro' => __('Coleta prevista de :inicio a :fim. Só para estudantes já declarados na 1ª etapa.', [
                'inicio' => RxCensoCalendar::formatDate((string) $s2['collect_start']),
                'fim' => RxCensoCalendar::formatDate((string) ($s2['collect_end'] ?? '')),
            ]),
            'items' => [
                __('Situação final do ano letivo: aprovado, reprovado, em exame ou progressão continuada.'),
                __('Movimento escolar: transferência, abandono, falecimento ou conclusão.'),
                __('Após a coleta, há período de conferência e retificação específico da 2ª etapa.'),
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
