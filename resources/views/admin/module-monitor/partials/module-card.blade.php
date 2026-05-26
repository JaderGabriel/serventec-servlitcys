@php
    /** @var array<string, mixed> $module */
    $anchor = 'modulo-'.$module['id'];
@endphp

<article
    id="{{ $anchor }}"
    class="module-monitor-card module-monitor-card--{{ $module['accent'] }} scroll-mt-6"
>
    <header class="module-monitor-card__header">
        <div class="flex items-start gap-3 min-w-0">
            <span class="module-monitor-card__icon" aria-hidden="true">
                <x-ui.icon :name="$module['icon']" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <h3 class="module-monitor-card__title">{{ $module['label'] }}</h3>
                <p class="module-monitor-card__desc">{{ $module['description'] }}</p>
            </div>
        </div>
        @include('admin.module-monitor.partials.status-badge', ['status' => $module['status']])
    </header>

    <dl class="module-monitor-card__stats">
        @if (($module['sync_failed'] ?? 0) > 0 || ($module['sync_active'] ?? 0) > 0 || ($module['sync_completed'] ?? 0) > 0)
            <div>
                <dt>{{ __('Fila sync') }}</dt>
                <dd>
                    @if (($module['sync_failed'] ?? 0) > 0)
                        <span class="text-rose-700 dark:text-rose-300 font-medium">{{ (int) $module['sync_failed'] }} {{ __('falhas') }}</span>
                    @endif
                    @if (($module['sync_active'] ?? 0) > 0)
                        <span class="text-sky-700 dark:text-sky-300">{{ (int) $module['sync_active'] }} {{ __('ativas') }}</span>
                    @endif
                    @if (($module['sync_completed'] ?? 0) > 0)
                        <span class="text-emerald-700 dark:text-emerald-300">{{ (int) $module['sync_completed'] }} {{ __('ok') }}</span>
                    @endif
                </dd>
            </div>
        @endif
        @if (($module['pulse_errors'] ?? 0) > 0 || ($module['pulse_slow'] ?? 0) > 0)
            <div>
                <dt>{{ __('Pulse') }}</dt>
                <dd>
                    @if (($module['pulse_errors'] ?? 0) > 0)
                        <span class="text-rose-700 dark:text-rose-300">{{ (int) $module['pulse_errors'] }} {{ __('erros') }}</span>
                    @endif
                    @if (($module['pulse_slow'] ?? 0) > 0)
                        <span class="text-amber-700 dark:text-amber-300">{{ (int) $module['pulse_slow'] }} {{ __('lentos') }}</span>
                        @if (($module['pulse_max_ms'] ?? 0) > 0)
                            <span class="text-slate-500">· {{ number_format((int) $module['pulse_max_ms'], 0, ',', '.') }} ms</span>
                        @endif
                    @endif
                </dd>
            </div>
        @endif
        @if (($module['incident_count'] ?? 0) > 0)
            <div>
                <dt>{{ __('Incidentes') }}</dt>
                <dd class="font-medium">{{ (int) $module['incident_count'] }}</dd>
            </div>
        @endif
    </dl>

    <footer class="module-monitor-card__footer">
        @if (! empty($module['admin_url']))
            <a href="{{ $module['admin_url'] }}" class="text-xs font-medium text-teal-700 dark:text-teal-300 hover:underline">
                {{ __('Abrir módulo') }} →
            </a>
        @endif
        @if (! empty($module['queue_url']))
            <a href="{{ $module['queue_url'] }}" class="text-xs font-medium text-indigo-700 dark:text-indigo-300 hover:underline">
                {{ __('Ver fila') }} →
            </a>
        @endif
        @if (($module['incident_count'] ?? 0) > 0)
            <a href="#historico-incidentes" class="text-xs font-medium text-slate-600 dark:text-slate-400 hover:underline">
                {{ __('Histórico') }} ↓
            </a>
        @endif
    </footer>
</article>
