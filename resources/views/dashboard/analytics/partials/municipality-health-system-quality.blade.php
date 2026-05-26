@php
    $score = $h['compliance_score'] ?? null;
    $status = (string) ($h['compliance_status'] ?? 'neutral');
    $summary = is_array($h['summary'] ?? null) ? $h['summary'] : [];
    $pendencias = (int) ($summary['pendencias_cadastro'] ?? 0);
    $perda = (float) ($summary['perda_estimada_anual'] ?? 0);
    $modulos = (int) ($summary['modulos_fundeb_alerta'] ?? 0);
    $programas = (int) ($h['programas_alerta'] ?? 0);
    $censoPend = (int) ($summary['cadastros_quinzena'] ?? 0);

    $dimensoes = is_array($h['cadastro_dimensions'] ?? null) ? $h['cadastro_dimensions'] : [];
    $analisadas = count(array_filter($dimensoes, static fn (array $d): bool => ! in_array($d['availability'] ?? '', ['unavailable', 'no_data'], true)));
    $comPendencia = count(array_filter($dimensoes, static fn (array $d): bool => ($d['has_issue'] ?? false) === true));

    $statusLabel = match ($status) {
        'success' => __('Adequado no filtro'),
        'warning' => __('Atenção — corrigir antes do Censo'),
        'danger' => __('Crítico — ação imediata'),
        default => __('Sem índice'),
    };
@endphp

<section id="diag-qualidade-sistema" class="serv-panel overflow-hidden border border-slate-200/90 dark:border-slate-700 scroll-mt-24">
    <div class="px-4 py-3 border-b border-slate-200/80 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/50">
        <h3 class="text-sm font-semibold text-serv-navy dark:text-slate-100">
            {{ __('Índice geral de qualidade (filtro actual)') }}
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            {{ __('Único velocímetro consolidado do município. Os cartões «Explorar» abaixo mostram métricas específicas por área.') }}
        </p>
    </div>
    <div class="p-4 sm:p-5 grid grid-cols-1 lg:grid-cols-12 gap-4">
        <div class="lg:col-span-4 flex flex-col items-center justify-center p-4 rounded-lg bg-slate-50/80 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700">
            @if ($score !== null)
                <x-dashboard.compliance-speedometer
                    :score="(int) $score"
                    :status="$status"
                    :label="$statusLabel"
                    class="w-full max-w-[200px]"
                />
            @else
                <p class="text-sm text-slate-500">{{ __('Índice indisponível') }}</p>
            @endif
        </div>
        <div class="lg:col-span-8">
            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                <div class="rounded-lg border border-slate-200/80 dark:border-slate-700 px-3 py-2">
                    <dt class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Rotinas analisadas') }}</dt>
                    <dd class="font-semibold tabular-nums text-serv-navy dark:text-slate-100">{{ number_format($analisadas) }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200/80 dark:border-slate-700 px-3 py-2">
                    <dt class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Com pendência') }}</dt>
                    <dd class="font-semibold tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($comPendencia) }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200/80 dark:border-slate-700 px-3 py-2">
                    <dt class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Perda est. / ano') }}</dt>
                    <dd class="font-semibold tabular-nums text-orange-800 dark:text-orange-300">{{ 'R$ '.number_format($perda, 2, ',', '.') }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200/80 dark:border-slate-700 px-3 py-2">
                    <dt class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('FUNDEB alerta') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format($modulos) }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200/80 dark:border-slate-700 px-3 py-2">
                    <dt class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Programas alerta') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format($programas) }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200/80 dark:border-slate-700 px-3 py-2">
                    <dt class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Cadastro (quinzena)') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format($censoPend) }}</dd>
                </div>
            </dl>
            <p class="mt-4 text-xs text-slate-600 dark:text-slate-400 serv-callout">
                {{ __('O índice combina gravidade das rotinas de cadastro e alertas FUNDEB. Valide sempre na aba Discrepâncias (por escola) e em Censo (exportação).') }}
            </p>
        </div>
    </div>
</section>
