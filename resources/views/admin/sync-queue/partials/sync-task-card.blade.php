@php
    use App\Support\Admin\AdminSyncQueueIndexPresenter;

    $st = $task->statusEnum();
    $cityNames = $task->cityNames();
    $contextLines = AdminSyncQueueIndexPresenter::taskContextLines($task, 3);
    $canDownload = $task->isExportDownloadable();
@endphp

<article class="sync-queue-task-card sync-queue-task-card--{{ $accent ?? 'slate' }} group">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">#{{ $task->id }}</span>
                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $st->badgeClass() }}">
                    {{ $st->label() }}
                </span>
                @if ($task->isResumable())
                    <span class="text-[10px] font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">{{ __('Retomável') }}</span>
                @endif
            </div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-snug">
                <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.show', $task) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                    {{ $task->label }}
                </a>
            </h4>
            <p class="text-xs text-gray-600 dark:text-gray-400">
                @if ($cityNames === [])
                    —
                @elseif (count($cityNames) === 1)
                    {{ $cityNames[0] }}
                @else
                    <span title="{{ implode(', ', $cityNames) }}">{{ implode(', ', array_slice($cityNames, 0, 2)) }}@if (count($cityNames) > 2) (+{{ count($cityNames) - 2 }})@endif</span>
                @endif
                <span class="text-gray-400">·</span>
                {{ $task->created_at?->format('d/m/Y H:i') }}
                @if ($task->queuedBy?->name)
                    <span class="text-gray-400">·</span> {{ $task->queuedBy->name }}
                @endif
            </p>
            @if ($contextLines !== [])
                <ul class="mt-1.5 space-y-0.5 text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                    @foreach ($contextLines as $line)
                        <li class="line-clamp-1" title="{{ $line }}">{{ $line }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($task->error_message && $st === \App\Enums\AdminSyncTaskStatus::Failed)
                <p class="mt-1 text-[11px] text-red-700 dark:text-red-300 line-clamp-2" title="{{ $task->error_message }}">{{ $task->error_message }}</p>
            @endif
        </div>
        <div class="flex shrink-0 items-center gap-1.5">
            @if ($canDownload)
                <a
                    href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.download', $task) }}"
                    class="sync-queue-download-btn"
                    title="{{ __('Descarregar :file', ['file' => $task->exportFilename() ?? __('exportação')]) }}"
                >
                    <x-icons.file-download class="h-5 w-5" />
                    <span class="sr-only">{{ __('Descarregar') }}</span>
                    @if ($task->exportFormatLabel())
                        <span class="sync-queue-download-btn__fmt">{{ $task->exportFormatLabel() }}</span>
                    @endif
                </a>
            @endif
            <a
                href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.show', $task) }}"
                class="inline-flex items-center rounded-lg border border-slate-200 dark:border-slate-600 px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
            >
                {{ __('Detalhe') }}
            </a>
        </div>
    </div>
</article>
