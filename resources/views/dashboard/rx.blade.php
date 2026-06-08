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
                <p class="font-medium text-serv-navy dark:text-teal-100">{{ __('RX — cadastro em andamento e meta') }}</p>
                <p class="mt-1 text-slate-700 dark:text-slate-300 leading-relaxed">
                    {{ __('Por município: quantos alunos, matrículas e turmas já estão no i-Educar no ano vigente; comparação com o ano anterior; meta de volume (turmas + matrículas); Censo e prazos. Aluno ≠ matrícula ≠ turma — veja o quadro abaixo antes da tabela.') }}
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
                        <p class="serv-home-kpi__hint">{{ __('Pessoas distintas matriculadas') }}</p>
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
                            {{ $delta >= 0 ? '+' : '' }}{{ $fmtN($delta) }} {{ __('matr. vs :ano', ['ano' => $rx['anterior_ano'] ?? '']) }}
                        </p>
                        <p class="serv-home-kpi__hint text-slate-500 dark:text-slate-400">{{ __('Vínculos ativos no ano') }}</p>
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
                        <p class="serv-home-kpi__hint">{{ __('Turmas + matrículas ainda abaixo do alvo') }}</p>
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
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Leia da esquerda para a direita: meta → cadastrado → falta. Violeta · verde · âmbar.') }}</p>
                </div>
                <x-rx.cadastro-concepts
                    class="border-b border-slate-200/80 dark:border-slate-700/80 rounded-none shadow-none"
                    :vigenteAno="$rx['vigente_ano'] ?? ''"
                    :anteriorAno="$rx['anterior_ano'] ?? ''"
                />
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
                                <th class="{{ $rxTh('semaforo') }}" title="{{ $thTitle('semaforo') }}">
                                    <x-rx.table-column-header :title="$th('semaforo', __('Indicador'))" />
                                </th>
                                <th class="{{ $rxTh('municipio') }}" title="{{ $thTitle('municipio') }}">
                                    <x-rx.table-column-header icon="map-pin" :title="$th('municipio', __('Município'))" />
                                </th>
                                <th class="{{ $rxTh('meta', true) }} min-w-[8.5rem]" title="{{ $thTitle('meta') }}">
                                    <x-rx.table-column-header icon="chart-bar" align="right" :title="$th('meta', __('Meta alvo'))" :subtitle="__('tur. · mat.')" />
                                </th>
                                <th class="{{ $rxTh('alunos', true) }}" title="{{ $thTitle('alunos') }}">
                                    <x-rx.table-column-header icon="users" align="right" :title="$th('alunos', __('Alunos'))" :subtitle="__('distintos')" />
                                </th>
                                <th class="{{ $rxTh('matriculas', true) }}" title="{{ $thTitle('matriculas') }}">
                                    <x-rx.table-column-header icon="clipboard-document-list" align="right" :title="$th('matriculas', __('Matrículas'))" :subtitle="__('activas')" />
                                </th>
                                <th class="{{ $rxTh('turmas', true) }}" title="{{ $thTitle('turmas') }}">
                                    <x-rx.table-column-header icon="academic-cap" align="right" :title="$th('turmas', __('Turmas'))" :subtitle="__('abertas')" />
                                </th>
                                <th class="{{ $rxTh('progresso', true) }} min-w-[9rem]" title="{{ $thTitle('progresso') }}">
                                    <x-rx.table-column-header icon="check-circle" align="right" :title="$th('progresso', __('Progresso'))" :subtitle="__('ritmo 72h')" />
                                </th>
                                <th class="{{ $rxTh('falta', true) }}" title="{{ $thTitle('falta') }}">
                                    <x-rx.table-column-header icon="exclamation-triangle" align="right" :title="$th('falta', __('Falta'))" :subtitle="__('registos')" />
                                </th>
                                <th class="{{ $rxTh('dias', true) }}" title="{{ $thTitle('dias') }}">
                                    <x-rx.table-column-header icon="command-line" align="right" :title="$th('dias', __('Dias'))" :subtitle="__('p/ meta')" />
                                </th>
                                <th class="{{ $rxTh('delta', true) }}" title="{{ $thTitle('delta') }}">
                                    <x-rx.table-column-header icon="arrow-path" align="right" :title="$th('delta', __('Δ matr.'))" :subtitle="__('vs :ano', ['ano' => $rx['anterior_ano'] ?? ''])" />
                                </th>
                                <th class="{{ $rxTh('censo', true) }}" title="{{ $thTitle('censo') }}">
                                    <x-rx.table-column-header icon="document-text" align="right" :title="$th('censo', __('Censo'))" />
                                </th>
                                <th class="{{ $rxTh('situacao') }}" title="{{ $thTitle('situacao') }}">
                                    <x-rx.table-column-header icon="signal" :title="$th('situacao', __('Leitura'))" />
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($rx['rows'] ?? [] as $row)
                                @php
                                    $censo = is_array($row['censo'] ?? null) ? $row['censo'] : [];
                                    $pctC = $censo['pct_concluido'] ?? null;
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
                                    <td class="{{ $rxTd('meta', true) }} text-xs leading-snug align-top">
                                        <x-rx.meta-alvo-cell
                                            :row="$row"
                                            :anteriorAno="$rx['anterior_ano'] ?? ''"
                                        />
                                    </td>
                                    <td class="{{ $rxTd('alunos', true) }} align-top">
                                        <span class="serv-rx-val--vigente">{{ number_format((int) ($row['alunos_vigente'] ?? 0), 0, ',', '.') }}</span>
                                        @if ((int) ($row['matriculas_vigente'] ?? 0) > (int) ($row['alunos_vigente'] ?? 0) && (int) ($row['alunos_vigente'] ?? 0) > 0)
                                            <span class="block text-[10px] text-amber-700/90 dark:text-amber-200/90" title="{{ __('Mais matrículas que alunos — verifique transferências.') }}">
                                                {{ __('+ matrículas duplicadas') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="{{ $rxTd('matriculas', true) }} align-top">
                                        <span class="serv-rx-val--vigente">{{ number_format((int) ($row['matriculas_vigente'] ?? 0), 0, ',', '.') }}</span>
                                        <span class="serv-rx-val--anterior">
                                            {{ __(':n em :ano', [
                                                'n' => number_format((int) ($row['matriculas_anterior'] ?? 0), 0, ',', '.'),
                                                'ano' => $rx['anterior_ano'] ?? '',
                                            ]) }}
                                        </span>
                                    </td>
                                    <td class="{{ $rxTd('turmas', true) }} align-top">
                                        <span class="serv-rx-val--vigente">{{ number_format((int) ($row['turmas_vigente'] ?? 0), 0, ',', '.') }}</span>
                                    </td>
                                    <td class="{{ $rxTd('progresso', true) }} text-xs leading-snug align-top min-w-[9rem]">
                                        <x-rx.progresso-cadastro-cell :row="$row" />
                                    </td>
                                    <td class="{{ $rxTd('falta', true) }} align-top">
                                        <x-rx.falta-cadastro-cell :row="$row" />
                                    </td>
                                    <td class="{{ $rxTd('dias', true) }} tabular-nums align-top">
                                        @php
                                            $diasMeta = $row['dias_para_meta'] ?? null;
                                            $faltaTotal = (int) ($row['falta_turmas'] ?? 0) + (int) ($row['falta_matriculas'] ?? 0);
                                        @endphp
                                        @if ($faltaTotal === 0 && ($row['meta_encontrou_referencia'] ?? false))
                                            <span class="text-emerald-700 dark:text-emerald-300 text-xs font-medium">—</span>
                                        @elseif ($diasMeta !== null && $diasMeta !== '')
                                            <span class="serv-rx-val--falta font-semibold">{{ $diasMeta }}</span>
                                            <span class="block text-[10px] text-amber-800/80 dark:text-amber-200/80">{{ __('dias est.') }}</span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="{{ $rxTd('delta', true) }} tabular-nums text-xs align-top">
                                        @php
                                            $d = (int) ($row['matriculas_delta'] ?? 0);
                                            $semBase = (bool) ($row['matriculas_delta_sem_base'] ?? false);
                                        @endphp
                                        <span class="{{ $d >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                                            {{ $d >= 0 ? '+' : '' }}{{ number_format($d, 0, ',', '.') }}
                                            @if ($semBase)
                                                <span class="block text-[10px] text-slate-500 font-normal">{{ __('novo cadastro') }}</span>
                                            @elseif ($row['matriculas_delta_pct'] !== null)
                                                <span class="block text-[10px] font-normal text-slate-500">({{ number_format((float) $row['matriculas_delta_pct'], 1, ',', '.') }}%)</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td class="{{ $rxTd('censo', true) }} tabular-nums text-xs align-top">
                                        @if ($pctC !== null)
                                            {{ number_format((float) $pctC, 1, ',', '.') }}%
                                            <span class="block text-[10px] text-slate-500">
                                                {{ (int) ($censo['pendentes'] ?? 0) }} {{ __('pend.') }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="{{ $rxTd('situacao') }} text-xs max-w-[14rem] align-top">
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
