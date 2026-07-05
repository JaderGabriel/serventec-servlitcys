@php
    use App\Support\Brazil\IbgeMunicipalityCatalog;
    use App\Support\Horizonte\HorizonteFortnightlyFeedPhaseCatalog;

    $hub = is_array($horizonteHub ?? null) ? $horizonteHub : [];
    $brazilianUfs = IbgeMunicipalityCatalog::brazilianUfs();
    $coverage = is_array($hub['coverage'] ?? null) ? $hub['coverage'] : [];
    $phases = is_array($hub['phases'] ?? null) ? $hub['phases'] : [];
    $feedFlash = session('horizonte_feed');
    $pipeline = is_array($hub['pipeline'] ?? null) ? $hub['pipeline'] : (is_array($feedFlash['pipeline'] ?? null) ? $feedFlash['pipeline'] : null);
    $enabled = (bool) ($hub['enabled'] ?? true);
    $feedEnabled = (bool) ($hub['feed_enabled'] ?? true);
    $bundle = is_array($hub['bundle'] ?? null) ? $hub['bundle'] : [];
    $ibgeUfsTotal = (int) ($coverage['ibge_ufs_total'] ?? 27);

    $phaseDefinitions = HorizonteFortnightlyFeedPhaseCatalog::definitions();
    $phaseGroups = HorizonteFortnightlyFeedPhaseCatalog::groups();
    $phasesByGroup = [];
    foreach ($phaseDefinitions as $def) {
        $phasesByGroup[$def['group']][] = $def;
    }

    $toneBorder = static fn (string $tone): string => match ($tone) {
        'amber' => 'border-amber-300 dark:border-amber-700',
        'emerald' => 'border-emerald-300 dark:border-emerald-700',
        'sky' => 'border-sky-300 dark:border-sky-700',
        'rose' => 'border-rose-300 dark:border-rose-700',
        'violet' => 'border-violet-300 dark:border-violet-700',
        'indigo' => 'border-indigo-300 dark:border-indigo-700',
        default => 'border-slate-300 dark:border-slate-600',
    };
    $toneBg = static fn (string $tone): string => 'bg-white dark:bg-slate-800';
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
    @if (! $enabled)
        <x-admin.import-hub.callout variant="info" :title="__('Horizonte desativado')">
            {{ __('Defina HORIZONTE_ENABLED=true para ativar o mapa e este painel.') }}
        </x-admin.import-hub.callout>
    @elseif (is_array($feedFlash))
        <x-admin.import-hub.callout :variant="($feedFlash['success'] ?? false) ? 'success' : 'warning'" :title="($feedFlash['success'] ?? false) ? __('Pipeline iniciado') : __('Pipeline com falhas')">
            {{ $feedFlash['message'] ?? '' }}
            @if ($feedFlash['staged'] ?? false)
                <p class="mt-2 text-xs">{{ __('Modo em etapas — fases restantes continuam pelo agendador ou:') }}</p>
                <code class="mt-1 block text-[10px] font-mono">php artisan horizonte:fortnightly-feed --staged --continue</code>
            @endif
        </x-admin.import-hub.callout>
    @endif

    @if ($enabled && $feedEnabled)
        <form method="POST" action="{{ route('admin.horizonte-import.feed') }}" id="horizonte-feed-form" class="rounded-2xl border border-sky-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4 sm:p-5 space-y-4 shadow-sm">
            @csrf

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <x-ui.icon name="arrow-path" class="h-4 w-4 text-sky-600 dark:text-sky-400" />
                        {{ __('Fases a executar') }}
                    </h4>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 max-w-2xl">
                        {{ __('Marque o que importar neste ciclo. A ordem segue a sequência oficial do feed bimestral.') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 text-[11px]">
                    <button type="button" data-horizonte-phases="all" class="rounded-md border border-sky-300 dark:border-sky-600 bg-sky-50 dark:bg-sky-900 px-2.5 py-1 font-semibold text-sky-900 dark:text-sky-100 hover:bg-sky-100 dark:hover:bg-sky-800">{{ __('Todas') }}</button>
                    <button type="button" data-horizonte-phases="core" class="rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-2.5 py-1 font-semibold text-slate-800 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-700">{{ __('Núcleo triad') }}</button>
                    <button type="button" data-horizonte-phases="none" class="rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-2.5 py-1 font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700">{{ __('Limpar') }}</button>
                </div>
            </div>

            @foreach ($phaseGroups as $groupKey => $groupMeta)
                @if (($phasesByGroup[$groupKey] ?? []) === [])
                    @continue
                @endif
                <div class="space-y-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $groupMeta['label'] }}</p>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400">{{ $groupMeta['description'] }}</p>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($phasesByGroup[$groupKey] as $def)
                            <label class="group flex gap-3 rounded-xl border p-3 cursor-pointer transition {{ $toneBorder($def['tone']) }} {{ $toneBg($def['tone']) }} has-[:checked]:ring-2 has-[:checked]:ring-sky-500 dark:has-[:checked]:ring-sky-400">
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
                                            <span class="rounded px-1 py-0.5 text-[9px] font-bold uppercase bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-100">{{ __('Incremental') }}</span>
                                        @endif
                                    </span>
                                    <span class="mt-1 block text-[11px] text-slate-700 dark:text-slate-300 leading-snug">{{ $def['description'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between pt-2 border-t border-slate-200 dark:border-slate-700">
                <label class="block text-xs text-slate-700 dark:text-slate-300 sm:max-w-xs">
                    <span class="font-medium">{{ __('Restringir a UF (opcional)') }}</span>
                    <select name="uf" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="">{{ __('Nacional — 27 UFs') }}</option>
                        @foreach ($brazilianUfs as $ufOption)
                            <option value="{{ $ufOption }}">{{ $ufOption }}</option>
                        @endforeach
                    </select>
                    <span class="mt-0.5 block text-[10px] text-slate-600 dark:text-slate-400">{{ __('Filtra FUNDEB, Censo, SAEB, IBGE e SGE pela UF escolhida.') }}</span>
                </label>
                <button
                    type="submit"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-sky-700 bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-white dark:border-sky-500 dark:bg-sky-600 dark:hover:bg-sky-500 dark:focus:ring-offset-slate-900"
                >
                    <x-ui.icon name="arrow-path" class="h-4 w-4 text-white" />
                    {{ __('Executar fases selecionadas') }}
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
