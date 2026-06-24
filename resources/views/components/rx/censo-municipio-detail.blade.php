@props([
    'censo' => [],
    'compact' => false,
])

@php
    $c = is_array($censo) ? $censo : [];
    $available = (bool) ($c['available'] ?? false);
    $total = (int) ($c['total_escolas'] ?? 0);
    $exportadas = (int) ($c['exportadas'] ?? 0);
    $fechadas = (int) ($c['fechadas'] ?? 0);
    $pendentes = (int) ($c['pendentes'] ?? 0);
    $concluidas = (int) ($c['concluidas'] ?? ($exportadas + $fechadas));
    $pct = $c['pct_concluido'] ?? null;

    $statusGroups = [
        'exported' => ['label' => __('Exportada'), 'tone' => 'emerald', 'items' => is_array($c['exported'] ?? null) ? $c['exported'] : []],
        'closed' => ['label' => __('Fechada'), 'tone' => 'sky', 'items' => is_array($c['closed'] ?? null) ? $c['closed'] : []],
        'pending' => ['label' => __('Pendente'), 'tone' => 'amber', 'items' => is_array($c['pending'] ?? null) ? $c['pending'] : []],
    ];

    $escolas = [];
    foreach ($statusGroups as $kind => $group) {
        foreach ($group['items'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $escolas[] = array_merge($row, [
                'status' => $kind,
                'status_label' => $group['label'],
                'status_tone' => $group['tone'],
            ]);
        }
    }
    usort($escolas, static fn (array $a, array $b): int => strcasecmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? '')));

    $compact = (bool) ($compact ?? false);
    $schoolCount = count($escolas);

@endphp

<div {{ $attributes->merge(['class' => $compact ? 'mt-1' : 'mt-1.5']) }}>
    @if (! $available && ! $compact)
        <p class="text-[10px] text-slate-500 dark:text-slate-400 leading-snug" title="{{ $c['note'] ?? '' }}">
            {{ __('Censo: indisponível na base') }}
        </p>
    @elseif ($available && ($total > 0 || $schoolCount > 0))
        <details class="group/censo">
            <summary class="cursor-pointer list-none inline-flex items-center gap-1.5 text-[10px] font-medium text-blue-700 dark:text-blue-300 hover:underline">
                <x-ui.icon name="building-office-2" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                <span class="group-open/censo:hidden">{{ __('Ver escolas') }} ({{ number_format(max($total, $schoolCount), 0, ',', '.') }})</span>
                <span class="hidden group-open/censo:inline">{{ __('Ocultar escolas') }}</span>
            </summary>

            <div class="mt-2 rounded-md border border-slate-200/90 dark:border-slate-600/80 bg-slate-50/80 dark:bg-slate-900/50 p-2.5 space-y-2.5 max-w-md">
                @if (filled($c['source_label'] ?? null))
                    <p class="text-[10px] text-slate-500 dark:text-slate-400">
                        {{ __('Fonte:') }} <span class="font-mono">{{ $c['source_label'] }}</span>
                    </p>
                @endif

                @if ($total === 0 && filled($c['note'] ?? null))
                    <p class="text-[10px] text-amber-800 dark:text-amber-200 leading-snug">{{ $c['note'] }}</p>
                @elseif (count($escolas) === 0)
                    <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ __('Nenhuma escola no filtro do ano vigente.') }}</p>
                @else
                    <div class="overflow-x-auto rounded border border-slate-200/80 dark:border-slate-700/80 bg-white/80 dark:bg-slate-900/40">
                        <table class="min-w-full text-[10px] text-left">
                            <thead class="bg-slate-100/90 dark:bg-slate-800/80 text-slate-600 dark:text-slate-400">
                                <tr>
                                    <th class="px-2 py-1.5 font-semibold uppercase tracking-wide">{{ __('Escola') }}</th>
                                    <th class="px-2 py-1.5 font-semibold uppercase tracking-wide">{{ __('Censo') }}</th>
                                    <th class="px-2 py-1.5 font-semibold uppercase tracking-wide hidden sm:table-cell">{{ __('INEP') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach (array_slice($escolas, 0, 50) as $escola)
                                    @php
                                        $tone = (string) ($escola['status_tone'] ?? 'gray');
                                        $badgeTone = match ($tone) {
                                            'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
                                            'sky' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
                                            'amber' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100',
                                            default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                        };
                                    @endphp
                                    <tr class="text-slate-800 dark:text-slate-200">
                                        <td class="px-2 py-1.5 max-w-[10rem] sm:max-w-[12rem]">
                                            <span class="block truncate" title="{{ $escola['nome'] ?? '' }}">{{ $escola['nome'] ?? '—' }}</span>
                                        </td>
                                        <td class="px-2 py-1.5 whitespace-nowrap">
                                            <span class="inline-flex rounded-full px-1.5 py-0.5 font-semibold {{ $badgeTone }}">
                                                {{ $escola['status_label'] ?? '—' }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-1.5 font-mono hidden sm:table-cell whitespace-nowrap text-slate-500 dark:text-slate-400">
                                            {{ $escola['inep'] ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (count($escolas) > 50)
                        <p class="text-[10px] text-slate-500 dark:text-slate-400">
                            {{ __('+:n escolas omitidas na listagem.', ['n' => number_format(count($escolas) - 50, 0, ',', '.')]) }}
                        </p>
                    @endif
                @endif
            </div>
        </details>
    @endif
</div>
