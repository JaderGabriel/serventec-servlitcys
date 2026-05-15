<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ config('app.name') }} — plataforma de dados educacionais por município.">
        <title>{{ config('app.name', 'servlitcys') }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen antialiased font-sans text-slate-100 selection:bg-cyan-400/30 selection:text-white">
        {{-- Fundo: gradiente + orbs + grelha --}}
        <div class="fixed inset-0 -z-10 bg-slate-950">
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-950 via-slate-950 to-cyan-950"></div>
            <div class="absolute top-[-20%] left-[-10%] h-[50vh] w-[50vh] rounded-full bg-violet-600/25 blur-[100px] animate-float"></div>
            <div class="absolute bottom-[-15%] right-[-5%] h-[45vh] w-[45vh] rounded-full bg-cyan-500/20 blur-[90px] animate-float-delayed"></div>
            <div class="absolute top-1/3 right-1/4 h-64 w-64 rounded-full bg-fuchsia-600/15 blur-[80px]"></div>
            <div class="absolute inset-0 bg-grid-edu opacity-60"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent"></div>
        </div>

        <div class="relative flex min-h-screen flex-col">
            <header class="border-b border-white/10 bg-slate-950/40 backdrop-blur-xl">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ url('/') }}" class="group flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-indigo-600 shadow-lg shadow-indigo-500/25 ring-1 ring-white/20 transition group-hover:scale-105">
                            <x-application-logo class="h-6 w-6 text-white shrink-0" />
                        </span>
                        <span class="font-display text-lg font-semibold tracking-tight text-white sm:text-xl">
                            {{ config('app.name', 'servlitcys') }}
                        </span>
                    </a>
                    @php
                        $waDigits = config('services.serventec.whatsapp');
                        $waHref = filled($waDigits)
                            ? 'https://wa.me/'.preg_replace('/\D+/', '', (string) $waDigits)
                            : null;
                    @endphp
                    @if (Route::has('login'))
                        <nav class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                            @auth
                                <a href="{{ Auth::user()->homeUrl() }}" class="rounded-lg border border-white/15 bg-white/5 px-4 py-2 text-sm font-medium text-white transition hover:border-white/25 hover:bg-white/10">
                                    {{ Auth::user()->canViewAdminDashboard() ? __('Painel') : __('Análise') }}
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="rounded-lg border border-white/20 bg-white/5 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400">
                                    {{ __('Entrar') }}
                                </a>
                                @if ($waHref)
                                    <a href="{{ $waHref }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg bg-[#25D366] px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-900/30 transition hover:brightness-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400">
                                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                        {{ __('Contactar Serventec') }}
                                    </a>
                                @else
                                    <span class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg border border-dashed border-white/25 bg-white/5 px-4 py-2 text-sm font-medium text-white/50" title="{{ __('Configure SERVENTEC_WHATSAPP_NUMBER no .env para ativar o WhatsApp.') }}">
                                        <svg class="h-4 w-4 shrink-0 text-white/40" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                        {{ __('WhatsApp Serventec') }}
                                    </span>
                                @endif
                            @endauth
                        </nav>
                    @endif
                </div>
            </header>

            <main class="flex flex-1 flex-col">
                <section class="mx-auto flex w-full max-w-6xl flex-col items-center px-4 pb-24 pt-16 text-center sm:px-6 lg:px-8 lg:pt-24">
                    <span class="inline-flex items-center gap-2 rounded-full border border-cyan-400/30 bg-cyan-500/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-cyan-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-cyan-400 shadow-[0_0_8px_rgba(34,211,238,0.9)]"></span>
                        {{ __('Plataforma educacional') }}
                    </span>
                    <h1 class="font-display mt-8 max-w-4xl text-4xl font-bold leading-[1.1] tracking-tight text-white sm:text-5xl lg:text-6xl">
                        {{ __('Dados que') }}
                        <span class="bg-gradient-to-r from-cyan-300 via-indigo-300 to-fuchsia-300 bg-clip-text text-transparent"> {{ __('educam') }} </span>
                        {{ __('as cidades') }}
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-300 sm:text-xl">
                        {!! __('O <strong class="font-semibold text-white">:app</strong> reúne indicadores por município para apoiar análise, planeamento e decisões. Explore painéis, compare territórios e tenha uma visão consolidada do sistema educativo na sua região.', ['app' => e(config('app.name', 'servlitcys'))]) !!}
                    </p>

                    {{-- Ilustração decorativa (SVG) --}}
                    <div class="relative mt-16 w-full max-w-3xl">
                        <div class="absolute -inset-4 rounded-3xl bg-gradient-to-r from-cyan-500/20 via-indigo-500/10 to-fuchsia-500/20 blur-2xl"></div>
                        <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-slate-900/50 p-6 shadow-2xl backdrop-blur-md sm:p-8">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                <div class="text-left">
                                    <p class="text-xs font-medium uppercase tracking-wider text-slate-400">{{ __('Indicadores') }}</p>
                                    <p class="font-display mt-1 text-2xl font-bold text-white">{{ __('Evolução por território') }}</p>
                                </div>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <span class="rounded-lg border border-amber-400/40 bg-amber-500/15 px-3 py-1 text-xs font-semibold text-amber-200">{{ __('Exemplo visual') }}</span>
                                    <span class="rounded-lg border border-slate-500/40 bg-slate-800/80 px-3 py-1 text-xs font-medium text-slate-300">{{ __('Sem dados reais') }}</span>
                                </div>
                            </div>
                            <div class="mt-6 flex h-32 items-end justify-between gap-1 sm:h-40 sm:gap-2">
                                @foreach ([40, 65, 45, 80, 55, 90, 70, 95, 60, 85, 75] as $h)
                                    <div class="flex-1 rounded-t-md bg-gradient-to-t from-cyan-600/80 to-indigo-500/60 transition hover:from-cyan-500 hover:to-indigo-400" style="height: {{ $h }}%"></div>
                                @endforeach
                            </div>
                            <p class="mt-4 text-center text-xs text-slate-500">{{ __('Barras apenas decorativas. Os valores reais aparecem no painel analítico após entrar com a sua conta.') }}</p>
                        </div>
                    </div>
                </section>

                <section class="border-t border-white/10 bg-slate-950/40 py-16 backdrop-blur-sm">
                    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div class="text-center">
                            <h2 class="font-display text-2xl font-bold text-white sm:text-3xl">{{ __('Áreas do painel analítico') }}</h2>
                            <p class="mx-auto mt-3 max-w-2xl text-slate-400">{{ __('Temas alinhados aos módulos disponíveis após autenticação — sem números nesta página.') }}</p>
                        </div>
                        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ([
                                ['t' => __('Visão geral'), 'd' => __('Resumo e indicadores-chave por município e filtros.'), 'c' => 'from-cyan-500/20 to-cyan-600/5 ring-cyan-400/20 text-cyan-200'],
                                ['t' => __('Matrículas'), 'd' => __('Oferta, distorção idade/série e leituras da rede.'), 'c' => 'from-indigo-500/20 to-indigo-600/5 ring-indigo-400/20 text-indigo-200'],
                                ['t' => __('Rede & oferta'), 'd' => __('Estrutura da rede e oferta educativa no território.'), 'c' => 'from-violet-500/20 to-violet-600/5 ring-violet-400/20 text-violet-200'],
                                ['t' => __('Unidades escolares'), 'd' => __('Mapa, transporte e apoio à gestão das escolas.'), 'c' => 'from-fuchsia-500/20 to-fuchsia-600/5 ring-fuchsia-400/20 text-fuchsia-200'],
                                ['t' => __('Inclusão & diversidade'), 'd' => __('Educação especial, equidade e indicadores de inclusão.'), 'c' => 'from-emerald-500/20 to-emerald-600/5 ring-emerald-400/20 text-emerald-200'],
                                ['t' => __('Desempenho'), 'd' => __('Referências e leituras cruzadas com a realidade local.'), 'c' => 'from-amber-500/20 to-amber-600/5 ring-amber-400/20 text-amber-200'],
                                ['t' => __('Frequência'), 'd' => __('Acompanhamento de frequência escolar quando a base permitir.'), 'c' => 'from-sky-500/20 to-sky-600/5 ring-sky-400/20 text-sky-200'],
                                ['t' => __('FUNDEB'), 'd' => __('Quadro de apoio à análise — não substitui relatórios oficiais.'), 'c' => 'from-rose-500/20 to-rose-600/5 ring-rose-400/20 text-rose-200'],
                            ] as $tile)
                                <article class="rounded-xl border border-white/10 bg-gradient-to-b {{ $tile['c'] }} p-4 text-left ring-1 transition hover:border-white/20">
                                    <h3 class="font-display text-sm font-semibold text-white">{{ $tile['t'] }}</h3>
                                    <p class="mt-2 text-xs leading-relaxed text-slate-400">{{ $tile['d'] }}</p>
                                </article>
                            @endforeach
                        </div>
                        @if (Route::has('login'))
                            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                                @auth
                                    <a href="{{ route('dashboard.analytics') }}" class="inline-flex items-center justify-center rounded-lg bg-gradient-to-r from-cyan-500 to-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-900/30 transition hover:brightness-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400">
                                        {{ __('Abrir painel analítico') }}
                                    </a>
                                @else
                                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-lg border border-white/20 bg-white/5 px-5 py-2.5 text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400">
                                        {{ __('Entrar para explorar estes módulos') }}
                                    </a>
                                @endauth
                            </div>
                        @endif
                    </div>
                </section>

                <section class="border-t border-white/10 bg-slate-950/50 py-20 backdrop-blur-sm">
                    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div class="text-center">
                            <h2 class="font-display text-2xl font-bold text-white sm:text-3xl">{{ __('Feito para quem decide com dados') }}</h2>
                            <p class="mx-auto mt-3 max-w-2xl text-slate-400">{{ __('Ferramentas pensadas para equipas educativas e gestão municipal.') }}</p>
                        </div>
                        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <article class="group rounded-2xl border border-white/10 bg-gradient-to-b from-white/5 to-transparent p-6 transition hover:border-cyan-500/30 hover:shadow-lg hover:shadow-cyan-500/10">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-500/15 text-cyan-300 ring-1 ring-cyan-400/20">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" /></svg>
                                </div>
                                <h3 class="font-display mt-4 text-lg font-semibold text-white">{{ __('Cidades e território') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ __('Indicadores agregados por município para comparar realidades e priorizar oportunidades.') }}</p>
                            </article>
                            <article class="group rounded-2xl border border-white/10 bg-gradient-to-b from-white/5 to-transparent p-6 transition hover:border-indigo-500/30 hover:shadow-lg hover:shadow-indigo-500/10">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-300 ring-1 ring-indigo-400/20">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                </div>
                                <h3 class="font-display mt-4 text-lg font-semibold text-white">{{ __('Painéis analíticos') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ __('Visualize tendências e métricas num só lugar, com filtros que aceleram a leitura.') }}</p>
                            </article>
                            <article class="group rounded-2xl border border-white/10 bg-gradient-to-b from-white/5 to-transparent p-6 transition hover:border-fuchsia-500/30 hover:shadow-lg hover:shadow-fuchsia-500/10 sm:col-span-2 lg:col-span-1">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-fuchsia-500/15 text-fuchsia-300 ring-1 ring-fuchsia-400/20">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                </div>
                                <h3 class="font-display mt-4 text-lg font-semibold text-white">{{ __('Decisões informadas') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ __('Apoie planeamento e políticas públicas com base em dados educacionais consolidados.') }}</p>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="border-t border-white/10 bg-slate-950/70 py-16 backdrop-blur-sm">
                    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                        <h2 class="font-display text-center text-xl font-bold text-white sm:text-2xl">{{ __('Confiança e interpretação dos dados') }}</h2>
                        <p class="mx-auto mt-3 text-center text-sm text-slate-400">{{ __('Informação institucional — esta página não exibe valores da sua rede.') }}</p>
                        <ul class="mt-8 space-y-4 text-sm leading-relaxed text-slate-300">
                            <li class="flex gap-3">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cyan-500/20 text-cyan-300 ring-1 ring-cyan-400/30" aria-hidden="true">1</span>
                                <span>{{ __('Os indicadores do painel refletem o registo administrativo na base municipal (por exemplo, i-Educar), sujeito à qualidade do cadastro e às regras configuradas na plataforma.') }}</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cyan-500/20 text-cyan-300 ring-1 ring-cyan-400/30" aria-hidden="true">2</span>
                                <span>{{ __('Para fins legais ou oficiais (Censo Escolar, INEP, teses perante tribunais), use sempre as fontes e relatórios previstos na legislação.') }}</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cyan-500/20 text-cyan-300 ring-1 ring-cyan-400/30" aria-hidden="true">3</span>
                                <span>{{ __('Os valores apresentados no painel correspondem ao estado da base no momento da consulta e aos filtros escolhidos (ano, escola, segmento, etc.).') }}</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cyan-500/20 text-cyan-300 ring-1 ring-cyan-400/30" aria-hidden="true">4</span>
                                <span>{{ __('O tratamento de dados pessoais segue a legislação aplicável (incluindo LGPD no Brasil). O acesso ao painel é restrito a utilizadores autorizados.') }}</span>
                            </li>
                        </ul>
                    </div>
                </section>
            </main>

            <footer class="border-t border-white/10 bg-slate-950/80 py-10 backdrop-blur-xl">
                <div class="mx-auto flex max-w-6xl flex-col items-center justify-center gap-3 px-4 text-center sm:px-6 lg:px-8">
                    <p class="text-sm text-slate-500">
                        © {{ date('Y') }} Serventec Analítico by
                        <a href="https://serventecassessoria.com.br" target="_blank" rel="noopener noreferrer" class="font-semibold text-cyan-300 underline decoration-cyan-500/50 underline-offset-2 transition hover:text-cyan-200">
                             Serventec Assessoria
                        </a>
                    </p>
                    <p class="text-sm text-slate-400">
                        Powered by:
                        <a href="https://github.com/jadergabriel" target="_blank" rel="noopener noreferrer" class="font-semibold text-cyan-300 underline decoration-cyan-500/50 underline-offset-2 transition hover:text-cyan-200">
                            Jader Gabriel
                        </a>
                        

                    </p>
                </div>
            </footer>
        </div>
    </body>
</html>
