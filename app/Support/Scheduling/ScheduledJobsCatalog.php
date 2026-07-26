<?php

namespace App\Support\Scheduling;

use App\Support\Cadunico\CadunicoBeneficiosPortalScheduleCadence;
use App\Support\Cadunico\CadunicoEscolarizacaoFeedScheduleCadence;
use App\Support\Horizonte\HorizonteFortnightlyFeedScheduleCadence;
use App\Support\Horizonte\HorizonteSiconfiScheduleCadence;
use Carbon\CarbonInterface;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Inventário dos jobs do Laravel Schedule para a UI de filas.
 * Cruza eventos vivos (bootstrap/app.php) com metadados — novos schedules aparecem automaticamente.
 */
final class ScheduledJobsCatalog
{
    /**
     * @return array{
     *     runner: array{cron: string, interval_minutes: int, timezone: string},
     *     registered: int,
     *     active_filters: int,
     *     groups: list<array{id: string, label: string, description: string, jobs: list<array<string, mixed>>}>,
     *     jobs: list<array<string, mixed>>,
     *     missing: list<array<string, mixed>>,
     *     generated_at: string
     * }
     */
    public static function build(?Schedule $schedule = null): array
    {
        if ($schedule !== null) {
            return self::buildFresh($schedule);
        }

        $ttl = max(15, min(300, (int) config('schedule.catalog_cache_seconds', 60)));

        return \Illuminate\Support\Facades\Cache::remember(
            'admin:scheduled_jobs_catalog:v1',
            $ttl,
            static fn (): array => self::buildFresh(app(Schedule::class)),
        );
    }

    public static function forgetCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget('admin:scheduled_jobs_catalog:v1');
    }

    /**
     * @return array{
     *     runner: array{cron: string, interval_minutes: int, timezone: string, command?: string},
     *     registered: int,
     *     active_filters: int,
     *     groups: list<array{id: string, label: string, description: string, jobs: list<array<string, mixed>>}>,
     *     jobs: list<array<string, mixed>>,
     *     missing: list<array<string, mixed>>,
     *     generated_at: string
     * }
     */
    public static function buildFresh(?Schedule $schedule = null): array
    {
        $schedule ??= app(Schedule::class);
        $timezone = (string) config('app.timezone', 'UTC');
        $runnerMinutes = max(1, min(59, (int) config('schedule.runner_interval_minutes', 3)));
        $metaByName = self::metadataByName();

        /** @var list<array<string, mixed>> $jobs */
        $jobs = [];
        $seenNames = [];

        foreach ($schedule->events() as $event) {
            if (! $event instanceof Event) {
                continue;
            }

            $name = trim((string) ($event->description ?? ''));
            if ($name === '') {
                $name = 'unnamed-'.substr(sha1(spl_object_hash($event)), 0, 8);
            }
            $seenNames[$name] = true;

            $meta = $metaByName[$name] ?? null;
            $jobs[] = self::hydrateLiveJob($event, $name, $meta, $timezone);
        }

        usort($jobs, static function (array $a, array $b): int {
            $ga = (string) ($a['group'] ?? 'z');
            $gb = (string) ($b['group'] ?? 'z');
            if ($ga !== $gb) {
                return $ga <=> $gb;
            }

            return ((string) ($a['label'] ?? '')) <=> ((string) ($b['label'] ?? ''));
        });

        $missing = [];
        foreach ($metaByName as $name => $meta) {
            if (isset($seenNames[$name])) {
                continue;
            }
            if (! ($meta['expected'] ?? true)) {
                continue;
            }
            $missing[] = [
                'name' => $name,
                'label' => $meta['label'] ?? $name,
                'description' => $meta['description'] ?? '',
                'group' => $meta['group'] ?? 'outros',
                'group_label' => self::groupLabel((string) ($meta['group'] ?? 'outros')),
                'command' => $meta['command'] ?? null,
                'summary' => $meta['summary'] ?? null,
                'registered' => false,
                'status' => 'disabled',
                'status_label' => __('Não registado (config desactivada)'),
                'filters_passes' => false,
                'next_run_at' => null,
                'expression' => $meta['expression_hint'] ?? null,
                'related' => $meta['related'] ?? null,
            ];
        }

        $groups = [];
        foreach (self::groupOrder() as $groupId => $groupMeta) {
            $groupJobs = array_values(array_filter(
                $jobs,
                static fn (array $j): bool => ($j['group'] ?? 'outros') === $groupId,
            ));
            $groupMissing = array_values(array_filter(
                $missing,
                static fn (array $j): bool => ($j['group'] ?? 'outros') === $groupId,
            ));
            if ($groupJobs === [] && $groupMissing === []) {
                continue;
            }
            $groups[] = [
                'id' => $groupId,
                'label' => $groupMeta['label'],
                'description' => $groupMeta['description'],
                'jobs' => array_merge($groupJobs, $groupMissing),
            ];
        }

        $orphanJobs = array_values(array_filter(
            $jobs,
            static fn (array $j): bool => ! array_key_exists((string) ($j['group'] ?? ''), self::groupOrder()),
        ));
        if ($orphanJobs !== []) {
            $groups[] = [
                'id' => 'outros',
                'label' => __('Outros'),
                'description' => __('Agendamentos sem grupo catalogado — registados no Schedule.'),
                'jobs' => $orphanJobs,
            ];
        }

        return [
            'runner' => [
                'cron' => '*/'.$runnerMinutes.' * * * *',
                'interval_minutes' => $runnerMinutes,
                'timezone' => $timezone,
                'command' => 'php artisan schedule:run',
            ],
            'registered' => count($jobs),
            'active_filters' => count(array_filter($jobs, static fn (array $j): bool => (bool) ($j['filters_passes'] ?? false))),
            'groups' => $groups,
            'jobs' => $jobs,
            'missing' => $missing,
            'generated_at' => now()->timezone($timezone)->toIso8601String(),
        ];
    }

    /**
     * @param  ?array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function hydrateLiveJob(Event $event, string $name, ?array $meta, string $timezone): array
    {
        $command = self::eventCommand($event);
        $expression = trim((string) ($event->expression ?? ''));
        $filtersPass = self::filtersPass($event);
        $nextRun = self::nextRunAt($event, $timezone);

        $group = (string) ($meta['group'] ?? 'outros');
        $summary = $meta['summary'] ?? null;
        if (! is_string($summary) || $summary === '') {
            $summary = self::humanizeExpression($expression);
        }

        $status = 'scheduled';
        $statusLabel = __('Agendado');
        if (! $filtersPass) {
            $status = 'gated';
            $statusLabel = __('Condicional (filtro activo)');
        }

        return [
            'name' => $name,
            'label' => $meta['label'] ?? $name,
            'description' => $meta['description'] ?? '',
            'group' => $group,
            'group_label' => self::groupLabel($group),
            'command' => $command !== '' ? $command : ($meta['command'] ?? null),
            'summary' => $summary,
            'expression' => $expression !== '' ? $expression : null,
            'registered' => true,
            'status' => $status,
            'status_label' => $statusLabel,
            'filters_passes' => $filtersPass,
            'next_run_at' => $nextRun?->toIso8601String(),
            'next_run_human' => $nextRun?->timezone($timezone)->format('d/m/Y H:i'),
            'timezone' => $timezone,
            'related' => $meta['related'] ?? null,
            'queue_domain' => $meta['queue_domain'] ?? null,
            'accent' => $meta['accent'] ?? 'slate',
        ];
    }

    private static function eventCommand(Event $event): string
    {
        if ($event instanceof CallbackEvent) {
            return 'closure:'.(string) ($event->description ?? 'callback');
        }

        $command = (string) ($event->command ?? '');
        // Laravel prefixa com php binary + artisan.
        if (preg_match("/artisan['\"]?\s+(.+)$/i", $command, $m) === 1) {
            return 'php artisan '.trim($m[1], " \t\"'");
        }

        return trim($command);
    }

    private static function filtersPass(Event $event): bool
    {
        try {
            return $event->filtersPass(app());
        } catch (Throwable) {
            return true;
        }
    }

    private static function nextRunAt(Event $event, string $timezone): ?CarbonInterface
    {
        try {
            $next = $event->nextRunDate(Carbon::now($timezone), 0);
            if ($next instanceof CarbonInterface) {
                return $next->timezone($timezone);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private static function humanizeExpression(string $expression): string
    {
        $expression = trim($expression);
        if ($expression === '') {
            return __('Cadência configurada no Schedule');
        }

        if (preg_match('/^\*\/(\d+)\s+\*\s+\*\s+\*\s+\*$/', $expression, $m) === 1) {
            return __('A cada :n minuto(s)', ['n' => $m[1]]);
        }

        return __('Cron :expr', ['expr' => $expression]);
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    private static function groupOrder(): array
    {
        return [
            'infra' => [
                'label' => __('Infra & monitorização'),
                'description' => __('Pulse, monitor de módulos, limpeza e alertas operacionais.'),
            ],
            'filas' => [
                'label' => __('Workers de fila'),
                'description' => __('Disparam admin-sync e PDF analítico (tarefas enfileiradas nas telas).'),
            ],
            'cadunico' => [
                'label' => __('CadÚnico / Cecad'),
                'description' => __('Sync municipal, território, card Escolarização e benefícios Portal (CUN-04).'),
            ],
            'horizonte' => [
                'label' => __('Horizonte'),
                'description' => __('Feed bimestral (inclui procurement MEC·FNDE), SICONFI, obras/canteiro e cache do mapa.'),
            ],
            'dados' => [
                'label' => __('Dados públicos'),
                'description' => __('Verificação diária de fontes oficiais e sync massiva.'),
            ],
            'produto' => [
                'label' => __('Produto'),
                'description' => __('Manutenção Clio e outras rotinas de produto.'),
            ],
        ];
    }

    private static function groupLabel(string $group): string
    {
        return self::groupOrder()[$group]['label'] ?? __('Outros');
    }

    /**
     * Metadados conhecidos de um job agendado (por `->name(...)`).
     *
     * @return array<string, mixed>|null
     */
    public static function metaFor(string $name): ?array
    {
        return self::metadataByName()[$name] ?? null;
    }

    /**
     * Metadados por nome do evento (`->name(...)` em bootstrap/app.php).
     *
     * @return array<string, array<string, mixed>>
     */
    private static function metadataByName(): array
    {
        $runner = max(1, min(59, (int) config('schedule.runner_interval_minutes', 3)));
        $pulse = max(1, min(59, (int) config('pulse.schedule.interval_minutes', $runner)));
        $ops = max(5, min(120, (int) config('notifications.operational_alerts.schedule.interval_minutes', 15)));
        $monitor = max(1, min(59, (int) config('module_monitor.schedule.interval_minutes', 10)));
        $feedStep = max(5, (int) config('horizonte.fortnightly_feed.schedule.step_interval_minutes', 20));
        $escStep = max(5, (int) config('ieducar.cadunico.escolarizacao_feed.schedule.step_interval_minutes', 30));
        $siconfiStep = max(5, (int) config('horizonte.siconfi_sync.schedule.step_interval_minutes', 30));
        $obrasStep = max(5, (int) config('horizonte.obras.schedule.step_interval_minutes', 30));

        $syncTimes = ScheduleIntervals::normalizeDailyTimes(
            config('ieducar.admin_sync.schedule.times', ['06:00', '18:00']),
        );

        return [
            'pulse-scheduled-check' => [
                'label' => __('Pulse — check'),
                'description' => __('Snapshots de servidores/infra para o cartão Pulse.'),
                'group' => 'infra',
                'accent' => 'sky',
                'command' => 'php artisan pulse:check --once',
                'summary' => __('A cada :n min', ['n' => (string) $pulse]),
                'expected' => true,
            ],
            'pulse-scheduled-work' => [
                'label' => __('Pulse — digest'),
                'description' => __('Processa a fila de ingestão Pulse (stop-when-empty).'),
                'group' => 'infra',
                'accent' => 'sky',
                'command' => 'php artisan pulse:work --stop-when-empty',
                'summary' => __('A cada :n min', ['n' => (string) $pulse]),
                'expected' => true,
            ],
            'module-monitor-collect' => [
                'label' => __('Monitor de módulos'),
                'description' => __('Recolhe saúde por módulo e notifica falhas no sino admin.'),
                'group' => 'infra',
                'accent' => 'violet',
                'command' => 'php artisan module-monitor:collect',
                'summary' => __('A cada :n min', ['n' => (string) $monitor]),
                'expected' => true,
            ],
            'operational-alerts-check' => [
                'label' => __('Alertas operacionais'),
                'description' => __('Notifica admins sobre falhas / filas / cobertura.'),
                'group' => 'infra',
                'accent' => 'amber',
                'command' => 'php artisan notifications:operational-alerts',
                'summary' => __('A cada :n min', ['n' => (string) $ops]),
                'expected' => true,
            ],
            'app-tmp-purge' => [
                'label' => __('Limpeza TMP'),
                'description' => __('Remove ficheiros temporários e staging expirados.'),
                'group' => 'infra',
                'accent' => 'slate',
                'command' => 'php artisan tmp:purge',
                'summary' => __('Diário às :time', ['time' => (string) config('tmp.schedule.time', '03:15')]),
                'expected' => true,
            ],
            'admin-sync-scheduled-work' => [
                'label' => __('Worker admin-sync'),
                'description' => __('Processa a fila admin-sync (FUNDEB, geo, SAEB, CadÚnico, NEE…).'),
                'group' => 'filas',
                'accent' => 'sky',
                'queue_domain' => null,
                'command' => 'php artisan admin-sync:work',
                'summary' => $syncTimes !== []
                    ? __('Diário às :times', ['times' => implode(', ', $syncTimes)])
                    : __('Intervalo configurado'),
                'expected' => true,
            ],
            'admin-sync-on-demand' => [
                'label' => __('Worker admin-sync (sob demanda)'),
                'description' => __('Entre as janelas diárias: se houver tarefa pendente, dispara o worker.'),
                'group' => 'filas',
                'accent' => 'sky',
                'command' => 'php artisan admin-sync:work (on-demand)',
                'summary' => __('A cada :n min se houver pendentes', ['n' => (string) $runner]),
                'expected' => true,
            ],
            'analytics-pdf-on-demand' => [
                'label' => __('Worker PDF analítico'),
                'description' => __('Gera PDFs da aba Diagnóstico quando há exportações pendentes.'),
                'group' => 'filas',
                'accent' => 'rose',
                'command' => 'php artisan analytics-pdf:work (on-demand)',
                'summary' => __('A cada :n min se houver pendentes', ['n' => (string) $runner]),
                'related' => '#fila-pdf',
                'expected' => true,
            ],
            'cadunico-auto-sync-enqueue' => [
                'label' => __('CadÚnico — auto-sync'),
                'description' => __('Enfileira sync Misocial/CECAD (domínio cadastro).'),
                'group' => 'cadunico',
                'accent' => 'fuchsia',
                'queue_domain' => 'cadastro',
                'command' => 'php artisan cadunico:auto-sync --queue',
                'summary' => __('Semanal — dia :dow às :time', [
                    'dow' => (string) config('ieducar.cadunico.auto_sync.schedule.day_of_week', 1),
                    'time' => (string) config('ieducar.cadunico.auto_sync.schedule.time', '03:30'),
                ]),
                'related' => '#fila-cadastro',
                'expected' => true,
            ],
            'cadunico-sync-territorio-enqueue' => [
                'label' => __('CadÚnico — território'),
                'description' => __('Territórios IBGE + rateio para previsão territorial.'),
                'group' => 'cadunico',
                'accent' => 'fuchsia',
                'command' => 'php artisan cadunico:sync-territorio --all --queue',
                'summary' => __('Semanal — dia :dow às :time', [
                    'dow' => (string) config('ieducar.cadunico.territorio.schedule.day_of_week', 1),
                    'time' => (string) config('ieducar.cadunico.territorio.schedule.time', '04:30'),
                ]),
                'expected' => true,
            ],
            'cadunico-escolarizacao-feed-start' => [
                'label' => __('Escolarização — início pipeline'),
                'description' => __('Arranca o feed do card Escolarização (bimestral).'),
                'group' => 'cadunico',
                'accent' => 'fuchsia',
                'command' => 'php artisan cadunico:escolarizacao-feed --staged --reset',
                'summary' => CadunicoEscolarizacaoFeedScheduleCadence::summary(),
                'expression_hint' => CadunicoEscolarizacaoFeedScheduleCadence::cronExpression(),
                'expected' => true,
            ],
            'cadunico-escolarizacao-feed-step' => [
                'label' => __('Escolarização — passo'),
                'description' => __('Continua o pipeline enquanto estiver activo.'),
                'group' => 'cadunico',
                'accent' => 'fuchsia',
                'command' => 'php artisan cadunico:escolarizacao-feed --staged --continue',
                'summary' => __('A cada :n min (só com pipeline activo)', ['n' => (string) $escStep]),
                'expected' => true,
            ],
            'cadunico-escolarizacao-feed' => [
                'label' => __('Escolarização — one-shot'),
                'description' => __('Feed completo numa invocação (modo staged=false).'),
                'group' => 'cadunico',
                'accent' => 'fuchsia',
                'command' => 'php artisan cadunico:escolarizacao-feed --all',
                'summary' => CadunicoEscolarizacaoFeedScheduleCadence::summary(),
                'expected' => false,
            ],
            'cadunico-beneficios-portal' => [
                'label' => __('Benefícios Portal (CUN-04)'),
                'description' => __('PBF/NBF/BPC agregados por IBGE — callouts no card Escolarização.'),
                'group' => 'cadunico',
                'accent' => 'fuchsia',
                'command' => 'php artisan cadunico:sync-beneficios-portal',
                'summary' => CadunicoBeneficiosPortalScheduleCadence::summary(),
                'expression_hint' => CadunicoBeneficiosPortalScheduleCadence::cronExpression(),
                'expected' => true,
            ],
            'horizonte-fortnightly-feed-start' => [
                'label' => __('Horizonte — início feed'),
                'description' => __('Abastecimento nacional: FUNDEB, Censo, Educacenso, CadÚnico, SIDRA, repasses, SICONFI, transparência, procurement MEC·FNDE, obras, SAEB, IBGE, SGE, alertas.'),
                'group' => 'horizonte',
                'accent' => 'indigo',
                'command' => 'php artisan horizonte:fortnightly-feed --staged --reset',
                'summary' => HorizonteFortnightlyFeedScheduleCadence::summary(),
                'expression_hint' => HorizonteFortnightlyFeedScheduleCadence::cronExpression(),
                'related' => '#fila-horizonte',
                'expected' => true,
            ],
            'horizonte-fortnightly-feed-step' => [
                'label' => __('Horizonte — passo do feed'),
                'description' => __('Executa a próxima fase (incl. procurement_sync) enquanto o pipeline estiver activo.'),
                'group' => 'horizonte',
                'accent' => 'indigo',
                'command' => 'php artisan horizonte:fortnightly-feed --staged --continue',
                'summary' => __('A cada :n min (só com pipeline activo)', ['n' => (string) $feedStep]),
                'related' => '#fila-horizonte',
                'expected' => true,
            ],
            'horizonte-fortnightly-feed' => [
                'label' => __('Horizonte — feed one-shot'),
                'description' => __('Feed completo numa invocação (modo staged=false).'),
                'group' => 'horizonte',
                'accent' => 'indigo',
                'command' => 'php artisan horizonte:fortnightly-feed --all',
                'summary' => HorizonteFortnightlyFeedScheduleCadence::summary(),
                'related' => '#fila-horizonte',
                'expected' => false,
            ],
            'horizonte-siconfi-sync-start' => [
                'label' => __('SICONFI — início'),
                'description' => __('Indicadores fiscais RREO — ciclo semestral por UF.'),
                'group' => 'horizonte',
                'accent' => 'violet',
                'command' => 'php artisan horizonte:sync-siconfi --reset --continue',
                'summary' => HorizonteSiconfiScheduleCadence::summary(),
                'expression_hint' => HorizonteSiconfiScheduleCadence::cronExpression(),
                'expected' => true,
            ],
            'horizonte-siconfi-sync-step' => [
                'label' => __('SICONFI — passo'),
                'description' => __('Continua sync por UF enquanto o progresso estiver activo.'),
                'group' => 'horizonte',
                'accent' => 'violet',
                'command' => 'php artisan horizonte:sync-siconfi --continue',
                'summary' => __('A cada :n min (só com progresso activo)', ['n' => (string) $siconfiStep]),
                'expected' => true,
            ],
            'horizonte-siconfi-sync' => [
                'label' => __('SICONFI — one-shot'),
                'description' => __('Sync completa (staged=false).'),
                'group' => 'horizonte',
                'accent' => 'violet',
                'command' => 'php artisan horizonte:sync-siconfi --reset',
                'summary' => HorizonteSiconfiScheduleCadence::summary(),
                'expected' => false,
            ],
            'horizonte-warm-map-cache' => [
                'label' => __('Cache do mapa Horizonte'),
                'description' => __('Aquece fingerprints/JSON do mapa nacional.'),
                'group' => 'horizonte',
                'accent' => 'indigo',
                'command' => 'php artisan horizonte:warm-map-cache',
                'summary' => __('Semanal — domingo :time', [
                    'time' => (string) config('horizonte.map_cache_warm.time', '05:30'),
                ]),
                'expected' => true,
            ],
            'horizonte-sync-obras-start' => [
                'label' => __('Canteiro — início sync obras'),
                'description' => __('Obras FNDE/SIMEC via Obrasgov (fase também no feed bimestral).'),
                'group' => 'horizonte',
                'accent' => 'orange',
                'command' => 'php artisan horizonte:sync-obras --reset',
                'summary' => __('Mensal — dia :day às :time', [
                    'day' => (string) config('horizonte.obras.schedule.day', 5),
                    'time' => (string) config('horizonte.obras.schedule.time', '05:30'),
                ]),
                'expected' => true,
            ],
            'horizonte-sync-obras-step' => [
                'label' => __('Canteiro — passo obras'),
                'description' => __('Continua sync por UF enquanto houver progresso em cache.'),
                'group' => 'horizonte',
                'accent' => 'orange',
                'command' => 'php artisan horizonte:sync-obras --continue',
                'summary' => __('A cada :n min (só com progresso activo)', ['n' => (string) $obrasStep]),
                'expected' => true,
            ],
            'horizonte-sync-obras' => [
                'label' => __('Canteiro — obras one-shot'),
                'description' => __('Sync completa (staged=false).'),
                'group' => 'horizonte',
                'accent' => 'orange',
                'command' => 'php artisan horizonte:sync-obras',
                'expected' => false,
            ],
            'horizonte-canteiro-alerts' => [
                'label' => __('Canteiro — alertas'),
                'description' => __('Snapshot de alertas de obras para o mapa.'),
                'group' => 'horizonte',
                'accent' => 'orange',
                'command' => 'php artisan horizonte:canteiro-alerts',
                'summary' => __('Mensal — dia :day às :time', [
                    'day' => (string) config('horizonte.obras.alerts.schedule_day', 8),
                    'time' => (string) config('horizonte.obras.alerts.schedule_time', '06:00'),
                ]),
                'expected' => true,
            ],
            'public-data-daily-check' => [
                'label' => __('Verificação de fontes oficiais'),
                'description' => __('public-data:check-official — disponibilidade FNDE/INEP/Portal.'),
                'group' => 'dados',
                'accent' => 'emerald',
                'command' => 'php artisan public-data:check-official',
                'summary' => __('Diário às :time', [
                    'time' => (string) config('public_data_availability.schedule.time', '07:00'),
                ]),
                'expected' => true,
            ],
            'weekly-mass-sync-enqueue' => [
                'label' => __('Sync massiva semanal'),
                'description' => __('Enfileira sincronização massiva com checkpoint retomável (domínio system).'),
                'group' => 'dados',
                'accent' => 'slate',
                'queue_domain' => 'system',
                'command' => 'php artisan weekly-mass-sync:run',
                'summary' => __('Semanal — dia :dow às :time', [
                    'dow' => (string) config('ieducar.weekly_mass_sync.schedule.day_of_week', 0),
                    'time' => (string) config('ieducar.weekly_mass_sync.schedule.time', '02:00'),
                ]),
                'related' => '#fila-system',
                'expected' => true,
            ],
            'clio-prune-artifacts' => [
                'label' => __('Clio — retenção'),
                'description' => __('Remove artefactos Clio expirados.'),
                'group' => 'produto',
                'accent' => 'slate',
                'command' => 'php artisan clio:prune-artifacts',
                'summary' => __('Semanal — domingo 04:00'),
                'expected' => true,
            ],
        ];
    }
}
