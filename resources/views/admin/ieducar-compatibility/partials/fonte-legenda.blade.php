@php
    $fonteKeys = [
        'fnde_portaria_receita_ieducar' => __('Estimado (portaria ÷ matrículas)'),
        'api_ckan_fnde' => __('Oficial FNDE'),
        'referencia_nacional_config' => __('Piso nacional'),
        'fnde_estado_vaaf_consultas' => __('Referência estadual'),
    ];
@endphp

<details class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40 group">
    <summary class="cursor-pointer list-none px-3 py-2 text-[11px] font-semibold text-slate-700 dark:text-slate-300 flex items-center justify-between gap-2">
        <span>{{ __('O que significa a coluna «Fonte»?') }}</span>
        <span class="text-[10px] font-normal text-slate-500 group-open:hidden">{{ __('Expandir') }}</span>
    </summary>
    <ul class="px-3 pb-2.5 text-[11px] text-slate-700 dark:text-slate-300 space-y-1.5 border-t border-slate-200/80 dark:border-slate-700/80 pt-2">
        @foreach ($fonteKeys as $key => $shortLabel)
            <li class="leading-snug">
                <span class="font-medium text-slate-800 dark:text-slate-200">{{ $shortLabel }}</span>
                <span class="text-slate-600 dark:text-slate-400"> — {{ __('admin_ieducar_compatibility.fonte_labels.'.$key) }}</span>
            </li>
        @endforeach
    </ul>
</details>
