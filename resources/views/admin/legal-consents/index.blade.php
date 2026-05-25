<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <p class="serv-eyebrow">{{ __('Administração') }}</p>
                <h2 class="font-display font-semibold text-xl text-slate-800 dark:text-slate-100">
                    {{ __('Consentimentos legais (LGPD)') }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Versões vigentes: PP :pp · Cookies :ck', ['pp' => $privacyVersion, 'ck' => $cookiesVersion]) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a
                    href="{{ route('admin.legal-consents.index') }}"
                    @class([
                        'serv-btn-secondary',
                        'ring-2 ring-teal-500/40' => $filter === '',
                    ])
                >{{ __('Todos os activos') }}</a>
                <a
                    href="{{ route('admin.legal-consents.index', ['filter' => 'pending']) }}"
                    @class([
                        'serv-btn-secondary',
                        'ring-2 ring-amber-500/40' => $filter === 'pending',
                    ])
                >{{ __('Pendentes') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="serv-page-shell space-y-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="serv-panel p-4">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Utilizadores activos') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ number_format($summary['total_users']) }}</p>
                </div>
                <div class="serv-panel p-4 border-amber-200/80 dark:border-amber-800/50">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">{{ __('PP pendente') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-amber-900 dark:text-amber-100">{{ number_format($summary['pending_privacy']) }}</p>
                </div>
                <div class="serv-panel p-4 border-amber-200/80 dark:border-amber-800/50">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">{{ __('Cookies pendentes') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-amber-900 dark:text-amber-100">{{ number_format($summary['pending_cookies']) }}</p>
                </div>
                <div class="serv-panel p-4 border-rose-200/80 dark:border-rose-800/50">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-800 dark:text-rose-200">{{ __('Qualquer pendência') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-rose-900 dark:text-rose-100">{{ number_format($summary['pending_any']) }}</p>
                </div>
            </div>

            <div class="serv-panel overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Utilizadores') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-slate-100 dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Nome') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('PP aceite') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Cookies') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Última aceitação PP') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900/30">
                            @forelse ($users as $u)
                                @php
                                    $ppOk = $u->privacy_policy_version_accepted === $privacyVersion;
                                    $ckOk = $u->cookies_consent_version === $cookiesVersion;
                                @endphp
                                <tr>
                                    <td class="px-4 py-2.5">
                                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ $u->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $u->email }}</p>
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
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-400 tabular-nums">
                                        {{ $u->privacy_policy_accepted_at?->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-slate-500">{{ __('Nenhum utilizador neste filtro.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($users->hasPages())
                    <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700">
                        {{ $users->links() }}
                    </div>
                @endif
            </div>

            <div class="serv-panel overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Últimos registos (auditoria)') }}</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Até 50 eventos mais recentes na base.') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-slate-100 dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Data') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Utilizador') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Tipo') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Versões') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Origem') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($logs as $log)
                                <tr>
                                    <td class="px-4 py-2.5 tabular-nums text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                        {{ $log->accepted_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @if ($log->user)
                                            {{ $log->user->name }}
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs">{{ $log->consent_type }}</td>
                                    <td class="px-4 py-2.5 text-xs">
                                        PP {{ $log->privacy_version ?? '—' }} · CK {{ $log->cookies_version ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-slate-500">{{ $log->source }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-slate-500">{{ __('Sem registos ainda.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
