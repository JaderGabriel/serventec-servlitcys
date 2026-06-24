@props([
    'educacensoAnalysis' => null,
    'selectedCity' => null,
    'filters' => null,
    'yearFilterReady' => false,
    'chartExportContext' => [],
])

@php
    $report = is_array($educacensoAnalysis) ? $educacensoAnalysis : null;
    $city = $selectedCity;
    $cityId = $city?->getKey();
    $filtersObj = $filters instanceof \App\Support\Dashboard\IeducarFilterState ? $filters : null;
    $enabled = filter_var(config('educacenso.enabled', true), FILTER_VALIDATE_BOOL);
    $maxMb = (int) config('educacenso.upload_max_mb', 64);
    $status = (string) ($report['status'] ?? '');
    $statusShell = match ($status) {
        'critical' => 'border-rose-400 bg-rose-50/60 dark:bg-rose-950/25 dark:border-rose-700',
        'error' => 'border-orange-400 bg-orange-50/50 dark:bg-orange-950/20 dark:border-orange-700',
        'warning' => 'border-amber-400 bg-amber-50/50 dark:bg-amber-950/20 dark:border-amber-700',
        'ok' => 'border-emerald-400 bg-emerald-50/40 dark:bg-emerald-950/20 dark:border-emerald-700',
        default => 'border-sky-300 bg-sky-50/30 dark:bg-sky-950/20 dark:border-sky-700',
    };
    $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
    $bySchool = is_array($report['by_school'] ?? null) ? $report['by_school'] : [];
    $kpis = is_array($report['kpis'] ?? null) ? $report['kpis'] : [];
    $stats = is_array($report['statistics'] ?? null) ? $report['statistics'] : [];
    $filterQuery = $filtersObj && $cityId ? $filtersObj->toQueryParamsWithCity((int) $cityId) : [];
@endphp

<section id="censo-educacenso-analise" class="serv-panel border-l-4 border-l-sky-500 px-4 py-4 space-y-5 scroll-mt-24">
    <div>
        <p class="serv-eyebrow text-sky-800/90 dark:text-sky-200/90">{{ __('Conferência Educacenso') }}</p>
        <h3 class="text-sm font-semibold uppercase tracking-wide text-sky-950 dark:text-sky-100">
            {{ __('Análise do arquivo Educacenso × i-Educar') }}
        </h3>
        <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 leading-relaxed max-w-3xl">
            {{ __('Carregue o arquivo obtido no portal Educacenso (INEP). O sistema interpreta a declaração oficial e compara com o cadastro i-Educar — sem alterar nenhuma base.') }}
        </p>
    </div>

    @if (session('educacenso_success'))
        <div class="serv-callout serv-callout--success text-sm">{{ session('educacenso_success') }}</div>
    @endif
    @if (session('educacenso_error'))
        <div class="serv-callout serv-callout--danger text-sm">{{ session('educacenso_error') }}</div>
    @endif

    @if (! $enabled)
        <p class="text-sm text-slate-500">{{ __('Módulo desactivado (EDUCACENSO_DRY_RUN_ENABLED=false).') }}</p>
    @elseif (! $yearFilterReady || $cityId === null)
        <p class="text-sm text-amber-800 dark:text-amber-200">{{ __('Seleccione município e ano letivo para analisar o arquivo.') }}</p>
    @else
        <form
            method="post"
            action="{{ route('dashboard.analytics.educacenso.analyze') }}"
            enctype="multipart/form-data"
            class="rounded-lg border border-sky-200/80 dark:border-sky-800/60 bg-white/50 dark:bg-slate-900/40 px-4 py-4 space-y-3"
        >
            @csrf
            <input type="hidden" name="city_id" value="{{ $cityId }}" />
            @foreach ($filterQuery as $fk => $fv)
                @if ($fk !== 'city_id')
                    <input type="hidden" name="{{ $fk }}" value="{{ $fv }}" />
                @endif
            @endforeach

            <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1 min-w-0">
                    <label for="educacenso_file" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Arquivo Educacenso (.txt)') }}
                    </label>
                    <input
                        type="file"
                        name="educacenso_file"
                        id="educacenso_file"
                        accept=".txt,.csv,text/plain"
                        required
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-sky-600 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-sky-700"
                    />
                    <p class="mt-1 text-[10px] text-slate-500">{{ __('Máx. :mb MB · origem: portal Educacenso', ['mb' => $maxMb]) }}</p>
                </div>
                <button type="submit" class="serv-btn serv-btn--primary shrink-0">
                    {{ __('Analisar arquivo') }}
                </button>
            </div>
        </form>
    @endif

    @if ($report !== null)
        @include('dashboard.analytics.partials.educacenso-analysis-result', [
            'report' => $report,
            'status' => $status,
            'statusShell' => $statusShell,
            'findings' => $findings,
            'bySchool' => $bySchool,
            'kpis' => $kpis,
            'cityId' => $cityId,
            'filterQuery' => $filterQuery,
            'chartExportContext' => $chartExportContext,
        ])
    @endif
</section>
