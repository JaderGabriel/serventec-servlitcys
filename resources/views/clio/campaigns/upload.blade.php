<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Clio') }} · {{ __('Upload') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ $campaign->municipality_name }} — {{ $campaign->year }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 max-w-2xl">
                    {{ __('Envie CSV do portal, ZIP municipal ou pasta de escolas. Máx. :mb MB · até :n ficheiros.', ['mb' => $maxMb, 'n' => $maxFiles]) }}
                </p>
            </div>
            <a href="{{ route('clio.campaigns.show', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Voltar ao hub') }}</a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('warning'))
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                    {{ session('warning') }}
                </div>
            @endif

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="serv-panel p-4">
                    <p class="serv-eyebrow">{{ __('Inventário') }}</p>
                    <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-serv-navy dark:text-white">{{ $counts['total'] }}</p>
                    <p class="text-xs text-slate-500">{{ __('arquivo(s)') }}</p>
                </div>
                <div class="serv-panel p-4">
                    <p class="serv-eyebrow">{{ __('Pendentes de parse') }}</p>
                    <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-serv-navy dark:text-white">{{ $counts['pending'] }}</p>
                    <p class="text-xs text-slate-500">{{ __('Aguardando interpretação CSV') }}</p>
                </div>
                <div class="serv-panel p-4">
                    <p class="serv-eyebrow">{{ __('Estado') }}</p>
                    <p class="mt-1 font-medium text-serv-navy dark:text-white">{{ $campaign->statusLabel() }}</p>
                    <p class="text-xs text-slate-500">{{ $campaign->schools->count() }} {{ __('escola(s)') }}</p>
                </div>
            </div>

            @can('upload', $campaign)
            <form
                method="post"
                action="{{ route('clio.campaigns.upload.store', $campaign) }}"
                enctype="multipart/form-data"
                class="serv-panel space-y-5 p-6"
                x-data="clioUploadPreview(@js($maxFiles))"
            >
                @csrf
                <div class="space-y-3">
                    <div>
                        <label for="files" class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Ficheiros / ZIP') }}</label>
                        <input
                            id="files"
                            type="file"
                            name="files[]"
                            multiple
                            required
                            accept=".csv,.zip,.txt,text/csv,application/zip"
                            class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-sky-500"
                            @change="onFiles($event)"
                        />
                    </div>
                    <div>
                        <label for="folder" class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Ou pasta de escolas') }}</label>
                        <input
                            id="folder"
                            type="file"
                            multiple
                            webkitdirectory
                            directory
                            class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-600"
                            @change="onFolder($event)"
                        />
                        <p class="mt-1 text-xs text-slate-500">{{ __('Usa webkitdirectory — pastas tipo «29174651 - Escola».') }}</p>
                    </div>
                    <template x-for="(path, i) in relativePaths" :key="'rp-'+i">
                        <input type="hidden" name="relative_paths[]" :value="path">
                    </template>
                    @error('files')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    @error('files.*')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="async_zip" value="1" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                    {{ __('Expandir ZIP em segundo plano (fila clio)') }}
                </label>

                <div x-show="rows.length > 0" x-cloak class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                    <p class="border-b border-slate-100 bg-slate-50 px-3 py-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-900/60">
                        {{ __('Pré-visualização') }} (<span x-text="rows.length"></span>)
                    </p>
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ __('Nome') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('Tipo') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('Escola') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('Tamanho') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <template x-for="row in rows.slice(0, 80)" :key="row.key">
                                <tr>
                                    <td class="max-w-xs truncate px-3 py-1.5 font-mono text-xs" x-text="row.name"></td>
                                    <td class="px-3 py-1.5 text-xs" x-text="row.kind"></td>
                                    <td class="px-3 py-1.5 font-mono text-xs tabular-nums" x-text="row.inep || '—'"></td>
                                    <td class="px-3 py-1.5 text-xs tabular-nums" x-text="row.sizeLabel"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p x-show="rows.length > 80" class="px-3 py-2 text-xs text-slate-500" x-text="'… +' + (rows.length - 80) + ' ficheiros'"></p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="serv-btn-primary text-sm">{{ __('Enviar e classificar') }}</button>
                </div>
            </form>
            @else
                <div class="serv-panel p-5 text-sm text-slate-600 dark:text-slate-300">
                    {{ __('Só administradores podem enviar ficheiros. Pode consultar o inventário abaixo.') }}
                </div>
            @endcan

            <section class="serv-panel overflow-hidden" id="inventario">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h3 class="font-medium text-serv-navy dark:text-white">{{ __('Inventário (parse_status=pending)') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ __('Nome') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Tipo') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Escola') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Parse') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Tamanho') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($campaign->artifacts as $artifact)
                                <tr>
                                    <td class="max-w-sm truncate px-4 py-2 font-mono text-xs" title="{{ $artifact->original_name }}">{{ $artifact->original_name }}</td>
                                    <td class="px-4 py-2">{{ $artifact->kindLabel() }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $artifact->school?->inep_code ?? '—' }}</td>
                                    <td class="px-4 py-2">{{ $artifact->parse_status }}</td>
                                    <td class="px-4 py-2 tabular-nums">{{ number_format($artifact->size_bytes / 1024, 1) }} KB</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-500 text-sm">{{ __('Nenhum arquivo enviado.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <script>
        function clioUploadPreview(maxFiles) {
            const classify = (name, relative) => {
                const base = name.split(/[\\/]/).pop();
                if (base.startsWith('.~lock.') || base.startsWith('.')) return { kind: 'ignorado', inep: null, ignored: true };
                let inep = null;
                const m = (relative || name).match(/(?:^|\/)(\d{8})\s*[-– ]/);
                if (m) inep = m[1];
                if (/Relatorio_Acomp_Coleta_1Etapa_/i.test(base)) return { kind: 'Acomp 1ª etapa', inep, ignored: false };
                if (/RelacaoAlunoEscola_/i.test(base)) return { kind: 'Alunos', inep, ignored: false };
                if (/RelacaoTurmaEscola_/i.test(base)) return { kind: 'Turmas', inep, ignored: false };
                if (/RelacaoProfissionalEscola_/i.test(base)) return { kind: 'Profissionais', inep, ignored: false };
                if (/\.zip$/i.test(base)) return { kind: 'ZIP', inep: null, ignored: false };
                if (/\.txt$/i.test(base)) return { kind: 'TXT migração', inep, ignored: false };
                return { kind: 'Desconhecido', inep, ignored: false };
            };
            const sizeLabel = (n) => (n / 1024).toFixed(1) + ' KB';
            return {
                maxFiles,
                rows: [],
                relativePaths: [],
                onFiles(e) {
                    this.applyList(e.target.files, false);
                },
                onFolder(e) {
                    const folderFiles = e.target.files;
                    const main = document.getElementById('files');
                    const dt = new DataTransfer();
                    Array.from(folderFiles).forEach(f => dt.items.add(f));
                    main.files = dt.files;
                    this.applyList(folderFiles, true);
                },
                applyList(fileList, useRelative) {
                    const files = Array.from(fileList || []).slice(0, this.maxFiles);
                    this.relativePaths = files.map(f => useRelative ? (f.webkitRelativePath || f.name) : f.name);
                    this.rows = files.map((f, i) => {
                        const rel = this.relativePaths[i];
                        const c = classify(f.name, rel);
                        return {
                            key: i + '-' + f.name,
                            name: f.name,
                            kind: c.ignored ? 'Ignorado' : c.kind,
                            inep: c.inep,
                            sizeLabel: sizeLabel(f.size),
                        };
                    });
                },
            };
        }
    </script>
</x-app-layout>
