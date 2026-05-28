@props([
    'informe' => [],
    'anchor' => 'comparativo-informes',
    'title' => null,
    'subtitle' => null,
])

@php
    $informe = is_array($informe) ? $informe : [];
    $blocos = is_array($informe['blocos'] ?? null) ? $informe['blocos'] : [];
    $informeRing = static fn (string $s): string => match ($s) {
        'success' => 'border-l-teal-500',
        'warning' => 'border-l-amber-500',
        'danger' => 'border-l-rose-500',
        default => 'border-l-slate-400',
    };
@endphp

@if (count($blocos) > 0)
    <x-dashboard.consultoria-section
        :anchor="$anchor"
        :title="$title ?? __('Informes para consultoria')"
        :subtitle="$subtitle ?? ($informe['aviso'] ?? __('Síntese para apresentação à gestão municipal.'))"
    >
        @foreach ($blocos as $bloco)
            @php
                $st = (string) ($bloco['status'] ?? 'neutral');
                $indicadores = is_array($bloco['indicadores'] ?? null) ? $bloco['indicadores'] : [];
                $acoes = is_array($bloco['acoes'] ?? null) ? $bloco['acoes'] : [];
            @endphp
            <article class="serv-panel border-l-4 {{ $informeRing($st) }} px-4 py-3 space-y-2">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                    <div>
                        <h4 class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ $bloco['titulo'] ?? '' }}</h4>
                        @if (filled($bloco['subtitulo'] ?? null))
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $bloco['subtitulo'] }}</p>
                        @endif
                    </div>
                    @if (filled($bloco['status_label'] ?? null))
                        <x-status-pill :status="$st" :label="$bloco['status_label']" class="shrink-0" />
                    @endif
                </div>
                @foreach ($bloco['paragrafos'] ?? [] as $par)
                    <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">{{ $par }}</p>
                @endforeach
                @if (count($indicadores) > 0)
                    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-sm">
                        @foreach ($indicadores as $ind)
                            <div class="serv-panel border-slate-200/90 dark:border-slate-700/60 px-3 py-2">
                                <dt class="text-[11px] text-slate-500 dark:text-slate-400">{{ $ind['label'] ?? '' }}</dt>
                                <dd class="font-semibold tabular-nums text-serv-navy dark:text-slate-100">{{ $ind['value'] ?? '' }}</dd>
                                @if (filled($ind['hint'] ?? null))
                                    <dd class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $ind['hint'] }}</dd>
                                @endif
                            </div>
                        @endforeach
                    </dl>
                @endif
                @if (count($acoes) > 0)
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">{{ __('Recomendações') }}</p>
                        <ul class="list-disc list-inside text-xs text-slate-700 dark:text-slate-300 space-y-0.5">
                            @foreach ($acoes as $acao)
                                <li>{{ $acao }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </article>
        @endforeach
    </x-dashboard.consultoria-section>
@endif
