@props([
    'examples' => [],
    'commands' => [],
    'title' => null,
])

@php
    $examples = is_array($examples) ? $examples : [];
    $commands = is_array($commands) ? $commands : [];
@endphp

@if ($examples !== [] || $commands !== [])
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 px-3 py-2.5 space-y-2']) }}>
        @if (filled($title))
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">{{ $title }}</p>
        @else
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">{{ __('Comandos CLI') }}</p>
        @endif
        <ul class="space-y-2">
            @foreach ($examples as $example)
                @php
                    $cmd = is_array($example) ? (string) ($example['command'] ?? '') : (string) $example;
                    $summary = is_array($example) ? ($example['summary'] ?? null) : null;
                @endphp
                @if ($cmd !== '')
                    <li>
                        @if (filled($summary))
                            <p class="text-[11px] text-slate-600 dark:text-slate-400 mb-0.5">{{ $summary }}</p>
                        @endif
                        <code class="block rounded border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-2 py-1.5 text-[10px] font-mono text-slate-800 dark:text-slate-100 break-all">{{ $cmd }}</code>
                    </li>
                @endif
            @endforeach
            @foreach ($commands as $command)
                @if (filled($command))
                    <li>
                        <code class="block rounded border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-2 py-1.5 text-[10px] font-mono text-slate-800 dark:text-slate-100 break-all">php artisan {{ $command }}</code>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
@endif
