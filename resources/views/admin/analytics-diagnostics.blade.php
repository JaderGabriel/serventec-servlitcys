<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="serv-eyebrow">{{ __('Diagnóstico') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ __('Painel analítico — teste de erro 500') }}
                </h2>
            </div>
            <a href="{{ route('dashboard.analytics', ['city_id' => $query['city_id'] ?: null, 'ano_letivo' => $query['ano_letivo']]) }}" class="serv-link text-sm">
                {{ __('← Consultoria') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="serv-panel serv-panel--info px-5 py-4 text-sm">
                <p>{{ __('Executa a mesma cadeia do «Aplicar filtros» (ligação BD, filtros, overview, renderização) com tempos e erros. Em dev sem VPN/firewall à BD remota, os passos de BD falham — isso já indica a causa do 500 em local.') }}</p>
            </div>

            <form method="get" action="{{ route('admin.analytics-diagnostics') }}" class="serv-panel p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="city_id" :value="__('Cidade')" />
                        <select id="city_id" name="city_id" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                            <option value="">{{ __('— Selecione —') }}</option>
                            @foreach ($cities as $c)
                                <option value="{{ $c->id }}" @selected((string) $query['city_id'] === (string) $c->id)>{{ $c->name }} ({{ $c->uf }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="ano_letivo" :value="__('Ano letivo')" />
                        <input id="ano_letivo" name="ano_letivo" type="text" value="{{ $query['ano_letivo'] }}" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="hidden" name="skip_slow" value="0" />
                            <input type="checkbox" name="skip_slow" value="1" @checked($query['skip_slow']) class="rounded" />
                            {{ __('Saltar passos lentos (overview/bootstrap)') }}
                        </label>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-primary-button type="submit">{{ __('Executar diagnóstico') }}</x-primary-button>
                    @if ($query['city_id'])
                        <a href="{{ route('admin.analytics-diagnostics', array_merge($query, ['format' => 'json'])) }}" class="serv-btn-secondary text-sm" target="_blank" rel="noopener">{{ __('JSON') }}</a>
                    @endif
                </div>
            </form>

            @if (isset($report))
                @php
                    $summary = $report['summary'] ?? [];
                    $ok = (bool) ($summary['ok'] ?? false);
                @endphp
                <div @class([
                    'rounded-lg border px-5 py-4',
                    'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/40' => $ok,
                    'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/40' => ! $ok,
                ])>
                    <p class="font-semibold text-lg">{{ $summary['headline'] ?? '' }}</p>
                    <p class="mt-1 text-sm opacity-90">
                        {{ __(':failed de :total passos falharam · :ms ms total', [
                            'failed' => $summary['steps_failed'] ?? 0,
                            'total' => $summary['steps_total'] ?? 0,
                            'ms' => number_format($report['total_ms'] ?? 0, 1, ',', '.'),
                        ]) }}
                    </p>
                </div>

                <div class="space-y-3">
                    @foreach ($report['steps'] ?? [] as $step)
                        @php
                            $stepOk = (bool) ($step['ok'] ?? false);
                        @endphp
                        <details class="serv-panel group" @if (! $stepOk) open @endif>
                            <summary class="cursor-pointer px-5 py-3 flex flex-wrap items-center justify-between gap-2 list-none">
                                <span class="font-mono text-sm font-semibold {{ $stepOk ? 'text-teal-800 dark:text-teal-200' : 'text-red-800 dark:text-red-200' }}">
                                    {{ $stepOk ? '✓' : '✗' }} {{ $step['id'] }}
                                </span>
                                <span class="text-xs text-slate-500">{{ $step['ms'] ?? 0 }} ms</span>
                            </summary>
                            <div class="px-5 pb-4 border-t border-slate-100 dark:border-slate-800 text-sm space-y-3">
                                @if (! empty($step['error']))
                                    <pre class="text-xs text-red-800 dark:text-red-200 whitespace-pre-wrap break-all bg-red-50/80 dark:bg-red-950/30 p-3 rounded-lg">{{ $step['error'] }}</pre>
                                @endif
                                @if (! empty($step['exception']))
                                    <p class="text-xs font-mono text-slate-600">{{ $step['exception'] }}</p>
                                @endif
                                @if (! empty($step['data']))
                                    <pre class="text-xs font-mono text-slate-700 dark:text-slate-300 whitespace-pre-wrap break-all bg-slate-50 dark:bg-slate-900/60 p-3 rounded-lg max-h-96 overflow-auto">{{ json_encode($step['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                @endif
                                @if (! empty($step['trace']))
                                    <details>
                                        <summary class="text-xs cursor-pointer text-slate-500">{{ __('Stack trace') }}</summary>
                                        <pre class="mt-2 text-[10px] font-mono whitespace-pre-wrap break-all max-h-64 overflow-auto">{{ $step['trace'] }}</pre>
                                    </details>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
