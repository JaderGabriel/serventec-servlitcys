<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Consentimentos legais (LGPD)') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Versões vigentes: PP :pp · Cookies :ck', ['pp' => $privacyVersion, 'ck' => $cookiesVersion]) }}
            </p>
        </div>
    </x-slot>

    <x-admin.screen-shell
        group="administration"
        active="legal-consents"
        accent="rose"
        :eyebrow="__('Administração · LGPD')"
        :title="__('Consentimentos e auditoria')"
        :description="__('Acompanhe aceites por utilizador, pendentes e revogue em massa quando publicar nova versão.')"
    >
        <x-slot name="flashes">
            @include('admin.partials.flash-messages')
        </x-slot>

        <x-slot name="headerActions">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.legal-documents.index') }}" class="inline-flex items-center {{ \App\Support\Admin\AdminVisualCatalog::chipClasses('rose') }} text-xs">
                    {{ __('Editar documentos') }}
                </a>
                <a
                    href="{{ route('admin.legal-consents.index') }}"
                    @class([
                        \App\Support\Admin\AdminVisualCatalog::chipClasses('slate'),
                        'text-xs ring-2 ring-rose-500/40' => $filter === '',
                    ])
                >{{ __('Todos os activos') }}</a>
                <a
                    href="{{ route('admin.legal-consents.index', ['filter' => 'pending']) }}"
                    @class([
                        \App\Support\Admin\AdminVisualCatalog::chipClasses('amber'),
                        'text-xs ring-2 ring-amber-500/40' => $filter === 'pending',
                    ])
                >{{ __('Pendentes') }}</a>
            </div>
        </x-slot>

        <section class="sync-queue-panel sync-queue-panel--rose">
            <header class="sync-queue-panel__header">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full">
                    <div class="flex gap-3 min-w-0">
                        <span class="sync-queue-panel__icon" aria-hidden="true">
                            <x-ui.icon name="shield-check" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="sync-queue-panel__title">{{ __('Revogar aceites (forçar novo consentimento)') }}</h3>
                            <p class="sync-queue-panel__desc">{{ __('Limpa versões aceites nos utilizadores activos — redireccionamento para /consentimento na próxima visita.') }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.legal-consents.revoke-all') }}" class="flex flex-wrap items-center gap-3 shrink-0" onsubmit="return confirm(@js(__('Revogar aceites de todos os utilizadores activos?')));">
                        @csrf
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="revoke_privacy" value="1" checked class="rounded border-gray-300 text-rose-600" />
                            {{ __('PP') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="revoke_cookies" value="1" checked class="rounded border-gray-300 text-rose-600" />
                            {{ __('Cookies') }}
                        </label>
                        <button type="submit" class="inline-flex items-center rounded-lg border border-amber-300 dark:border-amber-700 px-3 py-1.5 text-xs font-medium text-amber-900 dark:text-amber-100 hover:bg-amber-50 dark:hover:bg-amber-950/30">
                            {{ __('Revogar todos') }}
                        </button>
                    </form>
                </div>
            </header>
        </section>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-gray-200/90 dark:border-gray-700 bg-white/80 dark:bg-gray-900/40 p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Utilizadores activos') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($summary['total_users']) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200/80 dark:border-amber-800/50 bg-amber-50/30 dark:bg-amber-950/20 p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">{{ __('PP pendente') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-amber-900 dark:text-amber-100">{{ number_format($summary['pending_privacy']) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200/80 dark:border-amber-800/50 bg-amber-50/30 dark:bg-amber-950/20 p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">{{ __('Cookies pendentes') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-amber-900 dark:text-amber-100">{{ number_format($summary['pending_cookies']) }}</p>
            </div>
            <div class="rounded-xl border border-rose-200/80 dark:border-rose-800/50 bg-rose-50/30 dark:bg-rose-950/20 p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-800 dark:text-rose-200">{{ __('Qualquer pendência') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-rose-900 dark:text-rose-100">{{ number_format($summary['pending_any']) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200/90 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/50">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Utilizadores') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-900/60">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Nome') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('PP aceite') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Cookies') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Última aceitação PP') }}</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Ações') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900/30">
                        @forelse ($users as $u)
                            @php
                                $ppOk = $u->privacy_policy_version_accepted === $privacyVersion;
                                $ckOk = $u->cookies_consent_version === $cookiesVersion;
                            @endphp
                            <tr>
                                <td class="px-4 py-2.5">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $u->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $u->email }}</p>
                                </td>
                                <td class="px-4 py-2.5">
                                    @if ($ppOk)
                                        <span class="text-emerald-700 dark:text-emerald-300 font-medium">{{ $u->privacy_policy_version_accepted }}</span>
                                    @else
                                        <span class="text-amber-700 dark:text-amber-300">{{ $u->privacy_policy_version_accepted ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    @if ($ckOk)
                                        <span class="text-emerald-700 dark:text-emerald-300 font-medium">{{ $u->cookies_consent_version }}</span>
                                    @else
                                        <span class="text-amber-700 dark:text-amber-300">{{ $u->cookies_consent_version ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400 tabular-nums">
                                    {{ $u->privacy_policy_accepted_at?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <form method="POST" action="{{ route('admin.legal-consents.revoke-user', $u) }}" class="inline" onsubmit="return confirm(@js(__('Revogar aceite de :name?', ['name' => $u->name])));">
                                        @csrf
                                        <input type="hidden" name="revoke_privacy" value="1" />
                                        <input type="hidden" name="revoke_cookies" value="1" />
                                        <button type="submit" class="text-xs font-medium text-amber-800 dark:text-amber-200 hover:underline">
                                            {{ __('Revogar') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">{{ __('Nenhum utilizador neste filtro.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    {{ $users->links() }}
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200/90 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/50">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Últimos registos (auditoria)') }}</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Até 50 eventos mais recentes na base.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-900/60">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Data') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Utilizador') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Tipo') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Versões') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Origem') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="px-4 py-2.5 tabular-nums text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                    {{ $log->accepted_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-2.5">
                                    @if ($log->user)
                                        {{ $log->user->name }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 font-mono text-xs">{{ $log->consent_type }}</td>
                                <td class="px-4 py-2.5 text-xs">
                                    PP {{ $log->privacy_version ?? '—' }} · CK {{ $log->cookies_version ?? '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-xs text-gray-500">{{ $log->source }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('Sem registos ainda.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-admin.screen-shell>
</x-app-layout>
