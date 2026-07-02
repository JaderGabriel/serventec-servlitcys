@php
    $hub = is_array($horizonteHub ?? null) ? $horizonteHub : [];
    $coverage = is_array($hub['coverage'] ?? null) ? $hub['coverage'] : [];
    $ufsDone = (int) ($coverage['municipal_geo_ufs_done'] ?? $hub['municipal_geo_ufs_done'] ?? 0);
    $ufsTotal = max(1, (int) ($coverage['municipal_geo_ufs_total'] ?? $hub['municipal_geo_ufs_total'] ?? 27));
    $areaCount = (int) ($coverage['municipal_area_municipios'] ?? $hub['municipal_area_municipios'] ?? 0);
    $recentSteps = is_array($hub['municipal_geo_recent_steps'] ?? null) ? $hub['municipal_geo_recent_steps'] : [];
    $flash = session('horizonte_municipal_geo_sync');
    $completedFlash = is_array($flash['completed_steps'] ?? null) ? $flash['completed_steps'] : [];
    $pct = min(100, max(0, (int) round(($ufsDone / $ufsTotal) * 100)));
@endphp

<section id="horizonte-municipal-geo-sync" class="scroll-mt-24 rounded-xl border border-indigo-200/80 bg-indigo-50/40 dark:border-indigo-900/50 dark:bg-indigo-950/20 p-4 space-y-3">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h4 class="text-sm font-semibold text-indigo-950 dark:text-indigo-100">{{ __('Área geográfica municipal — malha IBGE') }}</h4>
            <p class="mt-1 text-xs text-indigo-900/80 dark:text-indigo-200/80 max-w-3xl">
                {{ __('Importação nacional por UF: polígonos municipais (contornos no mapa Horizonte) e área territorial km² por município.') }}
            </p>
        </div>
        <div class="text-right text-xs tabular-nums text-indigo-800 dark:text-indigo-200">
            <span class="font-semibold">{{ $ufsDone }}/{{ $ufsTotal }}</span>
            <span class="text-indigo-700/70 dark:text-indigo-300/70">{{ __('UFs') }}</span>
            @if ($areaCount > 0)
                <p class="mt-0.5 text-[10px] text-indigo-700/80 dark:text-indigo-300/80">{{ number_format($areaCount, 0, ',', '.') }} {{ __('municípios com área') }}</p>
            @endif
        </div>
    </div>

    <div>
        <div class="flex justify-between text-[10px] text-indigo-800/80 dark:text-indigo-200/80 mb-1">
            <span>{{ __('Progresso nacional') }}</span>
            <span>{{ $pct }}%</span>
        </div>
        <div class="h-2 rounded-full bg-indigo-100 dark:bg-indigo-950/60 overflow-hidden">
            <div class="h-full rounded-full bg-indigo-500 transition-all" style="width: {{ $pct }}%"></div>
        </div>
    </div>

    @if (is_array($flash))
        <x-admin.import-hub.callout :variant="($flash['success'] ?? false) ? 'success' : 'warning'" :title="($flash['success'] ?? false) ? __('Passo(s) concluído(s)') : __('Malha municipal — atenção')">
            <p>{{ $flash['message'] ?? '' }}</p>
            @if ($completedFlash !== [])
                <ul class="mt-2 space-y-0.5 text-xs font-mono">
                    @foreach ($completedFlash as $step)
                        <li class="text-emerald-800 dark:text-emerald-200">
                            ✓ {{ $step['uf'] ?? '—' }}
                            — {{ number_format((int) ($step['features'] ?? 0), 0, ',', '.') }} {{ __('polígonos') }}
                            · {{ number_format((int) ($step['imported'] ?? 0), 0, ',', '.') }} {{ __('áreas km²') }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-admin.import-hub.callout>
    @endif

    @if ($recentSteps !== [])
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-800/70 dark:text-indigo-300/80 mb-1.5">{{ __('Últimas UFs importadas') }}</p>
            <ul class="flex flex-wrap gap-1.5">
                @foreach ($recentSteps as $step)
                    <li class="inline-flex items-center rounded-full bg-white/80 dark:bg-slate-900/50 border border-indigo-200/70 dark:border-indigo-900/50 px-2 py-0.5 text-[10px] font-mono text-indigo-900 dark:text-indigo-100">
                        {{ $step['uf'] ?? '—' }}
                        <span class="ml-1 text-indigo-600/80">{{ number_format((int) ($step['imported'] ?? 0), 0, ',', '.') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($enabled ?? true)
        <form method="POST" action="{{ route('admin.public-data.horizonte-municipal-geo-sync') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
            @csrf
            <label class="block text-[11px] text-gray-700 dark:text-gray-300">
                <span class="font-medium">{{ __('UFs por clique') }}</span>
                <input type="number" name="ufs_per_step" min="1" max="3" value="{{ (int) config('horizonte.municipal_geo.ufs_per_step', 1) }}" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm dark:border-gray-600 dark:bg-gray-800" />
            </label>
            <label class="block text-[11px] text-gray-700 dark:text-gray-300">
                <span class="font-medium">{{ __('UF (opcional)') }}</span>
                <select name="uf" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">{{ __('Todas pendentes') }}</option>
                    @foreach (\App\Support\Brazil\IbgeMunicipalityCatalog::brazilianUfs() as $ufOption)
                        <option value="{{ $ufOption }}">{{ $ufOption }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex flex-col gap-2">
                <label class="inline-flex items-center gap-1.5 text-[11px] text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="force" value="1" class="rounded border-gray-300 text-indigo-600" />
                    {{ __('Rebuscar malha IBGE') }}
                </label>
                <label class="inline-flex items-center gap-1.5 text-[11px] text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="reset" value="1" class="rounded border-gray-300 text-indigo-600" />
                    {{ __('Limpar histórico recente') }}
                </label>
            </div>
            <div class="flex flex-col gap-2">
                <button type="submit" name="mode" value="step" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500">
                    {{ __('Executar próximo(s) passo(s)') }}
                </button>
                <button type="submit" name="mode" value="all" class="inline-flex items-center justify-center rounded-lg border border-indigo-300 bg-white/80 px-3 py-2 text-xs font-semibold text-indigo-800 hover:bg-indigo-50 dark:border-indigo-800 dark:bg-slate-900/50 dark:text-indigo-100">
                    {{ __('Importar nacional (--all)') }}
                </button>
            </div>
        </form>
        <code class="block rounded bg-white/70 dark:bg-slate-900/50 px-2 py-1.5 text-[10px] text-indigo-900 dark:text-indigo-100 font-mono">php artisan horizonte:import-municipal-geo --all</code>
    @endif
</section>
