@props(['enrollmentData'])

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Lista uma amostra das últimas linhas da tabela de matrícula do iEducar (códigos de matrícula e referência de turma). Serve para inspecionar dados brutos; para relatórios filtrados por ano ou escola, alinhe os repositórios com o schema local.') }}
    </p>

    @if (! empty($enrollmentData['error']))
        <div class="rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
            {{ $enrollmentData['error'] }}
        </div>
    @endif

    @if (! empty($enrollmentData['rows']))
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Matrícula') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Turma (ref.)') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($enrollmentData['rows'] as $row)
                        <tr>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $row->cod_matricula ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $row->ref_cod_turma ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Últimas 30 matrículas (amostra). Ajuste joins e filtros no repositório conforme o schema.') }}</p>
    @elseif (empty($enrollmentData['error']))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem dados de matrícula ou cidade não selecionada.') }}</p>
    @endif
</div>
