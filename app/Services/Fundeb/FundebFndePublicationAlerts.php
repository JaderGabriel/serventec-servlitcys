<?php

namespace App\Services\Fundeb;

use App\Models\FundebMunicipioReference;
use App\Support\Fundeb\FundebReferenceSource;

/**
 * Alertas de qualidade / inconsistência em publicações FNDE por município e ano.
 */
final class FundebFndePublicationAlerts
{
    /**
     * @param  array<int, array<string, mixed>>  $yearBlocks  Saída de FundebVaafProfileBuilder por ano
     * @return list<array{
     *   id: string,
     *   severity: 'danger'|'warning'|'info',
     *   ano: ?int,
     *   titulo: string,
     *   mensagem: string,
     *   acao: ?string
     * }>
     */
    public function evaluate(array $yearBlocks): array
    {
        $alerts = [];
        $pubYears = [];
        $receitas = [];

        foreach ($yearBlocks as $ano => $block) {
            if (! is_array($block)) {
                continue;
            }
            $pub = $block['receita']['ano_publicacao'] ?? null;
            if ($pub !== null) {
                $pubYears[(int) $pub] = ($pubYears[(int) $pub] ?? 0) + 1;
            }
            $rec = $block['receita']['total'] ?? null;
            if (is_numeric($rec)) {
                $receitas[$ano] = (float) $rec;
            }

            $this->appendYearAlerts($alerts, (int) $ano, $block);
        }

        $this->appendCrossYearAlerts($alerts, $yearBlocks, $receitas, $pubYears);

        return $this->dedupeAndSort($alerts);
    }

    /**
     * @param  list<array<string, mixed>>  $alerts
     * @param  array<string, mixed>  $block
     */
    private function appendYearAlerts(array &$alerts, int $ano, array $block): void
    {
        $db = $block['db_reference'] ?? null;
        if (is_array($db) && FundebReferenceSource::isPlaceholder($db['fonte'] ?? null)) {
            $alerts[] = $this->alert(
                'placeholder_db',
                'warning',
                $ano,
                __('VAAF gravado é piso nacional (placeholder)'),
                __('O registo em fundeb_municipio_references usa referencia_nacional_config — não representa o VAAF municipal. Execute fundeb:import-api ou sync FUNDEB.'),
                __('Admin → Compatibilidade i-Educar → sincronizar FUNDEB'),
            );
        }

        $mat = $block['matriculas'] ?? [];
        if (is_array($mat) && (int) ($mat['usado'] ?? 0) <= 0) {
            $alerts[] = $this->alert(
                'sem_matriculas',
                'danger',
                $ano,
                __('Sem matrículas para estimar VAAF'),
                __('Não há matrículas ativas no i-Educar nem total do Censo INEP para este ano. A portaria FNDE (receita) não pode ser convertida em VAAF municipal.'),
                __('Verificar conexão i-Educar, ano letivo e importar Censo (sync semanal).'),
            );
        } elseif (is_array($mat) && ($mat['fonte_usada'] ?? '') === 'censo_inep') {
            $alerts[] = $this->alert(
                'matriculas_censo_fallback',
                'info',
                $ano,
                __('VAAF estimado com matrículas do Censo INEP'),
                __('Matrículas i-Educar zeradas; o denominador usa inep_censo_municipio_matriculas. Confira divergência Censo×i-Educar na aba Discrepâncias.'),
                null,
            );
        }

        $est = $block['vaaf_estimado'] ?? null;
        if (is_array($est) && ($est['fora_limites'] ?? false)) {
            $alerts[] = $this->alert(
                'vaaf_fora_limites',
                'warning',
                $ano,
                __('VAAF estimado fora dos limites de sanidade'),
                __('Receita FNDE ÷ matrículas produziu valor fora do intervalo configurado (IEDUCAR_FUNDEB_VAAF_ESTIMATE_MIN/MAX). Revise matrículas ou receita publicada.'),
                null,
            );
        }

        $rec = $block['receita'] ?? [];
        if (is_array($rec) && empty($rec['disponivel'])) {
            $alerts[] = $this->alert(
                'sem_receita_portaria',
                'warning',
                $ano,
                __('Receita FNDE (Portaria) indisponível'),
                __('Não foi encontrada linha no CSV «Receita total do Fundeb» para este IBGE/ano. O FNDE pode ainda não ter publicado o exercício.'),
                __('Consultar gov.br/fnde → FUNDEB do exercício ou aguardar nova portaria.'),
            );
        }

        $est = $block['referencia_estadual'] ?? null;
        $vaafMun = is_array($est) && is_array($block['vaaf_estimado'] ?? null)
            ? (float) ($block['vaaf_estimado']['valor'] ?? 0)
            : 0.0;
        if (is_array($est) && ($est['disponivel'] ?? false) && $vaafMun > 0) {
            $vaafUf = (float) ($est['vaaf'] ?? 0);
            if ($vaafUf > 0) {
                $pctUf = round(100.0 * abs($vaafMun - $vaafUf) / $vaafUf, 1);
                if ($pctUf >= 20) {
                    $alerts[] = $this->alert(
                        'divergencia_vaaf_uf',
                        'info',
                        $ano,
                        __('VAAF municipal estimado difere do consolidado da UF'),
                        __('Estimativa portaria÷matrículas (:mun) vs VAAF UF FNDE (:uf) — :pct% de diferença. O valor municipal depende das matrículas locais.', [
                            'mun' => number_format($vaafMun, 2, ',', '.'),
                            'uf' => number_format($vaafUf, 2, ',', '.'),
                            'pct' => number_format($pctUf, 1, ',', '.'),
                        ]),
                        __('Consultas FNDE → Valor aluno/ano por Estado.'),
                    );
                }
            }
        }

        $div = $block['resolver']['divergencia'] ?? null;
        if (is_array($div) && abs((float) ($div['pct'] ?? 0)) >= 15) {
            $alerts[] = $this->alert(
                'divergencia_previa_federal',
                'info',
                $ano,
                __('Grande diferença entre VAAF municipal e prévia federal'),
                (string) ($div['mensagem'] ?? __('Divergência superior a 15%.')),
                null,
            );
        }

        $anoCivil = (int) date('Y');
        if ($ano > $anoCivil) {
            $alerts[] = $this->alert(
                'ano_futuro_planejamento',
                'info',
                $ano,
                __('Exercício futuro — dados preliminares'),
                __('Valores para anos posteriores ao ano civil atual dependem de portarias preliminares ou projeção; não use em prestação de contas sem validar no FNDE.'),
                null,
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $alerts
     * @param  array<int, array<string, mixed>>  $yearBlocks
     * @param  array<int, float>  $receitas
     * @param  array<int, int>  $pubYears
     */
    private function appendCrossYearAlerts(array &$alerts, array $yearBlocks, array $receitas, array $pubYears): void
    {
        if (count($receitas) >= 2) {
            $vals = array_values($receitas);
            $allEqual = count(array_unique(array_map(static fn (float $v): string => number_format($v, 2, '.', ''), $vals))) === 1;
            $anos = array_keys($receitas);
            if ($allEqual && max($anos) - min($anos) >= 1) {
                $alerts[] = $this->alert(
                    'receita_repetida_publicacao',
                    'warning',
                    null,
                    __('Mesma receita FNDE em anos distintos'),
                    __('O CSV da Portaria devolve a mesma receita total para exercícios diferentes (publicação única reutilizada). Pode indicar que o FNDE ainda não publicou o exercício mais recente — não trate como erro de cadastro local.'),
                    __('Reimportar após nova portaria no gov.br/fnde.'),
                );
            }
        }

        $futureYears = array_filter(array_keys($yearBlocks), static fn (int $y): bool => $y > (int) date('Y'));
        foreach ($futureYears as $fy) {
            $block = $yearBlocks[$fy] ?? [];
            $pub = $block['receita']['ano_publicacao'] ?? null;
            if ($pub !== null && (int) $pub < $fy) {
                $alerts[] = $this->alert(
                    'portaria_defasada_ano_futuro',
                    'warning',
                    $fy,
                    __('Portaria FNDE anterior ao exercício pedido'),
                    __('Para :ano só existe publicação de :pub. Planejamento deve considerar defasagem oficial do FNDE.', [
                        'ano' => (string) $fy,
                        'pub' => (string) $pub,
                    ]),
                    null,
                );
            }
        }
    }

    /**
     * @return array{id: string, severity: string, ano: ?int, titulo: string, mensagem: string, acao: ?string}
     */
    private function alert(
        string $id,
        string $severity,
        ?int $ano,
        string $titulo,
        string $mensagem,
        ?string $acao,
    ): array {
        return [
            'id' => $id,
            'severity' => $severity,
            'ano' => $ano,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'acao' => $acao,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $alerts
     * @return list<array<string, mixed>>
     */
    private function dedupeAndSort(array $alerts): array
    {
        $seen = [];
        $out = [];
        $order = ['danger' => 0, 'warning' => 1, 'info' => 2];

        foreach ($alerts as $a) {
            $key = ($a['id'] ?? '').'|'.($a['ano'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $a;
        }

        usort($out, static function (array $a, array $b) use ($order): int {
            $sa = $order[$a['severity'] ?? 'info'] ?? 9;
            $sb = $order[$b['severity'] ?? 'info'] ?? 9;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            return ((int) ($a['ano'] ?? 0)) <=> ((int) ($b['ano'] ?? 0));
        });

        return $out;
    }
}
