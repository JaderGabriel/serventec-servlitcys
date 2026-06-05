<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Documentos legais (LGPD)') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Política de privacidade e cookies — publicação versionada com opção de forçar novo consentimento.') }}
            </p>
        </div>
    </x-slot>

    <x-admin.screen-shell
        group="administration"
        active="legal-documents"
        accent="rose"
        :eyebrow="__('Administração · LGPD')"
        :title="__('Documentos legais')"
        :description="__('Edite e publique novas versões. Utilizadores activos podem ser redireccionados para /consentimento após publicação.')"
        :doc-href="route('legal.privacy')"
        :doc-label="__('Ver política pública')"
    >
        <x-slot name="flashes">
            @include('admin.partials.flash-messages')
        </x-slot>

        <x-slot name="headerActions">
            <a href="{{ route('admin.legal-consents.index') }}" class="inline-flex items-center {{ \App\Support\Admin\AdminVisualCatalog::chipClasses('rose') }} text-xs">
                {{ __('Consentimentos dos utilizadores') }} →
            </a>
        </x-slot>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach ([
                ['doc' => $privacy, 'type' => 'privacy', 'label' => __('Política de privacidade'), 'public' => route('legal.privacy')],
                ['doc' => $cookies, 'type' => 'cookies', 'label' => __('Política de cookies'), 'public' => route('legal.privacy')],
            ] as $card)
                <section class="sync-queue-panel sync-queue-panel--rose overflow-hidden">
                    <header class="sync-queue-panel__header">
                        <div class="flex gap-3 min-w-0">
                            <span class="sync-queue-panel__icon" aria-hidden="true">
                                <x-ui.icon name="document-text" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="sync-queue-panel__title">{{ $card['label'] }}</h3>
                            </div>
                        </div>
                    </header>
                    <div class="sync-queue-panel__body space-y-4">
                        @if ($card['doc'])
                            <dl class="text-sm space-y-2">
                                <div>
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Versão vigente') }}</dt>
                                    <dd class="font-mono font-medium text-rose-800 dark:text-rose-300">{{ $card['doc']->version }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Publicada em') }}</dt>
                                    <dd class="tabular-nums">{{ $card['doc']->published_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Hash conteúdo') }}</dt>
                                    <dd class="font-mono text-[10px] break-all text-gray-600 dark:text-gray-400">{{ Str::limit($card['doc']->content_hash, 16, '…') }}</dd>
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
                        <div class="flex flex-wrap gap-2 pt-1">
                            <a href="{{ route('admin.legal-documents.edit', ['type' => $card['type']]) }}" class="inline-flex items-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500">
                                {{ __('Editar e publicar') }}
                            </a>
                            <a href="{{ $card['public'] }}" target="_blank" rel="noopener" class="inline-flex items-center {{ \App\Support\Admin\AdminVisualCatalog::chipClasses('rose') }} text-xs">
                                {{ __('Ver página pública') }} ↗
                            </a>
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    </x-admin.screen-shell>
</x-app-layout>
