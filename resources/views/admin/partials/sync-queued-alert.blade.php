@if (session('admin_sync_queued'))
    @php
        $queued = session('admin_sync_queued');
        $taskId = (int) ($queued['task_id'] ?? 0);
    @endphp
    <div
        x-data="{ show: true }"
        x-show="show"
        x-transition
        class="rounded-lg border border-emerald-200 bg-emerald-50/95 dark:border-emerald-800 dark:bg-emerald-950/35 px-4 py-3 text-sm"
        role="status"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="font-semibold text-emerald-950 dark:text-emerald-100">{{ __('Pedido enviado para a fila') }}</p>
                <p class="mt-1 text-emerald-900/90 dark:text-emerald-200/90">
                    {{ __('A tarefa será processada em segundo plano. Atualize a fila para ver quando concluir.') }}
                </p>
                @if ($taskId > 0)
                    <p class="mt-1 text-xs text-emerald-800/80 dark:text-emerald-300/80 font-mono">#{{ $taskId }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                @if ($taskId > 0)
                    <a href="{{ route('admin.sync-queue.show', $taskId) }}" class="inline-flex items-center rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-600">
                        {{ __('Ver tarefa') }}
                    </a>
                @endif
                <a href="{{ route('admin.sync-queue.index') }}" class="inline-flex items-center rounded-lg border border-emerald-400/80 dark:border-emerald-600 px-3 py-1.5 text-xs font-medium text-emerald-900 dark:text-emerald-100 hover:bg-emerald-100/60 dark:hover:bg-emerald-900/40">
                    {{ __('Fila') }}
                </a>
                <button type="button" class="text-emerald-700 dark:text-emerald-300 text-lg leading-none px-1" x-on:click="show = false" aria-label="{{ __('Fechar') }}">×</button>
            </div>
        </div>
    </div>
@endif
