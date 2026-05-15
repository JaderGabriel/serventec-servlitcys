<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Fila de sincronização') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ __('Tarefas geográficas, pedagógicas, FUNDEB e i-Educar.') }}
                </p>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono shrink-0">
                {{ $queueConnection }} · {{ $queueName }}
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm space-y-2">
                @if (config('ieducar.admin_sync.schedule.enabled', true))
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ __('Agendador (recomendado)') }}</p>
                    <code class="block text-xs text-gray-600 dark:text-gray-400">* * * * * php artisan schedule:run</code>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('A cada minuto processa jobs pendentes (até :s s por execução).', ['s' => (string) config('ieducar.admin_sync.schedule.max_seconds', 55)]) }}</p>
                @else
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ __('Worker manual / Supervisor') }}</p>
                    <code class="block text-xs text-gray-600 dark:text-gray-400">php artisan admin-sync:work</code>
                @endif
            </div>

            <form method="get" class="flex flex-wrap gap-3 items-end text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Estado') }}</label>
                    <select name="status" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach (\App\Enums\AdminSyncTaskStatus::cases() as $st)
                            <option value="{{ $st->value }}" @selected($filterStatus === $st->value)>{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Domínio') }}</label>
                    <select name="domain" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach (\App\Enums\AdminSyncDomain::cases() as $dom)
                            <option value="{{ $dom->value }}" @selected($filterDomain === $dom->value)>{{ $dom->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('Filtrar') }}</button>
            </form>

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
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $task->city?->name ?? '—' }}</td>
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
                                    {{ __('Nenhuma tarefa. Use as páginas de sincronização para enfileirar.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $tasks->links() }}
        </div>
    </div>
</x-app-layout>
