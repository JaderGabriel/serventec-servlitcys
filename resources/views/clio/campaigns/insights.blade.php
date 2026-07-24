<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ __('Insights / BI') }} — {{ $campaign->municipality_name ?? $campaign->city?->name }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ __('Leitura gerencial da Matrícula inicial · exercício :y', ['y' => $campaign->year]) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('clio.home', ['year' => $campaign->year]) }}" class="serv-btn-secondary text-sm">{{ __('Início Clio') }}</a>
                <a href="{{ route('clio.campaigns.analysis', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Análise') }}</a>
                <a href="{{ route('clio.campaigns.show', $campaign) }}" class="serv-link text-sm">{{ __('Central') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="serv-page-shell py-8 space-y-6">
        @if (session('success'))
            <div class="clio-flash clio-flash--ok">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="clio-flash clio-flash--warn">{{ session('error') }}</div>
        @endif

        @if (! $analyzed)
            <div class="clio-note">
                <p class="clio-note__title">{{ __('Coleta ainda não analisada') }}</p>
                <p class="mt-1 text-xs">{{ __('Corra a análise na Central ou no painel para gerar indicadores e o dataset BI.') }}</p>
            </div>
        @elseif ($bi === null)
            <div class="clio-panel clio-panel--pad space-y-3">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    {{ __('Dataset BI ainda não foi gerado para esta coleta.') }}
                </p>
                @can('analyze', $campaign)
                    <form method="post" action="{{ route('clio.campaigns.insights.refresh', $campaign) }}">
                        @csrf
                        <button type="submit" class="serv-btn-primary text-sm">{{ __('Gerar dataset e insights') }}</button>
                    </form>
                @endcan
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-slate-500">
                    {{ __('Actualizado em :d', ['d' => $bi->refreshed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—']) }}
                    · {{ __('Tabelas bi_clio_* sem PII — ligáveis ao Power BI Desktop') }}
                </p>
                @can('analyze', $campaign)
                    <form method="post" action="{{ route('clio.campaigns.insights.refresh', $campaign) }}">
                        @csrf
                        <button type="submit" class="serv-btn-secondary text-sm">{{ __('Actualizar dataset') }}</button>
                    </form>
                @endcan
            </div>

            <div class="clio-kpi-grid">
                <div class="clio-kpi-tile clio-kpi-tile--sky">
                    <p class="clio-kpi-tile__label">{{ __('Tríade (ativas)') }}</p>
                    <p class="clio-kpi-tile__value clio-kpi-tile__value--sm">
                        {{ $bi->triade_pct === null ? '—' : number_format((float) $bi->triade_pct, 1, ',', '.').'%' }}
                    </p>
                    <p class="clio-kpi-tile__hint">{{ __(':a ativas · :t total', ['a' => $bi->schools_active, 't' => $bi->schools_total]) }}</p>
                </div>
                <div class="clio-kpi-tile clio-kpi-tile--{{ ($bi->distortion_pct ?? 0) >= 20 ? 'rose' : 'slate' }}">
                    <p class="clio-kpi-tile__label">{{ __('Distorção idade-série') }}</p>
                    <p class="clio-kpi-tile__value clio-kpi-tile__value--sm">
                        {{ $bi->distortion_pct === null ? '—' : number_format((float) $bi->distortion_pct, 1, ',', '.').'%' }}
                    </p>
                    <p class="clio-kpi-tile__hint">{{ __('Estimativa EF/EM · 31/03') }}</p>
                </div>
                <div class="clio-kpi-tile clio-kpi-tile--slate">
                    <p class="clio-kpi-tile__label">{{ __('Densidade curricular') }}</p>
                    <p class="clio-kpi-tile__value clio-kpi-tile__value--sm">
                        {{ $bi->density_avg === null ? '—' : number_format((float) $bi->density_avg, 1, ',', '.') }}
                    </p>
                    <p class="clio-kpi-tile__hint">{{ __('Alunos / turma curricular') }}</p>
                </div>
                <div class="clio-kpi-tile clio-kpi-tile--emerald">
                    <p class="clio-kpi-tile__label">{{ __('NEE (pessoas)') }}</p>
                    <p class="clio-kpi-tile__value clio-kpi-tile__value--sm">{{ number_format((int) $bi->nee_people) }}</p>
                    <p class="clio-kpi-tile__hint">{{ __('Com marcador deficiência/TEA/AH') }}</p>
                </div>
                <div class="clio-kpi-tile clio-kpi-tile--{{ $bi->findings_errors > 0 ? 'rose' : 'emerald' }}">
                    <p class="clio-kpi-tile__label">{{ __('Erros na coleta') }}</p>
                    <p class="clio-kpi-tile__value clio-kpi-tile__value--sm">{{ number_format((int) $bi->findings_errors) }}</p>
                    <p class="clio-kpi-tile__hint">{{ __('Avisos: :n', ['n' => $bi->findings_warnings]) }}</p>
                </div>
                <div class="clio-kpi-tile clio-kpi-tile--sky">
                    <p class="clio-kpi-tile__label">{{ __('Matrículas Acomp') }}</p>
                    <p class="clio-kpi-tile__value clio-kpi-tile__value--sm">{{ number_format((int) $bi->mat_curricular) }}</p>
                    <p class="clio-kpi-tile__hint">{{ __('AEE :a · AC :c', ['a' => $bi->mat_aee, 'c' => $bi->mat_ac]) }}</p>
                </div>
            </div>

            <section class="clio-panel overflow-hidden" aria-labelledby="clio-insights-heading">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h3 id="clio-insights-heading" class="clio-section-title text-base">{{ __('O que os indicadores mostram') }}</h3>
                    <p class="text-xs text-slate-500">{{ __('Mensagens para decisão da gestão educacional — sem dados pessoais.') }}</p>
                </div>
                @if ($insights->isEmpty())
                    <p class="px-4 py-8 text-center text-sm text-slate-500">{{ __('Sem insights gerados. Actualize o dataset.') }}</p>
                @else
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($insights as $insight)
                            <li class="px-4 py-4 flex gap-3">
                                <span class="clio-chip clio-chip--{{ $insight->severity === 'error' ? 'error' : ($insight->severity === 'warning' ? 'warn' : 'neutral') }} shrink-0 self-start">
                                    {{ $insight->metric_value ?: strtoupper($insight->severity) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-serv-navy dark:text-white">{{ $insight->title }}</p>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">{{ $insight->body }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <div class="clio-note">
                <p class="clio-note__title">{{ __('Power BI Desktop') }}</p>
                <p class="mt-1 text-xs leading-relaxed">
                    {{ __('Ligue às tabelas bi_clio_campaign, bi_clio_school, bi_clio_enrollment_stage, bi_clio_quality, bi_clio_inclusion e bi_clio_insight (utilizador só-leitura). Documentação em docs/POWERBI.md · secção Clio.') }}
                </p>
            </div>
        @endif
    </div>
</x-app-layout>
