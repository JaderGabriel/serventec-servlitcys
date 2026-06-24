@props(['data' => []])

@php
    $modal = is_array($data) ? $data : [];
    $conditions = is_array($modal['conditions'] ?? null) ? $modal['conditions'] : [];
    $pillars = is_array($modal['pillars'] ?? null) ? $modal['pillars'] : [];
    $programs = is_array($modal['complementary_programs'] ?? null) ? $modal['complementary_programs'] : [];
    $repasses = is_array($modal['public_repasses'] ?? null) ? $modal['public_repasses'] : [];
@endphp

<x-modal name="funding-loss-conditions" maxWidth="3xl" focusable>
    <div
        class="flex flex-col max-h-[min(92vh,56rem)]"
        x-data="{ activeIds: [], activeProgramIds: [] }"
        x-on:funding-loss-set-active.window="
            activeIds = Array.isArray($event.detail?.ids) ? $event.detail.ids : [];
            activeProgramIds = Array.isArray($event.detail?.programIds) ? $event.detail.programIds : [];
        "
    >
        <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
            <div class="pr-2 min-w-0">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('Financiamentos públicos — condições de perda ou redução') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                    {{ __('FUNDEB, VAAR, Censo e programas complementares (PNAE, PNATE, PDDE). VAAF de referência nas discrepâncias: :vaa por ocorrência (configurável).', ['vaa' => $modal['vaa_label'] ?? '—']) }}
                </p>
                <p class="mt-2 text-xs text-rose-700 dark:text-rose-300 font-medium" x-show="activeIds.length > 0 || activeProgramIds.length > 0" x-cloak>
                    {{ __('Destaque: itens com alerta detectado no município (filtro actual).') }}
                </p>
            </div>
            <button
                type="button"
                class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 shrink-0"
                x-on:click="$dispatch('close-modal', 'funding-loss-conditions')"
                aria-label="{{ __('Fechar') }}"
            >
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="overflow-y-auto px-6 py-4 space-y-6 text-sm flex-1 min-h-0">
            @if (filled($modal['aviso'] ?? null))
                <p class="text-xs text-amber-900 dark:text-amber-200 border border-amber-200 dark:border-amber-800 bg-amber-50/80 dark:bg-amber-950/30 rounded-md px-3 py-2 leading-relaxed">
                    {{ $modal['aviso'] }}
                </p>
            @endif

            @if (count($pillars) > 0)
                <section>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-200 mb-2">{{ __('FUNDEB, VAAR e matrícula no Censo') }}</h3>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach ($pillars as $pillar)
                            <li class="rounded-md border border-sky-200/70 dark:border-sky-800/50 bg-sky-50/50 dark:bg-sky-950/20 px-3 py-2">
                                <p class="font-medium text-sky-950 dark:text-sky-100">{{ $pillar['titulo'] ?? '' }}</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-sky-900/90 dark:text-sky-200/85">{{ $pillar['descricao'] ?? '' }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if (count($programs) > 0)
                <section>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-blue-800 dark:text-blue-200 mb-2">
                        {{ __('Programas complementares (:n)', ['n' => count($programs)]) }}
                    </h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-3 leading-relaxed">
                        {{ __('Repasses FNDE além do FUNDEB base. O painel mede cobertura de cadastro no i-Educar; valores de repasse exactos estão na aba Financiamentos e em fontes oficiais.') }}
                    </p>
                    <div class="space-y-3">
                        @foreach ($programs as $prog)
                            @php $progId = (string) ($prog['id'] ?? ''); @endphp
                            <article
                                class="rounded-lg border p-3 transition-all duration-200"
                                x-bind:class="activeProgramIds.includes(@js($progId))
                                    ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-400/60 dark:border-blue-600 dark:bg-blue-950/40 dark:ring-blue-500/40'
                                    : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/20'"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <span
                                            x-show="activeProgramIds.includes(@js($progId))"
                                            x-cloak
                                            class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-blue-600 text-white mr-1"
                                        >{{ __('Alerta cadastro') }}</span>
                                        <h4 class="font-semibold text-gray-900 dark:text-gray-100">{{ $prog['titulo'] ?? '' }}</h4>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $prog['repasse_fonte'] ?? '' }}</p>
                                    </div>
                                    @if (filled($prog['fnde_url'] ?? null))
                                        <a href="{{ $prog['fnde_url'] }}" target="_blank" rel="noopener noreferrer" class="text-xs text-sky-600 dark:text-sky-400 hover:underline shrink-0">{{ __('FNDE') }}</a>
                                    @endif
                                </div>
                                <p class="mt-2 text-gray-700 dark:text-gray-300 leading-relaxed">{{ $prog['explanation'] ?? '' }}</p>
                                @if (filled($prog['cadastro_ligacao'] ?? null))
                                    <p class="mt-1 text-xs text-sky-800 dark:text-sky-200"><span class="font-medium">{{ __('Cadastro:') }}</span> {{ $prog['cadastro_ligacao'] }}</p>
                                @endif
                                <p class="mt-2 text-rose-800/95 dark:text-rose-200/95 leading-relaxed">
                                    <span class="font-medium">{{ __('Risco / impacto:') }}</span> {{ $prog['impact'] ?? '' }}
                                </p>
                                <p class="mt-2 text-emerald-800 dark:text-emerald-200 leading-relaxed">
                                    <span class="font-medium">{{ __('Correção:') }}</span> {{ $prog['correction'] ?? '' }}
                                </p>
                                @if (! empty($prog['related_checks']) && is_array($prog['related_checks']))
                                    <p class="mt-1 text-[11px] text-sky-700 dark:text-sky-300">
                                        {{ __('Rotinas relacionadas em Discrepâncias:') }}
                                        {{ implode(', ', array_map('strval', $prog['related_checks'])) }}
                                    </p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @if (count($repasses) > 0)
                <section>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-200 mb-2">{{ __('Repasses e consultas públicas no painel') }}</h3>
                    <ul class="space-y-2">
                        @foreach ($repasses as $rep)
                            <li class="rounded-md border border-sky-200/70 dark:border-sky-800/50 bg-sky-50/40 dark:bg-sky-950/20 px-3 py-2">
                                <p class="font-medium text-sky-950 dark:text-sky-100">{{ $rep['titulo'] ?? '' }}</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-sky-900/90 dark:text-sky-200/85">{{ $rep['descricao'] ?? '' }}</p>
                                @if (filled($rep['onde'] ?? null))
                                    <p class="mt-1 text-[11px] text-gray-600 dark:text-gray-400"><span class="font-medium">{{ __('No servlitcys:') }}</span> {{ $rep['onde'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-rose-800 dark:text-rose-200 mb-2">
                    {{ __('Rotinas de cadastro monitoradas (:n)', ['n' => count($conditions)]) }}
                </h3>
                <p class="text-xs text-gray-600 dark:text-gray-400 mb-3">
                    {{ __('Cada rotina com pendência gera estimativa indicativa (ocorrências × VAAF × peso). Detalhe por escola na aba Discrepâncias.') }}
                </p>
                <div class="space-y-3">
                    @foreach ($conditions as $cond)
                        @php
                            $condId = (string) ($cond['id'] ?? '');
                            $sev = (string) ($cond['severity'] ?? 'warning');
                            $badge = match ($sev) {
                                'danger' => 'bg-red-100 text-red-900 dark:bg-red-900/50 dark:text-red-100',
                                'warning' => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
                                default => 'bg-slate-200 text-slate-800',
                            };
                        @endphp
                        <article
                            class="rounded-lg border p-3 transition-all duration-200"
                            x-bind:class="activeIds.includes(@js($condId))
                                ? 'border-rose-500 bg-rose-50 ring-2 ring-rose-400/70 dark:border-rose-600 dark:bg-rose-950/50 dark:ring-rose-500/50'
                                : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/20'"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                                <div class="flex items-start gap-2 min-w-0">
                                    <span
                                        x-show="activeIds.includes(@js($condId))"
                                        x-cloak
                                        class="shrink-0 mt-0.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-rose-600 text-white"
                                    >{{ __('No município') }}</span>
                                    <h4 class="font-semibold text-gray-900 dark:text-gray-100">{{ $cond['title'] ?? '' }}</h4>
                                </div>
                                <span class="inline-flex shrink-0 items-center px-2 py-0.5 rounded text-xs font-medium {{ $badge }}">
                                    {{ __('Peso :p', ['p' => $cond['peso_label'] ?? '1']) }}
                                </span>
                            </div>
                            @if (! empty($cond['vaar_refs']) && is_array($cond['vaar_refs']))
                                <p class="mt-1 text-xs text-sky-700 dark:text-sky-300">{{ implode(' · ', $cond['vaar_refs']) }}</p>
                            @endif
                            <p class="mt-2 text-gray-700 dark:text-gray-300 leading-relaxed">{{ $cond['explanation'] ?? '' }}</p>
                            <p class="mt-2 text-rose-800/95 dark:text-rose-200/95 leading-relaxed">
                                <span class="font-medium">{{ __('Risco financeiro / Censo:') }}</span>
                                {{ $cond['impact'] ?? '' }}
                            </p>
                            <p class="mt-1 text-xs text-orange-800 dark:text-orange-200 italic">{{ $cond['impacto_financeiro'] ?? '' }}</p>
                            <p class="mt-2 text-emerald-800 dark:text-emerald-200 leading-relaxed">
                                <span class="font-medium">{{ __('Correção:') }}</span>
                                {{ $cond['correction'] ?? '' }}
                            </p>
                        </article>
                    @endforeach
                </div>
            </section>

            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed border-t border-gray-200 dark:border-gray-700 pt-3">
                {{ __('Serventec consolida FUNDEB, programas e Censo. Financiamentos detalha cobertura de cadastro e consultas CKAN/API. Discrepâncias lista ocorrências por escola.') }}
                <button type="button" class="text-sky-600 dark:text-sky-400 hover:underline ml-1" x-on:click="$dispatch('close-modal', 'funding-loss-conditions'); $dispatch('set-analytics-tab', 'other_funding')">{{ __('Abrir Financiamentos') }}</button>
            </p>
        </div>

        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 flex flex-wrap justify-end gap-2 shrink-0">
            <button
                type="button"
                class="px-3 py-2 rounded-md text-xs font-medium text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 hover:bg-sky-50 dark:hover:bg-sky-950/30"
                x-on:click="$dispatch('close-modal', 'funding-loss-conditions'); $dispatch('set-analytics-tab', 'fundeb')"
            >
                {{ __('Aba FUNDEB') }}
            </button>
            <button
                type="button"
                class="px-3 py-2 rounded-md text-xs font-medium text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 hover:bg-blue-50 dark:hover:bg-blue-950/30"
                x-on:click="$dispatch('close-modal', 'funding-loss-conditions'); $dispatch('set-analytics-tab', 'other_funding')"
            >
                {{ __('Aba Financiamentos') }}
            </button>
            <button
                type="button"
                class="px-4 py-2 rounded-md text-sm font-medium bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600"
                x-on:click="$dispatch('close-modal', 'funding-loss-conditions')"
            >
                {{ __('Fechar') }}
            </button>
        </div>
    </div>
</x-modal>
