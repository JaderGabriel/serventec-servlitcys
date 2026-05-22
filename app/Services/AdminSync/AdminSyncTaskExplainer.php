<?php

namespace App\Services\AdminSync;

use App\Models\AdminSyncTask;

/** Textos de contexto para o log de andamento por tipo de tarefa enfileirada. */
final class AdminSyncTaskExplainer
{
    public static function summary(AdminSyncTask $task): string
    {
        $key = $task->domain.'::'.$task->task_key;

        return match ($key) {
            'fundeb::import_city_year' => __('Importa referências FUNDEB (VAAF/VAAT/VAAR) de uma cidade e um ano. Modo «atualizar» só grava se diferir; «apagar» remove o existente no âmbito antes de buscar.'),
            'fundeb::import_bulk_year' => __('Importação FUNDEB em lote para um ano (uma cidade ou todas com IBGE).'),
            'fundeb::sync_all_years' => __('Sincroniza vários anos FUNDEB para os municípios seleccionados. Respeita o modo de importação (atualizar ou apagar e buscar).'),
            'fundeb::new_city_auto' => __('Disparado ao guardar cidade: preenche anos FUNDEB por defeito para o novo IBGE.'),
            'geo::ieducar' => __('Lê unidades no i-Educar municipal e grava/atualiza coordenadas em school_unit_geos.'),
            'geo::microdados' => __('Importa cadastro de escolas INEP (microdados) e opcionalmente mapeia coordenadas.'),
            'geo::official' => __('Aplica coordenadas oficiais INEP e calcula divergência em relação ao i-Educar.'),
            'geo::pipeline' => __('Executa em sequência: i-Educar → microdados (se existir) → oficial INEP.'),
            'geo::probe' => __('Diagnóstico: testa fontes INEP e códigos das escolas da cidade (sem alterar dados).'),
            'pedagogical::import_official' => __('Agrega SAEB por IBGE; se a base estiver vazia, importa microdados INEP e mapeia INEP→cod_escola antes de usar a API interna.'),
            'pedagogical::import_urls' => __('Tenta cada URL em IEDUCAR_SAEB_IMPORT_URLS até obter JSON com chave «pontos».'),
            'pedagogical::import_csv' => __('Importa ficheiro CSV tabular (IBGE, ano, disciplina, valor) para saeb_indicator_points.'),
            'pedagogical::import_microdados' => __('Descarrega ZIP/CSV INEP, filtra pelos municípios cadastrados e normaliza para a base SAEB.'),
            'ieducar::schema_probe' => __('Gera JSON de compatibilidade do schema i-Educar da cidade (tabelas/colunas usadas pelo painel).'),
            'system::weekly_mass_sync' => __('Sincronização massiva semanal: geo (i-Educar+INEP), FUNDEB (VAAF/VAAT/VAAR), repasses, Censo e SAEB — com checkpoint retomável.'),
            default => __('Tarefa administrativa enfileirada (:key).', ['key' => $key]),
        };
    }

    /**
     * @return list<string>
     */
    public static function payloadHints(AdminSyncTask $task): array
    {
        $payload = $task->payload ?? [];
        $hints = [];

        if ($task->city_id !== null) {
            $hints[] = __('city_id: :id', ['id' => (string) $task->city_id]);
        }

        if (is_array($payload['city_ids'] ?? null) && $payload['city_ids'] !== []) {
            $hints[] = __('Municípios no payload: :n', ['n' => (string) count($payload['city_ids'])]);
        }

        if (isset($payload['ano'])) {
            $hints[] = __('Ano: :ano', ['ano' => (string) $payload['ano']]);
        }

        if (isset($payload['years']) && is_array($payload['years'])) {
            $hints[] = __('Anos: :anos', ['anos' => implode(', ', array_map('strval', $payload['years']))]);
        }

        if (isset($payload['md_year'])) {
            $hints[] = __('Ano microdados: :y', ['y' => (string) $payload['md_year']]);
        }

        if (! empty($payload['artisan_command'])) {
            $hints[] = __('Comando: php artisan :cmd', ['cmd' => (string) $payload['artisan_command']]);
        }

        $importMode = (string) ($payload['import_mode'] ?? '');
        if ($importMode !== '') {
            $hints[] = $importMode === 'replace'
                ? __('Modo: apagar referências do âmbito e buscar de novo')
                : __('Modo: atualizar só se VAAF/VAAT/VAAR diferirem');
        }

        return $hints;
    }
}
