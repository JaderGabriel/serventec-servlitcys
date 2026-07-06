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
            'fundeb::sync_all_years' => __('Sincroniza vários anos FUNDEB para os municípios selecionados. Respeita o modo de importação (atualizar ou apagar e buscar).'),
            'fundeb::new_city_auto' => __('Disparado ao guardar cidade: preenche anos FUNDEB por defeito para o novo IBGE.'),
            'geo::ieducar' => __('Lê unidades no i-Educar municipal e grava/atualiza coordenadas em school_unit_geos.'),
            'geo::microdados' => __('Importa cadastro de escolas INEP (microdados) e opcionalmente mapeia coordenadas.'),
            'geo::official' => __('Aplica coordenadas oficiais INEP e calcula divergência em relação ao i-Educar.'),
            'geo::pipeline' => __('Executa em sequência: i-Educar → microdados (se existir) → oficial INEP.'),
            'geo::probe' => __('Diagnóstico: testa fontes INEP e códigos das escolas da cidade (sem alterar dados).'),
            'pedagogical::import_official' => __('Agrega SAEB por IBGE; se a base estiver vazia, importa microdados INEP e mapeia INEP→cod_escola antes de usar a API interna.'),
            'pedagogical::import_urls' => __('Tenta cada URL em IEDUCAR_SAEB_IMPORT_URLS até obter JSON com chave «pontos».'),
            'pedagogical::import_csv' => __('Importa arquivo CSV tabular (IBGE, ano, disciplina, valor) para saeb_indicator_points.'),
            'pedagogical::import_microdados' => __('Descarrega ZIP/CSV INEP, filtra pelos municípios cadastrados e normaliza para a base SAEB.'),
            'cadastro::import_city_year' => __('CadÚnico/Cecad: tenta API ou CKAN, depois cache JSON e por fim CSV em storage para o município e ano.'),
            'cadastro::import_storage_year' => __('Importa todas as linhas de CSV Cecad em storage para o ano indicado (arquivo nacional ou vários municipais).'),
            'cadastro::import_csv' => __('Importa CSV Cecad enviado pelo admin (upload) para cadunico_municipio_snapshots.'),
            'cadastro::auto_sync' => __('Sem upload: descarrega CSV nacional da URL configurada, importa todos os municípios do arquivo e preenche lacunas via API/CKAN.'),
            'cadastro::sync_territorio_flow_city' => __('Fluxo completo do mapa: snapshot municipal (Misocial/API/CSV) e depois territórios IBGE (Censo 2022 + WFS) com rateio 4–17.'),
            'cadastro::sync_territorio_city' => __('Só mapa territorial: exige snapshot municipal já importado; usa FTP/WFS IBGE (cache em storage).'),
            'cadastro::sync_territorio_all' => __('Mapa territorial para todos os municípios analytics — após auto-sync municipal.'),
            'ieducar::schema_probe' => __('Gera JSON de compatibilidade do schema i-Educar da cidade (tabelas/colunas usadas pelo painel).'),
            'ieducar::inclusion_nee_export' => __('Exporta CSV ou Excel com matrículas NEE, dados pessoais, designações e inconsistências (recorte dos filtros da aba Inclusão).'),
            'system::weekly_mass_sync' => __('Sincronização massiva semanal: geo (i-Educar+INEP), FUNDEB (VAAF/VAAT/VAAR), repasses, Censo e SAEB — com checkpoint retomável.'),
            'funding::import_transfers_city_year' => __('Importa repasses municipais FUNDEB (CKAN/SISWEB/BB) com granularidade dia/mês; re-enriquece anos históricos em falta. Não grava total da UF.'),
            'funding::rebuild_finance_realtime' => __('Rebuild Finanças → Tempo Real: apaga snapshots do(s) ano(s) e reimporta só fontes municipais com meta.mensal/repasses.'),
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

        if (isset($payload['ano_letivo'])) {
            $hints[] = __('Ano letivo: :ano', ['ano' => (string) $payload['ano_letivo']]);
        }

        if (isset($payload['format'])) {
            $hints[] = __('Formato: :fmt', ['fmt' => strtoupper((string) $payload['format'])]);
        }

        if (isset($payload['inclusion_scope'])) {
            $hints[] = __('Recorte inclusão: :scope', ['scope' => (string) $payload['inclusion_scope']]);
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
