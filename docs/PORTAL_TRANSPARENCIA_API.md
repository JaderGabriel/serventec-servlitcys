# Portal da Transparência — API REST (oportunidades ServLITCYS)

**Versão do produto:** 8.2.2 · **Última revisão:** 2026-07-24 · **Estado:** inventário pós-auditoria Swagger

> **Portal:** [api-de-dados](https://portaldatransparencia.gov.br/api-de-dados) · **Cadastro chave:** [cadastrar-email](https://portaldatransparencia.gov.br/api-de-dados/cadastrar-email) · **Swagger:** [api.portaldatransparencia.gov.br/swagger-ui](https://api.portaldatransparencia.gov.br/swagger-ui/index.html) · **OpenAPI:** `/v3/api-docs` (~106 paths)  
> **Código actual:** `App\Support\Funding\PortalTransparenciaApiClient` · [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) § Portal · [ROADMAP_HORIZONTE.md](ROADMAP_HORIZONTE.md) HOR-08 · [ROADMAP_BASES_FINANCEIRAS.md](ROADMAP_BASES_FINANCEIRAS.md)

Autenticação: header `chave-api-dados` (`PORTAL_TRANSPARENCIA_API_KEY`). Rate limit típico: **400 req/min** (dia) / **700** (00h–06h); APIs restritas ~180/min. Paginação obrigatória (`pagina`) na maioria dos endpoints.

---

## 1. O que já usamos (2026-07)

| Capacidade | Endpoint(s) | Onde |
|------------|-------------|------|
| Recursos recebidos por município/ano | `GET /api-de-dados/despesas/recursos-recebidos` (`codigoIBGE`, `mesAnoInicio/Fim` MM/AAAA) | Enrich Financiamentos · warm aba · Horizonte transparency |
| Convênios (função educação) | `GET /api-de-dados/convenios` (`codigoIBGE`, `funcao=12`) | Idem |
| Header `chave-api-dados` | — | `PortalTransparenciaApiClient` |

**Removidos (404 / inexistentes no Swagger actual):** `/api-de-dados/transferencias`, `/api-de-dados/despesas?codigoMunicipio=`.

---

## 2. Catálogo útil ao produto (por prioridade)

### P1 — Finanças / consultoria (alto alinhamento)

| Endpoint | Filtros úteis | Uso proposto | ID sugerido |
|----------|---------------|--------------|-------------|
| `despesas/recursos-recebidos` | `codigoIBGE`, órgão FNDE/MEC, série mensal | Já implementado; evoluir: paginar até esgotar + classificar UG FNDE sem keyword frágil | FIN-07 |
| `convenios` | `codigoIBGE`, `funcao=12`, vigência, valor liberado | Já parcial; evoluir: lista na ficha municipal + alerta «convênio a vencer» | HOR-08b |
| `emendas` | `ano`, `codigoFuncao` (12=educação), autor | Emendas parlamentares educação no município (via documentos relacionados) | FIN-08 / HOR-08c |
| `despesas/por-funcional-programatica` | `ano`, `funcao=12` | Execução orçamental federal por função educação (contexto nacional/UF, não sempre IBGE) | FIN-09 |
| `despesas/documentos-por-favorecido` | CNPJ prefeitura / UG | Empenhos/liquidações/pagamentos ao ente (API **restrita** — rate menor) | FIN-10 |

### P2 — Horizonte / prospecção comercial

| Endpoint | Filtros úteis | Uso proposto | ID sugerido |
|----------|---------------|--------------|-------------|
| `contratos` | `codigoOrgao` (obrig.), datas | Contratos MEC/FNDE/UG educação — proxy SGE / incumbente (órgao SIAFI) | HOR-08d |
| `licitacoes` | `codigoOrgao`, datas | Licitações abertas/recentes do órgão — timing comercial | HOR-08e |
| `contratos/cpf-cnpj` + `itens-contratados` | CNPJ fornecedor | Cruzar fornecedores de software educação (lista curada) | HOR-08f |
| `ceis` / `cnep` / `cepim` | CNPJ | Sanções em fornecedores / convenentes (due diligence leve) | HOR-08g |

### P3 — CadÚnico / vulnerabilidade (agregado municipal)

| Endpoint | Filtros | Uso proposto | Cuidado |
|----------|---------|--------------|--------|
| `bolsa-familia-por-municipio` / `novo-bolsa-familia-por-municipio` | `codigoIbge`, `mesAno` | Volume PBF/NBF no município — cruzar com CadÚnico/Educacenso | Só agregados; **não** NIS/CPF em massa |
| `bpc-por-municipio` | `codigoIbge`, `mesAno` | Benefícios BPC — contexto inclusão | Idem LGPD |
| `peti-por-municipio` | município | Trabalho infantil — alerta social | Baixa prioridade |

**Fora de âmbito comercial próximo:** servidores/SIAPE, viagens, imóveis funcionais, PEPs, COVID transferências históricas, seguro-defeso/safra (agrícola), notas fiscais NFe genéricas, cartões de pagamento (sem vínculo municipal directo).

---

## 3. Limitações de desenho (importante)

| Tema | Implicação |
|------|------------|
| **Contratos / licitações exigem `codigoOrgao` (SIAFI)** | Precisa mapa órgão→educação (FNDE, MEC, UFs); não basta IBGE |
| **Emendas** filtráveis por função/ano, não por IBGE directo | Ligar via `emendas/documentos/{codigo}` ou localidade nos documentos |
| **Recursos recebidos** agregam por UG/mês | Bom para volume; fraco para «programa PNAE» sem classificação por órgão/palavra-chave |
| **APIs restritas** (`documentos-por-favorecido`, benefícios por NIS) | Rate menor; risco de suspensão de token se abusado |
| **PBF / BPC por município** | Complementa CadÚnico; não substitui microdados Misocial |

---

## 4. Implementação sugerida (incremental)

1. **Estabilizar P0 actual** — chave em produção + `funding:enrich-consultoria-financiamentos` + sync transparency com endpoints novos (já no client).
2. **FIN-07** — paginação completa de `recursos-recebidos` + mapa de órgãos FNDE (códigos SIAFI) para reduzir falso-negativo de keywords.
3. **HOR-08b** — persistir convênios educação na ficha Horizonte (além de contagens).
4. **FIN-08 / HOR-08c** — emendas função 12 + documentos, amostra por ano de referência.
5. **HOR-08d/e** — contratos/licitações por lista curada de órgãos MEC/FNDE (config).
6. **CUN-04 (opcional)** — série PBF/NBF mensal agregada por IBGE (sem beneficiários).

Cliente partilhado: estender `PortalTransparenciaApiClient` (não espalhar URLs). Testes com `Http::fake` por path. Respeitar rate limit nos loops `--continue`.

---

## 5. Variáveis / ops

| Variável | Uso |
|----------|-----|
| `PORTAL_TRANSPARENCIA_API_KEY` | Obrigatória |
| `IEDUCAR_PORTAL_TRANSPARENCIA_ENABLED` | Liga/desliga |
| `IEDUCAR_PORTAL_TRANSPARENCIA_KEYWORDS` | Filtro educação (Financiamentos) |
| `HORIZONTE_TRANSPARENCY_*` | Lotes sync nacional |

Após alterar `.env`: `php artisan config:clear` ou `config:cache`.

---

*Relacionado: [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) · [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) §4.1b · [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) (`FIN-*`, `HOR-08*`) · [ROADMAP_CADUNICO.md](ROADMAP_CADUNICO.md).*
