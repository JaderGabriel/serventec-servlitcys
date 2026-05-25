<x-layouts.legal :title="__('Política de privacidade')">
    <article class="serv-panel px-6 py-6 sm:px-8 sm:py-8">
        <p class="serv-eyebrow">{{ __('Proteção de dados') }}</p>
        <h1 class="font-display mt-2 text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">
            {{ __('Política de privacidade') }}
        </h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
            {{ __('Última atualização:') }} {{ $lastUpdated }}
            <span class="serv-app-footer__sep mx-1" aria-hidden="true">·</span>
            {{ $systemName }}
        </p>

        <div class="mt-8 space-y-6 text-sm leading-relaxed text-slate-700 dark:text-slate-300">
            <section>
                <h2 class="font-display text-base font-semibold text-slate-900 dark:text-white">{{ __('1. Quem somos') }}</h2>
                <p class="mt-2">
                    {!! __('A plataforma <strong>:app</strong> é operada no âmbito da consultoria educacional municipal, com tratamento de dados restrito a utilizadores autorizados e bases vinculadas por município. A operação comercial é da :serventec.', [
                        'app' => e($systemName),
                        'serventec' => e($serventecName),
                    ]) !!}
                </p>
            </section>

            <section>
                <h2 class="font-display text-base font-semibold text-slate-900 dark:text-white">{{ __('2. Dados tratados') }}</h2>
                <ul class="mt-2 list-disc space-y-1.5 ps-5">
                    <li>{{ __('Dados de conta: nome, e-mail, telefone, perfil de acesso e municípios vinculados.') }}</li>
                    <li>{{ __('Dados educacionais agregados ou cadastrais provenientes da base municipal (ex.: i-Educar) e importações públicas configuradas (Censo INEP, FUNDEB, etc.).') }}</li>
                    <li>{{ __('Registos técnicos: data e hora de acesso, endereço IP, sessão e logs de operação para segurança e suporte.') }}</li>
                </ul>
            </section>

            <section>
                <h2 class="font-display text-base font-semibold text-slate-900 dark:text-white">{{ __('3. Finalidades e bases legais (LGPD)') }}</h2>
                <ul class="mt-2 list-disc space-y-1.5 ps-5">
                    <li>{{ __('Execução de contrato ou procedimentos preliminares: prestação do serviço de painel e consultoria contratada pelo ente ou parceiro.') }}</li>
                    <li>{{ __('Legítimo interesse: melhoria da plataforma, prevenção de fraude e continuidade operacional, com medidas de minimização.') }}</li>
                    <li>{{ __('Obrigação legal ou regulatória: quando aplicável à gestão educacional e prestação de contas.') }}</li>
                    <li>{{ __('Consentimento: quando recolhido de forma específica (ex.: comunicações opcionais).') }}</li>
                </ul>
            </section>

            <section>
                <h2 class="font-display text-base font-semibold text-slate-900 dark:text-white">{{ __('4. Partilha e armazenamento') }}</h2>
                <p class="mt-2">
                    {{ __('Os dados não são vendidos. O acesso interno obedece ao perfil do utilizador (administrador, plataforma ou municipal). Prestadores de infraestrutura (hospedagem, e-mail) podem processar dados sob contrato e apenas na medida necessária.') }}
                </p>
            </section>

            <section>
                <h2 class="font-display text-base font-semibold text-slate-900 dark:text-white">{{ __('5. Retenção e segurança') }}</h2>
                <p class="mt-2">
                    {{ __('Conservamos os dados pelo tempo necessário à finalidade e às obrigações legais. Aplicamos controlo de acesso autenticado, comunicação cifrada (HTTPS) e boas práticas de gestão de credenciais nas bases municipais.') }}
                </p>
            </section>

            <section>
                <h2 class="font-display text-base font-semibold text-slate-900 dark:text-white">{{ __('6. Direitos do titular') }}</h2>
                <p class="mt-2">
                    {{ __('Nos termos da Lei nº 13.709/2018 (LGPD), pode solicitar confirmação de tratamento, acesso, correção, anonimização, portabilidade, eliminação e informação sobre partilhas. Pedidos devem ser feitos pelo canal indicado abaixo, com identificação suficiente.') }}
                </p>
            </section>

            <section>
                <h2 class="font-display text-base font-semibold text-slate-900 dark:text-white">{{ __('7. Contacto') }}</h2>
                <p class="mt-2">
                    @if (filled($privacyContactEmail))
                        {{ __('Encarregado / canal de privacidade:') }}
                        <a href="mailto:{{ $privacyContactEmail }}" class="serv-link font-medium">{{ $privacyContactEmail }}</a>
                    @else
                        {{ __('Para exercer direitos ou esclarecer dúvidas sobre privacidade, contacte a :serventec pelos canais oficiais de atendimento ou o administrador da sua conta na plataforma.', ['serventec' => $serventecName]) }}
                    @endif
                </p>
            </section>

            <section class="rounded-lg border border-slate-200/90 bg-slate-50/80 px-4 py-3 dark:border-slate-700/80 dark:bg-slate-900/50">
                <p class="text-xs text-slate-600 dark:text-slate-400">
                    {{ __('Os indicadores do painel refletem cadastros administrativos e não substituem documentos oficiais do Censo Escolar ou do INEP. O uso da plataforma implica aceitação desta política na versão vigente na data de acesso.') }}
                </p>
            </section>
        </div>

        <div class="mt-8 flex flex-wrap gap-3 border-t border-slate-200/90 pt-6 dark:border-slate-700/80">
            <a href="{{ url('/') }}" class="serv-link text-sm font-medium">{{ __('Página inicial') }}</a>
            @auth
                <a href="{{ Auth::user()->homeUrl() }}" class="serv-link text-sm font-medium">{{ __('Voltar ao painel') }}</a>
            @else
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="serv-link text-sm font-medium">{{ __('Entrar') }}</a>
                @endif
            @endauth
        </div>
    </article>
</x-layouts.legal>
