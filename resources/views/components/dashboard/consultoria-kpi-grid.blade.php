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
                    'indigo' => 'border-indigo-200/90 dark:border-indigo-800/60',
                    'teal' => 'border-teal-200/90 dark:border-teal-800/60',
                    default => 'border-slate-200/90 dark:border-slate-700/60',
                };
                $labelTone = match ($tone) {
                    'rose' => 'text-rose-800/90 dark:text-rose-200/90',
                    'orange' => 'text-orange-800/90 dark:text-orange-200/90',
                    'amber' => 'text-amber-800/90 dark:text-amber-200/90',
                    'emerald' => 'text-emerald-800/90 dark:text-emerald-200/90',
                    'indigo' => 'text-indigo-800/90 dark:text-indigo-200/90',
                    'teal' => 'text-teal-800/90 dark:text-teal-200/90',
                    default => 'text-slate-700 dark:text-slate-300',
                };
                $valueTone = match ($tone) {
                    'rose' => 'text-rose-700 dark:text-rose-300',
                    'orange' => 'text-orange-700 dark:text-orange-300',
                    'amber' => 'text-amber-700 dark:text-amber-300',
                    'emerald' => 'text-emerald-700 dark:text-emerald-300',
                    'indigo' => 'text-indigo-700 dark:text-indigo-300',
                    'teal' => 'text-teal-700 dark:text-teal-300',
                    default => 'text-slate-800 dark:text-slate-100',
                };
                $size = (string) ($item['size'] ?? 'lg');
            @endphp
            <div class="rounded-lg border {{ $border }} bg-white dark:bg-gray-900/40 p-4 shadow-sm space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide {{ $labelTone }}">{{ $item['label'] ?? '' }}</p>
                <p class="font-semibold tabular-nums {{ $valueTone }} {{ $size === 'xl' ? 'text-xl' : 'text-2xl' }}">{{ $item['value'] ?? '' }}</p>
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
