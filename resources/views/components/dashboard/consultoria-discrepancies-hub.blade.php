@props([
    'modules' => [],
    'fmtBrl' => null,
    'context' => 'consultoria',
    'consultoriaUrl' => null,
])

@php
    use App\Support\Ieducar\DiscrepanciesModuleCatalog;
    use App\Support\Ieducar\DiscrepanciesRoutineStatus;

    $formatBrl = $fmtBrl ?? static fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.');
    $statusStyles = [
        'danger' => 'disc-mod--danger',
        'warning' => 'disc-mod--warning',
        DiscrepanciesRoutineStatus::OK => 'disc-mod--ok',
        DiscrepanciesRoutineStatus::NO_DATA => 'disc-mod--nodata',
        DiscrepanciesRoutineStatus::UNAVAILABLE => 'disc-mod--unavailable',
    ];
@endphp

@if (count($modules) > 0)
    <div {{ $attributes->merge(['class' => 'grid grid-cols-1 xl:grid-cols-2 gap-3']) }}>
        @foreach ($modules as $module)
            @php
                $st = (string) ($module['status'] ?? DiscrepanciesRoutineStatus::UNAVAILABLE);
                $modClass = $statusStyles[$st] ?? 'disc-mod--unavailable';
                $perda = (float) ($module['perda_estimada_anual'] ?? 0);
                $ganho = (float) ($module['ganho_potencial_anual'] ?? 0);
                $issues = (int) ($module['routines_with_issue'] ?? 0);
            @endphp
            <article
                id="{{ $module['anchor'] ?? '' }}"
                class="disc-mod {{ $modClass }} scroll-mt-24"
            >
                <header class="disc-mod__head">
                    <div class="min-w-0 flex-1">
                        <p class="disc-mod__eyebrow">{{ $module['status_label'] ?? '' }}</p>
                        <h4 class="disc-mod__title">{{ $module['title'] ?? '' }}</h4>
                        @if (filled($module['subtitle'] ?? null))
                            <p class="disc-mod__subtitle">{{ $module['subtitle'] }}</p>
                        @endif
                    </div>
                    <div class="disc-mod__metrics shrink-0 text-right">
                        @if ($perda > 0)
                            <p class="disc-mod__perda tabular-nums">{{ __('Perda est.') }} {{ $formatBrl($perda) }}</p>
                        @endif
                        @if ($ganho > 0 && $ganho !== $perda)
                            <p class="disc-mod__ganho tabular-nums text-[11px]">{{ __('Ganho') }} {{ $formatBrl($ganho) }}</p>
                        @endif
                        @if ($issues > 0)
                            <p class="disc-mod__count tabular-nums">{{ $issues }}/{{ (int) ($module['routines_total'] ?? 0) }} {{ __('rotinas') }}</p>
                        @endif
                    </div>
                </header>

                <ul class="disc-mod__routines">
                    @foreach ($module['routines'] ?? [] as $routine)
                        @php
                            $rst = (string) ($routine['status'] ?? DiscrepanciesRoutineStatus::UNAVAILABLE);
                            $rui = DiscrepanciesRoutineStatus::presentation($rst);
                        @endphp
                        <li class="disc-mod__routine">
                            <a href="#{{ $routine['detail_anchor'] ?? '' }}" class="disc-mod__routine-link">
                                <span class="disc-mod__routine-icon" aria-hidden="true">{{ $rui['icon'] }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="disc-mod__routine-title">{{ $routine['title'] ?? '' }}</span>
                                    @if (! empty($routine['has_issue']))
                                        <span class="disc-mod__routine-meta tabular-nums">
                                            {{ DiscrepanciesModuleCatalog::routineMetricSummary($routine) }}
                                            @if ((float) ($routine['perda_estimada_anual'] ?? 0) > 0)
                                                · {{ $formatBrl((float) $routine['perda_estimada_anual']) }}
                                            @endif
                                        </span>
                                    @elseif ($rst === DiscrepanciesRoutineStatus::NO_DATA)
                                        <span class="disc-mod__routine-meta">{{ $routine['status_label'] ?? __('Sem dados') }}</span>
                                    @elseif ($rst === DiscrepanciesRoutineStatus::UNAVAILABLE)
                                        <span class="disc-mod__routine-meta">{{ $routine['unavailable_reason'] ?? __('Indisponível') }}</span>
                                    @else
                                        <span class="disc-mod__routine-meta">{{ __('Sem pendência') }}</span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if (filled($module['correction_hint'] ?? null))
                    <footer class="disc-mod__foot">
                        <p class="disc-mod__hint">{{ $module['correction_hint'] }}</p>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            @if ($context === 'admin')
                                @if (filled($module['admin_route'] ?? null))
                                    <a href="{{ route($module['admin_route']) }}" class="text-xs font-semibold text-sky-700 dark:text-sky-300 hover:underline">
                                        {{ $module['admin_route_label'] ?? __('Admin') }}
                                    </a>
                                @endif
                                @if (filled($consultoriaUrl))
                                    <a href="{{ $consultoriaUrl }}#disc-mod-{{ $module['id'] ?? '' }}" class="text-xs font-semibold text-blue-700 dark:text-blue-300 hover:underline">
                                        {{ __('Consultoria → Discrepâncias') }}
                                    </a>
                                @endif
                            @elseif (filled($module['correction_tab'] ?? null))
                                <x-consultoria-tab-link
                                    :tab="$module['correction_tab']"
                                    :label="$module['correction_label'] ?? __('Onde corrigir')"
                                    class="text-xs font-semibold"
                                />
                            @endif
                        </div>
                    </footer>
                @endif
            </article>
        @endforeach
    </div>
@endif
