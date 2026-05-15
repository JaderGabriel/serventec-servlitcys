<?php

namespace App\Support\Dashboard;

use App\Models\City;

/**
 * Catálogo de fontes públicas para extração de dados e relatórios (FUNDEB, FNDE, Tesouro, INEP, Simec).
 */
final class PublicDataSourcesCatalog
{
    /**
     * @param  'all'|'financeiro'|'pedagogico'|'compliance'  $context
     * @return array{
     *   intro: string,
     *   categories: list<array{
     *     id: string,
     *     titulo: string,
     *     descricao: string,
     *     links: list<array{
     *       id: string,
     *       label: string,
     *       url: string,
     *       tipo: string,
     *       nota: ?string,
     *       requer_login: bool
     *     }>
     *   }>
     * }
     */
    public static function build(?City $city = null, string $context = 'all'): array
    {
        if (! (bool) config('ieducar.public_data_sources.enabled', true)) {
            return ['intro' => '', 'categories' => []];
        }

        $ibge = filled($city?->ibge_municipio) ? (string) $city->ibge_municipio : null;
        $uf = filled($city?->uf) ? strtoupper((string) $city->uf) : null;
        $cityName = filled($city?->name) ? (string) $city->name : null;

        $categories = array_values(array_filter([
            self::categoryFundebFnde($context),
            self::categoryRepasses($context, $ibge, $uf),
            self::categorySimecVaar($context),
            self::categoryInepPedagogico($context),
            self::categoryTransparencia($context, $cityName, $uf),
        ]));

        $extra = config('ieducar.public_data_sources.extra_categories', []);
        if (is_array($extra)) {
            foreach ($extra as $cat) {
                if (is_array($cat) && filled($cat['id'] ?? null)) {
                    $categories[] = $cat;
                }
            }
        }

        return [
            'intro' => __(
                'Use estas fontes oficiais para extrair relatórios e cruzar com o i-Educar. Painéis e dados abertos são públicos; o Simec exige credencial de gestor para diligências e comprovação VAAR.'
            ),
            'categories' => $categories,
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function categoryFundebFnde(string $context): ?array
    {
        if (! self::matchesContext($context, ['all', 'financeiro', 'compliance'])) {
            return null;
        }

        return [
            'id' => 'fundeb-fnde',
            'titulo' => __('FUNDEB e FNDE'),
            'descricao' => __('Distribuição, complementação (VAAF, VAAT, VAAR), matrículas-base e cronograma de repasses.'),
            'links' => [
                self::link(
                    'fnde-consultas',
                    __('Consultas FUNDEB (painéis interativos)'),
                    'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas',
                    'painel',
                    __('Filtro por estado/município; exporte gráficos ou use capturas para anexos.')
                ),
                self::link(
                    'fnde-dados-abertos',
                    __('Dados abertos FNDE'),
                    'https://www.gov.br/fnde/pt-br/acesso-a-informacao/dados-abertos',
                    'dados_abertos',
                    __('CSV/XLSX e plano de dados abertos; ideal para planilhas de análise.')
                ),
                self::link(
                    'dados-gov-fnde',
                    __('Catálogo dados.gov.br (FNDE)'),
                    'https://dados.gov.br/organization/fundo-nacional-de-desenvolvimento-da-educacao-fnde',
                    'dados_abertos',
                    __('Conjuntos nacionais para download ou API CKAN.')
                ),
                self::link(
                    'fnde-fundeb-ano',
                    __('Página FUNDEB (ano corrente)'),
                    'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb',
                    'relatorio',
                    __('Normas, comunicados e materiais de referência.')
                ),
            ],
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function categoryRepasses(string $context, ?string $ibge, ?string $uf): ?array
    {
        if (! self::matchesContext($context, ['all', 'financeiro'])) {
            return null;
        }

        $notaIbge = $ibge !== null
            ? __('Use o código IBGE :c ao filtrar transferências por município.', ['c' => $ibge])
            : __('Configure o código IBGE do município na cidade para facilitar o filtro.');

        return [
            'id' => 'repasses',
            'titulo' => __('Repasses efetivados (União)'),
            'descricao' => __('Valores transferidos pela União, incluindo transferências constitucionais e FUNDEB.'),
            'links' => [
                self::link(
                    'tesouro-transferencias',
                    __('Transferências obrigatórias por município (CSV)'),
                    'https://www.tesourotransparente.gov.br/ckan/dataset/transferencias-obrigatorias-da-uniao-por-municipio',
                    'dados_abertos',
                    $notaIbge
                ),
                self::link(
                    'tesouro-api',
                    __('API CKAN — Transferências constitucionais'),
                    'https://www.tesourotransparente.gov.br/ckan/dataset/api-de-transferencias-constitucionais',
                    'api',
                    __('Integração técnica ou consultas automatizadas por período.')
                ),
                self::link(
                    'portal-transparencia',
                    __('Portal da Transparência'),
                    'https://portaldatransparencia.gov.br',
                    'painel',
                    __('Despesas e transferências federais; combine com o extrato local.')
                ),
            ],
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function categorySimecVaar(string $context): ?array
    {
        if (! self::matchesContext($context, ['all', 'financeiro', 'compliance'])) {
            return null;
        }

        return [
            'id' => 'simec-vaar',
            'titulo' => __('Simec / VAAR (comprovação)'),
            'descricao' => __('Situação do ciclo VAAR, diligências e envio de documentos — não substitui dados abertos.'),
            'links' => [
                self::link(
                    'simec-portal',
                    __('Sistema Simec (MEC)'),
                    'https://simec.mec.gov.br/',
                    'sistema',
                    __('Acesso com perfil habilitado (gestor municipal/estadual). Módulo FUNDEB → Situação VAAR.'),
                    true
                ),
                self::link(
                    'mec-fundeb',
                    __('MEC — FUNDEB e VAAR'),
                    'https://www.gov.br/mec/pt-br/acesso-a-informacao/institucional/fundeb',
                    'relatorio',
                    __('Orientações oficiais e comunicados.')
                ),
                self::link(
                    'vaar-email',
                    __('Suporte VAAR-FUNDEB (e-mail)'),
                    'mailto:vaarfundeb.seb@mec.gov.br',
                    'relatorio',
                    __('Dúvidas sobre diligências e prazos.')
                ),
            ],
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function categoryInepPedagogico(string $context): ?array
    {
        if (! self::matchesContext($context, ['all', 'pedagogico', 'compliance'])) {
            return null;
        }

        return [
            'id' => 'inep',
            'titulo' => __('INEP — aprendizagem e Censo'),
            'descricao' => __('IDEB, SAEB, microdados e catálogo de escolas para cruzamento com o cadastro local.'),
            'links' => [
                self::link(
                    'portal-ideb',
                    __('Portal IDEB'),
                    'https://www.portalideb.org.br',
                    'painel',
                    __('Séries por rede e escola.')
                ),
                self::link(
                    'inep-portal',
                    __('Portal INEP'),
                    'https://www.gov.br/inep/pt-br',
                    'relatorio',
                    __('SAEB, IDEB, Educacenso e downloads.')
                ),
                self::link(
                    'inep-microdados',
                    __('Microdados e avaliações (INEP)'),
                    'https://www.gov.br/inep/pt-br/acesso-a-informacao/dados-abertos/microdados',
                    'dados_abertos',
                    __('Extração para análises estatísticas.')
                ),
                self::link(
                    'catalogo-escolas',
                    __('Catálogo de Escolas (consulta)'),
                    'https://catalogo.inep.gov.br/',
                    'painel',
                    __('Confirme INEP, situação e localização das unidades.')
                ),
            ],
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function categoryTransparencia(string $context, ?string $cityName, ?string $uf): ?array
    {
        if (! self::matchesContext($context, ['all', 'financeiro'])) {
            return null;
        }

        return [
            'id' => 'transparencia-local',
            'titulo' => __('Transparência e controle local'),
            'descricao' => __('Execução municipal e fiscalização — complementam FNDE e Simec.'),
            'links' => array_values(array_filter([
                $cityName !== null && $uf !== null
                    ? self::link(
                        'hint-local',
                        __('Portal de transparência do município'),
                        '#',
                        'relatorio',
                        __('Consulte o site da Prefeitura de :c/:uf (educação e finanças). Não há URL única nacional.', [
                            'c' => $cityName,
                            'uf' => $uf,
                        ])
                    )
                    : null,
                self::link(
                    'tcu-portal',
                    __('Portal TCU — transparência'),
                    'https://portal.tcu.gov.br/',
                    'relatorio',
                    __('Referência para controle externo federal.')
                ),
            ])),
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function matchesContext(string $context, array $allowed): bool
    {
        return $context === 'all' || in_array($context, $allowed, true);
    }

    /**
     * @return array{id: string, label: string, url: string, tipo: string, nota: ?string, requer_login: bool}
     */
    private static function link(
        string $id,
        string $label,
        string $url,
        string $tipo,
        ?string $nota = null,
        bool $requerLogin = false,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'url' => $url,
            'tipo' => $tipo,
            'nota' => $nota,
            'requer_login' => $requerLogin,
        ];
    }
}
