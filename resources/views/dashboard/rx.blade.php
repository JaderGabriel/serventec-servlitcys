<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="serv-eyebrow">{{ __('Painel RX') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ __('Cadastro, meta e Censo Escolar') }}
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
            <x-rx.censo-deadline-banner :deadline="$rx['deadline'] ?? []" class="shadow-lg" />

            <div class="serv-panel serv-panel--info px-4 py-3 text-sm">
                <p class="font-medium text-serv-navy dark:text-teal-100">{{ __('RX — força de trabalho e prazos') }}</p>
                <p class="mt-1 text-slate-700 dark:text-slate-300 leading-relaxed">
                    {{ __('Visão consolidada por município: volumes digitados no i-Educar (em andamento), Censo Escolar, meta de cadastro e indicador de cumprimento. Abaixo, quando disponível, complementações FUNDEB da portaria (dados consolidados do FNDE).') }}
                </p>
            </div>

            @php
                $t = is_array($rx['totals'] ?? null) ? $rx['totals'] : [];
                $sem = is_array($rx['semaphore_summary'] ?? null) ? $rx['semaphore_summary'] : [];
                $fmtN = static fn (int $n): string => number_format($n, 0, ',', '.');
                $help = is_array($rx['column_help'] ?? null) ? $rx['column_help'] : [];
                $helpByKey = collect($help)->keyBy('key');
                $th = static fn (string $key, string $fallback) => $helpByKey->get($key)['title'] ?? $fallback;
                $thTitle = static fn (string $key) => $helpByKey->get($key)['description'] ?? '';
                $citiesOk = (int) ($rx['cities_ok'] ?? 0);
                $citiesTotal = (int) ($rx['cities_total'] ?? 0);
                $citiesPct = $citiesTotal > 0 ? (int) min(100, round(100 * $citiesOk / $citiesTotal)) : 0;
                $delta = (int) ($t['matriculas_delta'] ?? 0);
                $pctCenso = $t['pct_censo_rede'] ?? null;
                $censoBarPct = $pctCenso !== null ? min(100, max(0, (float) $pctCenso)) : 0;
            @endphp

            <section aria-labelledby="rx-kpis">
                <h3 id="rx-kpis" class="sr-only">{{ __('Indicadores RX') }}</h3>
                <div class="serv-rx-kpi-grid">
                    <div class="serv-home-kpi serv-home-kpi--teal">
                        <div class="serv-home-kpi__head">
                            <span class="serv-home-kpi__icon serv-home-kpi__icon--teal" aria-hidden="true">
                                <x-ui.icon name="map-pin" class="h-5 w-5" />
                            </span>
                            <p class="serv-home-kpi__label">{{ __('Municípios') }}</p>
                        </div>
                        <p class="serv-home-kpi__value">
                            {{ $fmtN($citiesOk) }}<span class="serv-home-kpi__suffix">/ {{ $fmtN($citiesTotal) }}</span>
                        </p>
                        @if ($citiesTotal > 0)
                            <div class="serv-home-kpi__bar" role="presentation" aria-hidden="true">
                                <span class="serv-home-kpi__bar-fill serv-home-kpi__bar-fill--teal" style="width: {{ $citiesPct }}%"></span>
                            </div>
                        @endif
                        <p class="serv-home-kpi__hint">{{ __('Com leitura i-Educar no período') }}</p>
                    </div>

                    <div class="serv-home-kpi">
                        <div class="serv-home-kpi__head">
                            <span class="serv-home-kpi__icon" aria-hidden="true">
                                <x-ui.icon name="academic-cap" class="h-5 w-5" />
                            </span>
                            <p class="serv-home-kpi__label">{{ __('Alunos :ano', ['ano' => $rx['vigente_ano'] ?? '']) }}</p>
                        </div>
                        <p class="serv-home-kpi__value">{{ $fmtN((int) ($t['alunos_vigente'] ?? 0)) }}</p>
                        <p class="serv-home-kpi__hint">
                            {{ __(':a em :ano', ['a' => $fmtN((int) ($t['alunos_anterior'] ?? 0)), 'ano' => $rx['anterior_ano'] ?? '']) }}
                        </p>
                    </div>

                    <div class="serv-home-kpi">
                        <div class="serv-home-kpi__head">
                            <span class="serv-home-kpi__icon serv-home-kpi__icon--violet" aria-hidden="true">
                                <x-ui.icon name="clipboard-document-list" class="h-5 w-5" />
                            </span>
                            <p class="serv-home-kpi__label">{{ __('Matrículas :ano', ['ano' => $rx['vigente_ano'] ?? '']) }}</p>
                        </div>
                        <p class="serv-home-kpi__value">{{ $fmtN((int) ($t['matriculas_vigente'] ?? 0)) }}</p>
                        <p class="serv-home-kpi__hint {{ $delta >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                            {{ $delta >= 0 ? '+' : '' }}{{ $fmtN($delta) }} {{ __('vs :ano', ['ano' => $rx['anterior_ano'] ?? '']) }}
                        </p>
                    </div>

                    <div class="serv-home-kpi">
                        <div class="serv-home-kpi__head">
                            <span class="serv-home-kpi__icon" aria-hidden="true">
                                <x-ui.icon name="check-circle" class="h-5 w-5" />
                            </span>
                            <p class="serv-home-kpi__label">{{ __('Censo — escolas OK') }}</p>
                        </div>
                        <p class="serv-home-kpi__value">
                            @if ($pctCenso !== null)
                                {{ number_format((float) $pctCenso, 1, ',', '.') }}%
                            @else
                                —
                            @endif
                        </p>
                        @if ($pctCenso !== null)
                            <div class="serv-home-kpi__bar" role="presentation" aria-hidden="true">
                                <span class="serv-home-kpi__bar-fill serv-home-kpi__bar-fill--teal" style="width: {{ $censoBarPct }}%"></span>
                            </div>
                        @endif
                        <p class="serv-home-kpi__hint">
                            {{ $fmtN((int) ($t['escolas_censo_concluidas'] ?? 0)) }}/{{ $fmtN((int) ($t['escolas_censo'] ?? 0)) }}
                            {{ __('exportadas ou fechadas') }}
                        </p>
                    </div>

                    <div class="serv-home-kpi serv-home-kpi--amber">
                        <div class="serv-home-kpi__head">
                            <span class="serv-home-kpi__icon serv-home-kpi__icon--amber" aria-hidden="true">
                                <x-ui.icon name="exclamation-triangle" class="h-5 w-5" />
                            </span>
                            <p class="serv-home-kpi__label">{{ __('Registos em falta') }}</p>
                        </div>
                        <p class="serv-home-kpi__value text-amber-800 dark:text-amber-200">{{ $fmtN((int) ($t['registros_restantes'] ?? 0)) }}</p>
                        <p class="serv-home-kpi__hint">{{ __('Turmas e matrículas abaixo da meta') }}</p>
                    </div>

                    <div class="serv-home-kpi">
                        <div class="serv-home-kpi__head">
                            <span class="serv-home-kpi__icon" aria-hidden="true">
                                <x-ui.icon name="command-line" class="h-5 w-5" />
                            </span>
                            <p class="serv-home-kpi__label">{{ __('Horas estimadas') }}</p>
                        </div>
                        <p class="serv-home-kpi__value">{{ number_format((float) ($t['horas_estimadas'] ?? 0), 1, ',', '.') }} h</p>
                        <p class="serv-home-kpi__hint">{{ __('Ritmo da quinzena recente') }}</p>
                    </div>
                </div>
            </section>

            <x-rx.legend-panel
                :semaphore="$sem"
                :columns="$help"
                :vigenteAno="$rx['vigente_ano'] ?? ''"
                :anteriorAno="$rx['anterior_ano'] ?? ''"
                :metaPctPerSalto="(float) ($rx['meta_pct_per_salto'] ?? 5)"
            />

            @if ((int) ($rx['cities_partial'] ?? 0) > 0)
                <div class="serv-panel border-amber-200/90 bg-amber-50/60 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/50 dark:bg-amber-950/25 dark:text-amber-100">
                    {{ __(':n município(s) com dados parciais (alguma consulta i-Educar falhou, mas a conexão está OK).', ['n' => (int) $rx['cities_partial']]) }}
                </div>
            @endif
            @if ((int) ($rx['cities_error'] ?? 0) > 0)
                <div class="serv-panel border-amber-200/90 bg-amber-50/60 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/50 dark:bg-amber-950/25 dark:text-amber-100">
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
                </div>
            @endif

            <div class="serv-panel overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
                    <p class="serv-eyebrow">{{ __('Tabela') }}</p>
                    <h3 class="font-display font-semibold text-serv-navy dark:text-white">{{ __('Detalhe por município') }}</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Tons alinhados às colunas. Passe o rato no cabeçalho para mais detalhe. Barra sob o nome = Censo por escola.') }}</p>
                </div>
                <div class="overflow-x-auto">
                    @php
                        $rxTh = static fn (string $key, bool $right = false): string => \App\Support\Rx\RxColumnTone::thClass(
                            \App\Support\Rx\RxColumnTone::headerToneForColumn($key),
                            $right,
                        );
                        $rxTd = static fn (string $key, bool $right = false): string => \App\Support\Rx\RxColumnTone::tdClass(
                            \App\Support\Rx\RxColumnTone::forColumn($key),
                            $right,
                        );
                    @endphp
                    <table class="serv-rx-table min-w-full text-sm text-left">
                        <thead>
                            <x-rx.table-group-row
                                :vigenteAno="$rx['vigente_ano'] ?? ''"
                                :anteriorAno="$rx['anterior_ano'] ?? ''"
                            />
                            <x-rx.table-tone-row
                                :vigenteAno="$rx['vigente_ano'] ?? ''"
                                :anteriorAno="$rx['anterior_ano'] ?? ''"
                            />
                            <tr>
                                <th class="{{ $rxTh('semaforo') }}" title="{{ $thTitle('semaforo') }}">{{ $th('semaforo', __('Indicador meta')) }}</th>
                                <th class="{{ $rxTh('municipio') }}" title="{{ $thTitle('municipio') }}">{{ $th('municipio', __('Município')) }}</th>
                                <th class="{{ $rxTh('alunos', true) }}" title="{{ $thTitle('alunos') }}">{{ $th('alunos', __('Alunos')) }}</th>
                                <th class="{{ $rxTh('matriculas', true) }}" title="{{ $thTitle('matriculas') }}">{{ $th('matriculas', __('Matrículas')) }}</th>
                                <th class="{{ $rxTh('delta', true) }}" title="{{ $thTitle('delta') }}">{{ $th('delta', __('Δ vs :ano', ['ano' => $rx['anterior_ano'] ?? ''])) }}</th>
                                <th class="{{ $rxTh('turmas', true) }}" title="{{ $thTitle('turmas') }}">{{ $th('turmas', __('Turmas')) }}</th>
                                <th class="{{ $rxTh('meta', true) }} min-w-[10rem]" title="{{ $thTitle('meta') }}">{{ $th('meta', __('Meta cadastro')) }}</th>
                                <th class="{{ $rxTh('censo', true) }}" title="{{ $thTitle('censo') }}">{{ $th('censo', __('Censo')) }}</th>
                                <th class="{{ $rxTh('progresso', true) }}" title="{{ $thTitle('progresso') }}">{{ $th('progresso', __('Progresso cad.')) }}</th>
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
                                        <x-rx.censo-municipio-bar :censo="$censo" :vigenteAno="$rx['vigente_ano'] ?? null" />
                                        <x-rx.censo-municipio-detail :censo="$censo" :compact="true" />
                                        <x-rx.fundeb-municipio-snippet :fundeb="$row['fundeb_resumo'] ?? null" />
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
                                            @if ($row['meta_ano_imediato_zerado'] ?? false)
                                                <span class="block text-[10px] font-medium text-amber-800 dark:text-amber-200">
                                                    {{ __(':ano sem cadastro — meta com salto(s) a partir de :ref.', [
                                                        'ano' => (string) ($rx['anterior_ano'] ?? ''),
                                                        'ref' => (int) ($row['meta_referencia_ano'] ?? 0),
                                                    ]) }}
                                                </span>
                                            @endif
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
                                            <x-rx.cadastro-pulse :pulse="$row['cadastro_pulse'] ?? null" />
                                        @else
                                            <span class="text-slate-400">—</span>
                                            <span class="serv-rx-val--meta-ref block">{{ __('Sem histórico') }}</span>
                                            <x-rx.cadastro-pulse :pulse="$row['cadastro_pulse'] ?? null" />
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
                                            <span class="font-medium text-sky-950 dark:text-sky-50">{{ number_format((float) $prog, 1, ',', '.') }}%</span>
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
                                    <td class="{{ $rxTd('falta', true) }} tabular-nums text-xs leading-snug font-medium text-sky-900 dark:text-sky-100">
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
                                    <td class="{{ $rxTd('dias', true) }} tabular-nums text-sky-950 dark:text-sky-50">
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

            @include('dashboard.rx.partials.fundeb-portaria', ['rx' => $rx])

            <div class="serv-panel serv-panel--info px-4 py-3 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                {{ __('Parâmetros da meta e do semáforo podem ser ajustados em config/rx.php (RX_META_*, RX_SEMAPHORE_*). O indicador de meta mede volume, não qualidade cadastral.') }}
            </div>
        </div>
    </div>
</x-app-layout>
