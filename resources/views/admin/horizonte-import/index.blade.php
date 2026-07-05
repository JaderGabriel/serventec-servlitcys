@php
    use App\Support\Admin\AdminImportHubCatalog;
    use App\Support\Horizonte\HorizonteFortnightlyFeedPhaseCatalog;
    use App\Support\Horizonte\HorizonteFeedPhaseOptions;

    $hub = is_array($horizonteHub ?? null) ? $horizonteHub : [];
    $phaseGroups = HorizonteFortnightlyFeedPhaseCatalog::groups();
    $phaseDefinitions = HorizonteFortnightlyFeedPhaseCatalog::definitions();
    $defaultPhases = HorizonteFeedPhaseOptions::defaultSelectedPhaseKeys();
    $toneBorder = static fn (string $tone): string => match ($tone) {
        'amber' => 'border-amber-300/80 dark:border-amber-800/60',
        'emerald' => 'border-emerald-300/80 dark:border-emerald-800/60',
        'sky' => 'border-sky-300/80 dark:border-sky-800/60',
        'rose' => 'border-rose-300/80 dark:border-rose-800/60',
        'violet' => 'border-violet-300/80 dark:border-violet-800/60',
        'indigo' => 'border-indigo-300/80 dark:border-indigo-800/60',
        'slate' => 'border-slate-300/80 dark:border-slate-700/60',
        default => 'border-slate-300/80 dark:border-slate-700/60',
    };
    $toneBg = static fn (string $tone): string => match ($tone) {
        'amber' => 'bg-amber-50/80 dark:bg-amber-950/25',
        'emerald' => 'bg-emerald-50/80 dark:bg-emerald-950/25',
        'sky' => 'bg-sky-50/80 dark:bg-sky-950/25',
        'rose' => 'bg-rose-50/80 dark:bg-rose-950/25',
        'violet' => 'bg-violet-50/80 dark:bg-violet-950/25',
        'indigo' => 'bg-indigo-50/80 dark:bg-indigo-950/25',
        'slate' => 'bg-slate-50/80 dark:bg-slate-900/40',
        default => 'bg-slate-50/80 dark:bg-slate-900/40',
    };
    $toneIcon = static fn (string $tone): string => match ($tone) {
        'amber' => 'text-amber-700 dark:text-amber-300',
        'emerald' => 'text-emerald-700 dark:text-emerald-300',
        'sky' => 'text-sky-700 dark:text-sky-300',
        'rose' => 'text-rose-700 dark:text-rose-300',
        'violet' => 'text-violet-700 dark:text-violet-300',
        'indigo' => 'text-indigo-700 dark:text-indigo-300',
        default => 'text-slate-600 dark:text-slate-300',
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight" style="font-family: Outfit, ui-sans-serif, system-ui, sans-serif;">
                {{ __('Horizonte — abastecimento') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Pipeline nacional de dados públicos para o mapa de oportunidade. Separado do hub municipal da consultoria.') }}
            </p>
        </div>
    </x-slot>

    <x-admin.import-hub.shell
        active="horizonte"
        accent="sky"
        :eyebrow="__('Horizonte')"
        :title="__('Abastecimento nacional — mapa de oportunidade')"
        :description="__('Seleccione as fases a executar, inicie o pipeline em etapas e acompanhe a cobertura FUNDEB × Censo × SAEB × CadÚnico em todo o país.')"
        :doc-href="route('admin.documentation.show', ['doc' => 'docs/HORIZONTE.md'])"
        :doc-label="__('Documentação Horizonte')"
    >
        <x-slot name="badges">
            <x-admin.import-hub.badge>{{ __('Ref. :ano', ['ano' => (string) ($hub['reference_year'] ?? config('horizonte.reference_year'))]) }}</x-admin.import-hub.badge>
            @if ($hub['feed_enabled'] ?? false)
                <x-admin.import-hub.badge>{{ $hub['schedule_summary'] ?? '' }}</x-admin.import-hub.badge>
            @endif
            <a href="{{ route('dashboard.horizonte') }}" class="rounded-full bg-sky-100 dark:bg-sky-950/50 px-3 py-1 font-medium text-sky-800 dark:text-sky-200 ring-1 ring-sky-200/80 dark:ring-sky-800 hover:bg-sky-200/80 dark:hover:bg-sky-900/50 text-xs">
                {{ __('Abrir mapa') }} →
            </a>
        </x-slot>

        <x-slot name="flashes">
            @if (session('public_data_error'))
                <div class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100" role="alert">
                    {{ session('public_data_error') }}
                </div>
            @endif
        </x-slot>

        @include('admin.horizonte-import.partials.hub-panel', ['horizonteHub' => $hub])

        <x-slot name="shortcuts">
            <x-admin.import-hub.link-chip tone="emerald" href="{{ route('admin.public-data.index') }}">{{ __('Dados públicos — consultoria') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="sky" href="{{ route('dashboard.horizonte') }}">{{ __('Mapa Horizonte') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="slate" href="{{ route('admin.sync-queue.index') }}">{{ __('Fila de processamento') }}</x-admin.import-hub.link-chip>
        </x-slot>
    </x-admin.import-hub.shell>
</x-app-layout>
