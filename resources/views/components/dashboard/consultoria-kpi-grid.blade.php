@props(['items' => []])

@if (count($items) > 0)
    <div {{ $attributes->merge(['class' => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3']) }}>
        @foreach ($items as $item)
            @php
                $tone = (string) ($item['tone'] ?? 'slate');
                $border = match ($tone) {
                    'rose' => 'border-rose-200/90 dark:border-rose-800/60',
                    'orange' => 'border-orange-200/90 dark:border-orange-800/60',
                    'amber' => 'border-amber-200/90 dark:border-amber-800/60',
                    'emerald' => 'border-emerald-200/90 dark:border-emerald-800/60',
                    'sky' => 'border-sky-200/90 dark:border-sky-800/60',
                    'violet' => 'border-violet-200/90 dark:border-violet-800/60',
                    'indigo', 'teal' => 'border-teal-200/90 dark:border-teal-800/60',
                    default => 'border-slate-200/90 dark:border-slate-700/60',
                };
                $labelTone = match ($tone) {
                    'rose' => 'text-rose-800/90 dark:text-rose-200/90',
                    'orange' => 'text-orange-800/90 dark:text-orange-200/90',
                    'amber' => 'text-amber-800/90 dark:text-amber-200/90',
                    'emerald' => 'text-emerald-800/90 dark:text-emerald-200/90',
                    'sky' => 'text-sky-800/90 dark:text-sky-200/90',
                    'violet' => 'text-violet-800/90 dark:text-violet-200/90',
                    'indigo', 'teal' => 'text-teal-800/90 dark:text-teal-200/90',
                    default => 'text-slate-700 dark:text-slate-300',
                };
                $valueTone = match ($tone) {
                    'rose' => 'text-rose-700 dark:text-rose-300',
                    'orange' => 'text-orange-700 dark:text-orange-300',
                    'amber' => 'text-amber-700 dark:text-amber-300',
                    'emerald' => 'text-emerald-700 dark:text-emerald-300',
                    'sky' => 'text-sky-700 dark:text-sky-300',
                    'violet' => 'text-violet-700 dark:text-violet-300',
                    'indigo', 'teal' => 'text-teal-700 dark:text-teal-300',
                    default => 'text-slate-800 dark:text-slate-100',
                };
                $size = (string) ($item['size'] ?? 'lg');
            @endphp
            <div class="serv-panel {{ $border }} p-4 space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide {{ $labelTone }}">{{ $item['label'] ?? '' }}</p>
                <p class="font-semibold tabular-nums {{ $valueTone }} {{ $size === 'xl' ? 'text-xl' : 'text-2xl' }}">{{ $item['value'] ?? '' }}</p>
                @if (is_array($item['comparacao'] ?? null))
                    @php $cmp = $item['comparacao']; @endphp
                    <div class="mt-2 pt-2 border-t border-slate-200/80 dark:border-slate-600/60 grid grid-cols-1 gap-2 text-[11px]">
                        <div>
                            <span class="font-medium text-slate-600 dark:text-slate-400">{{ $cmp['real']['label'] ?? __('Real') }}:</span>
                            <span class="tabular-nums text-slate-900 dark:text-slate-100">{{ $cmp['real']['value'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-slate-600 dark:text-slate-400">{{ $cmp['previa']['label'] ?? __('Prévia') }}:</span>
                            <span class="tabular-nums text-slate-900 dark:text-slate-100">{{ $cmp['previa']['value'] ?? '—' }}</span>
                        </div>
                    </div>
                @endif
                @if (filled($item['explicacao_resumo'] ?? null))
                    <p class="text-[10px] leading-snug text-slate-600 dark:text-slate-400">{{ $item['explicacao_resumo'] }}</p>
                @endif
                @if (is_array($item['funding_explicacao'] ?? null))
                    <x-dashboard.consultoria-funding-explanation :explicacao="$item['funding_explicacao']" compact />
                @endif
            </div>
        @endforeach
    </div>
@endif
