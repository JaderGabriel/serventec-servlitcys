@php
    use App\Support\Admin\AdminImportHubCatalog;

    $report = is_array($officialCheck ?? null) ? $officialCheck : null;
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : null;
    $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
    $checkedAt = filled($report['checked_at'] ?? null)
        ? \Illuminate\Support\Carbon::parse($report['checked_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i')
        : null;
    $scheduleTime = $officialCheckScheduleTime ?? '07:00';
    $enabled = (bool) ($officialCheckEnabled ?? true);
@endphp

<section id="verificacao-oficial" class="scroll-mt-24 space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                {{ __('Verificação de fontes oficiais') }}
            </h3>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400 max-w-2xl leading-relaxed">
                {{ __('Consulta read-only FNDE, INEP, MDS/Cecad, Tesouro e SAEB — compara com a cobertura local e indica rotinas de importação (não importa automaticamente).') }}
            </p>
            @if ($enabled)
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-500">
                    {{ __('Agendamento diário às :time (cron + schedule:run).', ['time' => $scheduleTime]) }}
                    @if ($checkedAt)
                        · {{ __('Última verificação: :when', ['when' => $checkedAt]) }}
                    @else
                        · {{ __('Ainda sem verificação registada — execute abaixo ou aguarde o agendamento.') }}
                    @endif
                </p>
            @endif
        </div>

        @if ($enabled)
            <form
                method="POST"
                action="{{ route('admin.public-data.check-official') }}"
                class="shrink-0 rounded-xl border border-gray-200/90 bg-gray-50/80 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/50"
            >
                @csrf
                <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                    <input
                        type="checkbox"
                        name="notify"
                        value="1"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900"
                    />
                    {{ __('Notificar administradores') }}
                </label>
                <button
                    type="submit"
                    class="mt-2 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                >
                    <x-ui.icon name="arrow-path" class="h-3.5 w-3.5" />
                    {{ __('Verificar agora') }}
                </button>
            </form>
        @endif
    </div>

    @if (! $enabled)
        <x-admin.import-hub.callout variant="info" :title="__('Verificação desactivada')">
            {{ __('Defina PUBLIC_DATA_DAILY_CHECK_ENABLED=true para activar o comando public-data:check-official e este painel.') }}
        </x-admin.import-hub.callout>
    @elseif ($report === null)
        <x-admin.import-hub.callout variant="info" :title="__('Sem relatório recente')">
            {{ __('Use «Verificar agora» para consultar as fontes oficiais ou aguarde a rotina diária.') }}
        </x-admin.import-hub.callout>
    @else
        <x-admin.import-hub.callout :variant="$summary['variant'] ?? 'info'" :title="$summary['headline'] ?? __('Resumo da verificação')">
            <div class="flex flex-wrap gap-3 text-[11px] font-medium">
                <span class="text-emerald-700 dark:text-emerald-300">{{ __('Sem novidade: :n', ['n' => (int) ($summary['ok'] ?? 0)]) }}</span>
                <span class="text-amber-700 dark:text-amber-300">{{ __('Atenção: :n', ['n' => (int) ($summary['attention'] ?? 0)]) }}</span>
                <span class="text-violet-700 dark:text-violet-300">{{ __('Novidades: :n', ['n' => (int) ($summary['news'] ?? 0)]) }}</span>
            </div>
        </x-admin.import-hub.callout>

        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="bg-gray-50/90 text-[11px] uppercase tracking-wide text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">{{ __('Fonte') }}</th>
                            <th class="px-4 py-2.5 font-semibold">{{ __('Estado') }}</th>
                            <th class="px-4 py-2.5 font-semibold">{{ __('Resumo') }}</th>
                            <th class="px-4 py-2.5 font-semibold">{{ __('Rotina sugerida') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($findings as $finding)
                            @php
                                $ui = is_array($finding['ui'] ?? null) ? $finding['ui'] : [];
                                $level = $ui['level'] ?? 'neutral';
                                $badgeClass = AdminImportHubCatalog::statusBadgeClasses()[$level]
                                    ?? AdminImportHubCatalog::statusBadgeClasses()['neutral'];
                                $status = (string) ($finding['status'] ?? '');
                                $showRoutine = in_array($status, ['new_available', 'attention', 'unreachable', 'not_configured'], true);
                                $anchor = (string) ($finding['source_anchor'] ?? '#');
                            @endphp
                            <tr class="bg-white dark:bg-gray-900/40">
                                <td class="px-4 py-3 align-top">
                                    <a href="{{ $anchor }}" class="font-medium text-indigo-700 dark:text-indigo-300 hover:underline">
                                        {{ $finding['source_title'] ?? $finding['source_id'] ?? '—' }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badgeClass }}">
                                        {{ $ui['label'] ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-300">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $finding['headline'] ?? '' }}</p>
                                    @if (filled($finding['detail'] ?? null))
                                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ $finding['detail'] }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    @if ($showRoutine && filled($finding['routine_cli'] ?? null))
                                        <code class="block rounded bg-gray-100 px-2 py-1 text-[10px] text-gray-800 dark:bg-gray-800 dark:text-gray-200">{{ $finding['routine_cli'] }}</code>
                                    @elseif ($showRoutine && filled($finding['routine_label'] ?? null))
                                        <a href="{{ $anchor }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                            {{ $finding['routine_label'] }}
                                        </a>
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
    @endif
</section>
