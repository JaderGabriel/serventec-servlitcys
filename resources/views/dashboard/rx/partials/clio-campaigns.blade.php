<section class="serv-panel p-5" aria-labelledby="rx-clio-heading">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="serv-eyebrow">{{ __('Clio') }}</p>
            <h3 id="rx-clio-heading" class="font-display font-semibold text-lg text-serv-navy dark:text-white">
                {{ __('Campanhas Educacenso :ano', ['ano' => $clio['year'] ?? '']) }}
            </h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                {{ __('Ranking por achados críticos e cobertura da tríade (aluno · turma · profissional).') }}
            </p>
        </div>
        <a href="{{ route('clio.campaigns.index', ['year' => $clio['year'] ?? null]) }}" class="serv-link text-sm shrink-0">
            {{ __('Ver todas as campanhas →') }}
        </a>
    </div>

    @if (($clio['campaigns_count'] ?? 0) === 0)
        <p class="mt-4 text-sm text-slate-500">{{ __('Ainda sem campanhas Clio neste exercício.') }}</p>
    @else
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/60">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('Município') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Estado') }}</th>
                        <th class="px-3 py-2 font-medium text-right">{{ __('Tríade %') }}</th>
                        <th class="px-3 py-2 font-medium text-right">{{ __('Erros') }}</th>
                        <th class="px-3 py-2 font-medium text-right">{{ __('Avisos') }}</th>
                        <th class="px-3 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($clio['rows'] as $row)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                            <td class="px-3 py-2">
                                <div class="font-medium text-serv-navy dark:text-white">{{ $row['municipality'] }}</div>
                                <div class="text-xs text-slate-500">{{ $row['uf'] }} · {{ $row['profile_label'] }}</div>
                            </td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $row['status_label'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['triade_pct'], 1, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right tabular-nums {{ (int) $row['errors'] > 0 ? 'text-rose-700 dark:text-rose-300 font-medium' : '' }}">{{ $row['errors'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row['warnings'] }}</td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ $row['analysis_url'] }}" class="serv-link text-sm">{{ __('Painel') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
