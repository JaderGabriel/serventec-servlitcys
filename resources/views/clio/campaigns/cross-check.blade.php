<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Clio') }} · {{ __('Cruzamento i-Educar') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ $campaign->municipality_name }} — {{ $campaign->year }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ __('INF-GAP: escolas só na campanha, só no i-Educar, ou em ambos (read-only).') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('analyze', $campaign)
                    @if ($canRun)
                        <form method="post" action="{{ route('clio.campaigns.cross-check.run', $campaign) }}">
                            @csrf
                            <button type="submit" class="serv-btn-primary text-sm">{{ __('Correr cruzamento') }}</button>
                        </form>
                    @elseif (Auth::user()->can('linkConsultancy', $campaign))
                        <a href="{{ route('clio.campaigns.link', $campaign) }}" class="serv-btn-primary text-sm">{{ __('Vincular i-Educar primeiro') }}</a>
                    @endif
                @endcan
                <a href="{{ route('clio.campaigns.analysis', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Painel') }}</a>
                <a href="{{ route('clio.campaigns.show', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Hub') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('warning'))
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                    {{ session('warning') }}
                </div>
            @endif

            @if ($gap)
                <div class="serv-panel p-5">
                    <p class="serv-eyebrow">INF-GAP</p>
                    <p class="mt-1 font-medium text-serv-navy dark:text-white">{{ $gap->summary }}</p>
                    @php $p = $gap->payload ?? []; @endphp
                    <dl class="mt-4 grid gap-3 sm:grid-cols-4 text-sm">
                        <div><dt class="text-xs text-slate-500">{{ __('Em ambos') }}</dt><dd class="tabular-nums font-semibold">{{ $p['matched'] ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">{{ __('Só Clio') }}</dt><dd class="tabular-nums font-semibold">{{ $p['only_in_clio'] ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">{{ __('Só i-Educar') }}</dt><dd class="tabular-nums font-semibold">{{ $p['only_in_ieducar'] ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">{{ __('Matrículas i-Educar') }}</dt><dd class="tabular-nums font-semibold">{{ $p['ieducar_matriculas'] ?? '—' }}</dd></div>
                    </dl>
                </div>
            @else
                <div class="serv-panel p-6 text-sm text-slate-600 dark:text-slate-300">
                    {{ $canRun
                        ? __('Ainda sem cruzamento. Clique em «Correr cruzamento».')
                        : __('Vincule as credenciais i-Educar do município para activar o INF-GAP.') }}
                </div>
            @endif

            <section class="serv-panel overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h3 class="font-medium text-serv-navy dark:text-white">{{ __('Achados de gap') }}</h3>
                </div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($findings as $finding)
                        <li class="px-4 py-3 text-sm">
                            <span class="font-mono text-xs text-slate-500">{{ $finding->code }}</span>
                            @if (! empty($finding->meta['inep']))
                                <span class="font-mono text-xs">· {{ $finding->meta['inep'] }}</span>
                            @endif
                            <p class="mt-0.5">{{ $finding->message }}</p>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-slate-500 text-sm">{{ __('Nenhum achado de gap.') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
