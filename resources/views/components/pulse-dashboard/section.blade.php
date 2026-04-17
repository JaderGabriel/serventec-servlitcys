@props([
    'title',
    'subtitle' => null,
    'accent' => 'indigo',
    'icon' => 'rectangle-group',
])
@php
    $styles = [
        'emerald' => 'border border-emerald-200/90 bg-gradient-to-r from-emerald-50/95 to-emerald-50/40 text-emerald-900 shadow-md ring-2 ring-emerald-200/50 dark:border-emerald-800/60 dark:from-emerald-950/50 dark:to-emerald-950/20 dark:text-emerald-100 dark:ring-emerald-800/40',
        'slate' => 'border border-slate-200/90 bg-gradient-to-r from-slate-50/95 to-slate-50/35 text-slate-900 shadow-md ring-2 ring-slate-200/45 dark:border-slate-600/60 dark:from-slate-900/55 dark:to-slate-900/25 dark:text-slate-100 dark:ring-slate-600/35',
        'amber' => 'border border-amber-200/90 bg-gradient-to-r from-amber-50/95 to-amber-50/35 text-amber-950 shadow-md ring-2 ring-amber-200/50 dark:border-amber-800/55 dark:from-amber-950/45 dark:to-amber-950/20 dark:text-amber-100 dark:ring-amber-800/35',
        'violet' => 'border border-violet-200/90 bg-gradient-to-r from-violet-50/95 to-violet-50/35 text-violet-950 shadow-md ring-2 ring-violet-200/50 dark:border-violet-800/55 dark:from-violet-950/50 dark:to-violet-950/20 dark:text-violet-100 dark:ring-violet-800/40',
        'sky' => 'border border-sky-200/90 bg-gradient-to-r from-sky-50/95 to-sky-50/35 text-sky-950 shadow-md ring-2 ring-sky-200/50 dark:border-sky-800/55 dark:from-sky-950/45 dark:to-sky-950/20 dark:text-sky-100 dark:ring-sky-800/35',
        'rose' => 'border border-rose-200/90 bg-gradient-to-r from-rose-50/95 to-rose-50/35 text-rose-950 shadow-md ring-2 ring-rose-200/50 dark:border-rose-800/55 dark:from-rose-950/45 dark:to-rose-950/20 dark:text-rose-100 dark:ring-rose-800/35',
        'red' => 'border border-red-200/90 bg-gradient-to-r from-red-50/95 to-red-50/35 text-red-950 shadow-md ring-2 ring-red-200/50 dark:border-red-900/50 dark:from-red-950/45 dark:to-red-950/20 dark:text-red-100 dark:ring-red-900/35',
        'indigo' => 'border border-indigo-200/90 bg-gradient-to-r from-indigo-50/95 to-indigo-50/35 text-indigo-950 shadow-md ring-2 ring-indigo-200/55 dark:border-indigo-800/55 dark:from-indigo-950/50 dark:to-indigo-950/20 dark:text-indigo-100 dark:ring-indigo-700/40',
    ];
    $cardClass = $styles[$accent] ?? $styles['indigo'];

    $bar = [
        'emerald' => 'bg-gradient-to-b from-emerald-500 to-teal-600',
        'slate' => 'bg-gradient-to-b from-slate-500 to-slate-700',
        'amber' => 'bg-gradient-to-b from-amber-500 to-orange-600',
        'violet' => 'bg-gradient-to-b from-violet-500 to-purple-600',
        'sky' => 'bg-gradient-to-b from-sky-500 to-blue-600',
        'rose' => 'bg-gradient-to-b from-rose-500 to-pink-600',
        'red' => 'bg-gradient-to-b from-red-500 to-rose-700',
        'indigo' => 'bg-gradient-to-b from-indigo-500 to-violet-600',
    ];
    $barClass = $bar[$accent] ?? $bar['indigo'];

    $icons = [
        'chart-bar' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
        'server' => 'M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0v-3.75a3.75 3.75 0 00-3.75-3.75H5.25a3.75 3.75 0 00-3.75 3.75v3.75m19.5 0h-21',
        'queue' => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99',
        'circle-stack' => 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 .414-.168.811-.464 1.102-.296.29-.697.476-1.122.526m-12.742 0c-.425-.05-.826-.235-1.122-.526A1.657 1.657 0 013.75 12.75v-3.75',
        'globe-alt' => 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418',
        'cpu' => 'M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-13.5h.008v.008H9v-.008zm0 3.75h.008v.008H9V9.375zm0 3.75h.008v.008H9v-.008zm0 3.75h.008v.008H9v-.008zm3.75-11.25h.008v.008h-.008v-.008zm0 3.75h.008v.008h-.008V9.375zm0 3.75h.008v.008h-.008v-.008zm0 3.75h.008v.008h-.008v-.008zm3.75-11.25h.008v.008h-.008v-.008zm0 3.75h.008v.008h-.008V9.375zm0 3.75h.008v.008h-.008v-.008zm0 3.75h.008v.008h-.008v-.008z',
        'bolt' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
        'exclamation' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
        'rectangle-group' => 'M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 01-1.125-1.125v-3.75zM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-2.25z',
        'circle' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.431l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456zM16.5 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z',
    ];
    $path = $icons[$icon] ?? $icons['rectangle-group'];
@endphp

<div {{ $attributes->merge(['class' => 'pulse-dashboard-theme default:col-span-full pt-10 first:pt-2 sm:pt-11 sm:first:pt-2']) }}>
    <div class="relative overflow-hidden rounded-2xl pl-4 sm:pl-5">
        <div class="absolute start-0 top-0 h-full w-1.5 {{ $barClass }}" aria-hidden="true"></div>
        <div class="flex items-start gap-3.5 px-4 py-3.5 sm:gap-4 sm:px-5 sm:py-4 {{ $cardClass }}">
            <div class="shrink-0 rounded-xl bg-white/70 p-2 shadow-sm ring-1 ring-white/60 dark:bg-black/25 dark:ring-white/10">
                <svg class="h-6 w-6 opacity-95" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                </svg>
            </div>
            <div class="min-w-0 pb-px">
                <h2 class="text-base font-semibold leading-snug tracking-tight sm:text-lg">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="mt-1.5 max-w-4xl text-sm leading-relaxed opacity-90">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
