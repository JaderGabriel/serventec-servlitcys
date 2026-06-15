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
            'stage1_required' => self::stage1RequiredData(),
            'rectification' => self::rectificationRules($calendar),
            'stage2_preview' => self::stage2Preview($calendar),
            'sources' => self::sources($calendar),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $calendar
     * @return list<array{key: string, label: string, date: string, date_label: string, note: string}>
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
            $milestones[] = [
                'key' => 'reference',
                'label' => __('Data de referência (Dia Nacional do Censo)'),
                'date' => $ref,
                'date_label' => RxCensoCalendar::formatDate($ref),
                'note' => __('Situação das escolas, turmas, matrículas e profissionais deve refletir esta data.'),
            ];
        }

        if (filled($s1['collect_start'] ?? null) && filled($s1['collect_end'] ?? null)) {
            $milestones[] = [
                'key' => 'stage1_collect',
                'label' => (string) ($s1['label'] ?? __('1ª etapa — Matrícula inicial')),
                'date' => (string) $s1['collect_start'],
                'date_label' => RxCensoCalendar::formatDate((string) $s1['collect_start'])
                    .' — '.RxCensoCalendar::formatDate((string) $s1['collect_end']),
                'note' => __('Coleta no Educacenso ou migração via exportação do i-Educar.'),
            ];
        }

        if (filled($s1['prelim_dou'] ?? null)) {
            $milestones[] = [
                'key' => 'prelim_dou',
                'label' => __('Publicação preliminar (DOU)'),
                'date' => (string) $s1['prelim_dou'],
                'date_label' => RxCensoCalendar::formatDate((string) $s1['prelim_dou']),
                'note' => __('Após esta data abre a janela de conferência e retificação.'),
            ];
        }

        if (filled($s1['rectification_end'] ?? null)) {
            $milestones[] = [
                'key' => 'rectification',
                'label' => __('Retificação da 1ª etapa'),
                'date' => (string) $s1['rectification_end'],
                'date_label' => __('30 dias após o DOU')
                    .' ('.RxCensoCalendar::formatDate((string) $s1['rectification_end']).')',
                'note' => __('Correções com base nos registros administrativos e acadêmicos.'),
            ];
        }

        if (filled($s1['fundeb_send'] ?? null)) {
            $milestones[] = [
                'key' => 'fundeb_send',
                'label' => __('Envio homologado ao FNDE (Fundeb)'),
                'date' => (string) $s1['fundeb_send'],
                'date_label' => RxCensoCalendar::formatDate((string) $s1['fundeb_send']),
                'note' => __('Dados finais para coeficientes de distribuição do Fundeb.'),
            ];
        }

        if (filled($s2['collect_start'] ?? null) && filled($s2['collect_end'] ?? null)) {
            $milestones[] = [
                'key' => 'stage2_collect',
                'label' => (string) ($s2['label'] ?? __('2ª etapa — Situação do aluno')),
                'date' => (string) $s2['collect_start'],
                'date_label' => RxCensoCalendar::formatDate((string) $s2['collect_start'])
                    .' — '.RxCensoCalendar::formatDate((string) $s2['collect_end']),
                'note' => __('Rendimento e movimento escolar dos alunos declarados na 1ª etapa.'),
            ];
        }

        return $milestones;
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
