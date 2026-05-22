<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ config('app.name') }} — plataforma de dados educacionais por município.">
        <title>{{ config('app.name', 'servlitcys') }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @include('partials.theme-init')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen antialiased font-sans text-slate-800 selection:bg-cyan-500/25 selection:text-slate-900 dark:text-slate-100 dark:selection:bg-cyan-400/30 dark:selection:text-white">
        {{-- Fundo: modo claro e escuro --}}
        <div class="fixed inset-0 -z-10 bg-slate-50 dark:bg-slate-950">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-100 via-white to-teal-50/40 dark:from-indigo-950 dark:via-slate-950 dark:to-cyan-950"></div>
            <div class="absolute top-[-20%] left-[-10%] h-[50vh] w-[50vh] rounded-full bg-teal-400/20 blur-[100px] animate-float dark:bg-violet-600/25"></div>
            <div class="absolute bottom-[-15%] right-[-5%] h-[45vh] w-[45vh] rounded-full bg-cyan-400/15 blur-[90px] animate-float-delayed dark:bg-cyan-500/20"></div>
            <div class="absolute top-1/3 right-1/4 hidden h-64 w-64 rounded-full bg-fuchsia-600/15 blur-[80px] dark:block"></div>
            <div class="absolute inset-0 bg-grid-edu opacity-40 dark:opacity-60"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-50 via-transparent to-transparent dark:from-slate-950"></div>
        </div>

        <div class="relative flex min-h-screen flex-col">
            <header class="serv-nav-brand sticky top-0 z-50 shrink-0 shadow-sm">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ url('/') }}" class="group flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-indigo-600 shadow-lg shadow-indigo-500/20 ring-1 ring-slate-900/10 transition group-hover:scale-105 dark:from-cyan-400 dark:to-indigo-600 dark:shadow-indigo-500/25 dark:ring-white/20">
                            <x-application-logo class="h-6 w-6 text-white shrink-0" />
                        </span>
                        <span class="font-display text-lg font-semibold tracking-tight text-slate-900 sm:text-xl dark:text-white">
                            {{ config('app.name', 'servlitcys') }}
                        </span>
                    </a>
                    @php
                        $waDigits = config('services.serventec.whatsapp');
                        $waHref = filled($waDigits)
                            ? 'https://wa.me/'.preg_replace('/\D+/', '', (string) $waDigits)
                            : null;
                    @endphp
                    <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                        <x-theme-toggle appearance="landing" />
                        @if (Route::has('login'))
                            <nav class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                                @auth
                                    <a href="{{ Auth::user()->homeUrl() }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-800 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/15 dark:bg-white/5 dark:text-white dark:hover:border-white/25 dark:hover:bg-white/10">
                                        {{ Auth::user()->canViewAdminDashboard() ? __('Início') : __('Análise') }}
                                    </a>
                                @else
                                    <a href="{{ route('login') }}" class="rounded-lg border border-slate-300 bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:border-white/20 dark:bg-white/5 dark:hover:border-white/30 dark:hover:bg-white/10 dark:focus-visible:outline-cyan-400">
                                        {{ __('Entrar') }}
                                    </a>
                                    @if ($waHref)
                                        <a href="{{ $waHref }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg bg-[#25D366] px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-900/20 transition hover:brightness-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:shadow-emerald-900/30 dark:focus-visible:outline-emerald-400">
                                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                            {{ __('Contactar Serventec') }}
                                        </a>
                                    @else
                                        <span class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg border border-dashed border-slate-300 bg-slate-100 px-4 py-2 text-sm font-medium text-slate-400 dark:border-white/25 dark:bg-white/5 dark:text-white/50" title="{{ __('Configure SERVENTEC_WHATSAPP_NUMBER no .env para ativar o WhatsApp.') }}">
                                            <svg class="h-4 w-4 shrink-0 opacity-50" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                            {{ __('WhatsApp Serventec') }}
                                        </span>
                                    @endif
                                @endauth
                            </nav>
                        @endif
                    </div>
                </div>
            </header>

            <main class="flex flex-1 flex-col">
                <section class="mx-auto flex w-full max-w-6xl flex-col items-center px-4 pb-24 pt-16 text-center sm:px-6 lg:px-8 lg:pt-24">
                    <span class="inline-flex items-center gap-2 rounded-full border border-cyan-600/25 bg-cyan-50 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-cyan-800 dark:border-cyan-400/30 dark:bg-cyan-500/10 dark:text-cyan-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-cyan-600 shadow-[0_0_8px_rgba(8,145,178,0.5)] dark:bg-cyan-400 dark:shadow-[0_0_8px_rgba(34,211,238,0.9)]"></span>
                        {{ __('Plataforma educacional') }}
                    </span>
                    <h1 class="font-display mt-8 max-w-4xl text-4xl font-bold leading-[1.1] tracking-tight text-slate-900 sm:text-5xl lg:text-6xl dark:text-white">
                        {{ __('Dados que') }}
                        <span class="bg-gradient-to-r from-cyan-600 via-indigo-600 to-fuchsia-600 bg-clip-text text-transparent dark:from-cyan-300 dark:via-indigo-300 dark:to-fuchsia-300"> {{ __('educam') }} </span>
                        {{ __('as cidades') }}
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-600 sm:text-xl dark:text-slate-300">
                        {!! __('O <strong class="font-semibold text-slate-900 dark:text-white">:app</strong> reúne indicadores por município para apoiar análise, planeamento e decisões. Explore painéis, compare territórios e tenha uma visão consolidada do sistema educativo na sua região.', ['app' => e(config('app.name', 'servlitcys'))]) !!}
                    </p>

                    <div class="relative mt-16 w-full max-w-3xl">
                        <div class="absolute -inset-4 rounded-3xl bg-gradient-to-r from-cyan-500/15 via-indigo-500/10 to-fuchsia-500/15 blur-2xl dark:from-cyan-500/20 dark:via-indigo-500/10 dark:to-fuchsia-500/20"></div>
                        <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 p-6 shadow-xl backdrop-blur-md dark:border-white/10 dark:bg-slate-900/50 dark:shadow-2xl sm:p-8">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                <div class="text-left">
                                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Indicadores') }}</p>
                                    <p class="font-display mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ __('Evolução por território') }}</p>
                                </div>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <span class="rounded-lg border border-amber-500/40 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 dark:border-amber-400/40 dark:bg-amber-500/15 dark:text-amber-200">{{ __('Exemplo visual') }}</span>
                                    <span class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:border-slate-500/40 dark:bg-slate-800/80 dark:text-slate-300">{{ __('Sem dados reais') }}</span>
                                </div>
                            </div>
                            <div class="mt-6 flex h-32 items-end justify-between gap-1 sm:h-40 sm:gap-2">
                                @foreach ([40, 65, 45, 80, 55, 90, 70, 95, 60, 85, 75] as $h)
                                    <div class="flex-1 rounded-t-md bg-gradient-to-t from-cyan-500/90 to-indigo-500/70 transition hover:from-cyan-500 hover:to-indigo-500 dark:from-cyan-600/80 dark:to-indigo-500/60 dark:hover:from-cyan-500 dark:hover:to-indigo-400" style="height: {{ $h }}%"></div>
                                @endforeach
                            </div>
                            <p class="mt-4 text-center text-xs text-slate-500">{{ __('Barras apenas decorativas. Os valores reais aparecem no painel analítico após entrar com a sua conta.') }}</p>
                        </div>
                    </div>
                </section>

                <section class="border-t border-slate-200/80 bg-white/60 py-16 backdrop-blur-sm dark:border-white/10 dark:bg-slate-950/40">
                    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div class="text-center">
                            <h2 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">{{ __('Áreas do painel analítico') }}</h2>
                            <p class="mx-auto mt-3 max-w-2xl text-slate-600 dark:text-slate-400">{{ __('Temas alinhados aos módulos disponíveis após autenticação — sem números nesta página.') }}</p>
                        </div>
                        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ([
                                ['t' => __('Visão geral'), 'd' => __('Resumo e indicadores-chave por município e filtros.'), 'accent' => 'border-cyan-200/80 bg-cyan-50/80', 'dark' => 'dark:border-white/10 dark:bg-gradient-to-b dark:from-cyan-500/20 dark:to-cyan-600/5 dark:ring-cyan-400/20'],
                                ['t' => __('Matrículas'), 'd' => __('Oferta, distorção idade/série e leituras da rede.'), 'accent' => 'border-indigo-200/80 bg-indigo-50/80', 'dark' => 'dark:border-white/10 dark:bg-gradient-to-b dark:from-indigo-500/20 dark:to-indigo-600/5 dark:ring-indigo-400/20'],
                                ['t' => __('Rede & oferta'), 'd' => __('Estrutura da rede e oferta educativa no território.'), 'accent' => 'border-violet-200/80 bg-violet-50/80', 'dark' => 'dark:border-white/10 dark:bg-gradient-to-b dark:from-violet-500/20 dark:to-violet-600/5 dark:ring-violet-400/20'],
                                ['t' => __('Unidades escolares'), 'd' => __('Mapa, transporte e apoio à gestão das escolas.'), 'accent' => 'border-fuchsia-200/80 bg-fuchsia-50/80', 'dark' => 'dark:border-white/10 dark:bg-gradient-to-b dark:from-fuchsia-500/20 dark:to-fuchsia-600/5 dark:ring-fuchsia-400/20'],
                                ['t' => __('Inclusão & diversidade'), 'd' => __('Educação especial, equidade e indicadores de inclusão.'), 'accent' => 'border-emerald-200/80 bg-emerald-50/80', 'dark' => 'dark:border-white/10 dark:bg-gradient-to-b dark:from-emerald-500/20 dark:to-emerald-600/5 dark:ring-emerald-400/20'],
                                ['t' => __('Desempenho'), 'd' => __('Referências e leituras cruzadas com a realidade local.'), 'accent' => 'border-amber-200/80 bg-amber-50/80', 'dark' => 'dark:border-white/10 dark:bg-gradient-to-b dark:from-amber-500/20 dark:to-amber-600/5 dark:ring-amber-400/20'],
                                ['t' => __('Frequência'), 'd' => __('Acompanhamento de frequência escolar quando a base permitir.'), 'accent' => 'border-sky-200/80 bg-sky-50/80', 'dark' => 'dark:border-white/10 dark:bg-gradient-to-b dark:from-sky-500/20 dark:to-sky-600/5 dark:ring-sky-400/20'],
                                ['t' => __('FUNDEB'), 'd' => __('Quadro de apoio à análise — não substitui relatórios oficiais.'), 'accent' => 'border-rose-200/80 bg-rose-50/80', 'dark' => 'dark:border-white/10 dark:bg-gradient-to-b dark:from-rose-500/20 dark:to-rose-600/5 dark:ring-rose-400/20'],
                            ] as $tile)
                                <article class="rounded-xl border p-4 text-left shadow-sm ring-1 ring-transparent transition hover:shadow-md {{ $tile['accent'] }} {{ $tile['dark'] }} dark:shadow-none dark:hover:border-white/20">
                                    <h3 class="font-display text-sm font-semibold text-slate-900 dark:text-white">{{ $tile['t'] }}</h3>
                                    <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">{{ $tile['d'] }}</p>
                                </article>
                            @endforeach
                        </div>
                        @if (Route::has('login'))
                            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                                @auth
                                    <a href="{{ route('dashboard.analytics') }}" class="inline-flex items-center justify-center rounded-lg bg-gradient-to-r from-cyan-600 to-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-900/20 transition hover:brightness-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:from-cyan-500 dark:to-indigo-600 dark:shadow-indigo-900/30 dark:focus-visible:outline-cyan-400">
                                        {{ __('Abrir painel analítico') }}
                                    </a>
                                @else
                                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:border-white/20 dark:bg-white/5 dark:text-white dark:hover:border-white/30 dark:hover:bg-white/10 dark:focus-visible:outline-cyan-400">
                                        {{ __('Entrar para explorar estes módulos') }}
                                    </a>
                                @endauth
                            </div>
                        @endif
                    </div>
                </section>

                <section class="border-t border-slate-200/80 bg-slate-100/80 py-20 backdrop-blur-sm dark:border-white/10 dark:bg-slate-950/50">
                    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div class="text-center">
                            <h2 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">{{ __('Feito para quem decide com dados') }}</h2>
                            <p class="mx-auto mt-3 max-w-2xl text-slate-600 dark:text-slate-400">{{ __('Ferramentas pensadas para equipes educativas e gestão municipal.') }}</p>
                        </div>
                        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <article class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition hover:border-cyan-500/40 hover:shadow-lg hover:shadow-cyan-500/10 dark:border-white/10 dark:bg-gradient-to-b dark:from-white/5 dark:to-transparent dark:shadow-none dark:hover:border-cyan-500/30">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-100 text-cyan-700 ring-1 ring-cyan-200/80 dark:bg-cyan-500/15 dark:text-cyan-300 dark:ring-cyan-400/20">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" /></svg>
                                </div>
                                <h3 class="font-display mt-4 text-lg font-semibold text-slate-900 dark:text-white">{{ __('Cidades e território') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ __('Indicadores agregados por município para comparar realidades e priorizar oportunidades.') }}</p>
                            </article>
                            <article class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition hover:border-indigo-500/40 hover:shadow-lg hover:shadow-indigo-500/10 dark:border-white/10 dark:bg-gradient-to-b dark:from-white/5 dark:to-transparent dark:shadow-none dark:hover:border-indigo-500/30">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200/80 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-400/20">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                </div>
                                <h3 class="font-display mt-4 text-lg font-semibold text-slate-900 dark:text-white">{{ __('Painéis analíticos') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ __('Visualize tendências e métricas num só lugar, com filtros que aceleram a leitura.') }}</p>
                            </article>
                            <article class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition hover:border-fuchsia-500/40 hover:shadow-lg hover:shadow-fuchsia-500/10 sm:col-span-2 lg:col-span-1 dark:border-white/10 dark:bg-gradient-to-b dark:from-white/5 dark:to-transparent dark:shadow-none dark:hover:border-fuchsia-500/30">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-fuchsia-100 text-fuchsia-700 ring-1 ring-fuchsia-200/80 dark:bg-fuchsia-500/15 dark:text-fuchsia-300 dark:ring-fuchsia-400/20">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                </div>
                                <h3 class="font-display mt-4 text-lg font-semibold text-slate-900 dark:text-white">{{ __('Decisões informadas') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ __('Apoie planeamento e políticas públicas com base em dados educacionais consolidados.') }}</p>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="border-t border-slate-200/80 bg-white/70 py-16 backdrop-blur-sm dark:border-white/10 dark:bg-slate-950/70">
                    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                        <h2 class="font-display text-center text-xl font-bold text-slate-900 sm:text-2xl dark:text-white">{{ __('Confiança e interpretação dos dados') }}</h2>
                        <p class="mx-auto mt-3 text-center text-sm text-slate-600 dark:text-slate-400">{{ __('Informação institucional — esta página não exibe valores da sua rede.') }}</p>
                        <ul class="mt-8 space-y-4 text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                            @foreach ([
                                __('Os indicadores do painel refletem o registro administrativo na base municipal (por exemplo, i-Educar), sujeito à qualidade do cadastro e às regras configuradas na plataforma.'),
                                __('Para fins legais ou oficiais (Censo Escolar, INEP, teses perante tribunais), use sempre as fontes e relatórios previstos na legislação.'),
                                __('Os valores apresentados no painel correspondem ao estado da base no momento da consulta e aos filtros escolhidos (ano, escola, segmento, etc.).'),
                                __('O tratamento de dados pessoais segue a legislação aplicável (incluindo LGPD no Brasil). O acesso ao painel é restrito a usuários autorizados.'),
                            ] as $i => $text)
                                <li class="flex gap-3">
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-cyan-800 ring-1 ring-cyan-300/60 dark:bg-cyan-500/20 dark:text-cyan-300 dark:ring-cyan-400/30" aria-hidden="true">{{ $i + 1 }}</span>
                                    <span>{{ $text }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            </main>

            <footer class="border-t border-slate-200/80 bg-white/80 py-10 backdrop-blur-xl dark:border-white/10 dark:bg-slate-950/80">
                <div class="mx-auto flex max-w-6xl flex-col items-center justify-center gap-3 px-4 text-center sm:px-6 lg:px-8">
                    <p class="text-sm text-slate-500">
                        © {{ date('Y') }} Serventec Analítico by
                        <a href="https://serventecassessoria.com.br" target="_blank" rel="noopener noreferrer" class="font-semibold text-cyan-700 underline decoration-cyan-600/40 underline-offset-2 transition hover:text-cyan-800 dark:text-cyan-300 dark:decoration-cyan-500/50 dark:hover:text-cyan-200">
                             Serventec Assessoria
                        </a>
                    </p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Powered by:
                        <a href="https://github.com/jadergabriel" target="_blank" rel="noopener noreferrer" class="font-semibold text-cyan-700 underline decoration-cyan-600/40 underline-offset-2 transition hover:text-cyan-800 dark:text-cyan-300 dark:decoration-cyan-500/50 dark:hover:text-cyan-200">
                            Jader Gabriel
                        </a>
                    </p>
                </div>
            </footer>

            <x-scroll-to-top />
        </div>
    </body>
</html>
