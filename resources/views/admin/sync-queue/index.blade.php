<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Filas de processamento') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ __('Sincronizações admin (geo, pedagógico, FUNDEB) e relatórios PDF do painel analítico.') }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 px-4 py-4 text-sm space-y-4">
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
                @if (config('ieducar.admin_sync.schedule.enabled', true))
                    <div class="border-t border-slate-200/80 dark:border-slate-700 pt-3">
                        <p class="text-xs font-medium text-gray-800 dark:text-gray-200">{{ __('Agendador') }}</p>
                        <code class="block text-xs text-gray-600 dark:text-gray-400 mt-1">*/{{ config('schedule.runner_interval_minutes', 3) }} * * * * php artisan schedule:run</code>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('admin-sync: cada :sync min (até :s s). Pulse: :pulse min.', [
                            'sync' => (string) config('ieducar.admin_sync.schedule.interval_minutes', 60),
                            's' => (string) config('ieducar.admin_sync.schedule.max_seconds', 3300),
                            'pulse' => (string) config('pulse.schedule.interval_minutes', 3),
                        ]) }}</p>
                    </div>
                @endif
            </div>

            {{-- Sincronização admin --}}
            <section class="space-y-4" id="fila-sync">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Sincronização admin') }}</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-mono mt-0.5">{{ $syncQueueConnection }} · {{ $syncQueueName }}</p>
                    </div>
                    <form method="get" class="flex flex-wrap gap-3 items-end text-sm">
                        <input type="hidden" name="pdf_status" value="{{ $filterPdfStatus }}" />
                        <input type="hidden" name="pdf_page" value="{{ request('pdf_page') }}" />
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Estado') }}</label>
                            <select name="status" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                <option value="">{{ __('Todos') }}</option>
                                @foreach (\App\Enums\AdminSyncTaskStatus::cases() as $st)
                                    <option value="{{ $st->value }}" @selected($filterStatus === $st->value)>{{ $st->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Domínio') }}</label>
                            <select name="domain" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                <option value="">{{ __('Todos') }}</option>
                                @foreach (\App\Enums\AdminSyncDomain::cases() as $dom)
                                    <option value="{{ $dom->value }}" @selected($filterDomain === $dom->value)>{{ $dom->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('Filtrar') }}</button>
                    </form>
                </div>

                <div class="flex flex-wrap gap-2 text-xs">
                    @foreach (\App\Enums\AdminSyncTaskStatus::cases() as $st)
                        <span class="rounded-full px-2.5 py-1 {{ $st->badgeClass() }}">
                            {{ $st->label() }}: {{ (int) ($counts[$st->value] ?? 0) }}
                        </span>
                    @endforeach
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/80 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-medium">#</th>
                                <th class="px-4 py-3 font-medium">{{ __('Área') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Tarefa') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Cidade') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Estado') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Criada') }}</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($tasks as $task)
                                <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                                    <td class="px-4 py-3 font-mono text-xs">{{ $task->id }}</td>
                                    <td class="px-4 py-3">{{ $task->domainEnum()->label() }}</td>
                                    <td class="px-4 py-3 max-w-xs truncate" title="{{ $task->label }}">{{ $task->label }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300 max-w-md">
                                        @php $cityNames = $task->cityNames(); @endphp
                                        @if ($cityNames === [])
                                            —
                                        @elseif (count($cityNames) === 1)
                                            {{ $cityNames[0] }}
                                        @else
                                            <p class="text-xs leading-relaxed" title="{{ implode(', ', $cityNames) }}">{{ implode(', ', $cityNames) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $task->statusEnum()->badgeClass() }}">
                                            {{ $task->statusEnum()->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap text-xs">{{ $task->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.sync-queue.show', $task) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline text-xs">{{ __('Abrir') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        {{ __('Nenhuma tarefa de sincronização.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $tasks->links() }}
            </section>

            {{-- PDF analítico --}}
            <section class="space-y-4" id="fila-pdf">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Relatórios PDF (Diagnóstico)') }}</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-mono mt-0.5">{{ $pdfQueueConnection }} · {{ $pdfQueueName }}</p>
                    </div>
                    <form method="get" class="flex flex-wrap gap-3 items-end text-sm">
                        <input type="hidden" name="status" value="{{ $filterStatus }}" />
                        <input type="hidden" name="domain" value="{{ $filterDomain }}" />
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Estado PDF') }}</label>
                            <select name="pdf_status" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                <option value="">{{ __('Todos') }}</option>
                                @foreach (\App\Enums\AnalyticsReportExportStatus::cases() as $st)
                                    <option value="{{ $st->value }}" @selected($filterPdfStatus === $st->value)>{{ $st->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('Filtrar') }}</button>
                    </form>
                </div>

                <div class="flex flex-wrap gap-2 text-xs">
                    @foreach (\App\Enums\AnalyticsReportExportStatus::cases() as $st)
                        <span class="rounded-full px-2.5 py-1 {{ $st->badgeClass() }}">
                            {{ $st->label() }}: {{ (int) ($pdfCounts[$st->value] ?? 0) }}
                        </span>
                    @endforeach
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/80 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-medium">#</th>
                                <th class="px-4 py-3 font-medium">{{ __('Município') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Pedido por') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Estado') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Criado') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Acção') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($pdfExports as $export)
                                @php $st = $export->statusEnum(); @endphp
                                <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                                    <td class="px-4 py-3 font-mono text-xs">{{ $export->id }}</td>
                                    <td class="px-4 py-3">{{ $export->city?->name ?? '—' }}@if ($export->city?->uf) <span class="text-gray-400">({{ $export->city->uf }})</span>@endif</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $export->user?->name ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $st->badgeClass() }}">
                                            {{ $st->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap text-xs">{{ $export->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 text-right text-xs">
                                        @if ($export->isDownloadable())
                                            <a href="{{ route('dashboard.analytics.pdf.download', $export) }}" class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">{{ __('PDF') }}</a>
                                        @elseif ($st === \App\Enums\AnalyticsReportExportStatus::Failed && filled($export->error_message))
                                            <span class="text-red-600 dark:text-red-400" title="{{ $export->error_message }}">{{ __('Erro') }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        {{ __('Nenhum relatório PDF. Use «Gerar PDF» na aba Diagnóstico do painel analítico.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $pdfExports->links() }}
            </section>
        </div>
    </div>
</x-app-layout>
