@php
    $queueTotal = (int) ($ops['sync_pending'] ?? 0) + (int) ($ops['pdf_pending'] ?? 0);
    $syncFailed = (int) ($ops['sync_failed_24h'] ?? 0);
@endphp

<section aria-labelledby="home-actions" class="space-y-6">
    <div>
        <h3 id="home-actions" class="font-display text-lg font-semibold text-serv-navy dark:text-slate-100">{{ __('Acesso rápido') }}</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 max-w-2xl">
            {{ __('Atalhos alinhados ao fluxo de dados: consultoria a partir do i-Educar, operação em filas e monitorização da plataforma.') }}
        </p>
    </div>

    <div class="space-y-5">
        {{-- Consultoria --}}
        <div class="serv-home-action-group">
            <p class="serv-home-action-group__label">
                <span class="serv-home-action-group__dot serv-home-action-group__dot--teal" aria-hidden="true"></span>
                {{ __('Consultoria e relatórios') }}
            </p>
            <div class="serv-home-action-grid serv-home-action-grid--featured">
                <a href="{{ route('dashboard.analytics') }}" class="serv-home-action serv-home-action--primary group">
                    <span class="serv-home-action__icon serv-home-action__icon--teal" aria-hidden="true">
                        <x-ui.icon name="chart-bar" class="h-6 w-6" />
                    </span>
                    <span class="serv-home-action__body">
                        <span class="serv-home-action__title">{{ __('Painel analítico') }}</span>
                        <span class="serv-home-action__desc">{{ __('FUNDEB, matrículas, rede, Censo e discrepâncias por município e ano letivo.') }}</span>
                        <span class="serv-home-action__ref">{{ __('Saída do fluxo: Consultoria') }}</span>
                    </span>
                    <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 text-teal-600/70 group-hover:text-teal-700 dark:text-teal-400" />
                </a>
            </div>
        </div>

        {{-- Municípios --}}
        <div class="serv-home-action-group">
            <p class="serv-home-action-group__label">
                <span class="serv-home-action-group__dot serv-home-action-group__dot--violet" aria-hidden="true"></span>
                {{ __('Municípios e ligações') }}
            </p>
            <div class="serv-home-action-grid">
                <a href="{{ route('cities.index') }}" class="serv-home-action group">
                    <span class="serv-home-action__icon serv-home-action__icon--violet" aria-hidden="true">
                        <x-ui.icon name="map-pin" class="h-6 w-6" />
                    </span>
                    <span class="serv-home-action__body">
                        <span class="serv-home-action__title">{{ __('Cidades') }}</span>
                        <span class="serv-home-action__desc">{{ __('Cadastro, IBGE, credenciais e activação no mapa.') }}</span>
                        <span class="serv-home-action__ref">{{ __('Liga ao mapa abaixo') }}</span>
                    </span>
                    <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 opacity-40 group-hover:opacity-70" />
                </a>
                <a href="{{ route('admin.connections.index') }}" class="serv-home-action group">
                    <span class="serv-home-action__icon serv-home-action__icon--violet" aria-hidden="true">
                        <x-ui.icon name="circle-stack" class="h-6 w-6" />
                    </span>
                    <span class="serv-home-action__body">
                        <span class="serv-home-action__title">{{ __('Conexões i-Educar') }}</span>
                        <span class="serv-home-action__desc">{{ __('Testar ligação, driver e versão da base por município.') }}</span>
                        <span class="serv-home-action__ref">{{ __('Zona municipal no fluxo') }}</span>
                    </span>
                    <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 opacity-40 group-hover:opacity-70" />
                </a>
                <a href="{{ route('admin.ieducar-compatibility.index') }}" class="serv-home-action group">
                    <span class="serv-home-action__icon serv-home-action__icon--violet" aria-hidden="true">
                        <x-ui.icon name="squares-2x2" class="h-6 w-6" />
                    </span>
                    <span class="serv-home-action__body">
                        <span class="serv-home-action__title">{{ __('Compatibilidade i-Educar') }}</span>
                        <span class="serv-home-action__desc">{{ __('Tabelas, métricas de cadastro e importação FUNDEB.') }}</span>
                        <span class="serv-home-action__ref">{{ __('Pré-requisito da consultoria') }}</span>
                    </span>
                    <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 opacity-40 group-hover:opacity-70" />
                </a>
            </div>
        </div>

        {{-- Operação --}}
        <div class="serv-home-action-group">
            <p class="serv-home-action-group__label">
                <span class="serv-home-action-group__dot serv-home-action-group__dot--sky" aria-hidden="true"></span>
                {{ __('Operação da plataforma') }}
            </p>
            <div class="serv-home-action-grid">
                <a href="{{ route('admin.sync-queue.index') }}" class="serv-home-action group @if ($queueTotal > 0 || $syncFailed > 0) serv-home-action--alert @endif">
                    <span class="serv-home-action__icon serv-home-action__icon--amber" aria-hidden="true">
                        <x-ui.icon name="queue-list" class="h-6 w-6" />
                    </span>
                    <span class="serv-home-action__body">
                        <span class="serv-home-action__title">{{ __('Filas de processamento') }}</span>
                        <span class="serv-home-action__desc">
                            @if ($queueTotal > 0)
                                {{ __(':n em fila (sync :sync · PDF :pdf).', [
                                    'n' => number_format($queueTotal),
                                    'sync' => number_format($ops['sync_pending'] ?? 0),
                                    'pdf' => number_format($ops['pdf_pending'] ?? 0),
                                ]) }}
                            @else
                                {{ __('Sincronização admin, geo, pedagógico e exportação PDF.') }}
                            @endif
                        </span>
                        <span class="serv-home-action__ref">{{ __('Saída do fluxo: Filas') }}</span>
                    </span>
                    <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 opacity-40 group-hover:opacity-70" />
                </a>
                <a href="{{ route('pulse') }}" class="serv-home-action group">
                    <span class="serv-home-action__icon serv-home-action__icon--sky" aria-hidden="true">
                        <x-ui.icon name="computer-desktop" class="h-6 w-6" />
                    </span>
                    <span class="serv-home-action__body">
                        <span class="serv-home-action__title">{{ __('Monitorização (Pulse)') }}</span>
                        <span class="serv-home-action__desc">{{ __('Pedidos lentos, erros, filas e uso da aplicação em tempo real.') }}</span>
                        <span class="serv-home-action__ref">{{ __('Hub :app no fluxo', ['app' => config('app.name')]) }}</span>
                    </span>
                    <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 opacity-40 group-hover:opacity-70" />
                </a>
            </div>
        </div>

        {{-- Equipa --}}
        <div class="serv-home-action-group">
            <p class="serv-home-action-group__label">
                <span class="serv-home-action-group__dot serv-home-action-group__dot--slate" aria-hidden="true"></span>
                {{ __('Equipa') }}
            </p>
            <div class="serv-home-action-grid serv-home-action-grid--compact">
                <a href="{{ route('users.index') }}" class="serv-home-action group">
                    <span class="serv-home-action__icon serv-home-action__icon--slate" aria-hidden="true">
                        <x-ui.icon name="users" class="h-6 w-6" />
                    </span>
                    <span class="serv-home-action__body">
                        <span class="serv-home-action__title">{{ __('Utilizadores') }}</span>
                        <span class="serv-home-action__desc">{{ __('Contas, perfis, municípios associados e sessões.') }}</span>
                    </span>
                    <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 opacity-40 group-hover:opacity-70" />
                </a>
            </div>
        </div>
    </div>
</section>
