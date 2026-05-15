# FUNDEB, VAAF, VAAR/VAAT e Onda 1 — documentação técnica

**Data:** maio de 2026  
**Relacionado:** `DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md`, `config/ieducar.php`

---

## 1. Resumo

O painel usa o **VAAF** como valor de referência (R$/aluno/ano) em dois modelos:

| Modelo | Fórmula | Onde |
|--------|---------|------|
| **Discrepâncias** | `ocorrências × VAAF × peso_por_check` | `DiscrepanciesFundingImpact` |
| **FUNDEB (previsão)** | `matrículas × VAAF` (+ cenários com perda/ganho das discrepâncias) | `FundebResourceProjection` |

Até maio/2026 o VAAF vinha só de `IEDUCAR_DISC_VAA_REFERENCIA` (default 4500). A partir desta evolução:

1. **`FundebMunicipalReferenceResolver`** tenta, por município (IBGE) e ano:
   - registo em `fundeb_municipio_references` (import CSV ou admin);
   - override por cidade em `config/ieducar.php` → `fundeb.vaaf_por_ibge`;
   - fallback para `discrepancies.vaa_referencia_anual`.

2. **Onda 1** (cadastro Censo): gráfico de recursos por tipo, detalhe de tipos na discrepância `recurso_prova_sem_nee`, KPI no Diagnóstico Geral.

**VAAR/VAAT oficiais** não são obtidos por API; a tabela e o config permitem importar `vaat` e `complementacao_vaar`. A aba FUNDEB exibe **informes narrativos** (F2) em `FundebComplementacaoInformeBuilder`.

---

## 2. Arquitectura do VAAF

```
City (ibge_municipio) + ano letivo
        │
        ▼
FundebMunicipalReferenceResolver::resolve()
        │
        ├─► fundeb_municipio_references (DB app)
        ├─► config fundeb.vaaf_por_ibge[ibge][ano]
        └─► config discrepancies.vaa_referencia_anual (fallback)
        │
        ▼
DiscrepanciesFundingImpact::vaaReferencia(City?, ?ano)
FundebResourceProjection::build(..., $reference)
```

### Campos devolvidos pelo resolver

| Campo | Significado |
|-------|-------------|
| `vaaf` | Valor usado nos cálculos (float) |
| `fonte` | `oficial_db` \| `config_ibge` \| `config_global` |
| `fonte_label` | Texto para UI |
| `ano` | Ano da referência (int ou null) |
| `vaat` | Opcional, para informes |
| `complementacao_vaar` | Opcional (R$ ou % conforme import) |

---

## 3. Configuração

| Variável | Uso |
|----------|-----|
| `IEDUCAR_DISC_VAA_REFERENCIA` | Fallback global (default 4500) |
| `IEDUCAR_FUNDEB_VAAR_PCT_BASE` | % indicativa sobre base (previsão FUNDEB) |
| `IEDUCAR_FUNDEB_AVISO_PREVISAO` | Aviso na aba FUNDEB |

**Persistência:** tabela `fundeb_municipio_references` (`city_id`, `ibge_municipio`, `ano` único). O resolver usa o **ano do filtro**; se não houver linha, o ano mais recente do município; depois config/fallback.

**Import na admin:** `/admin/ieducar-compatibility` → «Buscar na API e gravar».

**CLI:**

```bash
php artisan fundeb:import-api {city_id} --ano=2024
php artisan fundeb:import-references storage/app/fundeb_references.csv
```

**Env API:**

| Variável | Uso |
|----------|-----|
| `IEDUCAR_FUNDEB_CKAN_RESOURCE_ID` | Recurso CKAN FNDE (obrigatório para preencher cache automaticamente) |
| `IEDUCAR_FUNDEB_CKAN_URL` | Base CKAN (default FNDE dados abertos) |
| `IEDUCAR_FUNDEB_JSON_URL` | `storage://app/fundeb/api/{ibge}/{ano}.json` = **cache em disco**; ou URL `https://…` = JSON remoto por município/ano |
| `IEDUCAR_FUNDEB_CACHE_PATH` | Opcional; sobrescreve o caminho do cache |

**Fluxo de importação (`fundeb:import-api` / botão na admin):**

1. Lê `storage/app/fundeb/api/{ibge}/{ano}.json` se existir.
2. Se não existir, consulta CKAN (ou URL HTTP em `IEDUCAR_FUNDEB_JSON_URL`).
3. **Grava o JSON no cache** e persiste VAAF/VAAT em `fundeb_municipio_references`.
4. Próximas leituras usam o cache (útil quando o CKAN está instável).

Sem `IEDUCAR_FUNDEB_CKAN_RESOURCE_ID` e sem ficheiros em `storage/app/fundeb/…`, a importação falha para todos os municípios — o cache não se preenche sozinho.

**UI admin:** em Compatibilidade i-Educar, card FUNDEB — importar um município ou **todos**; ano sugerido = ano anterior (FNDE raramente tem o ano corrente, ex. 2026). Opção «usar ano mais recente na API» quando o ano pedido não existir.

```bash
php artisan fundeb:import-api {city_id} --ano=2024 --nearest
php artisan fundeb:import-api 0 --all --ano=2024 --nearest
```

---

## 4. Onda 1 — itens

| ID | Entregável | Ficheiros |
|----|------------|-----------|
| D1 | Gráfico «Recursos de prova por tipo» | `InclusionRepository`, `inclusion.blade.php` |
| D2 | Coluna `tipos_recurso` em `recurso_prova_sem_nee` + CSV | `InclusionRecursoProvaQueries`, `DiscrepanciesExportController` |
| D4 | KPI «Recurso de prova sem NEE» no Diagnóstico | `MunicipalityHealthRepository`, `municipality-health.blade.php` |

---

## 5. Estado da implementação (maio/2026)

| Item | Estado |
|------|--------|
| `FundebMunicipalReferenceResolver` | Implementado |
| Tabela `fundeb_municipio_references` + comando `fundeb:import-references` | Implementado |
| Integração Discrepâncias / FUNDEB / Diagnóstico | Implementado |
| D1 gráfico catálogo recursos | Implementado |
| D2 `tipos_recurso` no CSV e linhas da rotina | Implementado |
| D4 KPI Diagnóstico | Implementado |
| F2 Informes VAAF / VAAT / VAAR (`complementacao_informe`) | Implementado |

### F2 — informes na aba FUNDEB

- **Builder:** `app/Support/Ieducar/FundebComplementacaoInformeBuilder.php`
- **Payload:** `fundebData['complementacao_informe']` com `blocos[]` (vaaf, vaat, vaar, outras)
- **UI:** secção «Informes VAAF, VAAT e complementação VAAR» entre previsão de recursos e módulos VAAR temáticos
- Cruza VAAF resolvido, VAAT/complementação importados, pilares `funding_pillars` das Discrepâncias e recurso de prova sem NEE

---

## 6. O que ainda não está no âmbito desta entrega

- Fetch HTTP automático ao FNDE/dados.gov.br.
- Itens C1–C4 do roadmap (ficha médica, PNAE, etc.).

---

## 7. Testes

- `tests/Unit/FundebMunicipalReferenceResolverTest.php` — fallback e prioridade DB/config.
- `tests/Unit/FundebComplementacaoInformeBuilderTest.php` — blocos e status VAAT/VAAR.
- `tests/Unit/IeducarFilterStateInclusionTest.php` — filtros Inclusão (já existente).
