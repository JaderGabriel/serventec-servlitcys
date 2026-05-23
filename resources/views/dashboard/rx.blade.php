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
        <div class="max-w-[1680px] mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="serv-panel serv-panel--info px-4 py-3 text-sm">
                <p class="font-medium text-serv-navy dark:text-teal-100">{{ __('RX — força de trabalho e prazos') }}</p>
                <p class="mt-1 text-slate-700 dark:text-slate-300 leading-relaxed">
                    {{ __('Visão consolidada por município: volumes digitados, Censo Escolar, meta de cadastro (com busca em anos anteriores quando necessário) e indicador de cumprimento da meta. Sem indicadores financeiros.') }}
                </p>
            </div>

            <x-rx.censo-deadline-banner :deadline="$rx['deadline'] ?? []" />

            @php
                $t = is_array($rx['totals'] ?? null) ? $rx['totals'] : [];
                $sem = is_array($rx['semaphore_summary'] ?? null) ? $rx['semaphore_summary'] : [];
                $fmtN = static fn (int $n): string => number_format($n, 0, ',', '.');
                $help = is_array($rx['column_help'] ?? null) ? $rx['column_help'] : [];
                $helpByKey = collect($help)->keyBy('key');
                $th = static fn (string $key, string $fallback) => $helpByKey->get($key)['title'] ?? $fallback;
                $thTitle = static fn (string $key) => $helpByKey->get($key)['description'] ?? '';
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

            <div class="flex flex-wrap items-center gap-4 text-xs text-slate-600 dark:text-slate-400 px-1">
                <span class="font-medium text-slate-700 dark:text-slate-300">{{ __('Meta de cadastro — indicador:') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>{{ $fmtN((int) ($sem['green'] ?? 0)) }} {{ __('Meta OK') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>{{ $fmtN((int) ($sem['yellow'] ?? 0)) }} {{ __('Em andamento') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>{{ $fmtN((int) ($sem['red'] ?? 0)) }} {{ __('Atenção') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-slate-600"></span>{{ $fmtN((int) ($sem['neutral'] ?? 0)) }} {{ __('Sem base') }}</span>
            </div>

            <x-rx.column-legend
                :columns="$help"
                :metaPctPerSalto="(float) ($rx['meta_pct_per_salto'] ?? 5)"
                :anteriorAno="(string) ($rx['anterior_ano'] ?? '')"
            />

            @if ((int) ($rx['cities_partial'] ?? 0) > 0)
                <p class="text-sm text-amber-700 dark:text-amber-300">
                    {{ __(':n município(s) com dados parciais (alguma consulta i-Educar falhou, mas a conexão está OK).', ['n' => (int) $rx['cities_partial']]) }}
                </p>
            @endif
            @if ((int) ($rx['cities_error'] ?? 0) > 0)
                <p class="text-sm text-amber-700 dark:text-amber-300">
                    @if ((int) ($rx['cities_connection_error'] ?? 0) > 0 && (int) ($rx['cities_query_error'] ?? 0) > 0)
                        {{ __(':n município(s) com problema (:conn conexão, :q consulta SQL). A aba Conexões só testa conexão com a base — o RX executa consultas completas ao i-Educar.', [
                            'n' => (int) $rx['cities_error'],
                            'conn' => (int) $rx['cities_connection_error'],
                            'q' => (int) $rx['cities_query_error'],
                        ]) }}
                    @elseif ((int) ($rx['cities_query_error'] ?? 0) > 0)
                        {{ __(':n município(s) com falha na consulta i-Educar (a conexão na aba Conexões pode estar OK). Ver coluna "Leitura dos dados".', ['n' => (int) $rx['cities_query_error']]) }}
                    @else
                        {{ __(':n município(s) sem conexão à base — configure em Cidades / Conexões.', ['n' => (int) $rx['cities_connection_error']]) }}
                    @endif
                </p>
            @endif

            <div class="serv-panel overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 space-y-3">
                    <div>
                        <h3 class="font-semibold text-serv-navy dark:text-white">{{ __('Detalhe por município') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Passe o rato sobre o cabeçalho de cada coluna para ver a explicação.') }}</p>
                    </div>
                    <x-rx.data-tone-legend
                        :vigenteAno="$rx['vigente_ano'] ?? ''"
                        :anteriorAno="$rx['anterior_ano'] ?? ''"
                    />
                </div>
                <div class="overflow-x-auto">
                    @php
                        $rxTh = static fn (string $key, bool $right = false): string => \App\Support\Rx\RxColumnTone::thClass(
                            \App\Support\Rx\RxColumnTone::forColumn($key),
                            $right,
                        );
                        $rxTd = static fn (string $key, bool $right = false): string => \App\Support\Rx\RxColumnTone::tdClass(
                            \App\Support\Rx\RxColumnTone::forColumn($key),
                            $right,
                        );
                    @endphp
                    <table class="serv-rx-table min-w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th colspan="2" class="serv-rx-th-group serv-rx-th-group--neutral"></th>
                                <th colspan="2" class="serv-rx-th-group serv-rx-th-group--vigente">
                                    {{ __('Vigente :ano', ['ano' => $rx['vigente_ano'] ?? '']) }}
                                </th>
                                <th class="serv-rx-th-group serv-rx-th-group--comparativo">{{ __('Δ :ano', ['ano' => $rx['anterior_ano'] ?? '']) }}</th>
                                <th class="serv-rx-th-group serv-rx-th-group--vigente">{{ __('Turmas') }}</th>
                                <th class="serv-rx-th-group serv-rx-th-group--meta">{{ __('Meta') }}</th>
                                <th class="serv-rx-th-group serv-rx-th-group--vigente">{{ __('Censo') }}</th>
                                <th colspan="3" class="serv-rx-th-group serv-rx-th-group--comparativo">{{ __('Vs meta') }}</th>
                                <th class="serv-rx-th-group serv-rx-th-group--neutral"></th>
                            </tr>
                            <tr>
                                <th class="{{ $rxTh('semaforo') }}" title="{{ $thTitle('semaforo') }}">{{ $th('semaforo', __('Indicador meta')) }}</th>
                                <th class="{{ $rxTh('municipio') }}" title="{{ $thTitle('municipio') }}">{{ $th('municipio', __('Município')) }}</th>
                                <th class="{{ $rxTh('alunos', true) }}" title="{{ $thTitle('alunos') }}">{{ $th('alunos', __('Alunos')) }}</th>
                                <th class="{{ $rxTh('matriculas', true) }}" title="{{ $thTitle('matriculas') }}">{{ $th('matriculas', __('Matrículas')) }}</th>
                                <th class="{{ $rxTh('delta', true) }}" title="{{ $thTitle('delta') }}">{{ $th('delta', __('Δ vs :ano', ['ano' => $rx['anterior_ano'] ?? ''])) }}</th>
                                <th class="{{ $rxTh('turmas', true) }}" title="{{ $thTitle('turmas') }}">{{ $th('turmas', __('Turmas')) }}</th>
                                <th class="{{ $rxTh('meta', true) }} min-w-[10rem]" title="{{ $thTitle('meta') }}">{{ $th('meta', __('Meta cadastro')) }}</th>
                                <th class="{{ $rxTh('censo', true) }}" title="{{ $thTitle('censo') }}">{{ $th('censo', __('Censo')) }}</th>
                                <th class="{{ $rxTh('progresso', true) }}" title="{{ $thTitle('progresso') }}">{{ $th('progresso', __('Progresso')) }}</th>
                                <th class="{{ $rxTh('falta', true) }}" title="{{ $thTitle('falta') }}">{{ $th('falta', __('Pendente')) }}</th>
                                <th class="{{ $rxTh('dias', true) }}" title="{{ $thTitle('dias') }}">{{ $th('dias', __('Dias p/ meta')) }}</th>
                                <th class="{{ $rxTh('situacao') }}" title="{{ $thTitle('situacao') }}">{{ $th('situacao', __('Leitura dos dados')) }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($rx['rows'] ?? [] as $row)
                                @php
                                    $censo = is_array($row['censo'] ?? null) ? $row['censo'] : [];
                                    $pctC = $censo['pct_concluido'] ?? null;
                                    $prog = $row['progresso_cadastro_pct'] ?? null;
                                @endphp
                                <tr>
                                    <td class="{{ $rxTd('semaforo') }} whitespace-nowrap">
                                        <x-rx.semaphore-badge
                                            :status="$row['semaforo'] ?? 'neutral'"
                                            :label="$row['semaforo_label'] ?? ''"
                                            :title="$row['semaforo_title'] ?? ''"
                                        />
                                    </td>
                                    <td class="{{ $rxTd('municipio') }} font-medium text-slate-900 dark:text-slate-100 align-top min-w-[12rem]">
                                        <p class="leading-snug break-words">
                                            {{ $row['city_name'] ?? '' }}
                                            <span class="text-[10px] text-slate-500 font-normal whitespace-nowrap">({{ $row['uf'] ?? '' }})</span>
                                        </p>
                                        @if (is_array($row['reference_contact'] ?? null) && ($row['reference_contact']['available'] ?? false))
                                            <x-city.reference-contact
                                                :contact="$row['reference_contact']"
                                                variant="agenda"
                                                layout="stacked"
                                                class="mt-1.5"
                                            />
                                        @endif
                                    </td>
                                    <td class="{{ $rxTd('alunos', true) }}">
                                        <span class="serv-rx-val--vigente">{{ number_format((int) ($row['alunos_vigente'] ?? 0), 0, ',', '.') }}</span>
                                    </td>
                                    <td class="{{ $rxTd('matriculas', true) }}">
                                        <span class="serv-rx-val--vigente">{{ number_format((int) ($row['matriculas_vigente'] ?? 0), 0, ',', '.') }}</span>
                                        <span class="serv-rx-val--anterior">
                                            {{ number_format((int) ($row['matriculas_anterior'] ?? 0), 0, ',', '.') }}
                                            <span class="text-slate-400">({{ $rx['anterior_ano'] ?? '' }})</span>
                                        </span>
                                    </td>
                                    <td class="{{ $rxTd('delta', true) }} tabular-nums text-xs">
                                        @php
                                            $d = (int) ($row['matriculas_delta'] ?? 0);
                                            $semBase = (bool) ($row['matriculas_delta_sem_base'] ?? false);
                                        @endphp
                                        <span class="{{ $d >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                                            {{ $d >= 0 ? '+' : '' }}{{ number_format($d, 0, ',', '.') }}
                                            @if ($semBase)
                                                <span class="block text-[10px] text-slate-500 font-normal">{{ __('novo cadastro') }}</span>
                                            @elseif ($row['matriculas_delta_pct'] !== null)
                                                ({{ number_format((float) $row['matriculas_delta_pct'], 1, ',', '.') }}%)
                                            @endif
                                        </span>
                                    </td>
                                    <td class="{{ $rxTd('turmas', true) }}">
                                        <span class="serv-rx-val--vigente">{{ number_format((int) ($row['turmas_vigente'] ?? 0), 0, ',', '.') }}</span>
                                    </td>
                                    <td class="{{ $rxTd('meta', true) }} text-xs leading-snug">
                                        @if ($row['meta_encontrou_referencia'] ?? false)
                                            <span class="font-medium text-violet-950 dark:text-violet-50">
                                                {{ __('Ref. :ano', ['ano' => (int) ($row['meta_referencia_ano'] ?? 0)]) }}
                                            </span>
                                            <span class="serv-rx-val--meta-ref block">
                                                {{ __('Base: :m mat. · :t tur.', [
                                                    'm' => number_format((int) ($row['meta_referencia_matriculas'] ?? 0), 0, ',', '.'),
                                                    't' => number_format((int) ($row['meta_referencia_turmas'] ?? 0), 0, ',', '.'),
                                                ]) }}
                                            </span>
                                            @if ((int) ($row['meta_saltos'] ?? 0) > 0)
                                                <span class="serv-rx-val--meta-alvo">
                                                    {{ __('+:pct% (:n salto(s)) → alvo :mat mat.', [
                                                        'pct' => number_format((float) ($row['meta_acrescimo_pct'] ?? 0), 1, ',', '.'),
                                                        'n' => (int) ($row['meta_saltos'] ?? 0),
                                                        'mat' => number_format((int) ($row['meta_matriculas_alvo'] ?? 0), 0, ',', '.'),
                                                    ]) }}
                                                </span>
                                            @else
                                                <span class="serv-rx-val--meta-alvo">
                                                    {{ __('Alvo: :mat mat.', ['mat' => number_format((int) ($row['meta_matriculas_alvo'] ?? 0), 0, ',', '.')]) }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-slate-400">—</span>
                                            <span class="serv-rx-val--meta-ref block">{{ __('Sem histórico') }}</span>
                                        @endif
                                    </td>
                                    <td class="{{ $rxTd('censo', true) }} tabular-nums text-xs">
                                        @if ($pctC !== null)
                                            {{ number_format((float) $pctC, 1, ',', '.') }}%
                                            <span class="block text-[10px] text-slate-500">
                                                {{ (int) ($censo['pendentes'] ?? 0) }} {{ __('pend.') }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="{{ $rxTd('progresso', true) }} tabular-nums text-xs leading-snug">
                                        @if ($prog !== null)
                                            <span class="font-medium text-indigo-950 dark:text-indigo-50">{{ number_format((float) $prog, 1, ',', '.') }}%</span>
                                            @if (($row['progresso_matriculas_pct'] ?? null) !== null)
                                                <span class="block text-[10px] text-slate-500">
                                                    {{ __('Mat. :pct %', ['pct' => number_format((float) $row['progresso_matriculas_pct'], 1, ',', '.')]) }}
                                                </span>
                                            @endif
                                            @if (($row['progresso_turmas_pct'] ?? null) !== null && (int) ($row['meta_turmas_alvo'] ?? 0) > 0)
                                                <span class="block text-[10px] text-slate-500">
                                                    {{ __('Tur. :pct %', ['pct' => number_format((float) $row['progresso_turmas_pct'], 1, ',', '.')]) }}
                                                </span>
                                            @endif
                                        @elseif ($row['meta_encontrou_referencia'] ?? false)
                                            <span class="text-slate-400">0%</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="{{ $rxTd('falta', true) }} tabular-nums text-xs leading-snug font-medium text-indigo-900 dark:text-indigo-100">
                                        @php
                                            $faltaTur = (int) ($row['falta_turmas'] ?? 0);
                                            $faltaMat = (int) ($row['falta_matriculas'] ?? 0);
                                            $metaTur = (int) ($row['meta_turmas_alvo'] ?? 0);
                                            $metaMat = (int) ($row['meta_matriculas_alvo'] ?? 0);
                                        @endphp
                                        @if ($metaTur > 0 || $metaMat > 0)
                                            @if ($metaTur > 0)
                                                <span class="block">
                                                    {{ __(':n turma(s)', ['n' => number_format($faltaTur, 0, ',', '.')]) }}
                                                </span>
                                            @endif
                                            @if ($metaMat > 0)
                                                <span class="block {{ $metaTur > 0 ? 'text-[10px] font-normal text-amber-700/90 dark:text-amber-200/90' : '' }}">
                                                    {{ __(':n matrícula(s)', ['n' => number_format($faltaMat, 0, ',', '.')]) }}
                                                </span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="{{ $rxTd('dias', true) }} tabular-nums text-indigo-950 dark:text-indigo-50">
                                        {{ $row['dias_para_meta'] ?? '—' }}
                                    </td>
                                    <td class="{{ $rxTd('situacao') }} text-xs max-w-[14rem]">
                                        @php
                                            $sitLabel = $row['situacao_label'] ?? __('Erro');
                                            $sitCode = $row['situacao_codigo'] ?? '';
                                            $sitClass = match ($sitCode) {
                                                'ok' => 'text-emerald-700 dark:text-emerald-300',
                                                'parcial' => 'text-amber-700 dark:text-amber-300',
                                                'conexao' => 'text-rose-700 dark:text-rose-300',
                                                'consulta' => 'text-orange-700 dark:text-orange-300',
                                                default => 'text-rose-700 dark:text-rose-300',
                                            };
                                        @endphp
                                        <span class="font-medium {{ $sitClass }}" title="{{ $row['error'] ?? '' }}">{{ $sitLabel }}</span>
                                        @if (! empty($row['consulta_warnings'] ?? []))
                                            <span class="block text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug" title="{{ implode(' · ', $row['consulta_warnings']) }}">
                                                {{ count($row['consulta_warnings']) }} {{ __('aviso(s)') }}
                                            </span>
                                        @endif
                                        @if (filled($row['error'] ?? null) && ! ($row['ok'] ?? false))
                                            <span class="block text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 break-words">{{ \Illuminate\Support\Str::limit((string) $row['error'], 80) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="px-3 py-8 text-center text-slate-500">
                                        {{ __('Nenhum município disponível para o seu perfil.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                {{ __('Meta: volume do ano de referência com dados, mais :pct% por cada salto (ano a mais para trás quando :a está zerado). Censo: exportação/fecho por escola. Ajuste em config/rx.php (RX_META_*, RX_SEMAPHORE_*).', [
                    'pct' => number_format((float) ($rx['meta_pct_per_salto'] ?? 5), 0, ',', '.'),
                    'a' => (string) ($rx['anterior_ano'] ?? ''),
                ]) }}
            </p>
        </div>
    </div>
</x-app-layout>
