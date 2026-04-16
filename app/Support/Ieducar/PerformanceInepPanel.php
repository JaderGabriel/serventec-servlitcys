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
                __('IDEB (Índice de Desenvolvimento da Educação Básica)'),
                __('O IDEB agrega fluxo (aprovação) e notas médias da avaliação SAEB, divulgado pelo INEP por rede e ano de aplicação. O município compara-se à meta nacional/regional; não é calculado a partir apenas do registo local de situação de matrícula.')
            ),
            'saeb' => self::sectionTemplate(
                'saeb',
                __('SAEB (Sistema de Avaliação da Educação Básica)'),
                __('O SAEB mede desempenho em Língua Portuguesa e Matemática (escalas próprias e proficientes). Os resultados publicados referem-se à rede e ao ano de avaliação; a base i-Educar não substitui o portal do INEP.')
            ),
            'pne' => self::sectionTemplate(
                'pne',
                __('Metas do Plano Nacional de Educação (PNE)'),
                __('As metas do PNE (Lei 13.005/2014 e atualizações) incluem universalização, financiamento, educação infantil, ensino médio, entre outras, com indicadores nacionais. O acompanhamento municipal costuma usar relatórios do conselho de educação e do INEP.')
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
            'empty_hint' => __('Sem linhas SQL para este bloco — use a consulta configurada ou consulte o portal do INEP / documentos do PNE.'),
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
