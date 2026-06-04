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

        <div class="flex flex-wrap gap-2 text-xs">
            @foreach (\App\Enums\AdminSyncTaskStatus::cases() as $st)
                <span class="rounded-full px-2.5 py-1 {{ $st->badgeClass() }}">
                    {{ __('Sincronização') }} · {{ $st->label() }}: {{ (int) ($counts[$st->value] ?? 0) }}
                </span>
            @endforeach
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

        <section id="{{ $pdfThemeCard['anchor'] }}" class="sync-queue-panel sync-queue-panel--rose scroll-mt-6">
            <header class="sync-queue-panel__header">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex gap-3 min-w-0">
                        <span class="sync-queue-panel__icon" aria-hidden="true">
                            <x-ui.icon name="document-text" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="sync-queue-panel__title">{{ $pdfThemeCard['label'] }}</h3>
                            <p class="sync-queue-panel__desc">{{ $pdfThemeCard['description'] }}</p>
                            <p class="mt-1 text-[11px] font-mono text-slate-500 dark:text-slate-400">{{ $pdfQueueConnection }} · {{ $pdfQueueName }}</p>
                        </div>
                    </div>
                    <form method="get" class="flex flex-wrap gap-2 items-end text-sm">
                        <input type="hidden" name="domain" value="{{ $filterDomain }}" />
                        <input type="hidden" name="status" value="{{ $filterStatus }}" />
                        <div>
                            <label class="block text-[10px] font-medium uppercase tracking-wide text-slate-500 mb-1">{{ __('Estado PDF') }}</label>
                            <select name="pdf_status" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm min-w-[8rem]">
                                <option value="">{{ __('Todos') }}</option>
                                @foreach (\App\Enums\AnalyticsReportExportStatus::cases() as $st)
                                    <option value="{{ $st->value }}" @selected($filterPdfStatus === $st->value)>{{ $st->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('Filtrar') }}</button>
                    </form>
                </div>
                <div class="flex flex-wrap gap-2 text-xs mt-3">
                    @foreach (\App\Enums\AnalyticsReportExportStatus::cases() as $st)
                        @php $n = (int) ($pdfThemeCard['counts'][$st->value] ?? 0); @endphp
                        @if ($n > 0)
                            <span class="rounded-full px-2.5 py-1 {{ $st->badgeClass() }}">{{ $st->label() }}: {{ $n }}</span>
                        @endif
                    @endforeach
                </div>
            </header>
            <div class="sync-queue-panel__body space-y-2">
                @forelse ($pdfExports as $export)
                    @include('admin.sync-queue.partials.pdf-export-card', ['export' => $export])
                @empty
                    <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Nenhum relatório PDF. Use «Gerar PDF» na aba Diagnóstico do painel analítico.') }}
                    </p>
                @endforelse
                @if ($pdfExports->hasPages())
                    <div class="pt-2">{{ $pdfExports->links() }}</div>
                @endif
            </div>
        </section>

        <x-slot name="shortcuts">
            <x-admin.import-hub.link-chip href="{{ route('admin.public-data.index') }}">{{ __('Hub dados públicos') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.cadunico-sync.index') }}">{{ __('CadÚnico') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.geo-sync.index') }}">{{ __('Geo') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.pedagogical-sync.index') }}">{{ __('SAEB') }}</x-admin.import-hub.link-chip>
        </x-slot>
    </x-admin.import-hub.shell>
</x-app-layout>
