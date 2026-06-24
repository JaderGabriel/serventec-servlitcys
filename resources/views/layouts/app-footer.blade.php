{{-- Rodapé da área autenticada: barra compacta (marca, versão, links úteis). --}}
@php
    $pulseFooter = $pulseFooter ?? false;
    $brand = config('analytics.pdf_report.brand', []);
    $systemName = $brand['system_name'] ?? config('app.name');
    $systemTagline = $brand['system_tagline'] ?? __('Plataforma educacional municipal');
    $serventecName = $brand['serventec_name'] ?? 'Serventec Assessoria';
    $serventecUrl = rtrim((string) ($brand['serventec_url'] ?? 'https://analise.serventecassessoria.com.br/'), '/') . '/';
    $productVersion = trim((string) config('documentation.product.version', ''));
    $productBadge = \App\Support\Product\ProductVersion::badge();
    $appEnv = (string) config('app.env', 'production');
    $isProduction = $appEnv === 'production';
    $whatsAppDigits = preg_replace('/\D+/', '', (string) config('services.serventec.whatsapp', ''));
    $developerName = trim((string) ($brand['developer_name'] ?? ''));
    $developerGithub = rtrim((string) ($brand['developer_github'] ?? ''), '/');
    $githubRepository = rtrim((string) config('documentation.github.repository', ''), '/');
    $user = Auth::user();
    $municipalityLabel = $user->footerMunicipalityLabel();

    $footerLinks = array_values(array_filter([
        [
            'show' => true,
            'href' => route('profile.edit'),
            'label' => __('Perfil'),
            'active' => request()->routeIs('profile.*'),
        ],
        [
            'show' => $user->canViewDocumentation(),
            'href' => route($user->isAdmin() ? 'admin.documentation.index' : 'documentation.index'),
            'label' => __('Documentação'),
            'active' => request()->routeIs(['admin.documentation.*', 'documentation.*']),
        ],
        [
            'show' => $user->canViewSyncQueue(),
            'href' => route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index'),
            'label' => __('Filas'),
            'active' => request()->routeIs(['admin.sync-queue.*', 'sync-queue.*']),
        ],
        [
            'show' => strlen($whatsAppDigits) >= 10,
            'href' => 'https://wa.me/' . $whatsAppDigits,
            'label' => __('Suporte'),
            'active' => false,
            'external' => true,
        ],
        [
            'show' => Route::has('legal.privacy'),
            'href' => route('legal.privacy'),
            'label' => __('Privacidade'),
            'active' => request()->routeIs('legal.privacy'),
        ],
    ], fn (array $link): bool => (bool) ($link['show'] ?? false)));
@endphp

<footer
    role="contentinfo"
    @class([
        'serv-app-footer',
        'serv-app-footer--pulse' => $pulseFooter,
    ])
>
    <div class="serv-app-footer__accent" aria-hidden="true"></div>

    <div @class([
        'serv-page-shell',
        'py-3 sm:py-3.5' => ! $pulseFooter,
        'py-3' => $pulseFooter,
        'max-w-[min(100%,100rem)] px-4 sm:px-6 lg:px-10 xl:px-12' => $pulseFooter,
    ])>
        @if ($pulseFooter)
            <div class="text-center text-xs text-slate-500 dark:text-slate-400 space-y-1">
                <p>
                    © {{ date('Y') }}
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $systemName }}</span>
                    @if ($productVersion !== '' || filled($productBadge['release_tag'] ?? null))
                        <span class="serv-app-footer__sep" aria-hidden="true">·</span>
                        <x-product-version-badge />
                    @endif
                </p>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 max-w-2xl mx-auto leading-relaxed">
                    {{ __('Monitorização em tempo real (Laravel Pulse). Métricas agregadas conforme a configuração do servidor.') }}
                </p>
                @if ($developerName !== '' || $githubRepository !== '')
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">
                        @if ($developerName !== '')
                            {{ __('Desenvolvimento:') }}
                            <a
                                href="{{ $developerGithub !== '' ? $developerGithub : 'https://github.com/jadergabriel' }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="serv-link font-medium"
                            >{{ $developerName }}</a>
                        @endif
                        @if ($developerName !== '' && $githubRepository !== '')
                            <span class="serv-app-footer__sep" aria-hidden="true">·</span>
                        @endif
                        @if ($githubRepository !== '')
                            <a
                                href="{{ $githubRepository }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="serv-link font-medium"
                            >GitHub</a>
                        @endif
                    </p>
                @endif
            </div>
        @else
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between lg:gap-6">
                <div class="min-w-0 space-y-0.5">
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        <span class="font-semibold text-slate-800 dark:text-slate-100">© {{ date('Y') }} {{ $systemName }}</span>
                        <span class="serv-app-footer__sep" aria-hidden="true">·</span>
                        <span>{{ $systemTagline }}</span>
                    </p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Operado por') }}
                        <a
                            href="{{ $serventecUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="serv-link font-medium"
                        >{{ $serventecName }}</a>
                        @if ($developerName !== '')
                            <span class="serv-app-footer__sep" aria-hidden="true">·</span>
                            {{ __('Desenvolvimento:') }}
                            <a
                                href="{{ $developerGithub !== '' ? $developerGithub : 'https://github.com/jadergabriel' }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="serv-link font-medium"
                            >{{ $developerName }}</a>
                        @endif
                        @if ($githubRepository !== '')
                            <span class="serv-app-footer__sep" aria-hidden="true">·</span>
                            <a
                                href="{{ $githubRepository }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="serv-link font-medium"
                                title="{{ __('Código-fonte no GitHub') }}"
                            >GitHub</a>
                        @endif
                    </p>
                    @if (filled($municipalityLabel))
                        <p class="text-[11px] text-blue-800/90 dark:text-blue-300/90 leading-relaxed">
                            <x-ui.icon name="map-pin" class="inline h-3.5 w-3.5 -mt-px me-0.5 opacity-80" />
                            <span class="font-medium">{{ __('Município:') }}</span>
                            {{ $municipalityLabel }}
                        </p>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    @if ($productVersion !== '' || filled($productBadge['release_tag'] ?? null))
                        <x-product-version-badge />
                    @endif
                    @unless ($isProduction)
                        <span
                            @class([
                                'serv-app-footer__env',
                                'serv-app-footer__env--staging' => $appEnv === 'staging',
                                'serv-app-footer__env--local' => $appEnv !== 'staging',
                            ])
                            title="{{ __('Ambiente da aplicação') }}"
                        >{{ $appEnv }}</span>
                    @endunless
                </div>

                @if (count($footerLinks) > 0)
                    <nav class="serv-app-footer__nav lg:justify-end" aria-label="{{ __('Links úteis') }}">
                        @foreach ($footerLinks as $index => $link)
                            @if ($index > 0)
                                <span class="serv-app-footer__sep" aria-hidden="true">·</span>
                            @endif
                            <a
                                href="{{ $link['href'] }}"
                                @class(['font-semibold underline' => $link['active'] ?? false])
                                @if (! empty($link['external']))
                                    target="_blank"
                                    rel="noopener noreferrer"
                                @endif
                            >{{ $link['label'] }}</a>
                        @endforeach
                    </nav>
                @endif
            </div>
        @endif
    </div>
</footer>
