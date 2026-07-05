@props([
    'docUrl' => '#',
    'showLayoutToggle' => false,
])

<nav {{ $attributes->merge(['class' => 'serv-horizonte-help-nav']) }} aria-label="{{ __('Ajuda Horizonte') }}">
    <button
        type="button"
        class="serv-horizonte-help-nav__item serv-horizonte-help-nav__item--tour"
        onclick="window.dispatchEvent(new CustomEvent('horizonte-guide', { detail: { mode: 'tour' } }))"
    >
        <span class="serv-horizonte-help-nav__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
            </svg>
        </span>
        <span class="serv-horizonte-help-nav__label">{{ __('Como usar') }}</span>
    </button>
    <button
        type="button"
        class="serv-horizonte-help-nav__item serv-horizonte-help-nav__item--demo"
        onclick="window.dispatchEvent(new CustomEvent('horizonte-guide', { detail: { mode: 'demo' } }))"
    >
        <span class="serv-horizonte-help-nav__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" />
            </svg>
        </span>
        <span class="serv-horizonte-help-nav__label">{{ __('Demonstração') }}</span>
    </button>
    <a href="{{ $docUrl }}" class="serv-horizonte-help-nav__item serv-horizonte-help-nav__item--doc">
        <span class="serv-horizonte-help-nav__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
            </svg>
        </span>
        <span class="serv-horizonte-help-nav__label">{{ __('Documentação') }}</span>
    </a>
    @if ($showLayoutToggle)
        <button
            type="button"
            id="horizonte-layout-toggle"
            class="serv-horizonte-help-nav__item serv-horizonte-help-nav__item--layout"
            title="{{ __('Alternar entre interface desktop e versão para telemóvel.') }}"
            aria-label="{{ __('Alternar entre interface desktop e versão para telemóvel.') }}"
            onclick="window.dispatchEvent(new CustomEvent('horizonte-layout-toggle'))"
        >
            <span class="serv-horizonte-help-nav__icon" id="horizonte-layout-toggle-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                    <path d="M7 1a2 2 0 00-2 2v14a2 2 0 002 2h6a2 2 0 002-2V3a2 2 0 00-2-2H7zm1 14.5a.75.75 0 100 1.5h4a.75.75 0 100-1.5H8z" />
                </svg>
            </span>
            <span class="serv-horizonte-help-nav__label" id="horizonte-layout-toggle-label">{{ __('Versão mão') }}</span>
        </button>
    @endif
</nav>
