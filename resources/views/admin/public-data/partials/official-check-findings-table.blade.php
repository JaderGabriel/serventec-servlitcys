@php
    use App\Support\Admin\AdminImportHubCatalog;

    $tone = $tone ?? 'action';
    $rowMuted = $tone === 'aligned';
@endphp

<div @class([
    'overflow-hidden rounded-xl border',
    'border-amber-200 dark:border-amber-900/60' => $tone === 'action',
    'border-gray-200 dark:border-gray-700' => $tone === 'aligned',
])>
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-xs">
            <thead @class([
                'text-[11px] uppercase tracking-wide',
                'bg-amber-50/90 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200' => $tone === 'action',
                'bg-gray-50/90 text-gray-500 dark:bg-gray-900/60 dark:text-gray-400' => $tone === 'aligned',
            ])>
                <tr>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Fonte') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Estado') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Resumo') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Rotina sugerida') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($findings as $finding)
                    @php
                        $ui = is_array($finding['ui'] ?? null) ? $finding['ui'] : [];
                        $level = $ui['level'] ?? 'neutral';
                        $badgeClass = AdminImportHubCatalog::statusBadgeClasses()[$level]
                            ?? AdminImportHubCatalog::statusBadgeClasses()['neutral'];
                        $status = (string) ($finding['status'] ?? '');
                        $showRoutine = in_array($status, ['new_available', 'attention', 'unreachable', 'not_configured'], true);
                        $anchor = (string) ($finding['source_anchor'] ?? '#');
                    @endphp
                    <tr @class([
                        'bg-white dark:bg-gray-900/40' => ! $rowMuted,
                        'bg-gray-50/70 text-gray-600 dark:bg-gray-900/20 dark:text-gray-400' => $rowMuted,
                    ])>
                        <td class="px-4 py-3 align-top">
                            <a href="{{ $anchor }}" @class([
                                'font-medium hover:underline',
                                'text-sky-700 dark:text-sky-300' => ! $rowMuted,
                                'text-gray-700 dark:text-gray-300' => $rowMuted,
                            ])>
                                {{ $finding['source_title'] ?? $finding['source_id'] ?? '—' }}
                            </a>
                        </td>
                        <td class="px-4 py-3 align-top">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badgeClass }}">
                                {{ $ui['label'] ?? '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 align-top">
                            <p @class([
                                'font-medium',
                                'text-gray-900 dark:text-gray-100' => ! $rowMuted,
                                'text-gray-700 dark:text-gray-300' => $rowMuted,
                            ])>{{ $finding['headline'] ?? '' }}</p>
                            @if (filled($finding['detail'] ?? null))
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ $finding['detail'] }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 align-top">
                            @if ($showRoutine && filled($finding['routine_cli'] ?? null))
                                <code class="block rounded bg-gray-100 px-2 py-1 text-[10px] text-gray-800 dark:bg-gray-800 dark:text-gray-200">{{ $finding['routine_cli'] }}</code>
                            @elseif ($showRoutine && filled($finding['routine_label'] ?? null))
                                <a href="{{ $anchor }}" class="text-sky-600 dark:text-sky-400 hover:underline">
                                    {{ $finding['routine_label'] }}
                                </a>
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
