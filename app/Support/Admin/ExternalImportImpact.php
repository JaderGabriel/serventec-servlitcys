<?php

namespace App\Support\Admin;

use App\Models\AdminSyncTask;

/**
 * Liga importações externas (admin) ao que o usuário ganha no painel.
 */
final class ExternalImportImpact
{
    /**
     * @return array{
     *     title: string,
     *     intro: string,
     *     improves: list<string>,
     *     consumers: list<array{label: string, hint: string}>
     * }
     */
    public static function forDomain(string $domain): array
    {
        return match ($domain) {
            'fundeb' => [
                'title' => __('Para que serve a importação FUNDEB'),
                'intro' => __('Grava VAAF, VAAT e complementação VAAR por município/ano. Sem referência municipal, o sistema usa piso nacional — as estimativas de repasse ficam conservadoras.'),
                'improves' => [
                    __('Previsão de volume FUNDEB e comparativos com portarias FNDE'),
                    __('Impacto financeiro indicativo na aba Discrepâncias (R$ por ocorrência)'),
                    __('Matriz VAAF/VAAT abaixo e export CSV para auditoria'),
                    __('Relatório PDF analítico e aba FUNDEB da consultoria'),
                ],
                'consumers' => [
                    ['label' => __('Consultoria → FUNDEB'), 'hint' => __('tab=fundeb')],
                    ['label' => __('Consultoria → Discrepâncias'), 'hint' => __('tab=discrepancies')],
                ],
            ],
            'geo' => [
                'title' => __('Para que serve a sincronização geográfica'),
                'intro' => __('Alinha coordenadas do i-Educar com fontes INEP (oficial, microdados, ArcGIS). Reduz escolas «sem mapa» e permite medir divergência em metros.'),
                'improves' => [
                    __('Mapa de unidades escolares na consultoria (marcadores e QEdu)'),
                    __('Alertas de divergência i-Educar × INEP oficial'),
                    __('Cobertura geográfica no painel inicial (mapa de municípios)'),
                ],
                'consumers' => [
                    ['label' => __('Consultoria → Unidades escolares'), 'hint' => __('tab=school-units')],
                    ['label' => __('Início → mapa de municípios'), 'hint' => __('dashboard')],
                ],
            ],
            'pedagogical' => [
                'title' => __('Para que serve a importação SAEB'),
                'intro' => __('Preenche indicadores de desempenho (pontos SAEB) por município, escola e série. Na primeira carga use microdados INEP ou CSV — o passo HTTP por IBGE só funciona depois de já existirem pontos ou com URL externa.'),
                'improves' => [
                    __('Gráficos e séries na aba Desempenho (SAEB/IDEB)'),
                    __('Comparativos municipais e contexto de rede na consultoria'),
                    __('Cruzamento com cadastro i-Educar (INEP → cod_escola)'),
                ],
                'consumers' => [
                    ['label' => __('Consultoria → Desempenho'), 'hint' => __('tab=performance')],
                ],
            ],
            default => [
                'title' => __('Importação administrativa'),
                'intro' => __('Tarefa enfileirada que actualiza dados usados pelos painéis municipais.'),
                'improves' => [],
                'consumers' => [],
            ],
        };
    }

    /**
     * @return array{title: string, detail: string}|null
     */
    public static function taskOutcomeHint(AdminSyncTask $task): ?array
    {
        $key = $task->domain.'::'.$task->task_key;

        return match ($key) {
            'fundeb::import_city_year', 'fundeb::import_bulk_year', 'fundeb::sync_all_years', 'fundeb::new_city_auto' => [
                'title' => __('Quando concluir'),
                'detail' => __('Actualize a consultoria (FUNDEB / Discrepâncias) com o mesmo município e ano letivo. Valores «consolidados» (verde na matriz) substituem o piso nacional nas estimativas.'),
            ],
            'geo::ieducar', 'geo::official', 'geo::microdados', 'geo::pipeline' => [
                'title' => __('Quando concluir'),
                'detail' => __('Abra Unidades escolares no mapa: escolas com INEP devem mostrar coordenadas e, se aplicável, alerta de divergência.'),
            ],
            'geo::probe' => [
                'title' => __('Resultado esperado'),
                'detail' => __('Log na fila com fontes INEP testadas — não altera dados. Use para diagnosticar rede ou .env antes de repetir passos 1–4.'),
            ],
            'pedagogical::import_official', 'pedagogical::import_urls', 'pedagogical::import_csv', 'pedagogical::import_microdados' => [
                'title' => __('Quando concluir'),
                'detail' => __('Verifique o contador de pontos nesta página e abra Desempenho na consultoria do município.'),
            ],
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function recommendedOrder(string $domain): array
    {
        return match ($domain) {
            'fundeb' => [
                __('1. Confirme IBGE em cada cidade'),
                __('2. Importe ano de referência + 2 anteriores (modo «atualizar» na rotina)'),
                __('3. Se só aparecer piso nacional (âmbar), configure CKAN ou use «apagar e buscar»'),
            ],
            'geo' => [
                __('1. Passo 1 (i-Educar) ou pipeline completo'),
                __('2. Passo 2 (oficial INEP) para divergência'),
                __('3. Passo 3 (microdados) se faltar cadastro INEP'),
            ],
            'pedagogical' => [
                __('1. Primeira carga: passo 4 (microdados) ou 2 (CSV)'),
                __('2. Passo 3 (HTTP/IBGE) para actualizações posteriores'),
                __('3. Confirme pontos > 0 antes de abrir Desempenho'),
            ],
            default => [],
        };
    }
}
