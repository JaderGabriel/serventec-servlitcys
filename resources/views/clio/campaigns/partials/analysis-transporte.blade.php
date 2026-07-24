<section aria-labelledby="clio-tra-heading" class="space-y-4">
    <div>
        <h3 id="clio-tra-heading" class="clio-section-title">{{ __('Transporte escolar') }}</h3>
        <p class="mt-1 text-sm text-slate-500 max-w-3xl">
            {{ $transporte['summary'] ?? __('Sequência: uso → poder público → rural/urbano → tipo de veículo → escolas.') }}
        </p>
    </div>

    @if (empty($transporte['has_transporte_columns']))
        <div class="clio-note">
            <p class="clio-note__title">{{ __('Colunas de transporte não detectadas') }}</p>
            <p class="text-xs text-slate-600 dark:text-slate-400">{{ __('Reexporte a Relação de alunos com uso de transporte, poder público e tipo de veículo, se o portal os oferecer.') }}</p>
        </div>
    @else
        <div class="clio-kpi-grid clio-kpi-grid--4">
            <div class="clio-kpi-tile {{ $tileTone(($transporte['flagged'] ?? 0) > 0 ? 'sky' : 'slate') }}">
                <p class="clio-kpi-tile__label">{{ __('1. Usam transporte (rede)') }}</p>
                <p class="clio-kpi-tile__value clio-kpi-tile__value--sm {{ $toneClass(($transporte['flagged'] ?? 0) > 0 ? 'sky' : 'slate') }}">
                    {{ number_format((int) ($transporte['flagged'] ?? 0)) }}
                </p>
                <p class="clio-kpi-tile__hint">
                    {{ $transporte['pct'] === null ? '—' : number_format((float) $transporte['pct'], 1, ',', '.').'% · '.__(':s matrículas', ['s' => $transporte['scanned'] ?? 0]) }}
                </p>
            </div>
            <div class="clio-kpi-tile {{ $tileTone(($transporte['active']['flagged'] ?? 0) > 0 ? 'emerald' : 'slate') }}">
                <p class="clio-kpi-tile__label">{{ __('2. Usam · escolas ativas') }}</p>
                <p class="clio-kpi-tile__value clio-kpi-tile__value--sm {{ $toneClass(($transporte['active']['flagged'] ?? 0) > 0 ? 'emerald' : 'slate') }}">
                    {{ number_format((int) ($transporte['active']['flagged'] ?? 0)) }}
                </p>
                <p class="clio-kpi-tile__hint">
                    {{ ($transporte['active']['pct'] ?? null) === null ? '—' : number_format((float) $transporte['active']['pct'], 1, ',', '.').'%' }}
                </p>
            </div>
            <div class="clio-kpi-tile {{ $tileTone('amber') }}">
                <p class="clio-kpi-tile__label">{{ __('3. Usam · demais situações') }}</p>
                <p class="clio-kpi-tile__value clio-kpi-tile__value--sm {{ $toneClass('amber') }}">
                    {{ number_format((int) ($transporte['other']['flagged'] ?? 0)) }}
                </p>
                <p class="clio-kpi-tile__hint">{{ __('Extinta / paralisada / reforma') }}</p>
            </div>
            <div class="clio-kpi-tile {{ $tileTone('slate') }}">
                <p class="clio-kpi-tile__label">{{ __('4. Rural × urbana (quem usa)') }}</p>
                <p class="clio-kpi-tile__value clio-kpi-tile__value--sm text-base leading-snug">
                    @php
                        $locBars = $transporte['active']['by_location_users'] ?? $transporte['by_location_users'] ?? [];
                        $locShort = collect($locBars)->take(2)->map(fn ($b) => $b['label'].' '.$b['count'])->implode(' · ');
                    @endphp
                    {{ $locShort !== '' ? $locShort : '—' }}
                </p>
                <p class="clio-kpi-tile__hint">{{ __('Só escolas ativas no destaque') }}</p>
            </div>
        </div>

        <p class="text-xs text-slate-500">{{ $transporte['note_location'] ?? '' }}</p>

        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
            @if (! empty($transporte['by_transporte']))
                <div class="clio-panel clio-panel--pad space-y-3">
                    <h4 class="clio-section-title text-base">{{ __('1 · Uso de transporte') }}</h4>
                    @foreach ($transporte['by_transporte'] as $bar)
                        <div class="clio-dist__row">
                            <div class="clio-dist__head">
                                <span class="clio-dist__label">{{ $bar['label'] }}</span>
                                <span class="clio-dist__count">{{ $bar['count'] }} · {{ number_format((float) $bar['pct'], 0) }}%</span>
                            </div>
                            <div class="clio-dist__track">
                                <div class="clio-dist__fill {{ ($bar['label'] ?? '') === __('Sim') ? 'clio-dist__fill--sky' : 'clio-dist__fill--emerald' }}" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            @if (! empty($transporte['by_poder_publico']))
                <div class="clio-panel clio-panel--pad space-y-3">
                    <h4 class="clio-section-title text-base">{{ __('2 · Poder público') }}</h4>
                    @foreach ($transporte['by_poder_publico'] as $bar)
                        <div class="clio-dist__row">
                            <div class="clio-dist__head">
                                <span class="clio-dist__label">{{ $bar['label'] }}</span>
                                <span class="clio-dist__count">{{ $bar['count'] }} · {{ number_format((float) $bar['pct'], 0) }}%</span>
                            </div>
                            <div class="clio-dist__track">
                                <div class="clio-dist__fill clio-dist__fill--sky" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            @if (! empty($transporte['by_location_users']))
                <div class="clio-panel clio-panel--pad space-y-3">
                    <h4 class="clio-section-title text-base">{{ __('3 · Rural / urbana') }}</h4>
                    @foreach ($transporte['by_location_users'] as $bar)
                        <div class="clio-dist__row">
                            <div class="clio-dist__head">
                                <span class="clio-dist__label">{{ $bar['label'] }}</span>
                                <span class="clio-dist__count">{{ $bar['count'] }}</span>
                            </div>
                            <div class="clio-dist__track">
                                <div class="clio-dist__fill {{ preg_match('/rural/iu', $bar['label']) ? 'clio-dist__fill--amber' : 'clio-dist__fill--sky' }}" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            @if (! empty($transporte['by_veiculo']))
                <div class="clio-panel clio-panel--pad space-y-3">
                    <h4 class="clio-section-title text-base">{{ __('4 · Tipo de veículo') }}</h4>
                    @foreach ($transporte['by_veiculo'] as $bar)
                        <div class="clio-dist__row">
                            <div class="clio-dist__head">
                                <span class="clio-dist__label">{{ $bar['label'] }}</span>
                                <span class="clio-dist__count">{{ $bar['count'] }}</span>
                            </div>
                            <div class="clio-dist__track">
                                <div class="clio-dist__fill clio-dist__fill--emerald" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="clio-panel overflow-hidden">
            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h4 class="clio-section-title">{{ __('5 · Por escola — em atividade') }}</h4>
                <p class="text-xs text-slate-500">{{ __('Destaque para quem usa transporte; rural em âmbar.') }}</p>
            </div>
            <div class="clio-table-wrap">
                <table class="clio-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ __('Escola') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('Localização') }}</th>
                            <th class="px-4 py-2 font-medium text-right">{{ __('Usam') }}</th>
                            <th class="px-4 py-2 font-medium text-right">{{ __('%') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('Tipos de veículo') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($transporte['schools_active'] ?? [] as $row)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40 {{ ! empty($row['highlight_rural']) ? 'bg-amber-50/60 dark:bg-amber-950/20' : (! empty($row['highlight']) ? 'bg-sky-50/50 dark:bg-sky-950/20' : '') }}">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-serv-navy dark:text-white">{{ $row['name'] }}</div>
                                    <div class="font-mono text-[11px] text-slate-500">INEP {{ $row['inep'] }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="{{ ! empty($row['highlight_rural']) ? 'clio-chip clio-chip--warn' : 'clio-chip clio-chip--neutral' }}">{{ $row['location'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium {{ ($row['flagged'] ?? 0) > 0 ? 'text-sky-700 dark:text-sky-300' : '' }}">{{ $row['flagged'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-500">{{ number_format((float) ($row['pct'] ?? 0), 1, ',', '.') }}%</td>
                                <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                                    @forelse ($row['by_veiculo'] ?? [] as $v)
                                        <span class="mr-2">{{ $v['label'] }} ({{ $v['count'] }})</span>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ __('Sem dados de transporte nas escolas ativas.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (! empty($transporte['schools_other']))
            <div class="clio-panel overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h4 class="clio-section-title">{{ __('Por escola — demais situações') }}</h4>
                </div>
                <div class="clio-table-wrap">
                    <table class="clio-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ __('Escola') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Localização') }}</th>
                                <th class="px-4 py-2 font-medium text-right">{{ __('Usam') }}</th>
                                <th class="px-4 py-2 font-medium text-right">{{ __('%') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($transporte['schools_other'] as $row)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-serv-navy dark:text-white">{{ $row['name'] }}</div>
                                        <div class="font-mono text-[11px] text-slate-500">INEP {{ $row['inep'] }} · {{ $row['functioning'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm">{{ $row['location'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $row['flagged'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-500">{{ number_format((float) ($row['pct'] ?? 0), 1, ',', '.') }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</section>
