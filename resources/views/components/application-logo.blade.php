{{-- Consultoria financeira (barras + tendência), educação (capelo) e Horizonte (arco elevado) — sem base inferior para não confundir com a barra do menu --}}
<svg viewBox="0 0 48 28" xmlns="http://www.w3.org/2000/svg" fill="none" {{ $attributes }}>
    <g fill="currentColor">
        {{-- Gráfico de barras (analytics) --}}
        <rect x="1" y="16" width="4.5" height="8" rx="1" opacity="0.72" />
        <rect x="7.5" y="12" width="4.5" height="12" rx="1" opacity="0.88" />
        <rect x="14" y="7" width="4.5" height="17" rx="1" />
        {{-- Seta de tendência (consultoria financeira) --}}
        <path d="M16.8 6.2l-2 .8 1.2 1.9z" opacity="0.92" />
        {{-- Educação: capelo académico --}}
        <path d="M22 13.5 28 10.5 34 13.5 28 16.5Z" opacity="0.95" />
        <path d="M26 14.2v5c0 .45.4.8.9.8h2.2c.5 0 .9-.35.9-.8v-5" opacity="0.78" />
        <circle cx="33.2" cy="11.8" r="0.85" opacity="0.55" />
        {{-- Horizonte: nascer do sol / ponto territorial no arco --}}
        <circle cx="42" cy="10.2" r="1.85" opacity="0.92" />
    </g>
    <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3.2 14.8 10.2 10.5 16.8 6.2" stroke-width="1.35" opacity="0.9" />
        <path d="M36 15.5q6-7.5 12 0" stroke-width="1.45" opacity="0.85" />
    </g>
</svg>
