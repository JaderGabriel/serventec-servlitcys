@props(['profile' => []])

@php
    $years = is_array($profile['years'] ?? null) ? $profile['years'] : [];
    $alerts = is_array($profile['alerts'] ?? null) ? $profile['alerts'] : [];
    $fmtBrl = static fn (?float $v): string => $v !== null && $v > 0
        ? 'R$ '.number_format($v, 2, ',', '.')
        : '—';
    $alertRing = static fn (string $s): string => match ($s) {
        'danger' => 'border-rose-300 bg-rose-50 text-rose-950 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100',
        'warning' => 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100',
        default => 'border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100',
    };
@endphp

@if ($years !== [])
    <x-dashboard.consultoria-section
        anchor="fundeb-perfil-vaaf"
        :title="__('Perfil FUNDEB — receitas, VAAF e planejamento')"
        :subtitle="__('Portaria FNDE (receita por ente), matrículas i-Educar/Censo, VAAF estimado e distribuição legal indicativa para o exercício corrente e o próximo. Valores para planejamento — conferir no FNDE/Simec.')"
    >
        @if (count($alerts) > 0)
            <div class="space-y-2 mb-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                    {{ __('Alertas FNDE / qualidade dos dados (:n)', ['n' => count($alerts)]) }}
                </p>
                @foreach ($alerts as $alert)
                    <div class="rounded-lg border px-3 py-2 text-sm {{ $alertRing((string) ($alert['severity'] ?? 'info')) }}">
                        <p class="font-medium">
                            @if (filled($alert['ano'] ?? null))
                                <span class="tabular-nums">{{ $alert['ano'] }}</span> —
                            @endif
                            {{ $alert['titulo'] ?? '' }}
                        </p>
                        <p class="mt-0.5 text-[13px] opacity-90">{{ $alert['mensagem'] ?? '' }}</p>
                        @if (filled($alert['acao'] ?? null))
                            <p class="mt-1 text-[11px] font-medium">{{ $alert['acao'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/80 text-left text-[11px] uppercase tracking-wide text-slate-600 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2">{{ __('Exercício') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Receita total FNDE') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Compl. VAAF') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Matrículas') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('VAAF est.') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('VAAF UF') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Previsão base') }}</th>
                        <th class="px-3 py-2">{{ __('Publicação') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($years as $ano => $block)
                        @php
                            $rec = is_array($block['receita'] ?? null) ? $block['receita'] : [];
                            $mat = is_array($block['matriculas'] ?? null) ? $block['matriculas'] : [];
                            $est = is_array($block['vaaf_estimado'] ?? null) ? $block['vaaf_estimado'] : [];
                            $prev = is_array($block['previsao_recursos'] ?? null) ? $block['previsao_recursos'] : [];
                        @endphp
                        <tr>
                            <td class="px-3 py-2 font-medium text-slate-900 dark:text-slate-100">
                                {{ $block['label'] ?? $ano }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtBrl(isset($rec['total']) ? (float) $rec['total'] : null) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtBrl(isset($rec['complementacao_vaaf']) ? (float) $rec['complementacao_vaaf'] : null) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-xs">
                                {{ number_format((int) ($mat['usado'] ?? 0), 0, ',', '.') }}
                                <span class="block text-[10px] text-slate-500">{{ $mat['fonte_usada'] ?? '' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ isset($est['valor']) ? $fmtBrl((float) $est['valor']) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-xs">
                                @if (! empty($ufRef['disponivel']) && isset($ufRef['vaaf']))
                                    {{ $fmtBrl((float) $ufRef['vaaf']) }}
                                    @if (filled($ufRef['uf'] ?? null))
                                        <span class="block text-[10px] text-slate-500">{{ $ufRef['uf'] }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtBrl(isset($prev['base_anual']) ? (float) $prev['base_anual'] : null) }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600 dark:text-slate-400">
                                @if (filled($rec['ano_publicacao'] ?? null))
                                    {{ __('Portaria :p', ['p' => $rec['ano_publicacao']]) }}
                                @endif
                                @if (filled($rec['csv_url'] ?? null))
                                    <a href="{{ $rec['csv_url'] }}" target="_blank" rel="noopener" class="block text-teal-700 dark:text-teal-300 hover:underline truncate max-w-[12rem]">{{ __('CSV FNDE') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @php $dist = is_array($profile['distribuicao_legal']['itens'] ?? null) ? $profile['distribuicao_legal']['itens'] : []; @endphp
        @if (count($dist) > 0)
            <div class="mt-4">
                <p class="text-xs font-semibold uppercase text-slate-600 dark:text-slate-400 mb-2">{{ __('Distribuição legal planejada (ano do filtro)') }}</p>
                <ul class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-sm">
                    @foreach ($dist as $item)
                        <li class="rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2">
                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ $item['titulo'] ?? '' }}</p>
                            <p class="text-xs text-slate-600 dark:text-slate-400">{{ __('Mín. :pct%', ['pct' => number_format((float) ($item['percentual'] ?? 0), 0)]) }}</p>
                            @if (isset($item['valor']))
                                <p class="tabular-nums font-semibold mt-1">{{ $fmtBrl((float) $item['valor']) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if (filled($profile['distribuicao_legal']['referencia_legal'] ?? null))
                    <p class="mt-2 text-[11px] text-slate-500">{{ $profile['distribuicao_legal']['referencia_legal'] }}</p>
                @endif
            </div>
        @endif

        @php $pond = is_array($profile['ponderacoes_discrepancias'] ?? null) ? $profile['ponderacoes_discrepancias'] : []; @endphp
        @if ($pond !== [])
            <details class="mt-4 text-sm">
                <summary class="cursor-pointer font-medium text-slate-700 dark:text-slate-300">{{ __('Ponderações — impacto financeiro (Discrepâncias)') }}</summary>
                <ul class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-1 text-xs text-slate-600 dark:text-slate-400">
                    @foreach ($pond as $check => $peso)
                        <li><span class="font-mono">{{ $check }}</span>: ×{{ is_numeric($peso) ? number_format((float) $peso, 2, ',', '') : $peso }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </x-dashboard.consultoria-section>
@endif
