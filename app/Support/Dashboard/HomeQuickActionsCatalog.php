<?php

namespace App\Support\Dashboard;

use App\Models\User;
use App\Support\SyncQueue\SyncQueueUserScope;

/**
 * Atalhos do Início — operação admin: filas, integrações e visões multi-município.
 *
 * Evita links para abas da consultoria analítica (exigem escolher município/ano).
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

        $visao = [
            [
                'id' => 'rx',
                'href' => route('dashboard.rx'),
                'title' => __('RX · Cadastro e Censo'),
                'description' => __('Volume digitado, prazos INEP e gaps de cadastro em todos os municípios — sem escolher cidade.'),
                'icon' => 'clipboard-document-list',
                'kicker' => __('Multi-município'),
                'featured' => true,
                'badge' => $active > 0 ? number_format($active) : null,
                'badge_tone' => 'neutral',
                'alert' => false,
            ],
        ];

        if ($user?->canViewHorizonte()) {
            $visao[] = [
                'id' => 'horizonte',
                'href' => route('dashboard.horizonte'),
                'title' => __('Horizonte'),
                'description' => __('Mapa nacional de oportunidade — déficits públicos, regiões prioritárias e prospectos.'),
                'icon' => 'globe-alt',
                'kicker' => __('Expansão'),
                'featured' => false,
                'badge' => null,
                'badge_tone' => 'info',
                'alert' => false,
            ];
        }

        $sections = [
            [
                'id' => 'operacao',
                'title' => __('Filas e monitorização'),
                'subtitle' => __('Processamento, importações e saúde do sistema'),
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
                        'id' => 'public_data',
                        'href' => route('admin.public-data.index'),
                        'title' => __('Dados públicos'),
                        'description' => __('Hub de importação — FUNDEB, CadÚnico, SAEB, repasses Tesouro e abastecimento Horizonte.'),
                        'icon' => 'globe-alt',
                        'kicker' => __('Importação'),
                        'featured' => false,
                        'badge' => null,
                        'badge_tone' => 'neutral',
                        'alert' => false,
                    ],
                    $user?->canImportOrConfigure()
                        ? [
                            'id' => 'module_monitor',
                            'href' => route('admin.module-monitor.index'),
                            'title' => __('Monitor de módulos'),
                            'description' => __('Saúde por área, falhas de fila, lentidões e incidentes recentes.'),
                            'icon' => 'signal',
                            'kicker' => __('Saúde'),
                            'featured' => $syncFailed > 0,
                            'badge' => $syncFailed > 0 ? number_format($syncFailed) : null,
                            'badge_tone' => $syncFailed > 0 ? 'danger' : 'neutral',
                            'alert' => $syncFailed > 0,
                        ]
                        : null,
                ])),
            ],
            [
                'id' => 'dados',
                'title' => __('Rede municipal'),
                'subtitle' => __('Conexões i-Educar, cadastro e matriz FUNDEB'),
                'accent' => 'indigo',
                'actions' => [
                    [
                        'id' => 'connections',
                        'href' => route('admin.connections.index'),
                        'title' => __('Conexões i-Educar'),
                        'description' => __('Testar bases PostgreSQL/MySQL e estado da sincronização por município.'),
                        'icon' => 'circle-stack',
                        'kicker' => __('Base municipal'),
                        'featured' => $active > 0 && $ready < $active,
                        'badge' => $active > 0 ? $ready.'/'.$active : null,
                        'badge_tone' => $ready === $active && $active > 0 ? 'ok' : ($ready > 0 ? 'warn' : 'neutral'),
                        'alert' => $active > 0 && $ready === 0,
                    ],
                    [
                        'id' => 'cities',
                        'href' => route('cities.index'),
                        'title' => __('Municípios'),
                        'description' => __('IBGE, credenciais, ativação no mapa e vínculo de usuários.'),
                        'icon' => 'map-pin',
                        'kicker' => __('Cadastro'),
                        'featured' => false,
                        'badge' => number_format((int) ($stats['cities_active'] ?? 0)),
                        'badge_tone' => 'neutral',
                        'alert' => false,
                    ],
                    [
                        'id' => 'compatibility',
                        'href' => route('admin.ieducar-compatibility.index'),
                        'title' => __('admin_ieducar_compatibility.page.title'),
                        'description' => __('admin_ieducar_compatibility.page.subtitle'),
                        'icon' => 'banknotes',
                        'kicker' => __('FUNDEB'),
                        'featured' => false,
                        'badge' => null,
                        'badge_tone' => 'neutral',
                        'alert' => false,
                    ],
                ],
            ],
            [
                'id' => 'visao',
                'title' => __('Visão multi-município'),
                'subtitle' => __('Painéis que cobrem toda a rede — sem escolher cidade'),
                'accent' => 'teal',
                'actions' => $visao,
            ],
        ];

        if ($user?->canManageUsers()) {
            $sections[] = [
                'id' => 'gestao',
                'title' => __('Gestão'),
                'subtitle' => __('Contas, perfis e acessos'),
                'accent' => 'slate',
                'actions' => [
                    [
                        'id' => 'users',
                        'href' => route('users.index'),
                        'title' => __('Usuários e acessos'),
                        'description' => __('Contas, perfis, municípios associados e encerramento de sessões.'),
                        'icon' => 'users',
                        'kicker' => __('Contas'),
                        'featured' => false,
                        'badge' => number_format((int) ($stats['users_active'] ?? 0)),
                        'badge_tone' => 'neutral',
                        'alert' => false,
                    ],
                ],
            ];
        }

        return $sections;
    }
}
