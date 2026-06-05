@php
    use App\Support\Rx\RxFundebPortariaChart as RxFundebFmt;

    $fundebPortaria = is_array($rx['fundeb_portaria'] ?? null) ? $rx['fundeb_portaria'] : [];
    $available = ! empty($fundebPortaria['available']);

    $fmtBrl = static fn (?float $v, bool $compact = false): string => RxFundebFmt::formatBrl($v, $compact);
    $fmtPer = static fn (?float $v): string => RxFundebFmt::formatPerPupil($v);
    $fmtInt = static fn (?int $v): string => RxFundebFmt::formatInt($v);
    $fmtYn = static fn (?float $v): string => RxFundebFmt::formatYesNo($v);

    $national = is_array($fundebPortaria['national'] ?? null) ? $fundebPortaria['national'] : [];
    $mStats = is_array($fundebPortaria['municipal_stats'] ?? null) ? $fundebPortaria['municipal_stats'] : [];
    $totalsCards = is_array($fundebPortaria['totals_cards'] ?? null) ? $fundebPortaria['totals_cards'] : [];
    $ibgeTable = is_array($fundebPortaria['ibge_table'] ?? null) ? $fundebPortaria['ibge_table'] : [];
    $ibgeWarnings = is_array($fundebPortaria['ibge_warnings'] ?? null) ? $fundebPortaria['ibge_warnings'] : [];
    $vaatCompare = is_array($fundebPortaria['vaat_compare'] ?? null) ? $fundebPortaria['vaat_compare'] : [];
    $gapsTable = is_array($fundebPortaria['gaps_table'] ?? null) ? $fundebPortaria['gaps_table'] : [];
    $danger = is_array($fundebPortaria['danger_callout'] ?? null) ? $fundebPortaria['danger_callout'] : [];
    $fundebChart = is_array($fundebPortaria['chart'] ?? null) ? $fundebPortaria['chart'] : null;
    $totalMun = (int) ($fundebPortaria['municipios_total'] ?? count($ibgeTable));
@endphp

@if ($available)
<section aria-labelledby="rx-fundeb-portaria" class="space-y-5 rx-fundeb-portaria">
    <div class="serv-panel px-4 py-3 border-sky-200/80 dark:border-sky-800/50 bg-sky-50/50 dark:bg-sky-950/20">
        <p id="rx-fundeb-portaria" class="font-medium text-sky-950 dark:text-sky-100">
            {{ __('FUNDEB — complementações da portaria (:ano)', ['ano' => (string) ($fundebPortaria['exercicio'] ?? '')]) }}
        </p>
        <p class="mt-1 text-xs text-sky-900/90 dark:text-sky-200/90 leading-relaxed">
            {{ __('Dados consolidados do FNDE (:portaria). O cadastro RX mede o volume em andamento no i-Educar e só impacta repasses após consolidação no exercício seguinte.', [
                'portaria' => $fundebPortaria['portaria_label'] ?? __('portaria vigente'),
            ]) }}
        </p>
        <p class="mt-1 text-[11px] text-sky-800/80 dark:text-sky-300/80">
            {{ __('Recorte: :n município(s) visíveis no seu perfil.', ['n' => $totalMun]) }}
        </p>
    </div>

    <div class="serv-rx-kpi-grid serv-rx-kpi-grid--fundeb">
        <div class="serv-home-kpi serv-home-kpi--teal">
            <p class="serv-home-kpi__label">{{ __('VAAF-MIN nacional') }}</p>
            <p class="serv-home-kpi__value text-2xl">{{ $fmtPer($national['vaaf_min'] ?? null) }}</p>
        </div>
        <div class="serv-home-kpi serv-home-kpi--teal">
            <p class="serv-home-kpi__label">{{ __('VAAT-MIN nacional') }}</p>
            <p class="serv-home-kpi__value text-2xl">{{ $fmtPer($national['vaat_min'] ?? null) }}</p>
        </div>
        <div class="serv-home-kpi">
            <p class="serv-home-kpi__label">{{ __('Receita vinculada (BR)') }}</p>
            <p class="serv-home-kpi__value text-2xl">{{ $fmtBrl($national['receita_vinculada'] ?? null, true) }}</p>
        </div>
        <div class="serv-home-kpi">
            <p class="serv-home-kpi__label">{{ __('Complementação União (BR)') }}</p>
            <p class="serv-home-kpi__value text-2xl">{{ $fmtBrl($national['complementacao_uniao'] ?? null, true) }}</p>
        </div>
    </div>

    <div class="serv-panel overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-display font-semibold text-serv-navy dark:text-white">{{ __('IBGE dos municípios cadastrados') }}</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ __('Fonte: CSV receita FNDE + CSV VAAT · complementação = valor previsto em R$ na portaria') }}
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="serv-rx-table min-w-full text-sm text-left">
                <thead>
                    <tr>
                        <th class="px-3 py-2">{{ __('Município (sistema)') }}</th>
                        <th class="px-3 py-2 text-center">IBGE</th>
                        <th class="px-3 py-2">{{ __('Nome oficial (FNDE)') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Receita total') }}</th>
                        <th class="px-3 py-2 text-center">{{ __('Compl. VAAF') }}</th>
                        <th class="px-3 py-2 text-center">{{ __('Compl. VAAT') }}</th>
                        <th class="px-3 py-2 text-center">{{ __('Compl. VAAR') }}</th>
                        <th class="px-3 py-2 text-center">IEI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($ibgeTable as $row)
                        <tr>
                            <td class="px-3 py-2 font-medium text-slate-900 dark:text-slate-100">{{ $row['city_name'] ?? '' }}</td>
                            <td class="px-3 py-2 text-center tabular-nums">{{ $row['ibge'] ?? '' }}</td>
                            <td class="px-3 py-2">{{ $row['nome_oficial'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtBrl($row['receita_total'] ?? null, true) }}</td>
                            <td class="px-3 py-2 text-center">{{ $fmtYn($row['compl_vaaf'] ?? null) }}</td>
                            <td class="px-3 py-2 text-center">{{ $fmtYn($row['compl_vaat'] ?? null) }}</td>
                            <td class="px-3 py-2 text-center">{{ $fmtYn($row['compl_vaar'] ?? null) }}</td>
                            <td class="px-3 py-2 text-center">{{ $row['iei_pct'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($ibgeWarnings as $warn)
        <div class="serv-panel border-amber-200/90 bg-amber-50/60 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/50 dark:bg-amber-950/25 dark:text-amber-100">
            <strong>{{ $warn['ibge'] ?? '' }}</strong>
            — {{ __('cadastrado como «:nome», nome oficial FNDE/IBGE: :oficial', [
                'nome' => $warn['city_name'] ?? '',
                'oficial' => $warn['nome_oficial'] ?? '—',
            ]) }}
        </div>
    @endforeach

    <div class="serv-rx-kpi-grid serv-rx-kpi-grid--fundeb">
        <div class="serv-home-kpi">
            <p class="serv-home-kpi__label">{{ __('Receita total (:n mun.)', ['n' => $totalMun]) }}</p>
            <p class="serv-home-kpi__value text-2xl">{{ $fmtBrl($mStats['receita_total'] ?? null, true) }}</p>
        </div>
        <div class="serv-home-kpi serv-home-kpi--amber">
            <p class="serv-home-kpi__label">{{ __('Com complementação VAAR') }}</p>
            <p class="serv-home-kpi__value text-2xl">
                {{ (int) ($mStats['com_vaar'] ?? 0) }}/{{ $totalMun }}
            </p>
        </div>
        <div class="serv-home-kpi">
            <p class="serv-home-kpi__label">{{ __('Sem complementação VAAT') }}</p>
            <p class="serv-home-kpi__value text-2xl">
                {{ (int) ($mStats['sem_vaat'] ?? 0) }}/{{ $totalMun }}
            </p>
        </div>
        <div class="serv-home-kpi serv-home-kpi--amber">
            <p class="serv-home-kpi__label">{{ __('VAAT DB = piso (erro)') }}</p>
            <p class="serv-home-kpi__value text-2xl text-rose-700 dark:text-rose-300">
                {{ (int) ($mStats['vaat_piso_db'] ?? 0) }}/{{ $totalMun }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="serv-panel px-4 py-3">
            <p class="serv-eyebrow">{{ __('Totais') }}</p>
            <h4 class="font-display font-semibold text-serv-navy dark:text-white">{{ __('Complementação no recorte') }}</h4>
            <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-300">
                <li>VAAF: <span class="font-medium tabular-nums">{{ $fmtBrl($totalsCards['compl_vaaf'] ?? null) }}</span></li>
                <li>VAAT: <span class="font-medium tabular-nums">{{ $fmtBrl($totalsCards['compl_vaat'] ?? null) }}</span></li>
                <li>VAAR: <span class="font-medium tabular-nums">{{ $fmtBrl($totalsCards['compl_vaar'] ?? null) }}</span></li>
            </ul>
        </div>
        <div class="serv-panel px-4 py-3">
            <p class="serv-eyebrow">{{ __('VAAR') }}</p>
            <h4 class="font-display font-semibold text-serv-navy dark:text-white">{{ __('Elegibilidade VAAR (portaria)') }}</h4>
            @if (! empty($totalsCards['with_vaar']))
                <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">
                    <span class="font-medium">{{ __('Com VAAR:') }}</span>
                    {{ implode(', ', $totalsCards['with_vaar']) }}
                </p>
            @endif
            @if (! empty($totalsCards['without_vaar']))
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-medium">{{ __('Sem VAAR:') }}</span>
                    {{ implode(', ', $totalsCards['without_vaar']) }}
                </p>
            @endif
        </div>
    </div>

    <div class="serv-panel overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-display font-semibold text-serv-navy dark:text-white">{{ __('Portaria × banco de dados (:ano)', ['ano' => (string) ($fundebPortaria['exercicio'] ?? '')]) }}</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('VAAT municipal vs VAAT gravado em fundeb_municipio_references') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="serv-rx-table min-w-full text-sm text-left">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-center">IBGE</th>
                        <th class="px-3 py-2 text-right">{{ __('VAAT antes (portaria)') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('VAAT c/ compl.') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('VAAT no DB') }}</th>
                        <th class="px-3 py-2">{{ __('Diagnóstico') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($vaatCompare as $row)
                        <tr class="{{ ($row['vaat_piso_erro'] ?? false) ? 'bg-amber-50/80 dark:bg-amber-950/20' : '' }}">
                            <td class="px-3 py-2 text-center tabular-nums">{{ $row['ibge'] ?? '' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtPer($row['vaat_antes'] ?? null) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtPer($row['vaat_com_compl'] ?? null) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtPer($row['vaat_db'] ?? null) }}</td>
                            <td class="px-3 py-2 text-xs">{{ $row['vaat_diagnostico'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="serv-panel overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-display font-semibold text-serv-navy dark:text-white">{{ __('Base de cálculo e lacunas por município') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="serv-rx-table min-w-full text-sm text-left">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-center">IBGE</th>
                        <th class="px-3 py-2">{{ __('Município') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Censo INEP') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('i-Educar') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('VAAF DB') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('VAAT port.') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('VAAT DB') }}</th>
                        <th class="px-3 py-2">{{ __('Lacunas') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($gapsTable as $row)
                        @php
                            $gapCount = count($row['gaps'] ?? []);
                            $rowTone = $gapCount >= 3 ? 'bg-rose-50/70 dark:bg-rose-950/20' : ($gapCount >= 2 ? 'bg-amber-50/70 dark:bg-amber-950/20' : '');
                        @endphp
                        <tr class="{{ $rowTone }}">
                            <td class="px-3 py-2 text-center tabular-nums">{{ $row['ibge'] ?? '' }}</td>
                            <td class="px-3 py-2 font-medium">{{ $row['city_name'] ?? '' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtInt($row['censo_matriculas'] ?? null) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtInt($row['ieducar_matriculas'] ?? null) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtPer($row['vaaf_db'] ?? null) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtPer($row['vaat_antes'] ?? null) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtPer($row['vaat_db'] ?? null) }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600 dark:text-slate-400">{{ $row['gaps_label'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if (! empty($danger['show']))
        <div class="serv-panel border-rose-200/90 bg-rose-50/60 px-4 py-3 text-sm text-rose-900 dark:border-rose-800/50 dark:bg-rose-950/25 dark:text-rose-100 leading-relaxed">
            {{ __('Em :n de :total município(s) do recorte, as matrículas i-Educar no RX são zero enquanto o VAAF na portaria pode ter sido estimado via Censo INEP. A conciliação com o cadastro em andamento exige atenção.', [
                'n' => (int) ($danger['ieducar_zero'] ?? 0),
                'total' => (int) ($danger['total'] ?? 0),
            ]) }}
        </div>
    @endif

    @if ($fundebChart !== null)
        <div class="rx-fundeb-portaria__chart">
            <x-dashboard.chart-panel
                :chart="$fundebChart"
                export-filename="rx-fundeb-complementacoes-{{ $fundebPortaria['exercicio'] ?? 'ano' }}"
                :compact="false"
                chart-panel-id="rx-fundeb-complementacoes"
            />
        </div>
    @endif
</section>
@endif
