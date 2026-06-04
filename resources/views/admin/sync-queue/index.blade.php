<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Filas de processamento') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ __('Sincronizações admin (FUNDEB, geo, SAEB, CadÚnico/Cecad), exportações NEE e relatórios PDF — por área temática.') }}
                </p>
            </div>
            @if ($filterDomain !== '' || $filterStatus !== '' || $filterPdfStatus !== '')
                <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline shrink-0">
                    {{ __('Limpar todos os filtros') }}
                </a>
            @endif
        </div>
    </x-slot>

    <x-admin.import-hub.shell
        active="queue"
        accent="slate"
        :eyebrow="__('Operação e automação')"
        :title="__('Fila de sincronização e relatórios PDF')"
        :description="__('Acompanhe tarefas enfileiradas pelas telas de importação. Em produção, mantenha workers ativos e o agendador schedule:run conforme abaixo.')"
        queue-banner-compact
        :doc-href="route('admin.documentation.show', ['doc' => 'docs/IMPORTACAO_DADOS_PUBLICOS.md'])"
        :doc-label="__('Documentação de importação')"
    >
        <x-admin.import-hub.flow-panel :title="__('Workers e agendador')" open>
            <div class="mt-3 space-y-4 text-sm">
                <div>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ __('Workers (produção)') }}</p>
                    @if ($queueIsSync)
                        <p class="mt-2 text-amber-800 dark:text-amber-200 text-xs leading-relaxed">
                            {{ __('QUEUE_CONNECTION=sync — os jobs correm na própria requisição HTTP e não aparecem na tabela jobs. Para fila real, use database ou redis e:') }}
                        </p>
                    @endif
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <div class="rounded-lg border border-slate-200/80 dark:border-slate-600 bg-white/70 dark:bg-slate-900/60 px-3 py-2">
                            <p class="text-[10px] font-semibold uppercase text-slate-500 dark:text-slate-400">{{ __('Sincronização') }}</p>
                            <code class="mt-1 block text-[11px] text-slate-700 dark:text-slate-300 break-all">php artisan queue:work {{ $syncQueueConnection }} --queue={{ $syncQueueName }}</code>
                        </div>
                        <div class="rounded-lg border border-slate-200/80 dark:border-slate-600 bg-white/70 dark:bg-slate-900/60 px-3 py-2">
                            <p class="text-[10px] font-semibold uppercase text-slate-500 dark:text-slate-400">{{ __('PDF analítico') }}</p>
                            <code class="mt-1 block text-[11px] text-slate-700 dark:text-slate-300 break-all">php artisan queue:work {{ $pdfQueueConnection }} --queue={{ $pdfQueueName }}</code>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Combinado:') }}
                        <code class="font-mono text-[11px]">php artisan queue:work {{ $queueDefault }} --queue={{ $syncQueueName }},{{ $pdfQueueName }}</code>
                    </p>
                </div>
                @if (config('ieducar.admin_sync.schedule.enabled', true) || config('analytics.pdf_report.schedule.enabled', true))
                    <div class="border-t border-slate-200/80 dark:border-slate-700 pt-3">
                        <p class="text-xs font-medium text-gray-800 dark:text-gray-200">{{ __('Agendador (schedule:run)') }}</p>
                        <code class="block text-xs text-gray-600 dark:text-gray-400 mt-1">*/{{ config('schedule.runner_interval_minutes', 3) }} * * * * php artisan schedule:run</code>
                        <p class="mt-2 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            {{ __('Sincronizações automáticas (CadÚnico, geo, FUNDEB, etc.) dependem do cron acima e das opções em cada tela de importação.') }}
                        </p>
                    </div>
                @endif
            </div>
        </x-admin.import-hub.flow-panel>

        <section class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Filas por área') }}</h3>
            @include('admin.sync-queue.partials.theme-overview')
        </section>

        <div class="sync-queue-panel sync-queue-panel--slate">
            <div class="sync-queue-panel__header py-3">
                <div class="flex flex-wrap gap-2 text-xs">
                    @foreach (\App\Enums\AdminSyncTaskStatus::cases() as $st)
                        <span class="rounded-full px-2.5 py-1 {{ $st->badgeClass() }}">
                            {{ __('Sincronização') }} · {{ $st->label() }}: {{ (int) ($counts[$st->value] ?? 0) }}
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
                    <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Nenhuma tarefa de sincronização enfileirada.') }}
                        <p class="mt-2 text-xs">{{ __('Use Hub, CadÚnico, Geo ou SAEB para enfileirar importações.') }}</p>
                    </div>
                @endforelse
            </section>
        @endif

        @include('admin.sync-queue.partials.pdf-theme-panel')

        <x-slot name="shortcuts">
            <x-admin.import-hub.link-chip href="{{ route('admin.public-data.index') }}">{{ __('Hub dados públicos') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.cadunico-sync.index') }}">{{ __('CadÚnico') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.geo-sync.index') }}">{{ __('Geo') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.pedagogical-sync.index') }}">{{ __('SAEB') }}</x-admin.import-hub.link-chip>
        </x-slot>
    </x-admin.import-hub.shell>
</x-app-layout>
