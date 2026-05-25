@props([
    /** Fórmula ou regra numérica (texto curto). */
    'formula' => null,
    /** Complemento opcional (fonte, limite, aviso). */
    'note' => null,
])

@if (filled($formula) || filled($note))
    <p {{ $attributes->merge(['class' => 'text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed mt-2 pt-2 border-t border-gray-100 dark:border-gray-700/80']) }}>
        @if (filled($formula))
            <span class="font-medium text-gray-600 dark:text-gray-300">{{ __('Cálculo:') }}</span>
            <span>{{ $formula }}</span>
        @endif
        @if (filled($formula) && filled($note))
            <span class="text-gray-400 dark:text-gray-500 mx-1" aria-hidden="true">·</span>
        @endif
        @if (filled($note))
            <span>{{ $note }}</span>
        @endif
    </p>
@endif
