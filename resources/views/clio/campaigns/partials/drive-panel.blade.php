@php
    use App\Services\Clio\Drive\CampaignDriveImportService;

    $driveUrl = app(CampaignDriveImportService::class)->resolveDriveUrl($campaign) ?? '';
    $driveMeta = is_array($campaign->meta) ? $campaign->meta : [];
    $lastImport = $driveMeta['drive_last_import_at'] ?? null;
    $apiConfigured = filled(config('clio.drive.api_key'));
    $verifyResult = session('clio_drive_verify');
@endphp

<section class="serv-panel space-y-4 p-5 sm:p-6" aria-labelledby="clio-drive-heading">
    <div>
        <p class="serv-eyebrow">{{ __('Google Drive') }}</p>
        <h3 id="clio-drive-heading" class="font-display text-lg font-semibold text-serv-navy dark:text-white">
            {{ __('Pasta de dados da coleta') }}
        </h3>
        <p class="mt-1 text-sm text-slate-500">
            {{ __('Verifique o conteúdo da pasta partilhada e importe CSV/ZIP automaticamente para esta coleta.') }}
        </p>
    </div>

    @unless ($apiConfigured)
        <p class="text-xs text-slate-500">
            {{ __('Pastas partilhadas com «qualquer pessoa com o link» funcionam sem API key. CLIO_DRIVE_API_KEY é opcional (fallback).') }}
        </p>
    @endunless

    <form method="post" class="space-y-3">
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
            <button type="submit" formaction="{{ route('clio.campaigns.drive.verify', $campaign) }}" class="serv-btn-secondary text-sm">
                {{ __('Verificar dados') }}
            </button>
            @can('upload', $campaign)
                <button type="submit" formaction="{{ route('clio.campaigns.drive.import', $campaign) }}" class="serv-btn-primary text-sm"
                        onclick="return confirm(@js(__('Importar do Drive agora? Ficheiros CSV/ZIP reconhecidos serão descarregados e ingeridos nesta coleta.')))">
                    {{ __('Importar do Drive') }}
                </button>
            @endcan
        </div>
    </form>

    @if ($lastImport)
        <p class="text-xs text-slate-500">{{ __('Última importação: :d', ['d' => \Illuminate\Support\Carbon::parse($lastImport)->timezone(config('app.timezone'))->format('d/m/Y H:i')]) }}</p>
    @endif

    @if (is_array($verifyResult))
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-900/50">
            <p class="font-medium text-serv-navy dark:text-white">{{ $verifyResult['message'] ?? '' }}</p>
            @if (! empty($verifyResult['summary']['by_kind']))
                <ul class="mt-2 flex flex-wrap gap-2 text-xs text-slate-600 dark:text-slate-300">
                    @foreach ($verifyResult['summary']['by_kind'] as $kind => $count)
                        <li class="rounded-full bg-white px-2 py-0.5 dark:bg-slate-800">{{ $kind }} · {{ $count }}</li>
                    @endforeach
                </ul>
            @endif
            @if (! empty($verifyResult['files_preview']))
                <details class="mt-3">
                    <summary class="cursor-pointer text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Pré-visualização (:n)', ['n' => count($verifyResult['files_preview'])]) }}</summary>
                    <ul class="mt-2 max-h-48 overflow-y-auto space-y-1 text-xs text-slate-500">
                        @foreach ($verifyResult['files_preview'] as $file)
                            <li><span class="font-medium text-slate-700 dark:text-slate-200">{{ $file['kind'] }}</span> — {{ $file['path'] }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif
</section>
