@php
    $routeType = $documentType === \App\Models\LegalDocumentVersion::TYPE_COOKIES ? 'cookies' : 'privacy';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Editar: :label', ['label' => $typeLabel]) }}
            </h2>
        </div>
    </x-slot>

    <x-admin.screen-shell
        group="administration"
        active="legal-documents"
        accent="rose"
        :eyebrow="__('Documentos legais')"
        :title="$typeLabel"
        :description="$current
            ? __('Versão vigente: :v · :data', ['v' => $current->version, 'data' => $current->published_at?->format('d/m/Y H:i') ?? '—'])
            : __('Primeira publicação deste documento.')"
    >
        <x-slot name="flashes">
            @include('admin.partials.flash-messages')
        </x-slot>

        <x-slot name="headerActions">
            <a href="{{ route('admin.legal-documents.index') }}" class="inline-flex items-center {{ \App\Support\Admin\AdminVisualCatalog::chipClasses('slate') }} text-xs">
                ← {{ __('Voltar à lista') }}
            </a>
        </x-slot>

        @if ($contentChanged)
            <div class="rounded-lg border border-amber-300/80 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
                {{ __('O texto foi alterado em relação à versão vigente. Ao publicar, defina a nova versão e marque «Forçar novo consentimento» para exigir aceite em /consentimento.') }}
            </div>
        @else
            <div class="rounded-lg border border-gray-200 bg-gray-50/80 px-4 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300">
                {{ __('Sem alteração detectada no conteúdo (hash igual à versão vigente).') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.legal-documents.publish', ['type' => $routeType]) }}" class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
            @csrf
            <section class="sync-queue-panel sync-queue-panel--rose">
                <header class="sync-queue-panel__header">
                    <h3 class="sync-queue-panel__title">{{ __('Editor') }}</h3>
                </header>
                <div class="sync-queue-panel__body space-y-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Título do documento') }}</label>
                        <input type="text" name="title" id="title" value="{{ $title }}" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm" />
                    </div>
                    <div>
                        <label for="version" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Versão (identificador)') }}</label>
                        <input type="text" name="version" id="version" value="{{ old('version', $suggestedVersion) }}" required pattern="[0-9A-Za-z.\-_]+" class="mt-1 w-full rounded-lg border-gray-300 font-mono dark:border-gray-600 dark:bg-gray-900 text-sm" />
                        <p class="mt-1 text-xs text-gray-500">{{ __('Sugestão:') }} <span class="font-mono">{{ $suggestedVersion }}</span></p>
                    </div>
                    <div>
                        <label for="body_markdown" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Conteúdo (Markdown)') }}</label>
                        <textarea name="body_markdown" id="body_markdown" rows="22" required class="mt-1 w-full rounded-lg border-gray-300 font-mono text-sm dark:border-gray-600 dark:bg-gray-900">{{ $bodyMarkdown }}</textarea>
                    </div>
                    <label class="flex gap-2 items-start cursor-pointer">
                        <input type="checkbox" name="force_reconsent" value="1" class="mt-1 rounded border-gray-300 text-rose-600" @checked(old('force_reconsent', true)) />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            {{ __('Forçar novo consentimento de todos os utilizadores activos após publicar') }}
                        </span>
                    </label>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500 disabled:opacity-50" @disabled(! $contentChanged)>
                        {{ __('Publicar nova versão') }}
                    </button>
                </div>
            </section>

            <section class="sync-queue-panel sync-queue-panel--rose xl:sticky xl:top-4">
                <header class="sync-queue-panel__header">
                    <h3 class="sync-queue-panel__title">{{ __('Pré-visualização') }}</h3>
                </header>
                <div class="sync-queue-panel__body space-y-3">
                    <div class="serv-docs-prose text-sm max-h-[min(70vh,40rem)] overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-900/50">
                        {!! $previewHtml !!}
                    </div>
                    <p class="text-xs text-gray-500">{{ __('A pré-visualização reflecte o conteúdo actual do editor após recarregar a página.') }}</p>
                </div>
            </section>
        </form>

        @if (count($history) > 0)
            <div class="rounded-xl border border-gray-200/90 dark:border-gray-700 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/50">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Histórico de versões') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left">{{ __('Versão') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Publicada') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Por') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Vigente') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($history as $row)
                                <tr>
                                    <td class="px-4 py-2 font-mono">{{ $row->version }}</td>
                                    <td class="px-4 py-2 tabular-nums text-gray-600 dark:text-gray-400">{{ $row->published_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="px-4 py-2">{{ $row->publisher?->name ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        @if ($row->is_current)
                                            <span class="text-emerald-700 dark:text-emerald-300 font-medium">{{ __('Sim') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-admin.screen-shell>
</x-app-layout>
