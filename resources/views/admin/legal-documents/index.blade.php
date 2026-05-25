<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="serv-eyebrow">{{ __('Administração') }}</p>
                <h2 class="font-display font-semibold text-xl text-slate-800 dark:text-slate-100">
                    {{ __('Documentos legais (LGPD)') }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Edite a política de privacidade e cookies. Ao publicar uma nova versão, pode obrigar novo consentimento.') }}
                </p>
            </div>
            <a href="{{ route('admin.legal-consents.index') }}" class="serv-btn-secondary text-sm">
                {{ __('Consentimentos dos utilizadores') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="serv-page-shell space-y-8">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50/90 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/30 dark:text-red-200">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach ([
                    ['doc' => $privacy, 'type' => 'privacy', 'label' => __('Política de privacidade')],
                    ['doc' => $cookies, 'type' => 'cookies', 'label' => __('Política de cookies')],
                ] as $card)
                    <div class="serv-panel p-6 space-y-4">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $card['label'] }}</h3>
                        @if ($card['doc'])
                            <dl class="text-sm space-y-2">
                                <div>
                                    <dt class="text-xs text-slate-500">{{ __('Versão vigente') }}</dt>
                                    <dd class="font-mono font-medium text-teal-800 dark:text-teal-300">{{ $card['doc']->version }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-slate-500">{{ __('Publicada em') }}</dt>
                                    <dd class="tabular-nums">{{ $card['doc']->published_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-slate-500">{{ __('Hash conteúdo') }}</dt>
                                    <dd class="font-mono text-[10px] break-all text-slate-600 dark:text-slate-400">{{ Str::limit($card['doc']->content_hash, 16, '…') }}</dd>
                                </div>
                            </dl>
                        @else
                            <p class="text-sm text-amber-800 dark:text-amber-200">
                                {{ __('Ainda não publicado na base. A página pública usa o texto estático até a primeira publicação.') }}
                                @if ($card['type'] === 'privacy' && $privacyVersionConfig)
                                    <span class="block mt-1 font-mono text-xs">{{ __('Config .env:') }} {{ $privacyVersionConfig }}</span>
                                @endif
                            </p>
                        @endif
                        <div class="flex flex-wrap gap-2 pt-2">
                            <a href="{{ route('admin.legal-documents.edit', ['type' => $card['type']]) }}" class="serv-btn-primary text-sm">
                                {{ __('Editar e publicar') }}
                            </a>
                            <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="serv-btn-secondary text-sm">
                                {{ __('Ver página pública') }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
