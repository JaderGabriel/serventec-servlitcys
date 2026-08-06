<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Operação') }}</p>
                <h2 class="font-display font-semibold text-xl sm:text-2xl text-serv-navy dark:text-gray-100 leading-tight">
                    {{ __('Filas de processamento') }}
                </h2>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1 max-w-2xl leading-relaxed">
                    {{ __('Acompanhe importações, abastecimento Horizonte e exportações PDF enfileiradas — por área temática.') }}
                </p>
            </div>
            @if ($filterDomain !== '' || $filterStatus !== '' || $filterPdfStatus !== '')
                <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}" class="text-sm font-medium text-sky-700 dark:text-sky-300 hover:underline shrink-0">
                    {{ __('Limpar filtros') }}
                </a>
            @endif
        </div>
    </x-slot>

    <x-admin.import-hub.shell
        active="queue"
        accent="slate"
        :eyebrow="__('Workers e automação')"
        :title="__('Estado das filas')"
        :description="__('Tarefas criadas pelas telas de importação. Em produção, mantenha workers activos e o agendador a correr no intervalo indicado.')"
        queue-banner-compact
        :doc-href="route('admin.documentation.show', ['doc' => 'docs/IMPORTACAO_DADOS_PUBLICOS.md'])"
        :doc-label="__('Documentação de importação')"
    >
        <x-admin.import-hub.flow-panel :title="__('Como processar as filas')" open>
            <div class="mt-3 space-y-4 text-sm">
                <div>
                    <p class="font-medium text-serv-navy dark:text-slate-100">{{ __('Workers (produção)') }}</p>
                    @if ($queueIsSync)
                        <p class="mt-2 text-amber-800 dark:text-amber-200 text-xs leading-relaxed rounded-lg border border-amber-200/80 bg-amber-50/80 dark:border-amber-800/50 dark:bg-amber-950/30 px-3 py-2">
                            {{ __('QUEUE_CONNECTION=sync — os jobs correm na própria requisição e não aparecem na tabela jobs. Para fila real, use database ou redis com os comandos abaixo.') }}
                        </p>
                    @endif
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <div class="rounded-lg border border-slate-200/80 dark:border-slate-600 bg-white/70 dark:bg-slate-900/60 px-3 py-2">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Importações e sync') }}</p>
                            <code class="mt-1 block text-[11px] text-slate-700 dark:text-slate-300 break-all">php artisan queue:work {{ $syncQueueConnection }} --queue={{ $syncQueueName }}</code>
                        </div>
                        <div class="rounded-lg border border-slate-200/80 dark:border-slate-600 bg-white/70 dark:bg-slate-900/60 px-3 py-2">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Relatórios PDF') }}</p>
                            <code class="mt-1 block text-[11px] text-slate-700 dark:text-slate-300 break-all">php artisan queue:work {{ $pdfQueueConnection }} --queue={{ $pdfQueueName }}</code>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Um worker para as duas filas:') }}
                        <code class="font-mono text-[11px]">php artisan queue:work {{ $queueDefault }} --queue={{ $syncQueueName }},{{ $pdfQueueName }}</code>
                    </p>
                </div>
                <div class="border-t border-slate-200/80 dark:border-slate-700 pt-3">
                    <p class="text-xs font-semibold text-serv-navy dark:text-slate-100">{{ __('Agendador do sistema') }}</p>
                    <code class="block text-xs text-slate-600 dark:text-slate-400 mt-1">*/{{ config('schedule.runner_interval_minutes', 3) }} * * * * php artisan schedule:run</code>
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                        {{ __('Jobs periódicos (Horizonte, CadÚnico, limpeza, Pulse…) estão listados abaixo.') }}
                        <a href="#agendamentos" class="font-medium text-sky-700 dark:text-sky-300 hover:underline">{{ __('Ver agendamentos') }} →</a>
                    </p>
                </div>
            </div>
        </x-admin.import-hub.flow-panel>

        @include('admin.sync-queue.partials.scheduled-jobs-panel')

        <section class="space-y-3">
            <div>
                <h3 class="text-sm font-semibold font-display text-serv-navy dark:text-slate-100">{{ __('Áreas temáticas') }}</h3>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Abra uma área para filtrar tarefas ou saltar para a secção correspondente.') }}</p>
            </div>
            @include('admin.sync-queue.partials.theme-overview')
        </section>

        <div class="sync-queue-panel sync-queue-panel--slate">
            <div class="sync-queue-panel__header py-3">
                <p class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Resumo — sincronizações') }}</p>
                <div class="flex flex-wrap gap-2 text-xs">
                    @foreach (\App\Enums\AdminSyncTaskStatus::cases() as $st)
                        <span class="rounded-md px-2.5 py-1 {{ $st->badgeClass() }}">
                            {{ $st->label() }}: {{ (int) ($counts[$st->value] ?? 0) }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($activeThemeSection !== null)
            @include('admin.sync-queue.partials.sync-theme-panel', ['section' => $activeThemeSection])
        @else
            <section class="space-y-6" id="fila-sync">
                @forelse ($syncThemeSections as $section)
                    @include('admin.sync-queue.partials.sync-theme-panel', ['section' => $section])
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-600 px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                        <p class="font-medium text-slate-700 dark:text-slate-300">{{ __('Nenhuma tarefa de sincronização na fila') }}</p>
                        <p class="mt-2 text-xs max-w-md mx-auto leading-relaxed">{{ __('Enfileire importações a partir de Dados públicos, CadÚnico, Geo ou SAEB pedagógico.') }}</p>
                    </div>
                @endforelse
            </section>
        @endif

        @include('admin.sync-queue.partials.horizonte-theme-panel')

        @include('admin.sync-queue.partials.pdf-theme-panel')

        <x-slot name="shortcuts">
            <x-admin.import-hub.link-chip tone="slate" href="#agendamentos">{{ __('Agendamentos') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.public-data.index') }}">{{ __('Dados públicos') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="sky" href="{{ route('admin.horizonte-import.index') }}">{{ __('Horizonte') }}</x-admin.import-hub.link-chip>
            @if (auth()->user()?->canViewHorizonte())
                <x-admin.import-hub.link-chip tone="indigo" href="{{ route('dashboard.horizonte') }}">{{ __('Mapa Horizonte') }}</x-admin.import-hub.link-chip>
            @endif
            <x-admin.import-hub.link-chip href="{{ route('admin.cadunico-sync.index') }}">{{ __('CadÚnico') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.geo-sync.index') }}">{{ __('Geo') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.pedagogical-sync.index') }}">{{ __('SAEB / IDEB') }}</x-admin.import-hub.link-chip>
        </x-slot>
    </x-admin.import-hub.shell>
</x-app-layout>
