@php
    use App\Support\Admin\AdminImportHubCatalog;
    use App\Support\Brazil\IbgeMunicipalityCatalog;

    $hub = is_array($horizonteHub ?? null) ? $horizonteHub : [];
    $brazilianUfs = IbgeMunicipalityCatalog::brazilianUfs();
    $coverage = is_array($hub['coverage'] ?? null) ? $hub['coverage'] : [];
    $phases = is_array($hub['phases'] ?? null) ? $hub['phases'] : [];
    $lastFeed = is_array($hub['last_feed'] ?? null) ? $hub['last_feed'] : null;
    $feedFlash = session('horizonte_feed');
    $pipeline = is_array($hub['pipeline'] ?? null) ? $hub['pipeline'] : (is_array($feedFlash['pipeline'] ?? null) ? $feedFlash['pipeline'] : null);
    $scheduleSummary = (string) ($hub['schedule_summary'] ?? __('Bimestral — dia 1 às 03:00 (meses 01, 03, 05, 07, 09, 11)'));
    $enabled = (bool) ($hub['enabled'] ?? true);
    $feedEnabled = (bool) ($hub['feed_enabled'] ?? true);
    $bundle = is_array($hub['bundle'] ?? null) ? $hub['bundle'] : [];
    $ibgeUfsTotal = (int) ($coverage['ibge_ufs_total'] ?? 27);
@endphp

<section id="horizonte-hub" class="scroll-mt-24 space-y-4">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                {{ __('Horizonte — abastecimento nacional') }}
            </h3>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400 max-w-3xl leading-relaxed">
                {{ __('O mapa de oportunidade usa FUNDEB, Censo, SAEB, CadÚnico, SIDRA, repasses Tesouro e catálogo IBGE. Em produção a rotina corre em etapas (1 fase por vez; IBGE/SIDRA aquecem UFs incrementalmente).') }}
            </p>
            @if ($feedEnabled && ($hub['schedule_enabled'] ?? true))
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-500">
                    {{ __('Agendamento: :summary (horizonte:fortnightly-feed).', [
                        'summary' => $scheduleSummary,
                    ]) }}
                    @if ($lastFeed && filled($lastFeed['finished_at'] ?? null))
                        · {{ __('Última execução: :when', [
                            'when' => \Illuminate\Support\Carbon::parse($lastFeed['finished_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                        ]) }}
                    @endif
                </p>
            @endif
            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                <a href="{{ $hub['map_url'] ?? route('dashboard.horizonte') }}" class="font-medium text-indigo-700 dark:text-indigo-300 hover:underline">{{ __('Abrir mapa Horizonte') }} →</a>
                <a href="{{ $hub['doc_url'] ?? '#' }}" class="text-gray-500 hover:underline">{{ __('Documentação') }}</a>
            </div>
        </div>

        @if ($enabled && $feedEnabled)
            <form
                method="POST"
                action="{{ route('admin.public-data.horizonte-feed') }}"
                class="shrink-0 rounded-xl border border-indigo-200/90 bg-indigo-50/50 px-4 py-3 dark:border-indigo-900/60 dark:bg-indigo-950/30 space-y-2 max-w-xs w-full"
            >
                @csrf
                <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-800 dark:text-indigo-200">{{ __('Executar agora') }}</p>
                <p class="text-[11px] text-indigo-900/80 dark:text-indigo-200/80">{{ __('Inicia o pipeline em etapas. IBGE e fases pesadas continuam com --continue ou pelo agendador.') }}</p>
                <label class="block text-[11px] text-gray-700 dark:text-gray-300">
                    <span class="font-medium">{{ __('UF (opcional)') }}</span>
                    <select name="uf" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="">{{ __('Nacional (27 UFs)') }}</option>
                        @foreach ($brazilianUfs as $ufOption)
                            <option value="{{ $ufOption }}">{{ $ufOption }}</option>
                        @endforeach
                    </select>
                    <span class="mt-0.5 block text-[10px] text-gray-500 dark:text-gray-400">{{ __('Restringe FUNDEB, Censo, SAEB, IBGE e SGE à UF escolhida.') }}</span>
                </label>
                <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-[11px] text-gray-700 dark:text-gray-300">
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="skip_fundeb" value="1" class="rounded border-gray-300 text-indigo-600" /> {{ __('Ignorar FUNDEB') }}</label>
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="skip_censo" value="1" class="rounded border-gray-300 text-indigo-600" /> {{ __('Ignorar Censo') }}</label>
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="skip_cadunico" value="1" class="rounded border-gray-300 text-indigo-600" /> {{ __('Ignorar CadÚnico') }}</label>
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="skip_sidra" value="1" class="rounded border-gray-300 text-indigo-600" /> {{ __('Ignorar SIDRA') }}</label>
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="skip_repasses" value="1" class="rounded border-gray-300 text-indigo-600" /> {{ __('Ignorar repasses') }}</label>
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="skip_saeb" value="1" class="rounded border-gray-300 text-indigo-600" /> {{ __('Ignorar SAEB') }}</label>
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="skip_ibge" value="1" class="rounded border-gray-300 text-indigo-600" /> {{ __('Ignorar IBGE') }}</label>
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="skip_sge" value="1" class="rounded border-gray-300 text-indigo-600" /> {{ __('Ignorar SGE') }}</label>
                    <label class="col-span-2 flex items-center gap-1.5"><input type="checkbox" name="skip_verify" value="1" class="rounded border-gray-300 text-indigo-600" /> {{ __('Ignorar verificação oficial') }}</label>
                </div>
                <button
                    type="submit"
                    class="mt-1 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                >
                    <x-ui.icon name="arrow-path" class="h-3.5 w-3.5" />
                    {{ __('Abastecer Horizonte') }}
                </button>
            </form>
        @endif
    </div>

    @if (! $enabled)
        <x-admin.import-hub.callout variant="info" :title="__('Horizonte desactivado')">
            {{ __('Defina HORIZONTE_ENABLED=true para activar o mapa e este painel.') }}
        </x-admin.import-hub.callout>
    @elseif (is_array($feedFlash))
        <x-admin.import-hub.callout :variant="($feedFlash['success'] ?? false) ? 'success' : 'warning'" :title="($feedFlash['success'] ?? false) ? __('Abastecimento concluído') : __('Abastecimento com falhas')">
            {{ $feedFlash['message'] ?? '' }}
            @if ($feedFlash['staged'] ?? false)
                <p class="mt-2 text-xs opacity-90">{{ __('Modo em etapas — fases restantes continuam pelo agendador ou:') }}</p>
                <code class="mt-1 block text-[10px] font-mono opacity-90">php artisan horizonte:fortnightly-feed --staged --continue</code>
            @endif
        </x-admin.import-hub.callout>
    @endif

    @if ($pipeline !== null)
        @include('admin.public-data.partials.horizonte-feed-pipeline', [
            'pipeline' => $pipeline,
            'stepInterval' => $hub['feed_step_interval'] ?? 20,
        ])
    @endif

    <x-admin.import-hub.stats-grid columns="sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <x-admin.import-hub.stat label="FUNDEB" :value="number_format((int) ($coverage['fundeb_municipios'] ?? 0))" :hint="__('IBGE nacionais')" tone="amber" />
        <x-admin.import-hub.stat label="Censo" :value="number_format((int) ($coverage['censo_municipios'] ?? 0))" :hint="__('municípios indexados')" tone="emerald" />
        <x-admin.import-hub.stat label="SAEB" :value="number_format((int) ($coverage['saeb_municipios'] ?? 0))" :hint="__('municípios com indicadores')" tone="violet" />
        <x-admin.import-hub.stat :label="__('Triad completa')" :value="number_format((int) ($coverage['with_full_triad'] ?? 0))" :hint="__('FUNDEB + Censo + SAEB')" tone="sky" />
        <x-admin.import-hub.stat label="CadÚnico" :value="number_format((int) ($coverage['cadunico_municipios'] ?? 0))" :hint="__('agregados municipais')" tone="rose" />
        <x-admin.import-hub.stat label="SIDRA" :value="number_format((int) ($coverage['demography_municipios'] ?? 0))" :hint="__('pop. 4–17')" tone="slate" />
        <x-admin.import-hub.stat label="IBGE" :value="((int) ($coverage['ibge_ufs_warmed'] ?? 0)).'/'.$ibgeUfsTotal" :hint="__('UFs com catálogo')" tone="sky" />
    </x-admin.import-hub.stats-grid>

    <section id="horizonte-offline-bundle" class="scroll-mt-24 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40 p-4 space-y-3">
        <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Transferência offline (local → produção)') }}</h4>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 max-w-3xl">
                {{ __('Processe o feed numa máquina com RAM suficiente, exporte um ZIP e importe em produção sem passar pelo git.') }}
            </p>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route('admin.public-data.horizonte-bundle-export') }}" class="rounded-lg border border-slate-200/80 dark:border-slate-700 bg-white/80 dark:bg-slate-900/50 p-3 space-y-2">
                @csrf
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Exportar pacote (servidor actual)') }}</p>
                <div class="grid grid-cols-2 gap-1 text-[10px] text-slate-600 dark:text-slate-300">
                    @foreach (['fundeb' => 'FUNDEB', 'censo' => 'Censo', 'saeb' => 'SAEB', 'cadunico' => 'CadÚnico', 'demography' => 'SIDRA', 'transfers' => 'Repasses', 'ibge_cache' => 'IBGE cache', 'sge_registry' => 'SGE'] as $key => $label)
                        <label class="inline-flex items-center gap-1"><input type="checkbox" name="section_{{ $key }}" value="1" checked class="rounded border-gray-300 text-indigo-600" /> {{ $label }}</label>
                    @endforeach
                </div>
                <button type="submit" class="mt-2 inline-flex items-center rounded-md bg-slate-800 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-slate-700">{{ __('Gerar ZIP') }}</button>
                <code class="block rounded bg-slate-100 px-2 py-1 text-[10px] text-slate-700 dark:bg-slate-800 dark:text-slate-300">php artisan horizonte:export-data-bundle</code>
            </form>
            <form method="POST" action="{{ route('admin.public-data.horizonte-bundle-import') }}" enctype="multipart/form-data" class="rounded-lg border border-slate-200/80 dark:border-slate-700 bg-white/80 dark:bg-slate-900/50 p-3 space-y-2">
                @csrf
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Importar pacote (upload)') }}</p>
                <input type="file" name="bundle" accept=".zip,application/zip" required class="block w-full text-[11px] text-slate-700 file:mr-2 file:rounded file:border-0 file:bg-indigo-50 file:px-2 file:py-1 file:text-indigo-700 dark:text-slate-200" />
                <label class="inline-flex items-center gap-1 text-[10px] text-slate-600"><input type="checkbox" name="dry_run" value="1" class="rounded border-gray-300" /> {{ __('Dry-run (contar apenas)') }}</label>
                <button type="submit" class="mt-2 inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-indigo-500">{{ __('Importar ZIP') }}</button>
                <code class="block rounded bg-slate-100 px-2 py-1 text-[10px] text-slate-700 dark:bg-slate-800 dark:text-slate-300">php artisan horizonte:import-data-bundle …</code>
            </form>
        </div>
        @if (session('horizonte_bundle'))
            <x-admin.import-hub.callout :variant="(session('horizonte_bundle.success') ?? false) ? 'success' : 'warning'" :title="__('Pacote Horizonte')">
                {{ session('horizonte_bundle.message') ?? '' }}
            </x-admin.import-hub.callout>
        @endif
        @if ($bundle['latest_exists'] ?? false)
            <p class="text-[11px] text-emerald-800 dark:text-emerald-200">
                {{ __('Pacote latest.zip disponível') }}
                @if (filled($bundle['latest_updated_at'] ?? null))
                    · {{ \Illuminate\Support\Carbon::createFromTimestamp((int) $bundle['latest_updated_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                @endif
                @if (filled($bundle['latest_size'] ?? null))
                    · {{ number_format(((int) $bundle['latest_size']) / 1024 / 1024, 1) }} MB
                @endif
            </p>
        @else
            <p class="text-[11px] text-slate-500">{{ __('Nenhum pacote latest.zip em storage/app/horizonte/bundles/') }}</p>
        @endif
    </section>

    @if (! ($coverage['microdados_ok'] ?? false))
        <x-admin.import-hub.callout variant="warning" :title="__('Microdados Censo em falta')">
            {{ __('A fase Censo da rotina Horizonte requer o CSV Educacenso em storage — use Geo / pipeline ou importação manual antes do abastecimento.') }}
        </x-admin.import-hub.callout>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-xs">
                <thead class="bg-gray-50/90 text-[11px] uppercase tracking-wide text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Fase') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Cobertura') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Hub / rotina') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('CLI') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($phases as $phase)
                        @php
                            $ok = (bool) ($phase['ok'] ?? false);
                            $level = $ok ? 'ok' : (filled($phase['blocked'] ?? null) ? 'warn' : 'partial');
                            $badgeClass = AdminImportHubCatalog::statusBadgeClasses()[$level]
                                ?? AdminImportHubCatalog::statusBadgeClasses()['neutral'];
                            $hubLink = filled($phase['hub_anchor'] ?? null)
                                ? route('admin.public-data.index').($phase['hub_anchor'] ?? '')
                                : ($phase['admin_url'] ?? '#');
                        @endphp
                        <tr class="bg-white dark:bg-gray-900/40">
                            <td class="px-4 py-3 align-top">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $phase['label'] ?? '' }}</p>
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ $phase['description'] ?? '' }}</p>
                                @if (filled($phase['blocked'] ?? null))
                                    <p class="mt-1 text-[11px] font-medium text-amber-800 dark:text-amber-200">{{ $phase['blocked'] }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badgeClass }}">
                                    {{ $ok ? __('OK') : __('Pendente') }}
                                </span>
                                @if (($phase['metric'] ?? null) !== null)
                                    <p class="mt-1 tabular-nums text-gray-700 dark:text-gray-300">
                                        {{ number_format((int) $phase['metric']) }}
                                        <span class="text-gray-500">{{ $phase['metric_label'] ?? '' }}</span>
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                <a href="{{ $hubLink }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ $phase['routine_label'] ?? __('Ver fonte no hub') }}
                                </a>
                                @if (filled($phase['admin_url'] ?? null) && ($phase['admin_url'] ?? '') !== $hubLink)
                                    · <a href="{{ $phase['admin_url'] }}" class="text-gray-500 hover:underline">{{ __('Painel') }}</a>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                @if (filled($phase['cli'] ?? null))
                                    <code class="block rounded bg-gray-100 px-2 py-1 text-[10px] text-gray-800 dark:bg-gray-800 dark:text-gray-200">{{ $phase['cli'] }}</code>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
