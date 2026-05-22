<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="serv-eyebrow">RX</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ __('Painel operacional — cadastro e Censo') }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ $rx['scope_label'] ?? '' }}
                    · {{ __('Ano vigente :v (comparação com :a)', [
                        'v' => (string) ($rx['vigente_ano'] ?? ''),
                        'a' => (string) ($rx['anterior_ano'] ?? ''),
                    ]) }}
                </p>
            </div>
            <a href="{{ route('dashboard.analytics') }}" class="serv-link text-sm">{{ __('Consultoria por município →') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-[1600px] mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="serv-panel serv-panel--info px-4 py-3 text-sm">
                <p class="font-medium text-serv-navy dark:text-teal-100">{{ __('RX — força de trabalho e prazos') }}</p>
                <p class="mt-1 text-slate-700 dark:text-slate-300 leading-relaxed">
                    {{ __('Visão consolidada de todos os municípios acessíveis: volume digitado (alunos e matrículas), status do Censo e estimativa do trabalho de preenchimento restante. Sem indicadores financeiros.') }}
                </p>
            </div>

            <x-rx.censo-deadline-banner :deadline="$rx['deadline'] ?? []" />

            @php
                $t = is_array($rx['totals'] ?? null) ? $rx['totals'] : [];
                $fmtN = static fn (int $n): string => number_format($n, 0, ',', '.');
            @endphp

            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                <div class="serv-panel p-4">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">{{ __('Municípios') }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-serv-navy dark:text-white">
                        {{ (int) ($rx['cities_ok'] ?? 0) }}/{{ (int) ($rx['cities_total'] ?? 0) }}
                    </p>
                </div>
                <div class="serv-panel p-4">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">{{ __('Alunos :ano', ['ano' => $rx['vigente_ano'] ?? '']) }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ $fmtN((int) ($t['alunos_vigente'] ?? 0)) }}</p>
                    <p class="text-[10px] text-slate-500">{{ __(':a em :ano', ['a' => $fmtN((int) ($t['alunos_anterior'] ?? 0)), 'ano' => $rx['anterior_ano'] ?? '']) }}</p>
                </div>
                <div class="serv-panel p-4">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">{{ __('Matrículas :ano', ['ano' => $rx['vigente_ano'] ?? '']) }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ $fmtN((int) ($t['matriculas_vigente'] ?? 0)) }}</p>
                    @php $delta = (int) ($t['matriculas_delta'] ?? 0); @endphp
                    <p class="text-[10px] {{ $delta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $delta >= 0 ? '+' : '' }}{{ $fmtN($delta) }} {{ __('vs :ano', ['ano' => $rx['anterior_ano'] ?? '']) }}
                    </p>
                </div>
                <div class="serv-panel p-4">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">{{ __('Censo — escolas OK') }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">
                        @if (($t['pct_censo_rede'] ?? null) !== null)
                            {{ number_format((float) $t['pct_censo_rede'], 1, ',', '.') }}%
                        @else
                            —
                        @endif
                    </p>
                    <p class="text-[10px] text-slate-500">
                        {{ $fmtN((int) ($t['escolas_censo_concluidas'] ?? 0)) }}/{{ $fmtN((int) ($t['escolas_censo'] ?? 0)) }}
                    </p>
                </div>
                <div class="serv-panel p-4">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">{{ __('Registos em falta') }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-amber-700 dark:text-amber-300">
                        {{ $fmtN((int) ($t['registros_restantes'] ?? 0)) }}
                    </p>
                </div>
                <div class="serv-panel p-4">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">{{ __('Horas estimadas') }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format((float) ($t['horas_estimadas'] ?? 0), 1, ',', '.') }} h</p>
                </div>
            </div>

            @if ((int) ($rx['cities_error'] ?? 0) > 0)
                <p class="text-sm text-amber-700 dark:text-amber-300">
                    {{ __(':n município(s) com erro de conexão ou consulta — ver coluna «Situação».', ['n' => (int) $rx['cities_error']]) }}
                </p>
            @endif

            <div class="serv-panel overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="font-semibold text-serv-navy dark:text-white">{{ __('Detalhe por município') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/80 text-left text-[11px] uppercase tracking-wide text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-2">{{ __('Município') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Alunos') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Matrículas') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Δ vs :ano', ['ano' => $rx['anterior_ano'] ?? '']) }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Turmas') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Censo') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Progresso cad.') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Em falta') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Dias p/ meta') }}</th>
                                <th class="px-3 py-2">{{ __('Situação') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($rx['rows'] ?? [] as $row)
                                @php
                                    $censo = is_array($row['censo'] ?? null) ? $row['censo'] : [];
                                    $pctC = $censo['pct_concluido'] ?? null;
                                    $prog = $row['progresso_cadastro_pct'] ?? null;
                                @endphp
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                    <td class="px-3 py-2 font-medium text-slate-900 dark:text-slate-100">
                                        {{ $row['city_name'] ?? '' }}
                                        <span class="text-[10px] text-slate-500">({{ $row['uf'] ?? '' }})</span>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ number_format((int) ($row['alunos_vigente'] ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ number_format((int) ($row['matriculas_vigente'] ?? 0), 0, ',', '.') }}
                                        <span class="block text-[10px] text-slate-500">
                                            {{ number_format((int) ($row['matriculas_anterior'] ?? 0), 0, ',', '.') }} ({{ $rx['anterior_ano'] ?? '' }})
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums text-xs">
                                        @php $d = (int) ($row['matriculas_delta'] ?? 0); @endphp
                                        <span class="{{ $d >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                                            {{ $d >= 0 ? '+' : '' }}{{ number_format($d, 0, ',', '.') }}
                                            @if ($row['matriculas_delta_pct'] !== null)
                                                ({{ number_format((float) $row['matriculas_delta_pct'], 1, ',', '.') }}%)
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ (int) ($row['turmas_vigente'] ?? 0) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-xs">
                                        @if ($pctC !== null)
                                            {{ number_format((float) $pctC, 1, ',', '.') }}%
                                            <span class="block text-[10px] text-slate-500">
                                                {{ (int) ($censo['pendentes'] ?? 0) }} {{ __('pend.') }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        @if ($prog !== null)
                                            {{ number_format((float) $prog, 1, ',', '.') }}%
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums font-medium text-amber-800 dark:text-amber-200">
                                        {{ number_format((int) ($row['registros_restantes'] ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ $row['dias_para_meta'] ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs">
                                        @if ($row['ok'] ?? false)
                                            <span class="text-emerald-700 dark:text-emerald-300">{{ __('OK') }}</span>
                                        @else
                                            <span class="text-rose-700 dark:text-rose-300" title="{{ $row['error'] ?? '' }}">{{ __('Erro') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-3 py-8 text-center text-slate-500">
                                        {{ __('Nenhum município disponível para o seu perfil.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                {{ __('Meta de cadastro: volume do ano anterior (:ano). Censo: exportação/fecho por escola na base i-Educar. Ajuste prazos em config/rx.php (RX_CENSO_*).') }}
            </p>
        </div>
    </div>
</x-app-layout>
