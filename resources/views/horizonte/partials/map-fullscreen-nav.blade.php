{{-- Navegação recorte/UF — visível só em tela inteira (cmd-dock fica fora do mapShell). --}}
<div
    x-show="mapFullscreen"
    x-cloak
    class="serv-horizonte-map-fullscreen-nav"
    role="navigation"
    aria-label="{{ __('Navegação do mapa') }}"
>
    <label class="serv-horizonte-map-fullscreen-nav__recorte">
        <span class="serv-horizonte-map-fullscreen-nav__recorte-label">{{ __('Recorte') }}</span>
        <select
            :value="scopeUf"
            @change="onScopeUfPick($event)"
            :disabled="pageLoading || regionalLoading"
            class="serv-horizonte-map-fullscreen-nav__select"
        >
            <option value="">{{ __('Brasil (por UF)') }}</option>
            @foreach ($ufNames as $code => $name)
                <option value="{{ $code }}">{{ $code }} — {{ $name }}</option>
            @endforeach
        </select>
    </label>

    <span class="serv-horizonte-gis__mode-pill serv-horizonte-map-fullscreen-nav__pill" :class="isOverviewMode ? 'is-national' : 'is-regional'">
        <span x-show="isOverviewMode">{{ __('Visão nacional') }}</span>
        <span x-show="isMesoOverviewMode" x-cloak>{{ __('Mesorregiões') }} · <span x-text="ufLabel(scopeUf)"></span></span>
        <span x-show="isRegionalMode" x-cloak>
            <span x-show="scopeMeso" x-text="mesoScopeLabel()"></span>
            <span x-show="!scopeMeso" x-text="ufLabel(scopeUf)"></span>
        </span>
    </span>

    <div class="serv-horizonte-map-fullscreen-nav__actions">
        <button
            type="button"
            class="serv-horizonte-map-float-btn serv-horizonte-map-fullscreen-nav__btn"
            x-show="isRegionalMode && mesoMapPoints.length >= 2"
            x-cloak
            @click="backToMesoOverview()"
            :disabled="pageLoading || regionalLoading"
        >{{ __('← Regiões') }}</button>
        <button
            type="button"
            class="serv-horizonte-map-float-btn serv-horizonte-map-fullscreen-nav__btn"
            x-show="isUfScopedMode"
            x-cloak
            @click="backToOverview()"
            :disabled="pageLoading || regionalLoading"
        >{{ __('← Brasil') }}</button>
    </div>
</div>
