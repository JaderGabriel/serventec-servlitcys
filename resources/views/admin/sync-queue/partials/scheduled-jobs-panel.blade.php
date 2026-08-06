@php
    $catalog = is_array($scheduledJobs ?? null) ? $scheduledJobs : [];
    $runner = is_array($catalog['runner'] ?? null) ? $catalog['runner'] : [];
    $groups = is_array($catalog['groups'] ?? null) ? $catalog['groups'] : [];
    $registered = (int) ($catalog['registered'] ?? 0);
    $activeFilters = (int) ($catalog['active_filters'] ?? 0);
    $missingCount = count(is_array($catalog['missing'] ?? null) ? $catalog['missing'] : []);
    $generatedAt = $catalog['generated_at'] ?? null;
@endphp

<section id="agendamentos" class="sync-queue-panel sync-queue-panel--slate scroll-mt-6">
    <header class="sync-queue-panel__header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex gap-3 min-w-0">
                <span class="sync-queue-panel__icon" aria-hidden="true">
                    <x-ui.icon name="arrow-path" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h3 class="sync-queue-panel__title">{{ __('Agendamentos do sistema') }}</h3>
                    <p class="sync-queue-panel__desc">
                        {{ __('Jobs registados no Laravel Schedule (Pulse, CadÚnico, Horizonte, limpeza…). O servidor deve correr schedule:run no intervalo abaixo.') }}
                    </p>
                    <p class="mt-1 text-[11px] font-mono text-slate-500 dark:text-slate-400">
                        {{ $runner['command'] ?? 'php artisan schedule:run' }}
                        · cron {{ $runner['cron'] ?? '*/3 * * * *' }}
                        · {{ $runner['timezone'] ?? config('app.timezone') }}
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 text-xs shrink-0">
                <span class="rounded-full px-2.5 py-1 bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200">
                    {{ $registered }} {{ __('registados') }}
                </span>
                @if ($activeFilters < $registered)
                    <span class="rounded-full px-2.5 py-1 bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
                        {{ $registered - $activeFilters }} {{ __('condicionais') }}
                    </span>
                @endif
                @if ($missingCount > 0)
                    <span class="rounded-full px-2.5 py-1 bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        {{ $missingCount }} {{ __('desactivados') }}
                    </span>
                @endif
            </div>
        </div>
    </header>

    <div class="sync-queue-panel__body space-y-5">
        <x-admin.import-hub.callout variant="info" :title="__('Como ler esta lista')">
            {{ __('Jobs «condicionais» só disparam quando o filtro passa (ex.: passo do feed Horizonte com pipeline activo). Procurement MEC·FNDE entra na fase do feed bimestral — sem cron próprio.') }}
        </x-admin.import-hub.callout>

        @forelse ($groups as $group)
            <div>
                <div class="mb-2 flex flex-col gap-0.5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ $group['label'] ?? '' }}
                        </h4>
                        @if (filled($group['description'] ?? null))
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $group['description'] }}</p>
                        @endif
                    </div>
                    <span class="text-[11px] tabular-nums text-slate-400">{{ count($group['jobs'] ?? []) }}</span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full text-left text-xs">
                        <thead class="bg-slate-50/90 text-[11px] uppercase tracking-wide text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-2.5 font-semibold">{{ __('Job') }}</th>
                                <th class="px-3 py-2.5 font-semibold">{{ __('Cadência') }}</th>
                                <th class="px-3 py-2.5 font-semibold">{{ __('Próxima') }}</th>
                                <th class="px-3 py-2.5 font-semibold">{{ __('Estado') }}</th>
                                <th class="px-3 py-2.5 font-semibold">{{ __('CLI') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($group['jobs'] ?? [] as $job)
                                @php
                                    $status = (string) ($job['status'] ?? 'scheduled');
                                    $badge = match ($status) {
                                        'scheduled' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200',
                                        'gated' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
                                        'disabled' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                                        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                    };
                                @endphp
                                <tr class="bg-white dark:bg-slate-900/40 align-top">
                                    <td class="px-3 py-2.5">
                                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ $job['label'] ?? '' }}</p>
                                        @if (filled($job['description'] ?? null))
                                            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400 leading-snug">{{ $job['description'] }}</p>
                                        @endif
                                        @if (filled($job['related'] ?? null))
                                            <a href="{{ $job['related'] }}" class="mt-1 inline-block text-[11px] font-medium text-sky-600 dark:text-sky-400 hover:underline">
                                                {{ __('Ver na fila') }} →
                                            </a>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-slate-700 dark:text-slate-300">
                                        <p>{{ $job['summary'] ?? '—' }}</p>
                                        @if (filled($job['expression'] ?? null))
                                            <code class="mt-1 block text-[10px] text-slate-400 font-mono">{{ $job['expression'] }}</code>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 tabular-nums text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                        {{ $job['next_run_human'] ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badge }}">
                                            {{ $job['status_label'] ?? $status }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @if (filled($job['command'] ?? null) && ! str_starts_with((string) $job['command'], 'closure:'))
                                            <code class="block rounded bg-slate-100 px-2 py-1 text-[10px] text-slate-800 dark:bg-slate-800 dark:text-slate-200 break-all">{{ $job['command'] }}</code>
                                        @elseif (str_starts_with((string) ($job['command'] ?? ''), 'closure:'))
                                            <span class="text-slate-400">{{ __('Closure (on-demand)') }}</span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">{{ __('Nenhum agendamento registado — verifique as flags *.schedule.enabled no .env.') }}</p>
        @endforelse

        @if (filled($generatedAt))
            <p class="text-[10px] text-slate-400">
                {{ __('Gerado em') }}
                {{ \Illuminate\Support\Carbon::parse($generatedAt)->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}
                · {{ __('fonte: Illuminate\\Console\\Scheduling\\Schedule') }}
            </p>
        @endif
    </div>
</section>
