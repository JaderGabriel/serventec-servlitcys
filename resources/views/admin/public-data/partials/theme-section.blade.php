@php
    use App\Support\Admin\AdminVisualCatalog;

    $theme = $section['theme'];
    $sources = $section['sources'];
    $accent = $theme['accent'] ?? 'slate';
@endphp

<section
    id="{{ $theme['hub_anchor'] }}"
    class="sync-queue-panel sync-queue-panel--{{ $accent }} scroll-mt-6"
>
    <header class="sync-queue-panel__header">
        <div class="flex gap-3 min-w-0">
            <span class="sync-queue-panel__icon" aria-hidden="true">
                <x-ui.icon :name="$theme['icon']" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <h3 class="sync-queue-panel__title">{{ $theme['label'] }}</h3>
                <p class="sync-queue-panel__desc">{{ $theme['description'] }}</p>
                <a
                    href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index', ['domain' => $theme['domain']]) }}#{{ $theme['anchor'] }}"
                    class="mt-2 inline-flex text-xs font-medium {{ AdminVisualCatalog::linkClasses($accent) }}"
                >
                    {{ __('Ver fila desta área') }} →
                </a>
            </div>
        </div>
    </header>

    <div class="sync-queue-panel__body space-y-4">
        @foreach ($sources as $source)
            <x-admin.import-hub.source-card
                :id="'source-'.($source['id'] ?? '')"
                :title="$source['title']"
                :summary="$source['summary']"
                :status="$source['status'] ?? []"
                :data-class="$source['data_class'] ?? ''"
                :persistence="$source['persistence'] ?? ''"
                :pdf-sections="$source['pdf_sections'] ?? []"
                :admin-route="$source['admin_route'] ?? null"
                :queue-domain="$source['domain'] ?? null"
                :accent="$source['theme_accent'] ?? $accent"
                :icon="$source['theme_icon'] ?? $theme['icon']"
                nested
            >
                @if (filled($source['consultoria_tab'] ?? null))
                    <p class="px-5 pt-3 text-xs text-gray-600 dark:text-gray-400">
                        {{ __('Consultoria:') }}
                        <a href="{{ route('dashboard.analytics', ['tab' => $source['consultoria_tab']]) }}" class="font-medium {{ AdminVisualCatalog::linkClasses($source['theme_accent'] ?? $accent) }}">
                            {{ __('Finanças → Tempo Real') }} →
                        </a>
                    </p>
                @endif

                @php $hasActions = count($source['actions'] ?? []) > 0; @endphp
                @if ($hasActions)
                    @foreach ($source['actions'] as $action)
                        @php
                            $enriched = AdminVisualCatalog::enrichAction($action);
                            $actionAccent = $enriched['submit_accent'] ?? ($source['theme_accent'] ?? $accent);
                        @endphp
                        <x-admin.import-hub.action-card
                            method="post"
                            action="{{ route('admin.public-data.run') }}"
                            :title="$enriched['label']"
                            :hint="$enriched['hint'] ?? null"
                            :variant="$enriched['variant'] ?? 'default'"
                            :step="$enriched['step'] ?? null"
                            :tags="$enriched['tags'] ?? []"
                            :accent="$actionAccent"
                            :icon="$enriched['icon'] ?? null"
                        >
                            @csrf
                            <input type="hidden" name="source_id" value="{{ $source['id'] }}">
                            <input type="hidden" name="action_key" value="{{ $enriched['key'] }}">
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                @if ($enriched['needs_city'] ?? false)
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Município') }}</label>
                                        <select name="city_id" class="{{ $selectClass }}" @if (! in_array($enriched['key'], ['import_transfers_all_cities', 'rebuild_finance_realtime_all_cities'], true)) required @endif>
                                            <option value="">{{ __('Selecione…') }}</option>
                                            @foreach ($cities as $city)
                                                <option value="{{ $city->id }}" @selected(old('city_id') == $city->id)>{{ $city->name }}@if ($city->uf) ({{ $city->uf }})@endif</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($enriched['needs_year'] ?? false)
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano') }}</label>
                                        <select name="ano" class="{{ $selectClass }}" required>
                                            @foreach ($yearOptions as $y)
                                                <option value="{{ $y }}" @selected((int) old('ano', $defaultYear) === $y)>{{ $y }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($enriched['needs_years_range'] ?? false)
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('De') }}</label>
                                        <select name="ano_from" class="{{ $selectClass }}">
                                            @foreach ($yearOptions as $y)
                                                <option value="{{ $y }}" @selected((int) old('ano_from', min($syncYears)) === $y)>{{ $y }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Até') }}</label>
                                        <select name="ano_to" class="{{ $selectClass }}">
                                            @foreach ($yearOptions as $y)
                                                <option value="{{ $y }}" @selected((int) old('ano_to', $defaultYear) === $y)>{{ $y }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            </div>
                            @if (in_array($enriched['key'], ['import_city_year', 'import_bulk_year', 'sync_all_years'], true))
                                <div class="flex flex-wrap gap-4 text-xs">
                                    <label class="inline-flex items-center gap-2">
                                        <input type="checkbox" name="use_nearest_year" value="1" class="rounded border-gray-300 text-sky-600" @checked(old('use_nearest_year'))>
                                        {{ __('Usar ano mais próximo se CKAN não tiver o exercício') }}
                                    </label>
                                    <label class="inline-flex items-center gap-2">
                                        <input type="checkbox" name="include_cached_years" value="1" class="rounded border-gray-300 text-sky-600" checked>
                                        {{ __('Incluir anos em cache/BD') }}
                                    </label>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Modo') }}</label>
                                    <select name="import_mode" class="{{ $selectClass }} max-w-xs">
                                        @foreach ($importModes as $mode)
                                            <option value="{{ $mode }}">{{ $mode === 'replace' ? __('Apagar e buscar') : __('Atualizar existentes') }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </x-admin.import-hub.action-card>
                    @endforeach
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Use a tela dedicada ou os comandos CLI:') }}
                        <code class="text-xs">{{ implode(', ', $source['cli'] ?? []) }}</code>
                    </p>
                @endif

                @if (($source['cli'] ?? []) !== [] && $hasActions)
                    <p class="text-[11px] text-gray-500 dark:text-gray-500">CLI: {{ implode(' · ', $source['cli']) }}</p>
                @endif
            </x-admin.import-hub.source-card>
        @endforeach
    </div>
</section>
