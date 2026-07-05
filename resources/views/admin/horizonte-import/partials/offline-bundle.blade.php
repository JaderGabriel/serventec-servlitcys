@php
    $bundle = is_array($bundle ?? null) ? $bundle : [];
@endphp

<section id="horizonte-offline-bundle" class="scroll-mt-24 rounded-xl border border-indigo-200/80 dark:border-indigo-900/50 bg-indigo-50/30 dark:bg-indigo-950/20 p-4 space-y-3">
    <div>
        <h4 class="text-sm font-semibold text-indigo-950 dark:text-indigo-100 flex items-center gap-2">
            <x-ui.icon name="arrow-down-tray" class="h-4 w-4" />
            {{ __('Transferência offline (local → produção)') }}
        </h4>
        <p class="mt-1 text-xs text-indigo-900/80 dark:text-indigo-200/80 max-w-3xl">
            {{ __('Processe o feed numa máquina com RAM suficiente, exporte um ZIP e importe em produção sem passar pelo git.') }}
        </p>
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.horizonte-import.bundle-export') }}" class="rounded-lg border border-indigo-200/60 dark:border-indigo-900/40 bg-white/80 dark:bg-slate-900/50 p-3 space-y-2">
            @csrf
            <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-300">{{ __('Exportar pacote') }}</p>
            <div class="grid grid-cols-2 gap-1 text-[10px] text-slate-600 dark:text-slate-300">
                @foreach (['fundeb' => 'FUNDEB', 'censo' => 'Censo', 'saeb' => 'SAEB', 'cadunico' => 'CadÚnico', 'demography' => 'SIDRA', 'transfers' => 'Repasses', 'ibge_cache' => 'IBGE cache', 'sge_registry' => 'SGE'] as $key => $label)
                    <label class="inline-flex items-center gap-1"><input type="checkbox" name="section_{{ $key }}" value="1" checked class="rounded border-gray-300 text-indigo-600" /> {{ $label }}</label>
                @endforeach
            </div>
            <button type="submit" class="mt-2 inline-flex items-center rounded-md bg-indigo-700 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-indigo-600">{{ __('Gerar ZIP') }}</button>
            <code class="block rounded bg-slate-100 px-2 py-1 text-[10px] text-slate-700 dark:bg-slate-800 dark:text-slate-300">php artisan horizonte:export-data-bundle</code>
        </form>
        <form method="POST" action="{{ route('admin.horizonte-import.bundle-import') }}" enctype="multipart/form-data" class="rounded-lg border border-indigo-200/60 dark:border-indigo-900/40 bg-white/80 dark:bg-slate-900/50 p-3 space-y-2">
            @csrf
            <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-300">{{ __('Importar pacote') }}</p>
            <input type="file" name="bundle" accept=".zip,application/zip" required class="block w-full text-[11px] text-slate-700 file:mr-2 file:rounded file:border-0 file:bg-indigo-50 file:px-2 file:py-1 file:text-indigo-700 dark:text-slate-200" />
            <label class="inline-flex items-center gap-1 text-[10px] text-slate-600"><input type="checkbox" name="dry_run" value="1" class="rounded border-gray-300" /> {{ __('Dry-run (contar apenas)') }}</label>
            <button type="submit" class="mt-2 inline-flex items-center rounded-md bg-sky-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-sky-500">{{ __('Importar ZIP') }}</button>
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
