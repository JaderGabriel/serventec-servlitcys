# Horizonte — mapa de oportunidade municipal

**Rota:** `/dashboard/horizonte` (`dashboard.horizonte`)  
**Menu:** Consultoria → **Horizonte** (perfil com `canViewAdminDashboard()`)  
**Relacionado:** [ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md](ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md) · [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) · [INICIO_DASHBOARD.md](INICIO_DASHBOARD.md)

---

## 1. Objetivo

O **Horizonte** é o módulo de **inteligência territorial** do SERVLITCYS. Responde:

1. Quais municípios **já têm Consultoria** (base i-Educar activa no catálogo)?
2. Quais **ainda não têm** e aparecem nos dados públicos importados?
3. Onde há **déficits educacionais indicativos** (FUNDEB, SAEB, escala Censo)?
4. Quais **regiões (UF)** concentram maior **benefício potencial**?
5. Quais prospectos têm **maior propensão a sucesso** de implementação?

> **Natureza dos indicadores:** scores **indicativos** para priorização comercial e expansão. **Não substituem** o Diagnóstico (`municipality_health`), discrepâncias i-Educar nem publicações oficiais FNDE/MEC.

---

## 2. Público e acesso

| Perfil | Acesso |
|--------|--------|
| Admin / utilizador com painel Início | Sim |
| Municipal (só «Meu município») | Não |
| Convidado / sem `canViewAdminDashboard()` | Redireccionado (403) |

---

## 3. Fontes de dados

| Camada | Tabela / origem | Uso no Horizonte |
|--------|-----------------|------------------|
| **Catálogo SERVLITCYS** | `cities` | Presença, UF, ligação Consultoria (`is_active` + credenciais BD) |
| **FUNDEB** | `fundeb_municipio_references` | Complementação VAAR/VAAT/VAAF, receita, pressão financeira |
| **Censo INEP** | `inep_censo_municipio_matriculas` | Escala (matrículas municipais) |
| **SAEB / IDEB** | `saeb_indicator_points` | Déficit pedagógico (LP, MAT) |
| **CadÚnico** | `cadunico_municipio_snapshots` | Presença no universo IBGE (expansão futura de score) |
| **IBGE** | API localidades (cache) | Nome, UF, centroide para municípios só com dados públicos |

O universo do mapa = **união de IBGE** presentes em qualquer fonte acima ou no catálogo.

---

## 4. Estados no mapa (tiers)

| Tier | Cor | Significado |
|------|-----|-------------|
| `consultoria_active` | Verde | Consultoria activa — base i-Educar OK |
| `catalog_pending` | Laranja | No catálogo, sem base configurada |
| `prospect_high` | Vermelho | Alta propensão (score ≥ limiar alto) |
| `prospect_medium` | Âmbar | Média propensão |
| `prospect_low` | Cinza | Baixa propensão |
| `data_sparse` | Cinza claro | Sem dados públicos importados |

---

## 5. Metodologia de scoring (v1)

Configuração: `config/horizonte.php` · serviço: `HorizonteOpportunityScorer`

### 5.1 Dimensões (prospectos)

| Dimensão | Peso default | Descrição |
|----------|--------------|-----------|
| **Pressão financeira** | 30% | Complementação FUNDEB / receita ou por matrícula vs mediana nacional |
| **Déficit pedagógico** | 25% | SAEB LP/MAT abaixo do percentil 25 da amostra |
| **Escala** | 20% | log₁₀(matriculas Censo) — municípios maiores |
| **Prontidão de dados** | 15% | FUNDEB + Censo + SAEB disponíveis |
| **Benefício × escala** | 10% | Interacção escala × pressão financeira |

**Propensão a sucesso** (`success_score`, 0–100): combinação ponderada acima.

**Benefício territorial** (`benefit_score`, 0–100): enfatiza déficit pedagógico + financeiro + escala — usado para **ranking de UF** e «regiões mais afectadas».

### 5.2 Benchmarks

Calculados **na mesma geração do mapa** (amostra actual):

- `saeb_p25` — percentil 25 dos valores SAEB LP/MAT
- `compl_ratio_median` — mediana complementação/receita FUNDEB

### 5.3 Limites de tier

| Variável | Default |
|----------|---------|
| `HORIZONTE_HIGH_THRESHOLD` | 70 |
| `HORIZONTE_MEDIUM_THRESHOLD` | 40 |

---

## 6. Interface

### 6.1 Mapa

- Base **Leaflet** + OSM (mesmo stack do mapa do Início).
- **Buscador** por nome, UF ou código IBGE (sugestões + `flyTo`).
- Filtros: todos · prospectos · alta propensão · consultoria · catálogo pendente · UF.
- Tooltip: scores, matrículas Censo, SAEB, complementação FUNDEB, atalho Consultoria ou catálogo.

### 6.2 Painéis laterais

| Painel | Conteúdo |
|--------|----------|
| **Regiões mais afectadas** | Top 12 UFs por benefício médio + contagem alta propensão |
| **Mais propensos a sucesso** | Top 15 prospectos por `success_score` |

### 6.3 KPIs no topo

Municípios no mapa · sem Consultoria · Consultoria activa · alta propensão.

---

## 7. Arquitectura técnica

```
HorizonteController
  └── HorizonteMapService::build()   [cache AdminHomeMapCache]
        ├── citiesByIbge()
        ├── fundebByIbge / censoByIbge / saebByIbge
        ├── IbgeMunicipalityCatalog (nome + coordenadas)
        └── HorizonteOpportunityScorer
```

| Ficheiro | Função |
|----------|--------|
| `app/Http/Controllers/HorizonteController.php` | Entrada HTTP |
| `app/Services/Horizonte/HorizonteMapService.php` | Agregação e cache |
| `app/Services/Horizonte/HorizonteOpportunityScorer.php` | Scores |
| `app/Support/Horizonte/HorizonteMapPresenter.php` | Cores e legenda |
| `app/Support/Brazil/IbgeMunicipalityCatalog.php` | Metadados IBGE |
| `resources/js/horizonteMap.js` | Mapa Alpine + busca |
| `resources/views/horizonte/index.blade.php` | UI |
| `config/horizonte.php` | Pesos, cache, limiares |

---

## 8. Variáveis de ambiente

| Variável | Default | Descrição |
|----------|---------|-----------|
| `HORIZONTE_ENABLED` | `true` | Activa o módulo |
| `HORIZONTE_CACHE_SECONDS` | `900` | TTL cache do payload |
| `HORIZONTE_REFERENCE_YEAR` | ano−1 | Exercício FUNDEB/Censo/SAEB |
| `HORIZONTE_HIGH_THRESHOLD` | `70` | Limiar alta propensão |
| `HORIZONTE_MEDIUM_THRESHOLD` | `40` | Limiar média propensão |

---

## 9. Operacionalização

1. **Enriquecer dados:** Admin → Dados públicos — importar FUNDEB, Censo, SAEB para IBGE alvo.
2. **Actualizar mapa:** cache expira (`HORIZONTE_CACHE_SECONDS`) ou alteração nas tabelas fonte (invalidação por fingerprint).
3. **Priorizar expansão:** filtro «Alta propensão» + painel «Mais propensos» + ranking UF.
4. **Onboarding:** criar cidade no catálogo → configurar conexão → tier passa a `catalog_pending` → `consultoria_active`.

---

## 10. Impacto por sistema (interpretação)

| Sistema | O que o Horizonte mede | O que **não** mede |
|---------|------------------------|---------------------|
| **Dados oficiais** | Déficits proxy (FUNDEB, SAEB, Censo) | Repasse legal definitivo, IDEB oficial INEP em tempo real |
| **i-Educar** | Só indirectamente (Consultoria activa) | Qualidade cadastro, Censo, NEE |
| **SERVLITCYS** | Propensão/benefício estimado | `compliance_score` real — ver Consultoria |

Após activar Consultoria, use **Painel analítico → Diagnóstico** para indicadores de qualidade reais (0–100).

---

## 11. Roadmap

| Fase | Melhoria |
|------|----------|
| **v1 (actual)** | Mapa IBGE conhecidos + scores + busca + rankings UF/prospectos |
| **v1.1** | Importação nacional por UF (job batch) sem cadastrar cidade |
| **v1.2** | Choropleth UF + export CSV prospectos |
| **v2** | IBGE SIDRA população 4–17 (Onda 1 backlog `INT-05`) no score |
| **v2** | Comparativo antes/depois para clientes (delta compliance_score) |

Ver backlog §H em [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md).

---

## 12. Testes

```bash
php artisan test --filter=Horizonte
```

Cobertura: `HorizonteOpportunityScorerTest` (tiers, benchmarks, pesos).

---

*Última revisão: 2026-06-03 · Módulo Horizonte v1*
