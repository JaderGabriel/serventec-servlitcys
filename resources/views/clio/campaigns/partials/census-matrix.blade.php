{{-- Exposição das matrículas (escolas ativas) — Fundamental I/II separados --}}
@php
    $matrix = $censusMatrix ?? [];
@endphp
@if (! empty($matrix['available']))
    <section aria-labelledby="clio-census-heading" class="space-y-4">
        <div>
            <h3 id="clio-census-heading" class="clio-section-title">
                {{ __('Exposição das matrículas — escolas ativas (:ano)', ['ano' => $matrix['year'] ?? '']) }}
            </h3>
            <p class="mt-1 text-sm text-slate-500 max-w-3xl">
                {{ __('Cód. :ibge · :uf · :mun · :n escola(s) em atividade.', [
                    'ibge' => $matrix['ibge'] ?: '—',
                    'uf' => $matrix['uf'] ?? '',
                    'mun' => $matrix['municipality'] ?? '',
                    'n' => $matrix['schools_active'] ?? 0,
                ]) }}
            </p>
            <p class="mt-1 text-xs text-slate-500 max-w-3xl">{{ $matrix['note'] ?? '' }}</p>
        </div>

        @foreach (['infantil', 'fundamental', 'eja'] as $blockKey)
            @php $block = $matrix[$blockKey] ?? null; @endphp
            @if (is_array($block))
                <div class="clio-panel overflow-hidden">
                    <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                        <h4 class="clio-section-title text-base">{{ $block['title'] ?? '' }}</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/60">
                                <tr>
                                    <th class="px-3 py-2 font-medium">{{ __('Matrícula') }}</th>
                                    @foreach ($block['columns'] ?? [] as $col)
                                        <th class="px-3 py-2 font-medium text-center" colspan="2">{{ $col['label'] }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    <th class="px-3 py-1"></th>
                                    @foreach ($block['columns'] ?? [] as $col)
                                        <th class="px-3 py-1 font-medium text-center">{{ __('Urbana') }}</th>
                                        <th class="px-3 py-1 font-medium text-center">{{ __('Rural') }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach (($block['rows'] ?? []) as $modKey => $modLabel)
                                    <tr>
                                        <td class="px-3 py-2 font-medium text-serv-navy dark:text-white">{{ $modLabel }}</td>
                                        @foreach ($block['columns'] ?? [] as $col)
                                            @php
                                                $vals = $block['values'][$col['key']] ?? [];
                                                $u = (int) ($vals['Urbana'][$modKey] ?? 0);
                                                $r = (int) ($vals['Rural'][$modKey] ?? 0);
                                            @endphp
                                            <td class="px-3 py-2 text-right tabular-nums {{ $u > 0 ? 'font-semibold text-serv-navy dark:text-white' : 'text-slate-400' }}">{{ number_format($u) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums {{ $r > 0 ? 'font-semibold text-serv-navy dark:text-white' : 'text-slate-400' }}">{{ number_format($r) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endforeach

        @php $geral = $matrix['geral'] ?? []; @endphp
        @if (! empty($geral['columns']))
            <div class="clio-panel overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h4 class="clio-section-title text-base">{{ $geral['title'] ?? __('Análise geral') }}</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/60">
                            <tr>
                                @foreach ($geral['columns'] as $col)
                                    <th class="px-3 py-2 font-medium text-center">{{ $col['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                @foreach ($geral['columns'] as $col)
                                    @php $v = (int) ($geral['values'][$col['key']] ?? 0); @endphp
                                    <td class="px-3 py-3 text-center text-base font-bold tabular-nums {{ ($col['key'] ?? '') === 'geral' ? 'bg-sky-50 text-serv-navy dark:bg-sky-950/40 dark:text-white' : 'text-serv-navy dark:text-white' }}">
                                        {{ number_format($v) }}
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="px-4 py-2 text-xs text-slate-500">
                    {{ __('GERAL = soma das colunas de Regular por etapa/jornada (Educação Especial é informativa e não entra no GERAL). Fundamental I = anos iniciais (1º–5º); Fundamental II = anos finais (6º–9º).') }}
                </p>
            </div>
        @endif
    </section>
@endif
