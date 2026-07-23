@php
    use App\Services\Clio\Drive\CampaignDriveImportService;

    $driveUrl = app(CampaignDriveImportService::class)->resolveDriveUrl($campaign) ?? '';
    $driveMeta = is_array($campaign->meta) ? $campaign->meta : [];
    $lastImport = $driveMeta['drive_last_import_at'] ?? null;
    $apiConfigured = filled(config('clio.drive.api_key'));
    $verifyResult = session('clio_drive_verify');
    $catalog = is_array($driveMeta['drive_catalog'] ?? null)
        ? $driveMeta['drive_catalog']
        : (is_array($verifyResult['catalog'] ?? null) ? $verifyResult['catalog'] : []);
    $catalogFiles = is_array($catalog['files'] ?? null) ? $catalog['files'] : [];
    $counts = is_array($catalog['counts'] ?? null) ? $catalog['counts'] : [];
    $batchMode = ! empty($catalog['batch_mode']);
    $nextBatch = (int) ($catalog['next_batch'] ?? 0);
    $totalBatches = (int) ($catalog['total_batches'] ?? 1);
    $pendingWork = (int) ($counts['pending'] ?? 0) + (int) ($counts['failed'] ?? 0);
    $threshold = (int) config('clio.drive.batch_threshold', 100);
    $batchSize = (int) config('clio.drive.batch_size', 40);
@endphp

<section class="clio-panel clio-panel--pad space-y-4" aria-labelledby="clio-drive-heading">
    <div>
        <p class="clio-eyebrow">{{ __('Google Drive') }}</p>
        <h3 id="clio-drive-heading" class="clio-section-title text-base">
            {{ __('Pasta de dados da coleta') }}
        </h3>
        <p class="mt-1 text-sm text-slate-500">
            {{ __('Cataloge os ficheiros (tamanho e ticket), importe em lotes se houver mais de :n e acompanhe o que já foi processado.', ['n' => $threshold]) }}
        </p>
    </div>

    @unless ($apiConfigured)
        <p class="text-xs text-slate-500">
            {{ __('Pastas partilhadas com «qualquer pessoa com o link» funcionam sem API key. CLIO_DRIVE_API_KEY é opcional (fallback com tamanhos).') }}
        </p>
    @endunless

    <form method="post" class="space-y-3" data-serv-loading-on-submit data-serv-loading-preset="clio">
        @csrf
        <div>
            <label for="clio_drive_url" class="block text-sm font-medium">{{ __('Link da pasta / ficheiro') }}</label>
            <input id="clio_drive_url" name="clio_drive_url" type="url" value="{{ old('clio_drive_url', $driveUrl) }}"
                   placeholder="https://drive.google.com/drive/folders/…"
                   class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm"
                   @cannot('update', $campaign) readonly @endcannot />
            @error('clio_drive_url')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap gap-2">
            @can('update', $campaign)
                <button type="submit" formaction="{{ route('clio.campaigns.drive.update', $campaign) }}" class="serv-btn-secondary text-sm">
                    {{ __('Salvar link') }}
                </button>
            @endcan
            <button type="submit" formaction="{{ route('clio.campaigns.drive.verify', $campaign) }}"
                    class="serv-btn-secondary text-sm"
                    data-serv-loading-title="{{ __('Catalogando Drive') }}"
                    data-serv-loading-message="{{ __('Listando ficheiros, tamanhos e tickets. Aguarde…') }}">
                {{ __('Catalogar / Verificar') }}
            </button>
            @can('upload', $campaign)
                <button type="submit" formaction="{{ route('clio.campaigns.drive.import', $campaign) }}" class="serv-btn-primary text-sm"
                        data-serv-loading-title="{{ __('Importando lote') }}"
                        data-serv-loading-message="{{ __('Descarregando e ingerindo o próximo lote. Aguarde…') }}"
                        onclick="return confirm(@js($batchMode && $pendingWork > 0
                            ? __('Importar o próximo lote do Drive agora?')
                            : __('Importar do Drive agora? Ficheiros reconhecidos serão descarregados e ingeridos nesta coleta.')))">
                    @if ($batchMode && $pendingWork > 0 && $nextBatch > 0)
                        {{ __('Continuar lote :b/:t', ['b' => $nextBatch, 't' => $totalBatches]) }}
                    @elseif ($batchMode && $pendingWork === 0)
                        {{ __('Reimportar / retentar falhas') }}
                    @else
                        {{ __('Importar do Drive') }}
                    @endif
                </button>
            @endcan
        </div>
    </form>

    @if ($lastImport)
        <p class="text-xs text-slate-500">{{ __('Última importação: :d', ['d' => \Illuminate\Support\Carbon::parse($lastImport)->timezone(config('app.timezone'))->format('d/m/Y H:i')]) }}</p>
    @endif

    @if ($catalogFiles !== [])
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-900/50 space-y-3">
            <div class="flex flex-wrap items-center gap-2 justify-between">
                <p class="font-medium text-serv-navy dark:text-white">
                    {{ __('Catálogo Drive') }}
                    @if (! empty($catalog['cataloged_at']))
                        <span class="font-normal text-xs text-slate-500">
                            · {{ \Illuminate\Support\Carbon::parse($catalog['cataloged_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                        </span>
                    @endif
                </p>
                @if ($batchMode)
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-950/50 dark:text-amber-100">
                        {{ __('Modo lotes · :s ficheiros/lote', ['s' => $batchSize]) }}
                    </span>
                @endif
            </div>

            <ul class="flex flex-wrap gap-2 text-xs text-slate-600 dark:text-slate-300">
                <li class="rounded-full bg-white px-2 py-0.5 dark:bg-slate-800">{{ __('Total') }} · {{ $counts['total'] ?? count($catalogFiles) }}</li>
                <li class="rounded-full bg-white px-2 py-0.5 dark:bg-slate-800">{{ __('Aguardando') }} · {{ $counts['pending'] ?? 0 }}</li>
                <li class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">{{ __('Interpretado') }} · {{ $counts['parsed'] ?? 0 }}</li>
                <li class="rounded-full bg-sky-50 px-2 py-0.5 text-sky-800 dark:bg-sky-950/40 dark:text-sky-100">{{ __('Ingerido') }} · {{ $counts['ingested'] ?? 0 }}</li>
                <li class="rounded-full bg-rose-50 px-2 py-0.5 text-rose-800 dark:bg-rose-950/40 dark:text-rose-100">{{ __('Falhou') }} · {{ $counts['failed'] ?? 0 }}</li>
                <li class="rounded-full bg-white px-2 py-0.5 dark:bg-slate-800">{{ __('Ignorado') }} · {{ $counts['skipped'] ?? 0 }}</li>
            </ul>

            @if ($batchMode && $pendingWork > 0)
                <p class="text-xs text-amber-800 dark:text-amber-200">
                    {{ __('Município com muitos ficheiros: importe lote a lote. Após cada lote pode analisar o que já entrou; no fim, analise a coleta completa.') }}
                </p>
            @endif

            <div class="overflow-x-auto max-h-80 overflow-y-auto rounded-md border border-slate-200 dark:border-slate-700">
                <table class="min-w-full text-xs">
                    <thead class="sticky top-0 bg-slate-100 dark:bg-slate-800 text-left">
                        <tr>
                            <th class="px-2 py-1.5 font-medium">{{ __('Ticket') }}</th>
                            <th class="px-2 py-1.5 font-medium">{{ __('Lote') }}</th>
                            <th class="px-2 py-1.5 font-medium">{{ __('Estado') }}</th>
                            <th class="px-2 py-1.5 font-medium">{{ __('Tamanho') }}</th>
                            <th class="px-2 py-1.5 font-medium">{{ __('Tipo') }}</th>
                            <th class="px-2 py-1.5 font-medium">{{ __('Ficheiro') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($catalogFiles as $file)
                            @php
                                $status = (string) ($file['status'] ?? 'pending');
                                $tone = match ($status) {
                                    'parsed' => 'text-emerald-700 dark:text-emerald-300',
                                    'ingested' => 'text-sky-700 dark:text-sky-300',
                                    'failed' => 'text-rose-700 dark:text-rose-300',
                                    'skipped' => 'text-slate-400',
                                    default => 'text-amber-700 dark:text-amber-300',
                                };
                                $size = $file['size_local'] ?? $file['size'] ?? null;
                            @endphp
                            <tr class="hover:bg-white/70 dark:hover:bg-slate-900/40">
                                <td class="px-2 py-1 font-mono tabular-nums">{{ $file['ticket'] ?? '—' }}</td>
                                <td class="px-2 py-1 tabular-nums">{{ $file['batch'] ?? 1 }}</td>
                                <td class="px-2 py-1 font-medium {{ $tone }}">
                                    {{ CampaignDriveImportService::statusLabel($status) }}
                                    @if (! empty($file['error']))
                                        <span class="block font-normal text-[10px] text-slate-400 truncate max-w-[10rem]" title="{{ $file['error'] }}">{{ $file['error'] }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1 tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ CampaignDriveImportService::formatBytes(is_numeric($size) ? (int) $size : null) }}
                                </td>
                                <td class="px-2 py-1 text-slate-500">{{ $file['kind'] ?? '—' }}</td>
                                <td class="px-2 py-1 text-slate-700 dark:text-slate-200">
                                    <span class="block truncate max-w-md" title="{{ $file['path'] ?? $file['name'] ?? '' }}">
                                        {{ $file['path'] ?? $file['name'] ?? '—' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif (is_array($verifyResult))
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-900/50">
            <p class="font-medium text-serv-navy dark:text-white">{{ $verifyResult['message'] ?? '' }}</p>
            @if (! empty($verifyResult['summary']['by_kind']))
                <ul class="mt-2 flex flex-wrap gap-2 text-xs text-slate-600 dark:text-slate-300">
                    @foreach ($verifyResult['summary']['by_kind'] as $kind => $count)
                        <li class="rounded-full bg-white px-2 py-0.5 dark:bg-slate-800">{{ $kind }} · {{ $count }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</section>
