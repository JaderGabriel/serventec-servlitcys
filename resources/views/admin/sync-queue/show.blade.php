<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Tarefa #:id', ['id' => (string) $task->id]) }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $task->label }}</p>
        </div>
    </x-slot>

    <div class="py-10 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <a href="{{ route('admin.sync-queue.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← {{ __('Voltar à fila') }}</a>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 space-y-4 text-sm">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Domínio') }}</dt>
                    <dd class="font-medium">{{ $task->domainEnum()->label() }} <span class="text-gray-400">({{ $task->task_key }})</span></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Estado') }}</dt>
                    <dd>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $task->statusEnum()->badgeClass() }}">
                            {{ $task->statusEnum()->label() }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Cidade') }}</dt>
                    <dd class="font-medium">
                        @if ($task->city)
                            {{ $task->city->name }}
                            @if ($task->city->ibge_municipio)
                                <span class="text-gray-500">(IBGE {{ $task->city->ibge_municipio }})</span>
                            @endif
                        @else
                            {{ __('Todas / nacional') }}
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

            @if ($task->error_message)
                <div class="rounded-lg border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40 p-3 text-red-800 dark:text-red-200">
                    <p class="font-semibold text-xs uppercase">{{ __('Erro') }}</p>
                    <p class="mt-1">{{ $task->error_message }}</p>
                </div>
            @endif

            @if ($task->status === 'completed' && ($task->result['export_path'] ?? null))
                <a href="{{ route('admin.sync-queue.download', $task) }}" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    {{ __('Descarregar export JSON') }}
                </a>
            @endif

            @if (is_array($task->result) && ($task->result['message'] ?? '') !== '')
                <div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('Mensagem') }}</p>
                    <p class="mt-1 whitespace-pre-wrap">{{ $task->result['message'] }}</p>
                </div>
            @endif
        </div>

        @if (filled($task->output_log))
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-slate-950 text-slate-100 p-4">
                <p class="text-xs font-semibold text-slate-400 mb-2">{{ __('Log de andamento') }}</p>
                <pre class="text-xs overflow-x-auto whitespace-pre-wrap max-h-96">{{ $task->output_log }}</pre>
            </div>
        @endif

        @if ($task->payload)
            <details class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-xs">
                <summary class="cursor-pointer font-medium text-gray-700 dark:text-gray-300">{{ __('Payload') }}</summary>
                <pre class="mt-2 overflow-x-auto text-gray-600 dark:text-gray-400">{{ json_encode($task->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
        @endif
    </div>
</x-app-layout>
