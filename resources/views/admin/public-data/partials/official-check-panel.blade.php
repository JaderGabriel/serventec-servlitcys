@php
    use App\Support\Admin\AdminImportHubCatalog;

    $report = is_array($officialCheck ?? null) ? $officialCheck : null;
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : null;
    $groups = is_array($report['groups'] ?? null) ? $report['groups'] : [];
    $actionFindings = is_array($groups['action'] ?? null) ? $groups['action'] : [];
    $alignedFindings = is_array($groups['aligned'] ?? null) ? $groups['aligned'] : [];
    $counts = is_array($report['counts'] ?? null) ? $report['counts'] : [];
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
                class="shrink-0 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-slate-800"
            >
                @csrf
                <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                    <input
                        type="checkbox"
                        name="notify"
                        value="1"
                        class="rounded border-gray-300 text-sky-600 shadow-sm focus:ring-sky-500 dark:border-gray-600 dark:bg-gray-900"
                    />
                    {{ __('Notificar administradores') }}
                </label>
                <button
                    type="submit"
                    class="mt-2 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                >
                    <x-ui.icon name="arrow-path" class="h-3.5 w-3.5" />
                    {{ __('Verificar agora') }}
                </button>
            </form>
        @endif
    </div>

    @if (! $enabled)
        <x-admin.import-hub.callout variant="info" :title="__('Verificação desativada')">
            {{ __('Defina PUBLIC_DATA_DAILY_CHECK_ENABLED=true para ativar o comando public-data:check-official e este painel.') }}
        </x-admin.import-hub.callout>
    @elseif ($report === null)
        <x-admin.import-hub.callout variant="info" :title="__('Sem relatório recente')">
            {{ __('Use «Verificar agora» para consultar as fontes oficiais ou aguarde a rotina diária.') }}
        </x-admin.import-hub.callout>
    @else
        <x-admin.import-hub.callout :variant="$summary['variant'] ?? 'info'" :title="$summary['headline'] ?? __('Resumo da verificação')">
            @if (filled($summary['subline'] ?? null))
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ $summary['subline'] }}</p>
            @endif
            <div class="mt-2 flex flex-wrap gap-2 text-[11px] font-medium">
                @if ((int) ($counts['new_count'] ?? $summary['news'] ?? 0) > 0)
                    <span class="inline-flex rounded-full bg-violet-100 px-2.5 py-0.5 text-violet-800 dark:bg-violet-900 dark:text-violet-100">
                        {{ trans_choice(':n novidade|:n novidades', (int) ($counts['new_count'] ?? $summary['news'] ?? 0), ['n' => (int) ($counts['new_count'] ?? $summary['news'] ?? 0)]) }}
                    </span>
                @endif
                @if ((int) ($counts['attention_count'] ?? $summary['attention'] ?? 0) > 0)
                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-amber-900 dark:bg-amber-900 dark:text-amber-100">
                        {{ trans_choice(':n requer atenção|:n requerem atenção', (int) ($counts['attention_count'] ?? $summary['attention'] ?? 0), ['n' => (int) ($counts['attention_count'] ?? $summary['attention'] ?? 0)]) }}
                    </span>
                @endif
                @if ((int) ($counts['aligned_count'] ?? $summary['aligned'] ?? 0) > 0)
                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-100">
                        {{ trans_choice(':n alinhada|:n alinhadas', (int) ($counts['aligned_count'] ?? $summary['aligned'] ?? 0), ['n' => (int) ($counts['aligned_count'] ?? $summary['aligned'] ?? 0)]) }}
                    </span>
                @endif
            </div>
        </x-admin.import-hub.callout>

        @if ($actionFindings !== [])
            <div class="space-y-2">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">
                    {{ __('Requer ação') }}
                </h4>
                @include('admin.public-data.partials.official-check-findings-table', [
                    'findings' => $actionFindings,
                    'tone' => 'action',
                ])
            </div>
        @endif

        @if ($alignedFindings !== [])
            <div class="space-y-2">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ __('Sem alteração') }}
                    <span class="font-normal normal-case text-gray-400 dark:text-gray-500">— {{ __('cobertura local alinhada') }}</span>
                </h4>
                @include('admin.public-data.partials.official-check-findings-table', [
                    'findings' => $alignedFindings,
                    'tone' => 'aligned',
                ])
            </div>
        @endif
    @endif

    @if ($enabled)
        <x-admin.import-hub.cli-block
            :examples="[
                ['summary' => __('Verificação completa'), 'command' => 'php artisan public-data:check-official'],
                ['summary' => __('Sem notificar administradores'), 'command' => 'php artisan public-data:check-official --no-notify'],
            ]"
        />
    @endif
</section>
