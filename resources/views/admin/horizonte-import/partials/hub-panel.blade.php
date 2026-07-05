@php
    use App\Support\Admin\AdminImportHubCatalog;
    use App\Support\Brazil\IbgeMunicipalityCatalog;
    use App\Support\Horizonte\HorizonteFortnightlyFeedPhaseCatalog;
    use App\Support\Horizonte\HorizonteFeedPhaseOptions;

    $hub = is_array($horizonteHub ?? null) ? $horizonteHub : [];
    $brazilianUfs = IbgeMunicipalityCatalog::brazilianUfs();
    $coverage = is_array($hub['coverage'] ?? null) ? $hub['coverage'] : [];
    $phases = is_array($hub['phases'] ?? null) ? $hub['phases'] : [];
    $lastFeed = is_array($hub['last_feed'] ?? null) ? $hub['last_feed'] : null;
    $feedFlash = session('horizonte_feed');
    $pipeline = is_array($hub['pipeline'] ?? null) ? $hub['pipeline'] : (is_array($feedFlash['pipeline'] ?? null) ? $feedFlash['pipeline'] : null);
    $scheduleSummary = (string) ($hub['schedule_summary'] ?? '');
    $enabled = (bool) ($hub['enabled'] ?? true);
    $feedEnabled = (bool) ($hub['feed_enabled'] ?? true);
    $bundle = is_array($hub['bundle'] ?? null) ? $hub['bundle'] : [];
    $ibgeUfsTotal = (int) ($coverage['ibge_ufs_total'] ?? 27);

    $phaseDefinitions = HorizonteFortnightlyFeedPhaseCatalog::definitions();
    $phaseGroups = HorizonteFortnightlyFeedPhaseCatalog::groups();
    $defaultPhases = HorizonteFeedPhaseOptions::defaultSelectedPhaseKeys();
    $phasesByGroup = [];
    foreach ($phaseDefinitions as $def) {
        $phasesByGroup[$def['group']][] = $def;
    }

    $toneBorder = static fn (string $tone): string => match ($tone) {
        'amber' => 'border-amber-300/80 dark:border-amber-800/60 ring-amber-200/50',
        'emerald' => 'border-emerald-300/80 dark:border-emerald-800/60 ring-emerald-200/50',
        'sky' => 'border-sky-300/80 dark:border-sky-800/60 ring-sky-200/50',
        'rose' => 'border-rose-300/80 dark:border-rose-800/60 ring-rose-200/50',
        'violet' => 'border-violet-300/80 dark:border-violet-800/60 ring-violet-200/50',
        'indigo' => 'border-indigo-300/80 dark:border-indigo-800/60 ring-indigo-200/50',
        default => 'border-slate-300/80 dark:border-slate-700/60 ring-slate-200/50',
    };
    $toneBg = static fn (string $tone): string => match ($tone) {
        'amber' => 'bg-amber-50/90 dark:bg-amber-950/30',
        'emerald' => 'bg-emerald-50/90 dark:bg-emerald-950/30',
        'sky' => 'bg-sky-50/90 dark:bg-sky-950/30',
        'rose' => 'bg-rose-50/90 dark:bg-rose-950/30',
        'violet' => 'bg-violet-50/90 dark:bg-violet-950/30',
        'indigo' => 'bg-indigo-50/90 dark:bg-indigo-950/30',
        default => 'bg-slate-50/90 dark:bg-slate-900/50',
    };
    $toneIcon = static fn (string $tone): string => match ($tone) {
        'amber' => 'text-amber-700 dark:text-amber-300',
        'emerald' => 'text-emerald-700 dark:text-emerald-300',
        'sky' => 'text-sky-700 dark:text-sky-300',
        'rose' => 'text-rose-700 dark:text-rose-300',
        'violet' => 'text-violet-700 dark:text-violet-300',
        'indigo' => 'text-indigo-700 dark:text-indigo-300',
        default => 'text-slate-600 dark:text-slate-300',
    };
@endphp

<section id="horizonte-hub" class="scroll-mt-24 space-y-5">
    {{-- Hero Horizonte --}}
    <div class="relative overflow-hidden rounded-2xl border border-sky-200/80 dark:border-sky-900/60 bg-gradient-to-br from-slate-900 via-sky-950 to-slate-900 p-5 sm:p-6 text-white shadow-lg">
        <div class="absolute inset-0 opacity-20 pointer-events-none" style="background-image: radial-gradient(circle at 20% 20%, rgb(56 189 248 / 0.35), transparent 45%), radial-gradient(circle at 80% 70%, rgb(99 102 241 / 0.25), transparent 40%);"></div>
        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 max-w-2xl">
                <div class="flex items-center gap-2 text-sky-300">
                    <x-ui.icon name="map" class="h-5 w-5 shrink-0" />
                    <span class="text-xs font-semibold uppercase tracking-widest">{{ __('Horizonte') }}</span>
                </div>
                <h3 class="mt-2 text-lg sm:text-xl font-bold tracking-tight" style="font-family: Outfit, ui-sans-serif, system-ui, sans-serif;">
                    {{ __('Abastecimento nacional do mapa') }}
                </h3>
                <p class="mt-2 text-sm text-sky-100/90 leading-relaxed">
                    {{ __('Marque as fases que deseja executar. O pipeline corre em etapas (1 fase por invocação); fases incrementais — Educacenso, SAEB, IBGE, SIDRA, SICONFI — continuam com passos automáticos.') }}
                </p>
                @if ($feedEnabled && ($hub['schedule_enabled'] ?? true))
                    <p class="mt-2 text-xs text-sky-200/70">
                        {{ __('Agendamento: :summary', ['summary' => $scheduleSummary]) }}
                        @if ($lastFeed && filled($lastFeed['finished_at'] ?? null))
                            · {{ __('Última execução: :when', [
                                'when' => \Illuminate\Support\Carbon::parse($lastFeed['finished_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                            ]) }}
                        @endif
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <a href="{{ $hub['map_url'] ?? route('dashboard.horizonte') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-sky-500 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-400 shadow-sm">
                    <x-ui.icon name="map" class="h-3.5 w-3.5" />
                    {{ __('Mapa') }}
                </a>
                <a href="{{ $hub['doc_url'] ?? route('admin.documentation.show', ['doc' => 'docs/HORIZONTE.md']) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-xs font-medium text-white hover:bg-white/15">
                    {{ __('Docs') }}
                </a>
            </div>
        </div>
    </div>

    @if (! $enabled)
        <x-admin.import-hub.callout variant="info" :title="__('Horizonte desactivado')">
            {{ __('Defina HORIZONTE_ENABLED=true para activar o mapa e este painel.') }}
        </x-admin.import-hub.callout>
    @elseif (is_array($feedFlash))
        <x-admin.import-hub.callout :variant="($feedFlash['success'] ?? false) ? 'success' : 'warning'" :title="($feedFlash['success'] ?? false) ? __('Pipeline iniciado') : __('Pipeline com falhas')">
            {{ $feedFlash['message'] ?? '' }}
            @if ($feedFlash['staged'] ?? false)
                <p class="mt-2 text-xs opacity-90">{{ __('Modo em etapas — fases restantes continuam pelo agendador ou:') }}</p>
                <code class="mt-1 block text-[10px] font-mono opacity-90">php artisan horizonte:fortnightly-feed --staged --continue</code>
            @endif
        </x-admin.import-hub.callout>
    @endif

    @if ($enabled && $feedEnabled)
        <form method="POST" action="{{ route('admin.horizonte-import.feed') }}" id="horizonte-feed-form" class="rounded-2xl border border-sky-200/90 dark:border-sky-900/50 bg-white/80 dark:bg-slate-900/40 p-4 sm:p-5 space-y-4 shadow-sm">
            @csrf

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <x-ui.icon name="arrow-path" class="h-4 w-4 text-sky-600 dark:text-sky-400" />
                        {{ __('Fases a executar') }}
                    </h4>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 max-w-2xl">
                        {{ __('Seleccione o que deseja importar neste ciclo. A ordem de execução segue a sequência oficial do feed bimestral.') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 text-[11px]">
                    <button type="button" data-horizonte-phases="all" class="rounded-md border border-sky-200 dark:border-sky-800 px-2.5 py-1 font-medium text-sky-800 dark:text-sky-200 hover:bg-sky-50 dark:hover:bg-sky-950/40">{{ __('Todas') }}</button>
                    <button type="button" data-horizonte-phases="core" class="rounded-md border border-slate-200 dark:border-slate-700 px-2.5 py-1 font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60">{{ __('Núcleo triad') }}</button>
                    <button type="button" data-horizonte-phases="none" class="rounded-md border border-slate-200 dark:border-slate-700 px-2.5 py-1 font-medium text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800/60">{{ __('Limpar') }}</button>
                </div>
            </div>

            @foreach ($phaseGroups as $groupKey => $groupMeta)
                @if (($phasesByGroup[$groupKey] ?? []) === [])
                    @continue
                @endif
                <div class="space-y-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $groupMeta['label'] }}</p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-500">{{ $groupMeta['description'] }}</p>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($phasesByGroup[$groupKey] as $def)
                            <label class="group flex gap-3 rounded-xl border p-3 cursor-pointer transition {{ $toneBorder($def['tone']) }} {{ $toneBg($def['tone']) }} has-[:checked]:ring-2 has-[:checked]:ring-sky-500/60">
                                <input
                                    type="checkbox"
                                    name="phases[]"
                                    value="{{ $def['key'] }}"
                                    checked
                                    class="mt-0.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800"
                                    data-horizonte-phase="{{ $def['key'] }}"
                                    @if ($def['incremental']) data-horizonte-incremental="1" @endif
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-1.5">
                                        <x-ui.icon :name="$def['icon']" class="h-4 w-4 shrink-0 {{ $toneIcon($def['tone']) }}" />
                                        <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __($def['label']) }}</span>
                                        @if ($def['incremental'])
                                            <span class="rounded px-1 py-0.5 text-[9px] font-bold uppercase bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-200">{{ __('Incremental') }}</span>
                                        @endif
                                    </span>
                                    <span class="mt-1 block text-[11px] text-slate-600 dark:text-slate-400 leading-snug">{{ $def['description'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between pt-2 border-t border-slate-200/80 dark:border-slate-700/80">
                <label class="block text-xs text-slate-700 dark:text-slate-300 sm:max-w-xs">
                    <span class="font-medium">{{ __('Restringir a UF (opcional)') }}</span>
                    <select name="uf" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="">{{ __('Nacional — 27 UFs') }}</option>
                        @foreach ($brazilianUfs as $ufOption)
                            <option value="{{ $ufOption }}">{{ $ufOption }}</option>
                        @endforeach
                    </select>
                    <span class="mt-0.5 block text-[10px] text-slate-500">{{ __('Filtra FUNDEB, Censo, SAEB, IBGE e SGE à UF escolhida.') }}</span>
                </label>
                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:from-sky-500 hover:to-indigo-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                >
                    <x-ui.icon name="arrow-path" class="h-4 w-4" />
                    {{ __('Executar fases seleccionadas') }}
                </button>
            </div>
        </form>

        <script>
            document.querySelectorAll('[data-horizonte-phases]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const mode = btn.getAttribute('data-horizonte-phases');
                    const boxes = document.querySelectorAll('[data-horizonte-phase]');
                    const core = ['fundeb_receita', 'censo_matriculas', 'saeb_planilhas'];
                    boxes.forEach(box => {
                        if (mode === 'all') box.checked = true;
                        else if (mode === 'none') box.checked = false;
                        else if (mode === 'core') box.checked = core.includes(box.value);
                    });
                });
            });
        </script>
    @endif

    @if ($pipeline !== null)
        @include('admin.horizonte-import.partials.feed-pipeline', [
            'pipeline' => $pipeline,
            'stepInterval' => $hub['feed_step_interval'] ?? 20,
        ])
    @endif

    @include('admin.horizonte-import.partials.educacenso-sync', [
        'horizonteHub' => $hub,
        'enabled' => $enabled && $feedEnabled,
    ])

    @include('admin.horizonte-import.partials.municipal-geo-sync', [
        'horizonteHub' => $hub,
        'enabled' => $enabled && $feedEnabled,
    ])

    <x-admin.import-hub.stats-grid columns="sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <x-admin.import-hub.stat label="FUNDEB" :value="number_format((int) ($coverage['fundeb_municipios'] ?? 0))" :hint="__('IBGE nacionais')" tone="amber" />
        <x-admin.import-hub.stat label="Censo" :value="number_format((int) ($coverage['censo_municipios'] ?? 0))" :hint="__('municípios indexados')" tone="emerald" />
        <x-admin.import-hub.stat :label="__('Educacenso')" :value="((int) ($coverage['educacenso_steps_done'] ?? 0)).'/'.((int) ($coverage['educacenso_steps_total'] ?? 135))" :hint="__('passos ano×UF')" tone="sky" />
        <x-admin.import-hub.stat label="SAEB" :value="number_format((int) ($coverage['saeb_municipios'] ?? 0))" :hint="__('municípios com indicadores')" tone="violet" />
        <x-admin.import-hub.stat :label="__('Triad completa')" :value="number_format((int) ($coverage['with_full_triad'] ?? 0))" :hint="__('FUNDEB + Censo + SAEB')" tone="sky" />
        <x-admin.import-hub.stat label="CadÚnico" :value="number_format((int) ($coverage['cadunico_municipios'] ?? 0))" :hint="__('agregados municipais')" tone="rose" />
        <x-admin.import-hub.stat label="SIDRA" :value="number_format((int) ($coverage['demography_municipios'] ?? 0))" :hint="__('pop. 4–17')" tone="slate" />
        <x-admin.import-hub.stat label="IBGE" :value="((int) ($coverage['ibge_ufs_warmed'] ?? 0)).'/'.$ibgeUfsTotal" :hint="__('UFs com catálogo')" tone="sky" />
        <x-admin.import-hub.stat :label="__('Malha IBGE')" :value="((int) ($coverage['municipal_geo_ufs_done'] ?? 0)).'/'.((int) ($coverage['municipal_geo_ufs_total'] ?? 27))" :hint="__('polígonos + área')" tone="indigo" />
    </x-admin.import-hub.stats-grid>

    @include('admin.horizonte-import.partials.offline-bundle', [
        'bundle' => $bundle,
    ])

    @if (! ($coverage['microdados_ok'] ?? false))
        <x-admin.import-hub.callout variant="warning" :title="__('Microdados Censo em falta')">
            {{ __('A fase Censo requer o CSV Educacenso em storage — use Geo / pipeline ou importação manual antes do abastecimento.') }}
        </x-admin.import-hub.callout>
    @endif

    @include('admin.horizonte-import.partials.phases-table', [
        'phases' => $phases,
    ])
</section>
