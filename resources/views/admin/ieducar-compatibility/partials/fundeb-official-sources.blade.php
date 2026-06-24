@php
    $panel = is_array($fundebOfficialSources ?? null) ? $fundebOfficialSources : [];
    $portarias = is_array($panel['portarias'] ?? null) ? $panel['portarias'] : [];
    $fontes = is_array($panel['fontes'] ?? null) ? $panel['fontes'] : [];
    $updates = is_array($panel['updates'] ?? null) ? $panel['updates'] : [];
@endphp

<section class="rounded-lg border border-sky-200 dark:border-sky-800 bg-sky-50/40 dark:bg-sky-950/25 p-4 space-y-4">
    <div>
        <h4 class="text-sm font-semibold text-sky-950 dark:text-sky-100">{{ __('admin_ieducar_compatibility.official_sources.title') }}</h4>
        <p class="text-xs text-sky-900/90 dark:text-sky-200/90 mt-1 leading-relaxed">
            {{ __('admin_ieducar_compatibility.official_sources.intro') }}
        </p>
        @if (filled($panel['last_import_max'] ?? null))
            <p class="text-[11px] mt-2 text-slate-600 dark:text-slate-400">
                {{ __('admin_ieducar_compatibility.official_sources.last_import') }}
                <span class="font-mono">{{ $panel['last_import_max'] }}</span>
            </p>
        @endif
    </div>

    @if ($portarias !== [])
        <div>
            <p class="text-xs font-medium text-slate-800 dark:text-slate-200 mb-1">{{ __('admin_ieducar_compatibility.official_sources.portarias') }}</p>
            <ul class="text-xs space-y-1">
                @foreach ($portarias as $p)
                    <li>
                        <a href="{{ $p['url'] }}" target="_blank" rel="noopener" class="text-sky-800 dark:text-sky-300 underline">{{ $p['label'] ?? '' }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($fontes !== [])
        <div>
            <p class="text-xs font-medium text-slate-800 dark:text-slate-200 mb-1">{{ __('admin_ieducar_compatibility.official_sources.fontes') }}</p>
            <ul class="text-xs flex flex-wrap gap-x-3 gap-y-1">
                @foreach ($fontes as $f)
                    <li>
                        <a href="{{ $f['url'] }}" target="_blank" rel="noopener" class="underline text-sky-700 dark:text-sky-300">{{ $f['label'] ?? '' }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($updates !== [])
        <div>
            <p class="text-xs font-medium text-slate-800 dark:text-slate-200 mb-2">{{ __('admin_ieducar_compatibility.official_sources.updates') }}</p>
            <ul class="space-y-2 text-xs">
                @foreach ($updates as $u)
                    @php
                        $tone = match ($u['status'] ?? '') {
                            'ok' => 'text-emerald-800 dark:text-emerald-300',
                            'warning' => 'text-amber-800 dark:text-amber-300',
                            default => 'text-slate-700 dark:text-slate-300',
                        };
                    @endphp
                    <li class="rounded-md border border-slate-200/80 dark:border-slate-700 px-3 py-2 bg-white/60 dark:bg-gray-900/40">
                        <span class="font-semibold {{ $tone }}">{{ $u['source'] ?? '' }}</span>
                        <p class="mt-0.5 text-slate-600 dark:text-slate-400">{{ $u['message'] ?? '' }}</p>
                        @if (filled($u['action'] ?? null))
                            <p class="mt-1 text-[11px] italic">{{ $u['action'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
