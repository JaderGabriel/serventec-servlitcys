@php
    $h = is_array($healthData ?? null) ? $healthData : [];
    $complementaryPrograms = is_array($h['complementary_programs'] ?? null) ? $h['complementary_programs'] : [];
    $activeCheckIds = is_array($h['active_check_ids'] ?? null) ? $h['active_check_ids'] : [];
    $activeProgramIds = is_array($h['active_program_ids'] ?? null) ? $h['active_program_ids'] : [];
    $diagStep = is_array($diagStep ?? null) ? $diagStep : [];
@endphp

@if (count($complementaryPrograms) > 0)
    <x-dashboard.consultoria-section
        :step="$diagStep['diag-programas'] ?? null"
        anchor="diag-programas"
        :title="__('Financiamentos complementares (análise municipal)')"
        :subtitle="__('PNAE, PNATE, PDDE e correlatos — cobertura de cadastro no i-Educar (não é valor de repasse FNDE).')"
    >
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach ($complementaryPrograms as $prog)
                @php
                    $pst = (string) ($prog['status'] ?? 'neutral');
                    $pborder = match ($pst) {
                        'success' => 'border-emerald-300 dark:border-emerald-800',
                        'warning' => 'border-amber-300 dark:border-amber-800',
                        'danger' => 'border-rose-300 dark:border-rose-800',
                        default => '',
                    };
                @endphp
                <article class="serv-panel {{ $pborder }} px-3 py-3 text-sm">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h4 class="font-semibold text-serv-navy dark:text-slate-100 text-xs leading-snug">{{ $prog['titulo'] ?? '' }}</h4>
                        <span class="serv-status-pill
                            @if ($pst === 'success') serv-status-pill--success
                            @elseif ($pst === 'danger') serv-status-pill--danger
                            @elseif ($pst === 'warning') serv-status-pill--warning
                            @else serv-status-pill--neutral @endif">
                            {{ $prog['status_label'] ?? '' }}
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ $prog['resumo'] ?? '' }}</p>
                </article>
            @endforeach
        </div>
        <p class="serv-callout flex flex-wrap gap-x-2 gap-y-1">
            <x-consultoria-tab-link tab="other_funding" :label="__('Detalhe na aba Financiamentos')" class="text-xs" />
            <span class="text-gray-300 dark:text-gray-600">·</span>
            <button type="button" class="serv-inline-tab-link text-xs" x-on:click="$dispatch('funding-loss-set-active', { ids: @js($activeCheckIds), programIds: @js($activeProgramIds) }); $dispatch('open-modal', 'funding-loss-conditions')">{{ __('Condições de perda (todos os programas)') }}</button>
        </p>
    </x-dashboard.consultoria-section>
@else
    <p class="serv-callout text-sm text-slate-600 dark:text-slate-400">{{ __('Nenhum programa complementar avaliado neste filtro.') }}</p>
@endif
