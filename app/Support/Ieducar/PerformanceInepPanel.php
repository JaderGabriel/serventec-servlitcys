<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Consolida referências IDEB, SAEB e metas do PNE na aba Desempenho.
 * Valores oficiais vêm do INEP/Ministério; opcionalmente carregam-se linhas via SQL (planilhas importadas à base).
 */
final class PerformanceInepPanel
{
    /**
     * @return array{
     *   sections: array<string, array{key: string, title: string, intro: string, items: list<array{label: string, valor: ?float, referencia: string, unidade: string, detalhe: string}>, empty_hint: string}>,
     *   consolidated_chart: ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>},
     *   sql_note: ?string,
     *   sql_error: ?string
     * }
     */
    public static function build(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $sections = [
            'ideb' => self::sectionTemplate(
                'ideb',
                __('Índice de Desenvolvimento da Educação Básica'),
                __('Combina fluxo escolar e desempenho no SAEB. Use para comparar a rede com metas e anos anteriores — não sai só do cadastro de matrículas.')
            ),
            'saeb' => self::sectionTemplate(
                'saeb',
                __('Sistema de Avaliação da Educação Básica'),
                __('Mede aprendizagem em Língua Portuguesa e Matemática. Os valores oficiais vêm da divulgação do INEP; o i-Educar não os substitui.')
            ),
            'pne' => self::sectionTemplate(
                'pne',
                __('Plano Nacional de Educação'),
                __('Metas nacionais de universalização, financiamento e qualidade. O acompanhamento municipal usa relatórios do conselho e do INEP.')
            ),
        ];

        $sql = trim((string) config('ieducar.sql.performance_inep_indicadores', ''));
        $sqlError = null;

        if ($sql === '') {
            return [
                'sections' => $sections,
                'consolidated_chart' => null,
                'sql_note' => null,
                'sql_error' => null,
            ];
        }

        try {
            $sql = IeducarSqlPlaceholders::interpolate($sql, $city);
            $rows = $db->select($sql);
        } catch (QueryException|\Throwable $e) {
            return [
                'sections' => $sections,
                'consolidated_chart' => null,
                'sql_note' => null,
                'sql_error' => __('Não foi possível executar IEDUCAR_SQL_PERFORMANCE_INEP: :msg', ['msg' => $e->getMessage()]),
            ];
        }

        $yearHint = $filters->yearFilterValue();
        $yearStr = $yearHint !== null ? (string) $yearHint : '';

        foreach ($rows as $row) {
            $arr = (array) $row;
            $eixoRaw = strtolower((string) self::pick($arr, ['eixo', 'bloco', 'categoria', 'tipo', 'secao'], ''));
            $key = self::mapEixoKey($eixoRaw);
            if ($key === null || ! isset($sections[$key])) {
                continue;
            }

            $label = (string) self::pick($arr, ['indicador', 'label', 'nome', 'metric', 'titulo'], __('Indicador'));
            $valRaw = self::pick($arr, ['valor', 'value', 'v', 'pontos'], null);
            $valor = is_numeric($valRaw) ? (float) $valRaw : null;

            $ref = (string) self::pick($arr, ['referencia', 'ano', 'ano_ref', 'periodo'], '');
            if ($ref === '' && $yearStr !== '') {
                $ref = $yearStr;
            }

            $unidade = (string) self::pick($arr, ['unidade', 'unit', 'escala'], '');
            $detalhe = (string) self::pick($arr, ['detalhe', 'observacao', 'obs', 'fonte'], '');

            $sections[$key]['items'][] = [
                'label' => $label,
                'valor' => $valor,
                'referencia' => $ref,
                'unidade' => $unidade,
                'detalhe' => $detalhe,
            ];
        }

        $consolidated = self::buildConsolidatedChart($sections);

        return [
            'sections' => $sections,
            'consolidated_chart' => $consolidated,
            'sql_note' => null,
            'sql_error' => $sqlError,
        ];
    }

    /**
     * @param  array<string, mixed>  $arr
     */
    private static function pick(array $arr, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $arr) && $arr[$k] !== null && $arr[$k] !== '') {
                return $arr[$k];
            }
        }

        return $default;
    }

    private static function mapEixoKey(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }
        if (str_contains($raw, 'ideb')) {
            return 'ideb';
        }
        if (str_contains($raw, 'saeb')) {
            return 'saeb';
        }
        if (str_contains($raw, 'pne') || str_contains($raw, 'plano nacional')) {
            return 'pne';
        }

        return null;
    }

    /**
     * @return array{key: string, title: string, intro: string, items: list<array<string, mixed>>, empty_hint: string}
     */
    private static function sectionTemplate(string $key, string $title, string $intro): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'intro' => $intro,
            'items' => [],
            'empty_hint' => __('Sem valores locais neste bloco — consulte o portal do INEP ou a série importada abaixo.'),
        ];
    }

    /**
     * @param  array<string, array{key: string, title: string, intro: string, items: list<array<string, mixed>>, empty_hint: string}>  $sections
     */
    private static function buildConsolidatedChart(array $sections): ?array
    {
        $labels = [];
        $values = [];
        foreach (['ideb', 'saeb', 'pne'] as $k) {
            foreach ($sections[$k]['items'] ?? [] as $it) {
                if (! isset($it['valor']) || ! is_numeric($it['valor'])) {
                    continue;
                }
                $prefix = match ($k) {
                    'ideb' => 'IDEB',
                    'saeb' => 'SAEB',
                    'pne' => 'PNE',
                    default => strtoupper($k),
                };
                $labels[] = $prefix.' — '.($it['label'] ?? '');
                $values[] = (float) $it['valor'];
            }
        }

        if ($labels === []) {
            return null;
        }

        [$labels, $values] = ChartPayload::capTailAsOutros($labels, $values, 14, __('Outros indicadores'));

        return ChartPayload::barHorizontal(
            __('Indicadores externos consolidados (SQL — IDEB / SAEB / PNE)'),
            __('Valor'),
            $labels,
            $values
        );
    }
}
