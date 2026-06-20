<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php
            $brand = config('analytics.pdf_report.brand', []);
            $systemTagline = $brand['system_tagline'] ?? __('Plataforma educacional municipal');
        @endphp
        <meta name="description" content="{{ config('app.name') }} — {{ $systemTagline }}. Consultoria municipal, Horizonte GIS, cadastro i-Educar, Censo e dados públicos (FUNDEB, SAEB).">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'servlitcys') }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @include('partials.theme-init')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen antialiased font-sans text-slate-800 selection:bg-teal-500/25 selection:text-slate-900 dark:text-slate-100 dark:selection:bg-teal-400/30 dark:selection:text-white">
        <div class="fixed inset-0 -z-10 bg-slate-50 dark:bg-slate-950">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-100 via-white to-teal-50/50 dark:from-slate-950 dark:via-slate-950 dark:to-teal-950/40"></div>
            <div class="absolute top-[-20%] left-[-10%] h-[50vh] w-[50vh] rounded-full bg-teal-400/15 blur-[100px] animate-float dark:bg-teal-600/20"></div>
            <div class="absolute bottom-[-15%] right-[-5%] h-[45vh] w-[45vh] rounded-full bg-indigo-400/10 blur-[90px] animate-float-delayed dark:bg-indigo-600/15"></div>
            <div class="absolute inset-0 bg-grid-edu opacity-40 dark:opacity-50"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-50 via-transparent to-transparent dark:from-slate-950"></div>
        </div>

        <div class="relative flex min-h-screen flex-col">
            <header class="serv-nav-brand sticky top-0 z-50 shrink-0 shadow-sm">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                    <a href="{{ url('/') }}" class="group flex min-w-0 items-center gap-2.5 sm:gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-600 to-indigo-700 shadow-md shadow-teal-900/15 ring-1 ring-slate-900/10 transition group-hover:scale-105 dark:from-teal-500 dark:to-indigo-600 dark:ring-white/15">
                            <x-application-logo class="h-6 w-6 shrink-0 text-white" />
                        </span>
                        <span class="min-w-0 truncate font-display text-base font-semibold tracking-tight text-slate-900 sm:text-lg dark:text-white">
                            {{ config('app.name', 'servlitcys') }}
                        </span>
                    </a>
                    @php
                        $waDigits = config('services.serventec.whatsapp');
                        $waHref = filled($waDigits)
                            ? 'https://wa.me/'.preg_replace('/\D+/', '', (string) $waDigits)
                            : null;
                    @endphp
                    <div class="flex shrink-0 flex-nowrap items-center gap-1.5 sm:gap-2">
                        <x-theme-toggle appearance="landing" />
                        @if (Route::has('login'))
                            @auth
                                <a
                                    href="{{ Auth::user()->homeUrl() }}"
                                    class="serv-landing-icon-btn serv-landing-icon-btn--primary"
                                    title="{{ Auth::user()->canViewAdminDashboard() ? __('Ir para o início') : __('Abrir painel analítico') }}"
                                    aria-label="{{ Auth::user()->canViewAdminDashboard() ? __('Ir para o início') : __('Abrir painel analítico') }}"
                                >
                                    <x-ui.icon name="home" class="h-5 w-5" />
                                </a>
                            @else
                                <a
                                    href="{{ route('login') }}"
                                    class="serv-landing-icon-btn serv-landing-icon-btn--primary"
                                    title="{{ __('Entrar na plataforma') }}"
                                    aria-label="{{ __('Entrar na plataforma') }}"
                                >
                                    <x-ui.icon name="arrow-right-end-on-rectangle" class="h-5 w-5" />
                                </a>
                                @if ($waHref)
                                    <a
                                        href="{{ $waHref }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="serv-landing-icon-btn serv-landing-icon-btn--whatsapp"
                                        title="{{ __('Contactar Serventec no WhatsApp') }}"
                                        aria-label="{{ __('Contactar Serventec no WhatsApp') }}"
                                    >
                                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                    </a>
                                @else
                                    <span
                                        class="serv-landing-icon-btn serv-landing-icon-btn--disabled"
                                        title="{{ __('Configure SERVENTEC_WHATSAPP_NUMBER no .env para ativar o WhatsApp.') }}"
                                        aria-label="{{ __('WhatsApp Serventec indisponível') }}"
                                    >
                                        <svg class="h-5 w-5 shrink-0 opacity-60" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                    </span>
                                @endif
                            @endauth
                        @endif
                    </div>
                </div>
            </header>

            <main class="flex flex-1 flex-col">
                <section class="mx-auto flex w-full max-w-6xl flex-col items-center px-4 pb-20 pt-12 text-center sm:px-6 lg:px-8 lg:pt-20">
                    <span class="serv-eyebrow inline-flex items-center gap-2 rounded-full border border-teal-600/20 bg-teal-50/90 px-4 py-1.5 dark:border-teal-500/30 dark:bg-teal-950/40">
                        <span class="h-1.5 w-1.5 rounded-full bg-teal-600 dark:bg-teal-400"></span>
                        {{ $systemTagline }}
                    </span>
                    <h1 class="font-display mt-6 max-w-4xl text-4xl font-bold leading-[1.1] tracking-tight text-slate-900 sm:text-5xl lg:text-6xl dark:text-white">
                        {{ __('Consultoria educacional') }}
                        <span class="bg-gradient-to-r from-teal-700 via-teal-600 to-indigo-600 bg-clip-text text-transparent dark:from-teal-300 dark:via-teal-400 dark:to-indigo-300">
                            {{ __('com dados municipais') }}
                        </span>
                    </h1>
                    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-slate-600 sm:text-xl dark:text-slate-300">
                        {!! __('O <strong class="font-semibold text-slate-900 dark:text-white">:app</strong> integra i-Educar, importações públicas (FUNDEB, Censo INEP, SAEB) e o mapa <strong class="font-semibold text-slate-900 dark:text-white">Horizonte</strong> num único ecossistema. Acompanhe matrículas, finanças, rede escolar e oportunidades territoriais — município a município.', ['app' => e(config('app.name', 'servlitcys'))]) !!}
                    </p>

                    @if (Route::has('login'))
                        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                            @auth
                                <a href="{{ route('dashboard.analytics') }}" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-teal-700 to-indigo-700 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:brightness-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:from-teal-600 dark:to-indigo-600">
                                    <x-ui.icon name="chart-bar" class="h-5 w-5" />
                                    {{ __('Abrir consultoria municipal') }}
                                </a>
                                <a href="{{ route('dashboard.rx') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50 dark:border-white/20 dark:bg-white/5 dark:text-white dark:hover:bg-white/10">
                                    <x-ui.icon name="clipboard-document-list" class="h-5 w-5" />
                                    {{ __('Painel RX') }}
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-teal-700 to-indigo-700 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:brightness-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:from-teal-600 dark:to-indigo-600">
                                    <x-ui.icon name="arrow-right-end-on-rectangle" class="h-5 w-5" />
                                    {{ __('Entrar na plataforma') }}
                                </a>
                            @endauth
                        </div>
                    @endif

                    <div class="relative mt-14 w-full max-w-3xl">
                        <div class="absolute -inset-4 rounded-3xl bg-gradient-to-r from-teal-500/10 via-indigo-500/10 to-teal-500/10 blur-2xl dark:from-teal-500/15 dark:to-indigo-500/15"></div>
                        <div class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white/95 p-6 shadow-lg ring-1 ring-slate-900/5 backdrop-blur-md dark:border-slate-700/80 dark:bg-slate-900/60 dark:ring-teal-500/10 sm:p-8">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                <div class="text-left">
                                    <p class="serv-eyebrow">{{ __('Pré-visualização') }}</p>
                                    <p class="font-display mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ __('Indicadores por território') }}</p>
                                </div>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <span class="rounded-lg border border-amber-500/30 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900 dark:border-amber-500/40 dark:bg-amber-950/30 dark:text-amber-100">{{ __('Ilustração') }}</span>
                                </div>
                            </div>
                            <div class="mt-6 flex h-32 items-end justify-between gap-1 sm:h-40 sm:gap-2">
                                @foreach ([40, 65, 45, 80, 55, 90, 70, 95, 60, 85, 75] as $h)
                                    <div class="flex-1 rounded-t-md bg-gradient-to-t from-teal-600/90 to-indigo-600/70 transition hover:from-teal-600 hover:to-indigo-600 dark:from-teal-500/80 dark:to-indigo-500/60" style="height: {{ $h }}%"></div>
                                @endforeach
                            </div>
                            <p class="mt-4 flex items-center justify-center gap-1.5 text-center text-xs text-slate-500 dark:text-slate-400">
                                <x-ui.icon name="chart-bar" class="h-4 w-4 shrink-0 opacity-70" />
                                {{ __('Valores reais disponíveis após autenticação, com filtros por ano letivo e escola.') }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="border-t border-slate-200/80 bg-white/70 py-14 backdrop-blur-sm dark:border-slate-800 dark:bg-slate-950/50">
                    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div class="text-center">
                            <h2 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">{{ __('Três frentes de trabalho') }}</h2>
                            <p class="mx-auto mt-3 max-w-2xl text-slate-600 dark:text-slate-400">{{ __('Consultoria analítica, cadastro operacional (RX) e inteligência territorial (Horizonte) para decisão e expansão.') }}</p>
                        </div>
                        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <article class="serv-landing-module border-teal-200/80 bg-gradient-to-br from-teal-50/90 via-white to-slate-50/80 dark:border-teal-900/50 dark:from-teal-950/30 dark:via-slate-900/40 dark:to-slate-950/80">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-100 text-teal-800 ring-1 ring-teal-200/80 dark:bg-teal-500/15 dark:text-teal-200 dark:ring-teal-700/50">
                                    <x-ui.icon name="chart-bar" class="h-6 w-6" />
                                </div>
                                <h3 class="font-display mt-4 text-lg font-semibold text-slate-900 dark:text-white">{{ __('Consultoria municipal') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ __('Painel analítico com FUNDEB, matrículas, rede escolar, inclusão, desempenho (SAEB/IDEB), finanças em tempo real e mapa de unidades — filtros por ano letivo e território.') }}</p>
                            </article>
                            <article class="serv-landing-module border-indigo-200/80 bg-gradient-to-br from-indigo-50/80 via-white to-slate-50/80 dark:border-indigo-900/50 dark:from-indigo-950/25 dark:via-slate-900/40 dark:to-slate-950/80">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-100 text-indigo-800 ring-1 ring-indigo-200/80 dark:bg-indigo-500/15 dark:text-indigo-200 dark:ring-indigo-700/50">
                                    <x-ui.icon name="clipboard-document-list" class="h-6 w-6" />
                                </div>
                                <h3 class="font-display mt-4 text-lg font-semibold text-slate-900 dark:text-white">{{ __('RX — cadastro e Censo') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ __('Visão operacional por município: volume digitado, meta de cadastro, prazo do Censo e barra de escolas exportadas ou pendentes no ano vigente.') }}</p>
                            </article>
                            <article class="serv-landing-module border-amber-200/80 bg-gradient-to-br from-amber-50/80 via-white to-slate-50/80 dark:border-amber-900/40 dark:from-amber-950/25 dark:via-slate-900/40 dark:to-slate-950/80">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100 text-amber-900 ring-1 ring-amber-200/80 dark:bg-amber-500/15 dark:text-amber-100 dark:ring-amber-700/50">
                                    <x-ui.icon name="map-pin" class="h-6 w-6" />
                                </div>
                                <h3 class="font-display mt-4 text-lg font-semibold text-slate-900 dark:text-white">{{ __('Horizonte — inteligência territorial') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ __('Mapa GIS gerencial com visão nacional por UF, scores de oportunidade, filtros interativos e metodologia transparente — dados públicos FUNDEB, Censo e SAEB para priorizar abordagem comercial.') }}</p>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="border-t border-slate-200/80 bg-slate-100/60 py-14 backdrop-blur-sm dark:border-slate-800 dark:bg-slate-950/40">
                    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div class="text-center">
                            <h2 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">{{ __('Módulos da consultoria') }}</h2>
                            <p class="mx-auto mt-3 max-w-2xl text-slate-600 dark:text-slate-400">{{ __('Abas do painel analítico — acesse com credencial autorizada.') }}</p>
                        </div>
                        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ([
                                ['icon' => 'chart-bar', 't' => __('Visão geral'), 'd' => __('Resumo executivo: matrículas, rede e alertas do município filtrado.'), 'accent' => 'border-teal-200/80 bg-teal-50/70', 'iconBg' => 'bg-teal-100 text-teal-800 dark:bg-teal-500/15 dark:text-teal-200'],
                                ['icon' => 'users', 't' => __('Matrículas'), 'd' => __('Oferta, distorção idade/série e evolução da matrícula ativa.'), 'accent' => 'border-indigo-200/80 bg-indigo-50/70', 'iconBg' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/15 dark:text-indigo-200'],
                                ['icon' => 'building-office-2', 't' => __('Unidades escolares'), 'd' => __('Mapa territorial, transporte e gestão da rede no município.'), 'accent' => 'border-violet-200/80 bg-violet-50/70', 'iconBg' => 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-200'],
                                ['icon' => 'academic-cap', 't' => __('Desempenho'), 'd' => __('Referências SAEB/IDEB e leituras cruzadas com a realidade local.'), 'accent' => 'border-amber-200/80 bg-amber-50/70', 'iconBg' => 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-100'],
                                ['icon' => 'globe-alt', 't' => __('Inclusão'), 'd' => __('Educação especial, equidade e indicadores de inclusão.'), 'accent' => 'border-emerald-200/80 bg-emerald-50/70', 'iconBg' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200'],
                                ['icon' => 'signal', 't' => __('Frequência'), 'd' => __('Acompanhamento de frequência quando a base municipal permitir.'), 'accent' => 'border-sky-200/80 bg-sky-50/70', 'iconBg' => 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200'],
                                ['icon' => 'banknotes', 't' => __('FUNDEB'), 'd' => __('Quadro de apoio à análise financeira e finanças em tempo real — não substitui relatórios oficiais.'), 'accent' => 'border-rose-200/80 bg-rose-50/70', 'iconBg' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200'],
                                ['icon' => 'map-pin', 't' => __('Horizonte'), 'd' => __('Mapa GIS com propensão comercial, benefício estimado e cobertura de dados públicos por município.'), 'accent' => 'border-amber-200/80 bg-amber-50/70', 'iconBg' => 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-100'],
                            ] as $tile)
                                <article class="serv-landing-module {{ $tile['accent'] }} dark:border-slate-700/80 dark:bg-slate-900/30">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-lg ring-1 ring-slate-900/5 {{ $tile['iconBg'] }}">
                                        <x-ui.icon :name="$tile['icon']" class="h-5 w-5" />
                                    </div>
                                    <h3 class="font-display mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ $tile['t'] }}</h3>
                                    <p class="mt-1.5 text-xs leading-relaxed text-slate-600 dark:text-slate-400">{{ $tile['d'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="border-t border-slate-200/80 bg-white/70 py-14 backdrop-blur-sm dark:border-slate-800 dark:bg-slate-950/60">
                    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div class="text-center">
                            <h2 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">{{ __('Por que usar o painel') }}</h2>
                        </div>
                        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ([
                                ['icon' => 'map-pin', 'title' => __('Território e comparação'), 'text' => __('Indicadores agregados por município e mapa Horizonte para priorizar ações, comparar realidades e orientar expansão.')],
                                ['icon' => 'chart-bar', 'title' => __('Uma fonte integrada'), 'text' => __('i-Educar, importações públicas (FUNDEB, Censo INEP, SAEB) e regras da plataforma num só fluxo de leitura.')],
                                ['icon' => 'shield-check', 'title' => __('Acesso controlado'), 'text' => __('Perfis municipal e administrativo, com trilha de uso restrito à gestão autorizada (LGPD).')],
                            ] as $card)
                                <article class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm dark:border-slate-700/80 dark:bg-slate-900/40">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-50 text-teal-800 ring-1 ring-teal-200/80 dark:bg-teal-950/40 dark:text-teal-200 dark:ring-teal-800/60">
                                        <x-ui.icon :name="$card['icon']" class="h-6 w-6" />
                                    </div>
                                    <h3 class="font-display mt-4 text-lg font-semibold text-slate-900 dark:text-white">{{ $card['title'] }}</h3>
                                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $card['text'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="border-t border-slate-200/80 bg-slate-100/50 py-14 dark:border-slate-800 dark:bg-slate-950/70">
                    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                        <div class="flex items-center justify-center gap-2">
                            <x-ui.icon name="document-text" class="h-6 w-6 text-teal-700 dark:text-teal-400" />
                            <h2 class="font-display text-center text-xl font-bold text-slate-900 sm:text-2xl dark:text-white">{{ __('Interpretação responsável dos dados') }}</h2>
                        </div>
                        <p class="mx-auto mt-3 text-center text-sm text-slate-600 dark:text-slate-400">{{ __('Esta página é institucional — não exibe dados da sua rede.') }}</p>
                        <ul class="mt-8 space-y-4 text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                            @foreach ([
                                __('Os indicadores refletem o cadastro na base municipal (i-Educar) e importações configuradas, sujeitos à qualidade do registro.'),
                                __('Para fins legais ou oficiais (Censo INEP, tribunais), utilize sempre as fontes previstas em legislação.'),
                                __('Os valores correspondem ao momento da consulta e aos filtros aplicados (ano letivo, escola, segmento).'),
                                __('Dados pessoais: tratamento conforme LGPD; acesso apenas a usuários autorizados.'),
                            ] as $i => $text)
                                <li class="flex gap-3">
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-teal-100 text-xs font-bold text-teal-800 ring-1 ring-teal-300/60 dark:bg-teal-950/50 dark:text-teal-200 dark:ring-teal-700/50" aria-hidden="true">{{ $i + 1 }}</span>
                                    <span>{{ $text }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            </main>

            <footer class="border-t border-slate-200/80 bg-white/90 py-8 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/90">
                <div class="mx-auto flex max-w-6xl flex-col items-center justify-center gap-2 px-4 text-center sm:px-6 lg:px-8">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        © {{ date('Y') }}
                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ config('app.name') }}</span>
                        ·
                        <a href="https://serventecassessoria.com.br" target="_blank" rel="noopener noreferrer" class="font-semibold text-teal-700 underline decoration-teal-600/40 underline-offset-2 hover:text-teal-900 dark:text-teal-300 dark:hover:text-teal-200">
                            {{ $brand['serventec_name'] ?? 'Serventec Assessoria' }}
                        </a>
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-500 flex flex-wrap items-center justify-center gap-x-2 gap-y-1">
                        <x-product-version-badge class="mx-auto sm:mx-0" />
                        <a href="{{ route('legal.privacy') }}" class="text-teal-700 hover:underline dark:text-teal-400">{{ __('Política de privacidade') }}</a>
                        @if (filled($brand['developer_name'] ?? null))
                            <span aria-hidden="true"> · </span>
                            {{ __('Desenvolvimento:') }}
                            <a href="{{ $brand['developer_github'] ?? 'https://github.com/jadergabriel' }}" target="_blank" rel="noopener noreferrer" class="text-teal-700 hover:underline dark:text-teal-400">
                                {{ $brand['developer_name'] }}
                            </a>
                        @endif
                    </p>
                </div>
            </footer>

            <x-legal-cookie-banner />

            <x-scroll-to-top />
        </div>
    </body>
</html>
