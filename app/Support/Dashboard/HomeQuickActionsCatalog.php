<?php

namespace App\Support\Dashboard;

use App\Models\User;
use App\Support\SyncQueue\SyncQueueUserScope;

/**
 * Atalhos do Início — priorizam decisão (consultoria), dados e operação.
 */
final class HomeQuickActionsCatalog
{
    /**
     * @param  array{cities: int, cities_active: int, cities_ready: int, cities_this_month: int, users: int, users_active: int}  $stats
     * @param  array{sync_pending: int, sync_failed_24h: int, pdf_pending: int, pgsql: int, mysql: int}  $ops
     * @return list<array{
     *     id: string,
     *     title: string,
     *     subtitle: string,
     *     accent: string,
     *     actions: list<array{
     *         id: string,
     *         href: string,
     *         title: string,
     *         description: string,
     *         icon: string,
     *         kicker: string,
     *         featured: bool,
     *         badge: string|null,
     *         badge_tone: string,
     *         alert: bool
     *     }>
     * }>
     */
    public static function sections(array $stats, array $ops, ?User $user): array
    {
        $syncPrefix = SyncQueueUserScope::routePrefix($user);
        $queueTotal = (int) ($ops['sync_pending'] ?? 0) + (int) ($ops['pdf_pending'] ?? 0);
        $syncFailed = (int) ($ops['sync_failed_24h'] ?? 0);
        $ready = max(0, (int) ($stats['cities_ready'] ?? 0));
        $active = max(0, (int) ($stats['cities_active'] ?? 0));
        $financeRealtime = filter_var(config('ieducar.finance_realtime.enabled', true), FILTER_VALIDATE_BOOL);

        $analytics = static fn (string $tab = 'municipality_health'): string => route('dashboard.analytics', array_filter(['tab' => $tab !== 'municipality_health' ? $tab : null]));

        $consultoria = [
            [
                'id' => 'discrepancies',
                'href' => $analytics('discrepancies'),
                'title' => __('Discrepâncias'),
                'description' => __('Cadastro com impacto financeiro — priorize correções antes de fechar o ano.'),
                'icon' => 'exclamation-triangle',
                'kicker' => __('Decisão'),
                'featured' => true,
                'badge' => null,
                'badge_tone' => 'neutral',
                'alert' => false,
            ],
            [
                'id' => 'analytics',
                'href' => $analytics('municipality_health'),
                'title' => __('Diagnóstico geral'),
                'description' => __('Índice de qualidade, prioridades e leitura executiva por município/ano.'),
                'icon' => 'chart-bar',
                'kicker' => __('Consultoria'),
                'featured' => true,
                'badge' => null,
                'badge_tone' => 'neutral',
                'alert' => false,
            ],
        ];

        if ($financeRealtime) {
            $consultoria[] = [
                'id' => 'finance_realtime',
                'href' => $analytics('finance_realtime'),
                'title' => __('Finanças · Tempo Real'),
                'description' => __('Repasses observados (Tesouro/BB) × expectativa FUNDEB por município.'),
                'icon' => 'banknotes',
                'kicker' => __('Financiamento'),
                'featured' => false,
                'badge' => __('Novo'),
                'badge_tone' => 'info',
                'alert' => false,
            ];
        }

        $consultoria[] = [
            'id' => 'rx',
            'href' => route('dashboard.rx'),
            'title' => __('RX · Cadastro e Censo'),
            'description' => __('Força de trabalho, prazos e gaps de cadastro em todos os municípios.'),
            'icon' => 'clipboard-document-list',
            'kicker' => __('Multi-município'),
            'featured' => false,
            'badge' => null,
            'badge_tone' => 'neutral',
            'alert' => false,
        ];

        $consultoria[] = [
            'id' => 'fundeb',
            'href' => $analytics('fundeb'),
            'title' => __('FUNDEB e VAAF'),
            'description' => __('Previsão indicativa, complementação VAAR e referências FNDE.'),
                'icon' => 'banknotes',
            'kicker' => __('Financiamento'),
            'featured' => false,
            'badge' => null,
            'badge_tone' => 'neutral',
            'alert' => false,
        ];

        return [
            [
                'id' => 'consultoria',
                'title' => __('Consultoria e decisão'),
                'subtitle' => __('Onde a secretaria valida números e prioriza ações'),
                'accent' => 'teal',
                'actions' => $consultoria,
            ],
            [
                'id' => 'dados',
                'title' => __('Dados e integrações'),
                'subtitle' => __('Entrada municipal e fontes públicas que alimentam o painel'),
                'accent' => 'indigo',
                'actions' => [
                    [
                        'id' => 'public_data',
                        'href' => route('admin.public-data.index'),
                        'title' => __('Hub de dados públicos'),
                        'description' => __('FUNDEB, CadÚnico, SAEB, repasses Tesouro e filas temáticas de importação.'),
                        'icon' => 'globe-alt',
                        'kicker' => __('Importação'),
                        'featured' => false,
                        'badge' => null,
                        'badge_tone' => 'neutral',
                        'alert' => false,
                    ],
                    [
                        'id' => 'connections',
                        'href' => route('admin.connections.index'),
                        'title' => __('Conexões i-Educar'),
                        'description' => __('Testar bases PostgreSQL/MySQL e estado da sincronização por município.'),
                        'icon' => 'circle-stack',
                        'kicker' => __('Base municipal'),
                        'featured' => false,
                        'badge' => $active > 0 ? $ready.'/'.$active : null,
                        'badge_tone' => $ready === $active && $active > 0 ? 'ok' : ($ready > 0 ? 'warn' : 'neutral'),
                        'alert' => $active > 0 && $ready === 0,
                    ],
                    [
                        'id' => 'cities',
                        'href' => route('cities.index'),
                        'title' => __('Municípios'),
                        'description' => __('IBGE, credenciais, activação no mapa e vínculo de usuários.'),
                        'icon' => 'map-pin',
                        'kicker' => __('Cadastro'),
                        'featured' => false,
                        'badge' => number_format((int) ($stats['cities_active'] ?? 0)),
                        'badge_tone' => 'neutral',
                        'alert' => false,
                    ],
                    [
                        'id' => 'geo',
                        'href' => route('admin.geo-sync.index'),
                        'title' => __('Geo e mapa escolar'),
                        'description' => __('Coordenadas i-Educar, catálogo INEP e microdados para o mapa do Início.'),
                        'icon' => 'map',
                        'kicker' => __('Rede'),
                        'featured' => false,
                        'badge' => null,
                        'badge_tone' => 'neutral',
                        'alert' => false,
                    ],
                ],
            ],
            [
                'id' => 'operacao',
                'title' => __('Operação'),
                'subtitle' => __('Filas, relatórios PDF e monitorização técnica'),
                'accent' => 'amber',
                'actions' => array_values(array_filter([
                    [
                        'id' => 'sync_queue',
                        'href' => route($syncPrefix.'.index'),
                        'title' => __('Filas de processamento'),
                        'description' => $queueTotal > 0
                            ? __(':n em fila — sync :sync · PDF :pdf.', [
                                'n' => number_format($queueTotal),
                                'sync' => number_format($ops['sync_pending'] ?? 0),
                                'pdf' => number_format($ops['pdf_pending'] ?? 0),
                            ])
                            : __('Sincronização admin, pedagógico, geo e exportação PDF Serventec.'),
                        'icon' => 'queue-list',
                        'kicker' => __('Processamento'),
                        'featured' => $queueTotal > 0 || $syncFailed > 0,
                        'badge' => $queueTotal > 0 ? number_format($queueTotal) : ($syncFailed > 0 ? __('Falhas') : null),
                        'badge_tone' => $syncFailed > 0 ? 'danger' : ($queueTotal > 0 ? 'warn' : 'ok'),
                        'alert' => $queueTotal > 0 || $syncFailed > 0,
                    ],
                    [
                        'id' => 'compatibility',
                        'href' => route('admin.ieducar-compatibility.index'),
                        'title' => __('Compatibilidade e FUNDEB'),
                        'description' => __('Schema i-Educar, importação VAAF/VAAT e matriz de cobertura por município.'),
                        'icon' => 'squares-2x2',
                        'kicker' => __('Admin'),
                        'featured' => false,
                        'badge' => null,
                        'badge_tone' => 'neutral',
                        'alert' => false,
                    ],
                    [
                        'id' => 'pulse',
                        'href' => route('pulse'),
                        'title' => __('Pulse · Saúde técnica'),
                        'description' => __('Pedidos lentos, erros, Redis e uso da aplicação em tempo real.'),
                        'icon' => 'signal',
                        'kicker' => __('TI'),
                        'featured' => false,
                        'badge' => null,
                        'badge_tone' => 'neutral',
                        'alert' => false,
                    ],
                    $user?->canManageUsers()
                        ? [
                            'id' => 'users',
                            'href' => route('users.index'),
                            'title' => __('Usuários e acessos'),
                            'description' => __('Contas, perfis, municípios associados e encerramento de sessões.'),
                            'icon' => 'users',
                            'kicker' => __('Gestão'),
                            'featured' => false,
                            'badge' => number_format((int) ($stats['users_active'] ?? 0)),
                            'badge_tone' => 'neutral',
                            'alert' => false,
                        ]
                        : null,
                ])),
            ],
        ];
    }
}
