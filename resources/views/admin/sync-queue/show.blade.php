@php
    $accent = $taskTheme['accent'] ?? 'slate';
    $st = $task->statusEnum();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Tarefa #:id', ['id' => (string) $task->id]) }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $task->label }}</p>
        </div>
    </x-slot>

    <x-admin.import-hub.shell
        active="queue"
        accent="slate"
        :eyebrow="$taskTheme['label'] ?? $task->domainEnum()->label()"
        :title="$task->label"
        :description="$taskTheme['description'] ?? ''"
        queue-banner-compact
    >
        <x-slot name="badges">
            <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-medium {{ $st->badgeClass() }}">{{ $st->label() }}</span>
            <span class="rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 text-[11px] font-mono text-slate-600 dark:text-slate-300">#{{ $task->id }}</span>
        </x-slot>

        <div class="mb-2">
            <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index', ['domain' => $task->domain]) }}#{{ $taskTheme['anchor'] ?? 'fila-sync' }}" class="text-sm text-sky-600 dark:text-sky-400 hover:underline">
                ← {{ __('Voltar à fila') }} · {{ $taskTheme['label'] ?? $task->domainEnum()->label() }}
            </a>
        </div>

        @if ($outcomeHint !== null)
            <div class="rounded-lg border border-sky-200/90 bg-sky-50/80 dark:border-sky-800/60 dark:bg-sky-950/25 px-4 py-3 text-sm">
                <p class="font-semibold text-sky-950 dark:text-sky-100">{{ $outcomeHint['title'] }}</p>
                <p class="mt-1 text-xs text-sky-900/90 dark:text-sky-200/90 leading-relaxed">{{ $outcomeHint['detail'] }}</p>
            </div>
        @endif

        @if (in_array($task->domain, ['fundeb', 'geo', 'pedagogical'], true))
            @include('admin.partials.external-import-impact', ['domain' => $task->domain, 'cityId' => $task->city_id])
        @endif

        <section class="sync-queue-panel sync-queue-panel--{{ $accent }}">
            <header class="sync-queue-panel__header">
                <div class="flex gap-3 min-w-0">
                    <span class="sync-queue-panel__icon" aria-hidden="true">
                        <x-ui.icon :name="$taskTheme['icon'] ?? 'queue-list'" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="sync-queue-panel__title">{{ __('Detalhes da tarefa') }}</h3>
                        <p class="sync-queue-panel__desc">{{ $task->domainEnum()->label() }} · {{ $task->task_key }}</p>
                    </div>
                </div>
            </header>
            <div class="sync-queue-panel__body space-y-4 text-sm">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Domínio') }}</dt>
                        <dd class="font-medium">{{ $task->domainEnum()->label() }} <span class="text-gray-400">({{ $task->task_key }})</span></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Estado') }}</dt>
                        <dd>
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $st->badgeClass() }}">
                                {{ $st->label() }}
                            </span>
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                            {{ trans_choice('Município|Municípios', count($task->cityNames())) }}
                            @if ($task->targetsAllCities())
                                <span class="text-sky-600 dark:text-sky-400">({{ __('todas') }})</span>
                            @endif
                        </dt>
                        <dd class="font-medium">
                            @php $cityNames = $task->cityNames(); @endphp
                            @if ($cityNames === [])
                                —
                            @elseif (count($cityNames) === 1)
                                {{ $cityNames[0] }}
                                @if ($task->city?->ibge_municipio)
                                    <span class="text-gray-500 font-normal">(IBGE {{ $task->city->ibge_municipio }})</span>
                                @endif
                            @else
                                <ul class="mt-1 flex flex-wrap gap-1.5 text-sm font-normal text-gray-800 dark:text-gray-200">
                                    @foreach ($cityNames as $name)
                                        <li class="rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-0.5">{{ $name }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Enfileirado por') }}</dt>
                        <dd>{{ $task->queuedBy?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Criada') }}</dt>
                        <dd>{{ $task->created_at?->format('d/m/Y H:i:s') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Duração') }}</dt>
                        <dd>
                            @if ($task->durationSeconds() !== null)
                                {{ $task->durationSeconds() }} s
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($task->hasCheckpoint())
                    <div class="rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30 p-3 text-amber-950 dark:text-amber-100 text-xs">
                        <p class="font-semibold">{{ __('Checkpoint') }}</p>
                        <p class="mt-1">{{ __(':n município(s) já processados. Ao retomar, a fila continua nos restantes.', ['n' => count($task->checkpointCompletedCityIds())]) }}</p>
                    </div>
                @endif

                @if ($canResume ?? false)
                    <form method="post" action="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.resume', $task) }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                            {{ $task->hasCheckpoint() ? __('Retomar da fila (checkpoint)') : __('Reenfileirar tarefa') }}
                        </button>
                    </form>
                @endif

                @if ($task->error_message && $task->status === 'failed')
                    <div class="rounded-lg border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40 p-3 text-red-800 dark:text-red-200">
                        <p class="font-semibold text-xs uppercase">{{ __('Erro da fila') }}</p>
                        <p class="mt-1">{{ $task->error_message }}</p>
                        <p class="mt-2 text-xs opacity-90">{{ __('Tarefas geo com vários municípios passam a guardar progresso por cidade; use «Retomar» para continuar sem repetir o que já terminou.') }}</p>
                    </div>
                @endif

                @if ($task->isExportDownloadable())
                    <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.download', $task) }}" class="sync-queue-download-btn sync-queue-download-btn--lg">
                        <x-icons.file-download class="h-5 w-5" />
                        <span>
                            {{ __('Descarregar') }}
                            @if ($task->exportFormatLabel())
                                {{ $task->exportFormatLabel() }}
                            @endif
                            @if ($task->exportFilename())
                                <span class="font-normal opacity-90">({{ $task->exportFilename() }})</span>
                            @endif
                        </span>
                    </a>
                @endif

                @if (is_array($task->result) && ($task->result['message'] ?? '') !== '')
                    <div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('Mensagem') }}</p>
                        <p class="mt-1 whitespace-pre-wrap">{{ $task->result['message'] }}</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="sync-queue-panel sync-queue-panel--slate">
            <header class="sync-queue-panel__header py-3">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">{{ __('Log de andamento') }}</p>
            </header>
            <div class="sync-queue-panel__body bg-slate-950 text-slate-100 rounded-b-xl -mt-px">
                @if (filled($task->output_log))
                    <pre class="text-xs overflow-x-auto whitespace-pre-wrap max-h-96 p-1">{{ $task->output_log }}</pre>
                @elseif ($task->status === 'processing' || $task->status === 'pending')
                    <p class="text-xs text-slate-400 leading-relaxed">{{ __('A tarefa está na fila ou a correr — actualize a página para ver novas linhas (passos, notas e saída de comandos).') }}</p>
                @else
                    <p class="text-xs text-slate-500">{{ __('Sem registro de andamento para esta tarefa.') }}</p>
                @endif
            </div>
        </section>

        @if ($task->payload)
            <details class="sync-queue-panel sync-queue-panel--slate">
                <summary class="sync-queue-panel__header cursor-pointer text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Payload') }}</summary>
                <div class="sync-queue-panel__body">
                    <pre class="overflow-x-auto text-xs text-gray-600 dark:text-gray-400">{{ json_encode($task->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </details>
        @endif

        <x-slot name="shortcuts">
            <x-admin.import-hub.link-chip href="{{ route('admin.public-data.index') }}">{{ __('Hub dados públicos') }}</x-admin.import-hub.link-chip>
            @if (filled($taskTheme['admin_route'] ?? null))
                <x-admin.import-hub.link-chip href="{{ route($taskTheme['admin_route']) }}">{{ __('Módulo de sincronização') }}</x-admin.import-hub.link-chip>
            @endif
        </x-slot>
    </x-admin.import-hub.shell>
</x-app-layout>
