@props(['titulo', 'escolas' => [], 'tone' => 'gray'])

@php
    $rows = is_array($escolas) ? $escolas : [];
    $border = match ($tone) {
        'emerald' => 'border-emerald-200 dark:border-emerald-800',
        'sky' => 'border-sky-200 dark:border-sky-800',
        'amber' => 'border-amber-200 dark:border-amber-800',
        default => 'border-gray-200 dark:border-gray-700',
    };
@endphp

@if (count($rows) > 0)
    <details class="rounded-lg border {{ $border }} overflow-hidden" @if (count($rows) <= 8) open @endif>
        <summary class="px-4 py-2 text-sm font-medium cursor-pointer bg-white/60 dark:bg-gray-900/40">
            {{ $titulo }} ({{ number_format(count($rows), 0, ',', '.') }})
        </summary>
        <div class="overflow-x-auto border-t {{ $border }}">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Escola') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('INEP') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Registo') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach (array_slice($rows, 0, 80) as $row)
                        <tr>
                            <td class="px-4 py-2">{{ $row['nome'] ?? '—' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $row['inep'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $row['detalhe'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (count($rows) > 80)
            <p class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Listagem limitada a 80 unidades — refine o filtro por escola.') }}</p>
        @endif
    </details>
@endif
