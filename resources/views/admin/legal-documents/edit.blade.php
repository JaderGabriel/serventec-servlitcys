@php
    $routeType = $documentType === \App\Models\LegalDocumentVersion::TYPE_COOKIES ? 'cookies' : 'privacy';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="serv-eyebrow">{{ __('Documentos legais') }}</p>
                <h2 class="font-display font-semibold text-xl text-slate-800 dark:text-slate-100">
                    {{ __('Editar: :label', ['label' => $typeLabel]) }}
                </h2>
                @if ($current)
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Versão vigente:') }} <span class="font-mono text-teal-700 dark:text-teal-300">{{ $current->version }}</span>
                        · {{ $current->published_at?->format('d/m/Y H:i') }}
                    </p>
                @endif
            </div>
            <a href="{{ route('admin.legal-documents.index') }}" class="serv-btn-secondary text-sm">← {{ __('Voltar') }}</a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="serv-page-shell space-y-6">
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

            @if ($contentChanged)
                <div class="rounded-lg border border-amber-200/90 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
                    {{ __('O texto foi alterado em relação à versão vigente. Ao publicar, defina a nova versão e marque «Forçar novo consentimento» para exigir aceite em /consentimento.') }}
                </div>
            @else
                <div class="rounded-lg border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                    {{ __('Sem alteração detectada no conteúdo (hash igual à versão vigente).') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.legal-documents.publish', ['type' => $routeType]) }}" class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
                @csrf
                <div class="serv-panel p-6 space-y-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Título do documento') }}</label>
                        <input type="text" name="title" id="title" value="{{ $title }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    </div>
                    <div>
                        <label for="version" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Versão (identificador)') }}</label>
                        <input type="text" name="version" id="version" value="{{ old('version', $suggestedVersion) }}" required pattern="[0-9A-Za-z.\-_]+" class="mt-1 w-full rounded-lg border-slate-300 font-mono dark:border-slate-600 dark:bg-slate-900" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('Sugestão automática:') }} <span class="font-mono">{{ $suggestedVersion }}</span> · {{ __('Formato: AAAA-MM-DD ou AAAA-MM-DD.1') }}</p>
                    </div>
                    <div>
                        <label for="body_markdown" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Conteúdo (Markdown)') }}</label>
                        <textarea name="body_markdown" id="body_markdown" rows="22" required class="mt-1 w-full rounded-lg border-slate-300 font-mono text-sm dark:border-slate-600 dark:bg-slate-900">{{ $bodyMarkdown }}</textarea>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Use ## para secções. Placeholders na PP: evite HTML cru; preferir Markdown.') }}</p>
                    </div>
                    <label class="flex gap-2 items-start cursor-pointer">
                        <input type="checkbox" name="force_reconsent" value="1" class="mt-1 rounded border-slate-300 text-teal-600" @checked(old('force_reconsent', true)) />
                        <span class="text-sm text-slate-700 dark:text-slate-300">
                            {{ __('Forçar novo consentimento de todos os utilizadores activos após publicar') }}
                        </span>
                    </label>
                    <button type="submit" class="serv-btn-primary w-full sm:w-auto" @disabled(! $contentChanged)>
                        {{ __('Publicar nova versão') }}
                    </button>
                </div>

                <div class="serv-panel p-6 space-y-3 sticky top-4">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Pré-visualização') }}</h3>
                    <div class="serv-docs-prose text-sm max-h-[min(70vh,40rem)] overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-slate-900/50">
                        {!! $previewHtml !!}
                    </div>
                    <p class="text-xs text-slate-500">{{ __('A pré-visualização actualiza após guardar/publicar. Recarregue a página para ver alterações no rascunho antes de publicar.') }}</p>
                </div>
            </form>

            @if (count($history) > 0)
                <div class="serv-panel overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Histórico de versões') }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900/60 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-2 text-left">{{ __('Versão') }}</th>
                                    <th class="px-4 py-2 text-left">{{ __('Publicada') }}</th>
                                    <th class="px-4 py-2 text-left">{{ __('Por') }}</th>
                                    <th class="px-4 py-2 text-left">{{ __('Vigente') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($history as $row)
                                    <tr>
                                        <td class="px-4 py-2 font-mono">{{ $row->version }}</td>
                                        <td class="px-4 py-2 tabular-nums text-slate-600 dark:text-slate-400">{{ $row->published_at?->format('d/m/Y H:i') ?? '—' }}</td>
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
        </div>
    </div>
</x-app-layout>
