@php
    $score = $h['compliance_score'] ?? null;
    $status = (string) ($h['compliance_status'] ?? 'neutral');

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
    <div class="p-4 sm:p-6 flex flex-col items-center justify-center">
        @if ($score !== null)
            <x-dashboard.compliance-speedometer
                :score="(int) $score"
                :status="$status"
                :label="$statusLabel"
                class="w-full max-w-[260px]"
            />
            <p class="mt-4 max-w-xl text-xs text-slate-600 dark:text-slate-400 text-center leading-relaxed">
                {{ __('O índice combina gravidade das rotinas de cadastro e alertas FUNDEB. Valide sempre na aba Discrepâncias (por escola) e em Censo (exportação).') }}
            </p>
        @else
            <p class="text-sm text-slate-500 text-center">{{ __('Índice indisponível — verifique filtros e conexão ou abra Discrepâncias.') }}</p>
        @endif
    </div>
</section>
