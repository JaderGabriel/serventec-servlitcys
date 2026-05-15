@props(['data' => []])

@php
    $modal = is_array($data) ? $data : [];
    $conditions = is_array($modal['conditions'] ?? null) ? $modal['conditions'] : [];
    $pillars = is_array($modal['pillars'] ?? null) ? $modal['pillars'] : [];
@endphp

<x-modal name="funding-loss-conditions" maxWidth="2xl" focusable>
    <div class="flex flex-col max-h-[min(90vh,52rem)]">
        <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
            <div class="pr-2">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('Condições que podem implicar perda ou redução de recursos') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                    {{ __('Referência FUNDEB, VAAR e Censo. VAAF de referência: :vaa por ocorrência (configurável).', ['vaa' => $modal['vaa_label'] ?? '—']) }}
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
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-indigo-800 dark:text-indigo-200 mb-2">{{ __('Marco legal e repasses') }}</h3>
                    <ul class="grid grid-cols-1 gap-2">
                        @foreach ($pillars as $pillar)
                            <li class="rounded-md border border-indigo-200/70 dark:border-indigo-800/50 bg-indigo-50/50 dark:bg-indigo-950/20 px-3 py-2">
                                <p class="font-medium text-indigo-950 dark:text-indigo-100">{{ $pillar['titulo'] ?? '' }}</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-indigo-900/90 dark:text-indigo-200/85">{{ $pillar['descricao'] ?? '' }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-rose-800 dark:text-rose-200 mb-2">
                    {{ __('Rotinas de cadastro monitoradas (:n)', ['n' => count($conditions)]) }}
                </h3>
                <div class="space-y-3">
                    @foreach ($conditions as $cond)
                        @php
                            $sev = (string) ($cond['severity'] ?? 'warning');
                            $badge = match ($sev) {
                                'danger' => 'bg-red-100 text-red-900 dark:bg-red-900/50 dark:text-red-100',
                                'warning' => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
                                default => 'bg-slate-200 text-slate-800',
                            };
                        @endphp
                        <article class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                                <h4 class="font-semibold text-gray-900 dark:text-gray-100">{{ $cond['title'] ?? '' }}</h4>
                                <span class="inline-flex shrink-0 items-center px-2 py-0.5 rounded text-xs font-medium {{ $badge }}">
                                    {{ __('Peso :p', ['p' => $cond['peso_label'] ?? '1']) }}
                                </span>
                            </div>
                            @if (! empty($cond['vaar_refs']) && is_array($cond['vaar_refs']))
                                <p class="mt-1 text-xs text-indigo-700 dark:text-indigo-300">{{ implode(' · ', $cond['vaar_refs']) }}</p>
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
                {{ __('A aba «Saúde do município» resume o detectado na base; «Discrepâncias e Erros» detalha por escola no filtro actual.') }}
            </p>
        </div>

        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-end shrink-0">
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
