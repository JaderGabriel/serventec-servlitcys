@php
    $hub = is_array($horizonteHub ?? null) ? $horizonteHub : [];
    $coverage = is_array($hub['coverage'] ?? null) ? $hub['coverage'] : [];
    $stepsDone = (int) ($coverage['educacenso_steps_done'] ?? $hub['educacenso_steps_done'] ?? 0);
    $stepsTotal = max(1, (int) ($coverage['educacenso_steps_total'] ?? $hub['educacenso_steps_total'] ?? 1));
    $recentSteps = is_array($hub['educacenso_recent_steps'] ?? null) ? $hub['educacenso_recent_steps'] : [];
    $flash = session('horizonte_educacenso_sync');
    $completedFlash = is_array($flash['completed_steps'] ?? null) ? $flash['completed_steps'] : [];
    $pct = min(100, max(0, (int) round(($stepsDone / $stepsTotal) * 100)));
@endphp

<section id="horizonte-educacenso-sync" class="scroll-mt-24 rounded-xl border border-teal-200/80 bg-teal-50/40 dark:border-teal-900/50 dark:bg-teal-950/20 p-4 space-y-3">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h4 class="text-sm font-semibold text-teal-950 dark:text-teal-100">{{ __('Educacenso — reimportação ano × UF') }}</h4>
            <p class="mt-1 text-xs text-teal-900/80 dark:text-teal-200/80 max-w-3xl">
                {{ __('Necessário para o gráfico de matrículas (segmentos, etapas e filtro Municipal/Não municipal). Cada passo indexa uma UF num ano da janela.') }}
            </p>
        </div>
        <div class="text-right text-xs tabular-nums text-teal-800 dark:text-teal-200">
            <span class="font-semibold">{{ $stepsDone }}/{{ $stepsTotal }}</span>
            <span class="text-teal-700/70 dark:text-teal-300/70">{{ __('passos') }}</span>
        </div>
    </div>

    <div>
        <div class="flex justify-between text-[10px] text-teal-800/80 dark:text-teal-200/80 mb-1">
            <span>{{ __('Progresso nacional') }}</span>
            <span>{{ $pct }}%</span>
        </div>
        <div class="h-2 rounded-full bg-teal-100 dark:bg-teal-950/60 overflow-hidden">
            <div class="h-full rounded-full bg-teal-500 transition-all" style="width: {{ $pct }}%"></div>
        </div>
    </div>

    @if (is_array($flash))
        <x-admin.import-hub.callout :variant="($flash['success'] ?? false) ? 'success' : 'warning'" :title="($flash['success'] ?? false) ? __('Passo(s) concluído(s)') : __('Educacenso — atenção')">
            <p>{{ $flash['message'] ?? '' }}</p>
            @if ($completedFlash !== [])
                <ul class="mt-2 space-y-0.5 text-xs font-mono">
                    @foreach ($completedFlash as $step)
                        <li class="text-emerald-800 dark:text-emerald-200">
                            ✓ {{ (int) ($step['year'] ?? 0) }} / {{ $step['uf'] ?? '—' }}
                            — {{ number_format((int) ($step['indexed'] ?? 0), 0, ',', '.') }} {{ __('municípios') }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-admin.import-hub.callout>
    @endif

    @if ($recentSteps !== [])
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-teal-800/70 dark:text-teal-300/80 mb-1.5">{{ __('Últimos passos concluídos') }}</p>
            <ul class="flex flex-wrap gap-1.5">
                @foreach ($recentSteps as $step)
                    <li class="inline-flex items-center rounded-full bg-white/80 dark:bg-slate-900/50 border border-teal-200/70 dark:border-teal-900/50 px-2 py-0.5 text-[10px] font-mono text-teal-900 dark:text-teal-100">
                        {{ (int) ($step['year'] ?? 0) }}/{{ $step['uf'] ?? '—' }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($enabled ?? true)
        <form method="POST" action="{{ route('admin.public-data.horizonte-educacenso-sync') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
            @csrf
            <label class="block text-[11px] text-gray-700 dark:text-gray-300">
                <span class="font-medium">{{ __('Passos por clique') }}</span>
                <input type="number" name="steps" min="1" max="27" value="{{ (int) ($hub['educacenso_steps_per_step'] ?? 1) }}" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm dark:border-gray-600 dark:bg-gray-800" />
            </label>
            <label class="block text-[11px] text-gray-700 dark:text-gray-300">
                <span class="font-medium">{{ __('Ano (opcional)') }}</span>
                <input type="number" name="year" placeholder="2024" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm dark:border-gray-600 dark:bg-gray-800" />
            </label>
            <label class="block text-[11px] text-gray-700 dark:text-gray-300">
                <span class="font-medium">{{ __('UF (opcional)') }}</span>
                <select name="uf" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">{{ __('Todas') }}</option>
                    @foreach (\App\Support\Brazil\IbgeMunicipalityCatalog::brazilianUfs() as $ufOption)
                        <option value="{{ $ufOption }}">{{ $ufOption }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex flex-col gap-2">
                <label class="inline-flex items-center gap-1.5 text-[11px] text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="reset" value="1" class="rounded border-gray-300 text-teal-600" />
                    {{ __('Reiniciar progresso') }}
                </label>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-500">
                    {{ __('Executar próximo(s) passo(s)') }}
                </button>
            </div>
        </form>
        <code class="block rounded bg-white/70 dark:bg-slate-900/50 px-2 py-1.5 text-[10px] text-teal-900 dark:text-teal-100 font-mono">php artisan horizonte:sync-educacenso --reset --all</code>
    @endif
</section>
