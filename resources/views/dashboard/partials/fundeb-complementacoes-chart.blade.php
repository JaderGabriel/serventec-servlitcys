@php
    $fundebPortaria = is_array($fundebPortaria ?? null) ? $fundebPortaria : [];
    $available = ! empty($fundebPortaria['available']);
    $fundebChart = is_array($fundebPortaria['chart'] ?? null) ? $fundebPortaria['chart'] : null;
    $totalMun = (int) ($fundebPortaria['municipios_total'] ?? 0);
    $withChart = (int) ($fundebPortaria['municipios_com_dados'] ?? 0);
@endphp

@if ($available && $fundebChart !== null)
    <section aria-labelledby="home-fundeb-complementacoes" class="serv-panel overflow-x-hidden">
        <div class="px-5 py-4 border-b border-slate-200/90 dark:border-slate-700/90 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="serv-eyebrow">{{ __('FUNDEB') }}</p>
                <h3 id="home-fundeb-complementacoes" class="font-display text-lg font-semibold text-serv-navy dark:text-slate-100">
                    {{ __('Complementações previstas por município') }}
                </h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                    {{ __('Exercício :ano · :n município(s) no cadastro, :c com complementação na portaria FNDE. Mesmo gráfico do painel RX (dados consolidados, distintos do cadastro em andamento).', [
                        'ano' => (string) ($fundebPortaria['exercicio'] ?? ''),
                        'n' => number_format($totalMun, 0, ',', '.'),
                        'c' => number_format($withChart, 0, ',', '.'),
                    ]) }}
                </p>
            </div>
            <a href="{{ route('dashboard.rx') }}" class="serv-link text-sm shrink-0 self-start">
                {{ __('Painel RX completo') }}
            </a>
        </div>
        <div class="p-4 sm:p-5 rx-fundeb-portaria__chart">
            <x-dashboard.chart-panel
                :chart="$fundebChart"
                export-filename="home-fundeb-complementacoes-{{ $fundebPortaria['exercicio'] ?? 'ano' }}"
                :compact="false"
                chart-panel-id="home-fundeb-complementacoes"
            />
        </div>
    </section>
@endif
