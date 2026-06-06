@php
    $docRoute = Auth::user()?->isAdmin() ? 'admin.documentation.show' : 'documentation.show';
    $docUrl = route($docRoute, ['doc' => 'docs/CADUNICO_FAIXAS_ETARIAS_FUNDEB.md']);
@endphp

<div
    class="rounded-xl border-2 border-indigo-300/90 dark:border-indigo-600/60 bg-indigo-50/90 dark:bg-indigo-950/35 px-4 py-4 sm:px-5 sm:py-5 shadow-sm ring-1 ring-indigo-200/70 dark:ring-indigo-800/50"
    role="note"
    aria-labelledby="cadunico-faixas-callout-heading"
>
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="min-w-0 space-y-2">
            <h3
                id="cadunico-faixas-callout-heading"
                class="text-sm sm:text-base font-bold text-indigo-950 dark:text-indigo-100"
            >
                {{ __('Faixa etária 4–17 anos — porquê e impacto FUNDEB') }}
            </h3>
            <p class="text-sm text-indigo-950/95 dark:text-indigo-100/95 leading-relaxed">
                {{ __('Este painel cruza CadÚnico com a rede municipal na idade escolar obrigatória (4 a 17 anos). Creche (0–3) não entra na lacuna principal: é educação infantil, com metas e financiamento (IEI/VAAT) distintos do VAAF usado aqui.') }}
            </p>
            <ul class="text-sm text-indigo-950/90 dark:text-indigo-100/90 space-y-1.5 list-disc list-inside leading-relaxed">
                <li>
                    <strong>{{ __('Faixas no painel') }}</strong> —
                    {{ __('4–5 (pré), 6–10, 11–14 e 15–17 — conforme agregado Cecad/Misocial importado.') }}
                </li>
                <li>
                    <strong>{{ __('FUNDEB afectado (indicativo)') }}</strong> —
                    {{ __('lacuna × VAAF; cenários NEE/AEE (peso educação especial); VAAR proporcional em cenário. Não calcula VAAT nem IEI de creche.') }}
                </li>
                <li>
                    <strong>{{ __('0–3 anos') }}</strong> —
                    {{ __('coluna disponível só com CSV Cecad dedicado; ver documentação para medir demanda de creche no futuro.') }}
                </li>
            </ul>
            <p class="text-xs text-indigo-800/90 dark:text-indigo-200/85 italic leading-relaxed">
                {{ __('Valores indicativos para busca ativa e planeamento — não substituem repasse FNDE/Simec nem meta automática de matrícula.') }}
            </p>
        </div>
        <a
            href="{{ $docUrl }}"
            class="shrink-0 inline-flex items-center justify-center rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-3 py-2 shadow-sm whitespace-nowrap"
            target="_blank"
            rel="noopener noreferrer"
        >
            {{ __('Documentação completa') }}
        </a>
    </div>
</div>
