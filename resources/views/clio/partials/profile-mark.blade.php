@php
    $analysisOnly = (bool) ($analysisOnly ?? false);
    $label = $analysisOnly ? __('Só coleta') : __('Consultoria');
    $tone = $analysisOnly ? 'analysis' : 'consultancy';
@endphp
<span
    class="clio-profile-mark clio-profile-mark--{{ $tone }}"
    title="{{ $label }}"
    aria-label="{{ $label }}"
>
    @if ($analysisOnly)
        {{-- Documento: só coleta --}}
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 0 0 3 3.5v13A1.5 1.5 0 0 0 4.5 18h11a1.5 1.5 0 0 0 1.5-1.5V7.621a1.5 1.5 0 0 0-.44-1.06l-3.12-3.122A1.5 1.5 0 0 0 12.378 3H4.5Zm7.75 2.378L15.122 7.25H13a.75.75 0 0 1-.75-.75V4.378ZM6.5 9.25a.75.75 0 0 0 0 1.5h7a.75.75 0 0 0 0-1.5h-7Zm0 3a.75.75 0 0 0 0 1.5h4a.75.75 0 0 0 0-1.5h-4Z" clip-rule="evenodd" />
        </svg>
    @else
        {{-- Prédio: consultoria --}}
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M4 3.5A1.5 1.5 0 0 1 5.5 2h9A1.5 1.5 0 0 1 16 3.5v13a.5.5 0 0 1-.5.5H13v-2.25a.75.75 0 0 0-.75-.75h-2.5a.75.75 0 0 0-.75.75V17H4.5a.5.5 0 0 1-.5-.5v-13ZM7 5.75A.75.75 0 0 1 7.75 5h.5a.75.75 0 0 1 0 1.5h-.5A.75.75 0 0 1 7 5.75Zm0 3A.75.75 0 0 1 7.75 8h.5a.75.75 0 0 1 0 1.5h-.5A.75.75 0 0 1 7 8.75Zm0 3a.75.75 0 0 1 .75-.75h.5a.75.75 0 0 1 0 1.5h-.5a.75.75 0 0 1-.75-.75ZM11.75 5a.75.75 0 0 0 0 1.5h.5a.75.75 0 0 0 0-1.5h-.5Zm0 3a.75.75 0 0 0 0 1.5h.5a.75.75 0 0 0 0-1.5h-.5Zm0 3a.75.75 0 0 0 0 1.5h.5a.75.75 0 0 0 0-1.5h-.5Z" clip-rule="evenodd" />
        </svg>
    @endif
</span>
