<?php

namespace App\Support\Inep;

/**
 * Gera o bloco meta.explicacao_modal (texto para modal) a partir dos pontos SAEB importados.
 * O hash do conteúdo permite só regenerar o texto quando os dados mudam.
 */
final class SaebExplicacaoModalBuilder
{
    /**
     * Hash estável do conjunto de pontos (para detectar alterações após sincronização).
     *
     * @param  list<array<string, mixed>>  $pontos
     */
    public static function hashConteudo(array $pontos): string
    {
        $norm = [];
        foreach ($pontos as $p) {
            if (! is_array($p)) {
                continue;
            }
            $ano = isset($p['ano']) && is_numeric($p['ano']) ? (int) $p['ano'] : (isset($p['year']) && is_numeric($p['year']) ? (int) $p['year'] : 0);
            $disc = strtolower(trim((string) ($p['disciplina'] ?? $p['disc'] ?? '')));
            $etapa = strtolower(trim((string) ($p['etapa'] ?? $p['etapa_ensino'] ?? '')));
            $st = strtolower(trim((string) ($p['status'] ?? $p['tipo'] ?? '')));
            $val = $p['valor'] ?? $p['value'] ?? null;
            $esc = isset($p['escola_id']) && is_numeric($p['escola_id']) ? (int) $p['escola_id'] : null;
            $norm[] = [
                'ano' => $ano,
                'disciplina' => $disc,
                'etapa' => $etapa,
                'status' => $st,
                'escola_id' => $esc,
                'valor' => is_numeric($val) ? round((float) $val, 6) : null,
            ];
        }
        usort($norm, static function (array $a, array $b): int {
            return [$a['ano'], $a['disciplina'], $a['etapa'], $a['status'], $a['escola_id'] ?? 0]
                <=> [$b['ano'], $b['disciplina'], $b['etapa'], $b['status'], $b['escola_id'] ?? 0];
        });

        return hash('sha256', json_encode($norm, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $decoded  JSON completo (pontos + meta anterior)
     * @param  list<string>  $tentativasUrls
     * @return array{
     *   hash_conteudo: string,
     *   gerado_em: string,
     *   titulo: string,
     *   secoes: list<array{titulo: string, paragrafos: list<string>}>,
     *   links: list<array{label: string, url: string, nota: ?string}>
     * }
     */
    public static function build(array $decoded, string $fonteEfetiva, array $tentativasUrls): array
    {
        $pontos = $decoded['pontos'] ?? $decoded['points'] ?? [];
        $pontos = is_array($pontos) ? $pontos : [];
        $hash = self::hashConteudo($pontos);

        $anos = [];
        $discs = [];
        $etapas = [];
        $nFinal = 0;
        $nPre = 0;
        $nMunicipal = 0;
        $nEscola = 0;
        foreach ($pontos as $p) {
            if (! is_array($p)) {
                continue;
            }
            $hasEsc = (isset($p['escola_id']) && is_numeric($p['escola_id']) && (int) $p['escola_id'] > 0)
                || (isset($p['escola_ids']) && is_array($p['escola_ids']) && $p['escola_ids'] !== []);
            if ($hasEsc) {
                $nEscola++;
            } else {
                $nMunicipal++;
            }
            $ano = isset($p['ano']) && is_numeric($p['ano']) ? (int) $p['ano'] : (isset($p['year']) && is_numeric($p['year']) ? (int) $p['year'] : null);
            if ($ano !== null && $ano > 0) {
                $anos[$ano] = true;
            }
            $d = trim((string) ($p['disciplina'] ?? $p['disc'] ?? ''));
            if ($d !== '') {
                $discs[strtolower($d)] = $d;
            }
            $e = trim((string) ($p['etapa'] ?? ''));
            if ($e !== '') {
                $etapas[strtolower($e)] = $e;
            }
            $st = strtolower((string) ($p['status'] ?? $p['tipo'] ?? ''));
            if (str_contains($st, 'prelim')) {
                $nPre++;
            } else {
                $nFinal++;
            }
        }
        $anoMin = $anos !== [] ? min(array_keys($anos)) : null;
        $anoMax = $anos !== [] ? max(array_keys($anos)) : null;
        $nPontos = count($pontos);

        $tentativasTxt = $tentativasUrls === []
            ? __('Nenhuma URL HTTP configurada (IEDUCAR_SAEB_IMPORT_URLS); foi usada cópia local ou ficheiro modelo.')
            : implode('; ', $tentativasUrls);

        $secoes = [];

        $secoes[] = [
            'titulo' => __('O que é o SAEB neste painel'),
            'paragrafos' => [
                __('O SAEB (Sistema de Avaliação da Educação Básica) é conduzido pelo INEP/MEC, com provas amostrais e indicadores por rede e território. Os gráficos aqui usam apenas o ficheiro JSON importado em Admin → Sincronizações → Pedagógicas (não calculamos médias a partir do i-Educar).'),
                __('O IDEB combina fluxo escolar e resultados SAEB; para o detalhe oficial por rede e ano consulte o Portal IDEB e as publicações do INEP.'),
            ],
        ];

        $secoes[] = [
            'titulo' => __('Dados disponíveis nesta importação'),
            'paragrafos' => array_values(array_filter([
                $nPontos > 0
                    ? __('Total de registos (pontos) no ficheiro: :n.', ['n' => $nPontos])
                    : __('Ainda não há pontos numéricos no ficheiro.'),
                $anoMin !== null && $anoMax !== null
                    ? __('Anos presentes nos dados (eixo do gráfico): de :min a :max (anos de aplicação/divulgação conforme a fonte).', ['min' => $anoMin, 'max' => $anoMax])
                    : null,
                $discs !== []
                    ? __('Componentes curriculares indicados: :lista.', ['lista' => implode(', ', array_values($discs))])
                    : null,
                $etapas !== []
                    ? __('Etapas ou recortes indicados: :lista.', ['lista' => implode(', ', array_values($etapas))])
                    : null,
                __('Contagem por tipo de divulgação nos pontos: aprox. :final com indicador «final» e :prelim com «preliminar» (conforme o campo status em cada linha).', [
                    'final' => (string) $nFinal,
                    'prelim' => (string) $nPre,
                ]),
                __('Pontos de rede municipal (sem escola_id): :m; pontos por escola (cod_escola i-Educar): :e. No painel, sem filtro de escola mostra-se só a rede; com escola seleccionada, só a série dessa escola.', [
                    'm' => (string) $nMunicipal,
                    'e' => (string) $nEscola,
                ]),
            ], static fn ($x) => $x !== null)),
        ];

        $secoes[] = [
            'titulo' => __('Regras de leitura dos gráficos'),
            'paragrafos' => [
                __('A linha verde contínua com círculos representa resultados finais oficiais; a linha laranja tracejada com triângulos representa divulgações preliminares, sujeitas a revisão pelo INEP.'),
                __('No mesmo ano pode existir apenas um tipo por série, ou transição de preliminar para final em momentos diferentes do calendário de divulgação — não some os dois como se fossem dupla contagem do mesmo facto definitivo.'),
                __('O filtro de ano letivo do painel limita os pontos a anos ≤ ao ano seleccionado; «Todos os anos» mostra toda a série importada.'),
            ],
        ];

        $secoes[] = [
            'titulo' => __('Fonte desta sincronização'),
            'paragrafos' => [
                __('Fonte efectiva gravada no JSON: :f.', ['f' => $fonteEfetiva]),
                __('URLs tentadas nesta ordem (APIs públicas ou ficheiros remotos configurados): :t', ['t' => $tentativasTxt]),
                __('Este texto é gerado automaticamente após cada importação e só é reescrito quando o conjunto de pontos altera (hash de conteúdo).'),
            ],
        ];

        $links = [
            [
                'label' => __('INEP — portal institucional'),
                'url' => 'https://www.gov.br/inep/pt-br',
                'nota' => __('Normas, calendários e comunicados oficiais.'),
            ],
            [
                'label' => __('INEP — SAEB (área temática)'),
                'url' => 'https://www.gov.br/inep/pt-br/areas-de-atuacao/avaliacoes-e-exames-educacionais/saeb',
                'nota' => null,
            ],
            [
                'label' => __('Portal IDEB'),
                'url' => 'https://www.portalideb.org.br/',
                'nota' => __('Indicadores oficiais por rede e resultados agregados.'),
            ],
            [
                'label' => __('MEC — Ministério da Educação'),
                'url' => 'https://www.gov.br/mec/pt-br',
                'nota' => null,
            ],
            [
                'label' => __('INEP — dados abertos'),
                'url' => 'https://www.gov.br/inep/pt-br/acesso-a-informacao/dados-abertos',
                'nota' => __('Conjuntos para download (CSV, etc.), sujeitos a licenças e atualizações do órgão.'),
            ],
            [
                'label' => __('QEdu — consulta por escola'),
                'url' => 'https://www.qedu.org.br/',
                'nota' => __('Indicadores divulgados ao nível de escola quando disponíveis.'),
            ],
        ];

        return [
            'hash_conteudo' => $hash,
            'gerado_em' => now()->toIso8601String(),
            'titulo' => __('Informações sobre os dados SAEB importados'),
            'secoes' => $secoes,
            'links' => $links,
        ];
    }
}
