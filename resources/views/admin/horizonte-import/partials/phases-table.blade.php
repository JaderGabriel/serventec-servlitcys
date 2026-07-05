@php
    use App\Support\Admin\AdminImportHubCatalog;

    $phases = is_array($phases ?? null) ? $phases : [];
@endphp

@if ($phases !== [])
    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
        <div class="border-b border-slate-200 dark:border-slate-700 bg-slate-50/90 dark:bg-slate-900/60 px-4 py-3">
            <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Cobertura por fase') }}</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Estado operacional e comandos CLI de cada fonte Horizonte.') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-xs">
                <thead class="bg-slate-50/50 text-[11px] uppercase tracking-wide text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Fase') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Cobertura') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Rotina') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('CLI') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($phases as $phase)
                        @php
                            $ok = (bool) ($phase['ok'] ?? false);
                            $level = $ok ? 'ok' : (filled($phase['blocked'] ?? null) ? 'warn' : 'partial');
                            $badgeClass = AdminImportHubCatalog::statusBadgeClasses()[$level]
                                ?? AdminImportHubCatalog::statusBadgeClasses()['neutral'];
                            $hubLink = $phase['admin_url'] ?? route('admin.horizonte-import.index');
                        @endphp
                        <tr class="bg-white dark:bg-gray-900/40">
                            <td class="px-4 py-3 align-top">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $phase['label'] ?? '' }}</p>
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ $phase['description'] ?? '' }}</p>
                                @if (filled($phase['blocked'] ?? null))
                                    <p class="mt-1 text-[11px] font-medium text-amber-800 dark:text-amber-200">{{ $phase['blocked'] }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badgeClass }}">
                                    {{ $ok ? __('OK') : __('Pendente') }}
                                </span>
                                @if (($phase['metric'] ?? null) !== null)
                                    <p class="mt-1 tabular-nums text-gray-700 dark:text-gray-300">
                                        @if (filled($phase['metric_total'] ?? null))
                                            {{ number_format((int) $phase['metric']) }}/{{ number_format((int) $phase['metric_total']) }}
                                        @else
                                            {{ number_format((int) $phase['metric']) }}
                                        @endif
                                        <span class="text-gray-500">{{ $phase['metric_label'] ?? '' }}</span>
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                <a href="{{ $hubLink }}" class="text-sky-600 dark:text-sky-400 hover:underline">
                                    {{ $phase['routine_label'] ?? __('Ver no hub') }}
                                </a>
                            </td>
                            <td class="px-4 py-3 align-top">
                                @if (filled($phase['cli'] ?? null))
                                    <code class="block rounded bg-gray-100 px-2 py-1 text-[10px] text-gray-800 dark:bg-gray-800 dark:text-gray-200">{{ $phase['cli'] }}</code>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
