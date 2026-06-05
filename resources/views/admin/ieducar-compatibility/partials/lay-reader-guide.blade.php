<details class="rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/50 dark:bg-sky-950/20 shadow-sm group" open>
    <summary class="cursor-pointer list-none px-4 py-3 sm:px-5 sm:py-4 flex items-center justify-between gap-3">
        <span class="text-sm font-semibold text-sky-950 dark:text-sky-100">
            {{ __('admin_ieducar_compatibility.guide.title') }}
        </span>
        <span class="text-[11px] font-medium text-sky-800/80 dark:text-sky-300/80 group-open:hidden">{{ __('Expandir') }}</span>
        <span class="text-[11px] font-medium text-sky-800/80 dark:text-sky-300/80 hidden group-open:inline">{{ __('Recolher') }}</span>
    </summary>

    <div class="px-4 pb-4 sm:px-5 sm:pb-5 space-y-4 border-t border-sky-200/80 dark:border-sky-800/80 pt-4">
        <p class="text-sm text-sky-950/90 dark:text-sky-100/90 leading-relaxed">
            {{ __('admin_ieducar_compatibility.guide.what_is') }}
        </p>
        <p class="text-xs text-amber-900 dark:text-amber-200/90 rounded-md border border-amber-200/80 dark:border-amber-800/60 bg-amber-50/80 dark:bg-amber-950/30 px-3 py-2 leading-relaxed">
            {{ __('admin_ieducar_compatibility.guide.not_official') }}
        </p>

        <div>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-sky-900 dark:text-sky-200 mb-2">
                {{ __('admin_ieducar_compatibility.glossary.heading') }}
            </h4>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-2 text-xs">
                @foreach ([
                    'vaaf', 'vaat', 'exercicio', 'matriculas', 'probe', 'fila', 'piso',
                ] as $key)
                    <div class="rounded-md bg-white/70 dark:bg-gray-900/40 px-2.5 py-2 border border-sky-100 dark:border-sky-900/50">
                        <dt class="sr-only">{{ $key }}</dt>
                        <dd class="text-sky-950 dark:text-sky-100 leading-snug">{{ __('admin_ieducar_compatibility.glossary.'.$key) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-sky-900 dark:text-sky-200 mb-2">
                {{ __('admin_ieducar_compatibility.sources.heading') }}
            </h4>
            <div class="overflow-x-auto rounded-lg border border-sky-100 dark:border-sky-900/50">
                <table class="min-w-full text-xs">
                    <tbody class="divide-y divide-sky-100 dark:divide-sky-900/40 bg-white/60 dark:bg-gray-900/30">
                        @foreach ([
                            ['ieducar', 'i-Educar'],
                            ['fnde_portaria', 'FNDE / Portaria'],
                            ['fnde_ckan', 'FNDE / API'],
                            ['censo_inep', 'INEP / Censo'],
                            ['local_db', 'SERVLITCYS'],
                        ] as [$key, $label])
                            <tr>
                                <th scope="row" class="px-3 py-2 text-left font-semibold text-sky-900 dark:text-sky-100 whitespace-nowrap w-36">{{ $label }}</th>
                                <td class="px-3 py-2 text-sky-950/90 dark:text-sky-100/85 leading-snug">{{ __('admin_ieducar_compatibility.sources.'.$key) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-sky-900 dark:text-sky-200 mb-2">
                {{ __('admin_ieducar_compatibility.steps.heading') }}
            </h4>
            <ol class="list-decimal list-inside space-y-1 text-xs text-sky-950 dark:text-sky-100 leading-relaxed">
                <li>{{ __('admin_ieducar_compatibility.steps.1') }}</li>
                <li>{{ __('admin_ieducar_compatibility.steps.2') }}</li>
                <li>{{ __('admin_ieducar_compatibility.steps.3') }}</li>
                <li>{{ __('admin_ieducar_compatibility.steps.4') }}</li>
            </ol>
            <p class="mt-2 text-[11px] text-sky-800 dark:text-sky-300">
                <a href="{{ route('admin.public-data.index') }}" class="underline font-medium">{{ __('admin_ieducar_compatibility.matriculas.censo_link') }}</a>
                ·
                <a href="{{ route('admin.documentation.show', ['doc' => 'docs/FUNDEB_VAAF_E_ONDA1.md']) }}" class="underline font-medium">{{ __('Documentação FUNDEB') }}</a>
            </p>
        </div>
    </div>
</details>
