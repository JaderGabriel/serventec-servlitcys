# SAEB / IDEB — referências de outras plataformas e boas práticas

Este documento resume modelos visuais e analíticos usados em produtos públicos brasileiros e como o painel local pode evoluir (sem substituir o portal oficial do INEP).

## Plataformas de referência

| Plataforma | O que destaca | Ideia reutilizável aqui |
|------------|----------------|-------------------------|
| **Portal IDEB (INEP)** | Séries por rede, ano de aplicação, notas de corte e download | Manter links no modal; JSON importado deve espelhar o recorte (município/rede/etapa) usado na divulgação |
| **QEdu** | Ficha município/escola, IDEB, comparativos e evolução | Quadro por escola + gráfico comparativo LP/MAT (já suportado quando há `escola_id` no JSON) |
| **Sala do Futuro / dashboards estaduais** | Metas, alertas por faixa de desempenho | Campos opcionais `meta.meta_nacional_lp` / metas PNE no JSON para “semáforo” futuro |
| **Google Looker / Data Studio (secretarias)** | Painéis com filtros globais e cartões KPI | Cartões de resumo municipal + tabela por escola alinhados ao filtro de ano/escola do i-Educar |
| **Tableau Public (projetos educacionais)** | Small multiples (um gráfico por escola) ou mapa + ranking | Evolução temporal (linhas) + barras horizontais para comparar escolas no mesmo ano |

## Indicadores úteis para decisão (pedagógico)

1. **Brecha LP − MAT** na rede e por escola (priorização de formação e reforço).
2. **Tendência triênio** (inclinação das séries municipais), não só o último ponto.
3. **Dispersão entre escolas** (comparativo horizontal) quando o SAEB municipal é público por unidade.
4. **Alinhamento com matrícula**: cruzar escolas sem dados SAEB no JSON mas com matrículas EF — alerta de lacuna de dados, não de desempenho.

## Limitações legais e técnicas

- O INEP divulga **microdados** em arquivos (ex.: ZIP); não há API única em JSON no formato deste painel.
- Resultados **por escola** dependem de divulgação e amostra; muitas unidades aparecem agregadas ou suprimidas por privacidade.
- O ficheiro `saeb/historico.json` deve ser produzido por **ETL próprio** (Python/R/Laravel) a partir de bases oficiais, com revisão técnica.

## Próximos passos sugeridos no código

- Metas configuráveis (`config` ou JSON) para cor verde/âmbar no quadro escolar.
- Export CSV da tabela por escola a partir do mesmo payload do painel.
- Opcional: job agendado que baixa CSV do dados.gov.br e regenera o JSON (pipeline separado).
