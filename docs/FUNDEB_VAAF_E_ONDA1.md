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

**VAAR/VAAT oficiais** (complementação FNDE) ainda não são obtidos por API; a tabela permite guardar `vaat` e `complementacao_vaar` para informes futuros.

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

Import CSV:

```bash
php artisan fundeb:import-references storage/app/fundeb_references.csv
```

Colunas: `ibge_municipio;ano;vaaf;vaat;complementacao_vaar;fonte;notas` (separador `;`).

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

---

## 6. O que ainda não está no âmbito desta entrega

- Informes narrativos VAAR/VAAT com textos gerados (Onda 3 do plano financeiro).
- Fetch HTTP automático ao FNDE/dados.gov.br.
- Itens C1–C4 do roadmap (ficha médica, PNAE, etc.).

---

## 7. Testes

- `tests/Unit/FundebMunicipalReferenceResolverTest.php` — fallback e prioridade DB/config.
- `tests/Unit/IeducarFilterStateInclusionTest.php` — filtros Inclusão (já existente).
