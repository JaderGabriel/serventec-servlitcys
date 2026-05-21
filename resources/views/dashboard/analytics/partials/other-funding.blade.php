@props(['otherFundingData', 'yearFilterReady' => false, 'chartExportContext' => []])

@php
    $d = is_array($otherFundingData) ? $otherFundingData : [];
    $programs = is_array($d['programs'] ?? null) ? $d['programs'] : [];
    $transport = is_array($d['transport'] ?? null) ? $d['transport'] : null;
    $pillars = is_array($d['funding_pillars'] ?? null) ? $d['funding_pillars'] : [];
    $chartProgramas = is_array($d['chart_programas'] ?? null) ? $d['chart_programas'] : null;
    $publicMunicipal = is_array($d['public_municipal'] ?? null) ? $d['public_municipal'] : [];
    $statusBorder = static fn (string $s): string => match ($s) {
        'success' => 'border-emerald-200 dark:border-emerald-800',
        'warning' => 'border-amber-200 dark:border-amber-800',
        'danger' => 'border-rose-200 dark:border-rose-800',
        default => 'border-gray-200 dark:border-gray-700',
    };
    $statusHeader = static fn (string $s): string => match ($s) {
        'success' => 'bg-emerald-50/50 dark:bg-emerald-950/20',
        'warning' => 'bg-amber-50/50 dark:bg-amber-950/20',
        'danger' => 'bg-rose-50/50 dark:bg-rose-950/20',
        default => 'bg-gray-50/50 dark:bg-gray-900/30',
    };
@endphp

<div class="space-y-6">
    @if (! $yearFilterReady)
        <p class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
            {{ __('Seleccione o ano letivo e aplique os filtros para consultar demais financiamentos.') }}
        </p>
    @else
        <div class="rounded-lg border border-teal-200 dark:border-teal-800 bg-teal-50/70 dark:bg-teal-950/25 px-4 py-3 text-sm text-teal-950 dark:text-teal-100 space-y-2">
            <p class="font-semibold">{{ __('Financiamentos — programas complementares') }}</p>
            <p class="leading-relaxed">{{ $d['intro'] ?? '' }}</p>
            <p class="text-xs text-teal-800/90 dark:text-teal-300/90">
                {{ $d['city_name'] ?? '' }}
                @if (filled($d['year_label'] ?? null))
                    — {{ $d['year_label'] }}
                @endif
                @if (($d['total_matriculas'] ?? null) !== null)
                    · {{ number_format((int) $d['total_matriculas'], 0, ',', '.') }} {{ __('matrículas no filtro') }}
                @endif
            </p>
        </div>

        <p class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 leading-relaxed">
            {{ $d['footnote'] ?? '' }}
        </p>

        <p class="text-xs text-teal-800/90 dark:text-teal-200/90">
            <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'fundeb')">{{ __('FUNDEB') }}</button>
            ·
            <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'discrepancies')">{{ __('Discrepâncias') }}</button>
            {{ __('(impacto de cadastro nos repasses).') }}
        </p>

        @if (! empty($d['error']))
            <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                {{ $d['error'] }}
            </div>
        @endif

        <x-dashboard.municipal-public-queries
            :snapshot="$publicMunicipal"
            anchor="financiamentos-consultas-publicas"
        />

        @if ($chartProgramas !== null)
            <x-dashboard.chart-panel
                :chart="$chartProgramas"
                exportFilename="demais-financiamentos-cobertura"
                :exportMeta="$chartExportContext"
                chartPanelId="chart-other-funding-cobertura"
                panelTone="teal"
            />
        @endif

        @if ($transport !== null)
            <section class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50/40 dark:bg-indigo-950/20 px-4 py-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-indigo-950 dark:text-indigo-100">{{ __('Transporte escolar (PNATE)') }}</h3>
                <p class="mt-2 text-sm text-indigo-900/95 dark:text-indigo-200/95">{{ $transport['texto'] ?? '' }}</p>
                @if (count($transport['linhas'] ?? []) > 0)
                    <ul class="mt-3 text-xs font-mono text-indigo-900 dark:text-indigo-100 space-y-1">
                        @foreach ($transport['linhas'] as $linha)
                            <li>{{ $linha }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if (count($programs) === 0)
            <p class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
                {{ __('Nenhum programa configurado em ieducar.other_funding.programs.') }}
            </p>
        @endif

        @foreach ($programs as $prog)
            @php
                $st = (string) ($prog['status'] ?? 'neutral');
            @endphp
            <section class="rounded-lg border {{ $statusBorder($st) }} bg-white dark:bg-gray-900/40 shadow-sm overflow-hidden">
                <header class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 {{ $statusHeader($st) }}">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $prog['titulo'] ?? '' }}</h3>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ $prog['descricao'] ?? '' }}</p>
                    @if (filled($prog['fnde_url'] ?? null))
                        <a href="{{ $prog['fnde_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ __('Referência FNDE →') }}
                        </a>
                    @endif
                </header>
                <div class="px-4 py-4 space-y-3">
                    @if (count($prog['kpis'] ?? []) > 0)
                        <div class="flex flex-wrap gap-3">
                            @foreach ($prog['kpis'] as $kpi)
                                <div class="rounded-md border border-gray-200 dark:border-gray-600 px-3 py-2 min-w-[8rem]">
                                    <p class="text-[10px] uppercase text-gray-500 dark:text-gray-400">{{ $kpi['label'] ?? '' }}</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $kpi['value'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @foreach ($prog['distributions'] ?? [] as $col => $dist)
                        @if (is_array($dist) && count($dist['rows'] ?? []) > 0)
                            <div>
                                <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Campo :col', ['col' => $dist['col'] ?? $col]) }}</p>
                                <ul class="text-xs font-mono text-gray-800 dark:text-gray-200 space-y-0.5">
                                    @foreach ($dist['rows'] as $row)
                                        <li>{{ $row['value'] ?? '—' }}: {{ number_format((int) ($row['count'] ?? 0), 0, ',', '.') }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach

        @if (count($pillars) > 0)
            <section class="rounded-lg border border-rose-100 dark:border-rose-900/40 px-4 py-4">
                <h3 class="text-sm font-semibold text-rose-950 dark:text-rose-100">{{ __('Pilar «Programas complementares» (discrepâncias)') }}</h3>
                @foreach ($pillars as $pillar)
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $pillar['titulo'] ?? '' }} — {{ $pillar['descricao'] ?? '' }}</p>
                @endforeach
            </section>
        @endif
    @endif
</div>
