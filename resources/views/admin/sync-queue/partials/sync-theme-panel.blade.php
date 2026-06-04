@php
    $theme = $section['theme'];
    $tasks = $section['tasks'];
    $total = (int) ($section['total'] ?? 0);
    $accent = $theme['accent'] ?? 'slate';
    $domainValue = $theme['domain']->value;
    $isPaginated = $tasks instanceof \Illuminate\Pagination\AbstractPaginator;
@endphp

<section
    id="{{ $theme['anchor'] }}"
    class="sync-queue-panel sync-queue-panel--{{ $accent }} scroll-mt-6"
>
    <header class="sync-queue-panel__header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex gap-3 min-w-0">
                <span class="sync-queue-panel__icon" aria-hidden="true">
                    <x-ui.icon :name="$theme['icon']" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h3 class="sync-queue-panel__title">{{ $theme['label'] }}</h3>
                    <p class="sync-queue-panel__desc">{{ $theme['description'] }}</p>
                    <p class="mt-1 text-[11px] font-mono text-slate-500 dark:text-slate-400">{{ $syncQueueConnection }} · {{ $syncQueueName }}</p>
                    @if (! empty($theme['admin_route']))
                        <a href="{{ route($theme['admin_route']) }}" class="mt-2 inline-flex text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ __('Abrir módulo de sincronização') }} →
                        </a>
                    @endif
                </div>
            </div>
            <form method="get" action="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}" class="flex flex-wrap gap-2 items-end text-sm">
                <input type="hidden" name="domain" value="{{ $domainValue }}" />
                <input type="hidden" name="pdf_status" value="{{ $filterPdfStatus }}" />
                <div>
                    <label class="block text-[10px] font-medium uppercase tracking-wide text-slate-500 mb-1">{{ __('Estado') }}</label>
                    <select name="status" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm min-w-[8rem]">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach (\App\Enums\AdminSyncTaskStatus::cases() as $st)
                            <option value="{{ $st->value }}" @selected($filterStatus === $st->value)>{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('Filtrar') }}</button>
                @if ($filterStatus !== '' || $filterDomain === $domainValue)
                    <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index', array_filter(['pdf_status' => $filterPdfStatus !== '' ? $filterPdfStatus : null])) }}#{{ $theme['anchor'] }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                        {{ __('Limpar') }}
                    </a>
                @endif
            </form>
        </div>
        <div class="flex flex-wrap gap-2 text-xs mt-3">
            @foreach (\App\Enums\AdminSyncTaskStatus::cases() as $st)
                @php $n = (int) ($theme['counts'][$st->value] ?? 0); @endphp
                @if ($n > 0)
                    <span class="rounded-full px-2.5 py-1 {{ $st->badgeClass() }}">{{ $st->label() }}: {{ $n }}</span>
                @endif
            @endforeach
            <span class="text-slate-500 dark:text-slate-400 ml-auto">{{ __('Total na fila: :n', ['n' => $total]) }}</span>
        </div>
    </header>

    <div class="sync-queue-panel__body space-y-2">
        @forelse ($tasks as $task)
            @include('admin.sync-queue.partials.sync-task-card', ['task' => $task, 'accent' => $accent])
        @empty
            <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ $isPaginated ? __('Nenhuma tarefa com estes filtros.') : __('Nenhuma tarefa nesta fila.') }}
            </p>
        @endforelse
        @if ($isPaginated && $tasks->hasPages())
            <div class="pt-2">{{ $tasks->links() }}</div>
        @elseif (! $isPaginated && $total > $tasks->count())
            <div class="pt-2 text-center">
                <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index', array_filter(['domain' => $domainValue, 'status' => $filterStatus !== '' ? $filterStatus : null, 'pdf_status' => $filterPdfStatus !== '' ? $filterPdfStatus : null])) }}#{{ $theme['anchor'] }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                    {{ __('Ver todas (:n)', ['n' => $total]) }}
                </a>
            </div>
        @endif
    </div>
</section>
