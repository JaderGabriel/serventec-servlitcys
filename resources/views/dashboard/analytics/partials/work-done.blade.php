@props(['workDoneData', 'yearFilterReady' => false, 'chartExportContext' => []])

@php
    $d = is_array($workDoneData) ? $workDoneData : [];
    $periods = is_array($d['periods'] ?? null) ? $d['periods'] : [];
    $periodLabels = is_array($d['period_labels'] ?? null) ? $d['period_labels'] : [];
    $byUser = is_array($d['by_user'] ?? null) ? $d['by_user'] : [];
    $baseline = is_array($d['baseline'] ?? null) ? $d['baseline'] : [];
    $est = is_array($d['estimativa'] ?? null) ? $d['estimativa'] : [];
    $chartPeriods = is_array($d['chart_periods'] ?? null) ? $d['chart_periods'] : null;
    $chartUsers = is_array($d['chart_users'] ?? null) ? $d['chart_users'] : null;
@endphp

<div class="space-y-6">
    @if (! $yearFilterReady)
        <p class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
            {{ __('Seleccione o ano letivo e aplique os filtros para medir o trabalho de cadastro.') }}
        </p>
    @else
        <div class="rounded-lg border border-sky-200 dark:border-sky-800 bg-sky-50/70 dark:bg-sky-950/25 px-4 py-3 text-sm text-sky-950 dark:text-sky-100 space-y-2">
            <p class="font-semibold">{{ __('Trabalho realizado — cadastro no i-Educar') }}</p>
            <p class="leading-relaxed">{{ $d['intro'] ?? '' }}</p>
            <p class="text-xs text-sky-800/90 dark:text-sky-300/90">
                {{ $d['city_name'] ?? '' }}
                @if (filled($d['year_label'] ?? null))
                    — {{ $d['year_label'] }}
                @endif
            </p>
        </div>

        <p class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 leading-relaxed">
            {{ $d['footnote'] ?? '' }}
        </p>

        @if (! empty($d['error']))
            <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                {{ $d['error'] }}
            </div>
        @endif

        @if (! ($d['activity_available'] ?? false) && filled($d['activity_note'] ?? null))
            <div class="rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
                {{ $d['activity_note'] }}
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @foreach (['day', 'week', 'fortnight'] as $key)
                <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ $periodLabels[$key] ?? $key }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($periods[$key] ?? 0), 0, ',', '.') }}</p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ __('matrículas cadastradas') }}</p>
                </div>
            @endforeach
        </div>

        @if ($chartPeriods !== null)
            <x-dashboard.chart-panel
                :chart="$chartPeriods"
                exportFilename="trabalho-realizado-periodos"
                :exportMeta="$chartExportContext"
                chartPanelId="chart-work-done-periods"
                panelTone="sky"
            />
        @endif

        <section class="rounded-lg border border-violet-200 dark:border-violet-800 bg-violet-50/30 dark:bg-violet-950/20 px-4 py-4 space-y-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-violet-950 dark:text-violet-100">{{ __('Referência: ano letivo anterior') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Ano de referência') }}</p>
                    <p class="font-semibold">{{ ($baseline['ano'] ?? 0) > 0 ? $baseline['ano'] : '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Turmas (ano anterior)') }}</p>
                    <p class="font-semibold">{{ number_format((int) ($baseline['turmas'] ?? 0), 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Matrículas (ano anterior)') }}</p>
                    <p class="font-semibold">{{ number_format((int) ($baseline['matriculas'] ?? 0), 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Turmas (filtro actual)') }}</p>
                    <p class="font-semibold">{{ number_format((int) ($d['turmas_ano_atual'] ?? 0), 0, ',', '.') }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/30 dark:bg-emerald-950/20 px-4 py-4 space-y-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-emerald-950 dark:text-emerald-100">{{ __('Estimativa de esforço restante') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Meta (matrículas ano anterior)') }}</p>
                    <p class="font-semibold">{{ number_format((int) ($est['meta_matriculas_ano_anterior'] ?? 0), 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Matrículas activas (filtro)') }}</p>
                    <p class="font-semibold">{{ number_format((int) ($est['matriculas_ativas_filtro'] ?? 0), 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Registos restantes (estimados)') }}</p>
                    <p class="font-semibold">{{ number_format((int) ($est['registros_restantes_estimados'] ?? 0), 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Ritmo observado (cadastros/dia)') }}</p>
                    <p class="font-semibold">{{ number_format((float) ($est['ritmo_por_dia'] ?? 0), 1, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Horas totais estimadas') }}</p>
                    <p class="font-semibold">{{ number_format((float) ($est['horas_totais_estimadas'] ?? 0), 1, ',', '.') }} h</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Dias pessoa-equivalente') }}</p>
                    <p class="font-semibold">
                        @if (($est['dias_pessoa_equivalente'] ?? null) !== null)
                            {{ number_format((float) $est['dias_pessoa_equivalente'], 1, ',', '.') }}
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Dias para concluir (ritmo actual)') }}</p>
                    <p class="font-semibold">
                        @if (($est['dias_para_concluir_ritmo_atual'] ?? null) !== null)
                            {{ number_format((int) $est['dias_para_concluir_ritmo_atual'], 0, ',', '.') }}
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Minutos por registo (modelo)') }}</p>
                    <p class="font-semibold">{{ number_format((float) ($est['minutos_por_registro'] ?? 0), 1, ',', '.') }}</p>
                </div>
            </div>
        </section>

        @if ($chartUsers !== null)
            <x-dashboard.chart-panel
                :chart="$chartUsers"
                exportFilename="trabalho-realizado-utilizadores"
                :exportMeta="$chartExportContext"
                chartPanelId="chart-work-done-users"
                panelTone="sky"
            />
        @endif

        @if (count($byUser) > 0)
            <section class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <h3 class="px-4 py-3 text-sm font-semibold bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    {{ __('Por utilizador i-Educar (quinzena)') }}
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Login') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Nome') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Matrículas') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($byUser as $row)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $row['login'] ?: '—' }}</td>
                                    <td class="px-4 py-2">{{ $row['nome'] ?: '—' }}</td>
                                    <td class="px-4 py-2 text-right font-semibold">{{ number_format((int) ($row['total'] ?? 0), 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if (count($d['exclusion_notes'] ?? []) > 0)
            <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                <p class="font-medium text-gray-600 dark:text-gray-300">{{ __('Utilizadores excluídos da contagem') }}</p>
                @foreach ($d['exclusion_notes'] as $note)
                    <p>{{ $note }}</p>
                @endforeach
            </div>
        @endif

        @if (count($d['methodology'] ?? []) > 0)
            <details class="rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-3 text-xs text-gray-600 dark:text-gray-400">
                <summary class="cursor-pointer font-medium text-gray-700 dark:text-gray-300">{{ __('Metodologia') }}</summary>
                <ul class="mt-2 list-disc pl-5 space-y-1">
                    @foreach ($d['methodology'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    @endif
</div>
