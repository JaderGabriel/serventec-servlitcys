@php
    $h = is_array($healthData ?? null) ? $healthData : [];
    $vaafComparacao = is_array($h['vaaf_comparacao'] ?? null) ? $h['vaaf_comparacao'] : null;
    $previsaoComparacao = is_array($h['previsao_comparacao'] ?? null) ? $h['previsao_comparacao'] : null;
    $divergenciaVaaf = is_array($h['divergencia_vaaf'] ?? null) ? $h['divergencia_vaaf'] : null;
    $fundebMods = is_array($h['fundeb_modules'] ?? null) ? $h['fundeb_modules'] : [];
    $diagStep = is_array($diagStep ?? null) ? $diagStep : [];
    $score = $h['compliance_score'] ?? null;
@endphp

@if ($vaafComparacao !== null)
    <x-dashboard.consultoria-section
        :step="$diagStep['diag-vaaf'] ?? null"
        anchor="diag-vaaf"
        :title="__('Medidores financeiros (índice e projeção)')"
        :subtitle="__('Índice do exercício (portaria) × piso federal (comparação). Projeção = matrículas do filtro × índice — indicativa.')"
    >
        <x-dashboard.consultoria-vaaf-comparacao
            :comparacao="$vaafComparacao"
            :divergencia="$divergenciaVaaf"
            :previsaoComparacao="$previsaoComparacao"
        />
    </x-dashboard.consultoria-section>
@endif

@if (count($fundebMods) > 0)
    <x-dashboard.consultoria-section
        :step="$diagStep['diag-roteiro'] ?? null"
        anchor="diag-roteiro"
        :title="__('Roteiro FUNDEB / VAAR')"
        :subtitle="__('Eixos de condicionalidade e situação municipal.')"
    >
        <div class="space-y-2">
            @foreach ($fundebMods as $mod)
                @php
                    $mst = (string) ($mod['status'] ?? 'neutral');
                    $mchip = match ($mst) {
                        'success' => 'border-l-emerald-500',
                        'warning' => 'border-l-amber-500',
                        'danger' => 'border-l-red-500',
                        default => 'border-l-slate-400',
                    };
                @endphp
                <article class="serv-panel border-l-4 {{ $mchip }} px-3 py-2 text-xs">
                    <p class="font-medium text-serv-navy dark:text-slate-100">{{ $mod['title'] ?? '' }}</p>
                    <p class="text-slate-500 dark:text-slate-400 mt-0.5">{{ $mod['reference'] ?? '' }}</p>
                    <p class="mt-1 text-slate-700 dark:text-slate-300 leading-relaxed">{{ $mod['situacao'] ?? '' }}</p>
                </article>
            @endforeach
        </div>
        <p class="serv-callout">
            <x-consultoria-tab-link tab="fundeb" :label="__('Abrir aba FUNDEB completa')" class="text-xs" />
        </p>
    </x-dashboard.consultoria-section>
@endif

@if ($score !== null && filled($h['compliance_label'] ?? null))
    <script type="application/json" data-health-score-patch>@json([
        'compliance_score' => (int) $score,
        'compliance_status' => (string) ($h['compliance_status'] ?? 'neutral'),
        'compliance_label' => (string) ($h['compliance_label'] ?? ''),
        'summary_patch' => is_array($h['summary_patch'] ?? null) ? $h['summary_patch'] : [],
    ])</script>
@endif
