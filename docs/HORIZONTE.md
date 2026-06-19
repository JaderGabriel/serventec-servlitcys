# Horizonte — mapa de oportunidade municipal

**Rota:** `/dashboard/horizonte` (`dashboard.horizonte`)  
**Menu:** Consultoria → **Horizonte** (perfil com `canViewHorizonte()`)  
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
| Admin | Sim |
| Utilizador da plataforma | Sim |
| Municipal (só «Meu município») | Não (403) |
| Inactivo / convidado | Não (403) |

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

- Base **Leaflet** + OSM; modos **Calor** (propensão) e **Marcadores** (tiers).
- **Buscador** por nome, UF ou código IBGE (sugestões + `flyTo`).
- Filtros comerciais: propensão/benefício mínimos, matrículas, FUNDEB/Censo/SAEB, UF, segmentos «Onde buscar clientes».
- Overlay de carregamento durante fetch JSON e desenho do mapa.
- Tooltip: scores, matrículas Censo, SAEB, complementação FUNDEB, **SGE** (sistema, estado, detalhe), fontes, atalho Consultoria ou portal do sistema.

### 6.2 Painéis laterais

| Painel | Conteúdo |
|--------|----------|
| **Sistemas de gestão (SGE)** | Identificados, consultoria i-Educar, registo externo, não identificados |
| **Cobertura de dados** | Contagem FUNDEB / Censo / SAEB / triad completa |
| **UFs prioritárias** | Top 12 UFs por benefício médio + clique filtra UF |
| **Top prospectos** | Melhores scores nacionais (clicáveis no mapa) |

### 6.3 KPIs e prospecção

| Área | Conteúdo |
|--------|----------|
| **KPIs** | Dados públicos · prospectos · alta propensão · consultoria · matrículas prospecto |
| **Segmentos** | Prontos para abordagem · pressão FUNDEB · déficit SAEB · grande escala |
| **Tabela** | Até 50 municípios do recorte, ordenados para abordagem comercial (inclui coluna SGE) |

---

## 6.4 Sistemas de gestão educacional (SGE)

O Horizonte tenta identificar o **SGE** de cada município por duas fontes (em ordem de prioridade):

1. **Catálogo ServLITCYS** (`cities`) — i-Educar com estados: consultoria activa, base configurada ou pendente.
2. **Registo externo opcional** — JSON local ou URL remota (não bloqueia o mapa se ausente ou inválido).

Quando nenhuma fonte identifica o sistema, o município aparece como **SGE não identificado** (`N/I` na tabela); o restante do payload (FUNDEB, Censo, SAEB, scores) continua disponível.

### Formato do registo externo

Ficheiro default: `storage/app/horizonte/sge_registry.json` (configurável via `HORIZONTE_SGE_REGISTRY_PATH`).

```json
{
  "3550308": {
    "system": "GDAE",
    "vendor": "SME-SP",
    "notes": "Portal municipal de gestão escolar",
    "app_url": "https://portal.exemplo.sp.gov.br"
  },
  "municipios": [
    {
      "ibge": "2910800",
      "system": "SIGE",
      "fornecedor": "Secretaria municipal"
    }
  ]
}
```

Chaves aceites por entrada: `system`/`sistema`, `vendor`/`fornecedor`, `notes`/`notas`, `app_url`/`url`, `ibge`/`ibge_municipio`.

A fase **SGE** do feed quinzenal (`horizonte:fortnightly-feed`) sincroniza o registo para cache; falhas são registadas em log e **não impedem** as restantes fases nem o uso do mapa.

```bash
php artisan horizonte:fortnightly-feed --skip-sge   # ignorar registo SGE
```

## 7. Arquitectura técnica

```
HorizonteController
  └── HorizonteMapService::build()   [cache AdminHomeMapCache]
        ├── citiesByIbge()
        ├── fundebByIbge / censoByIbge / saebByIbge
        ├── IbgeMunicipalityCatalog (nome + coordenadas)
        ├── HorizonteMunicipalSgeResolver + HorizonteMunicipalSgeRegistryService (cache)
        └── HorizonteOpportunityScorer
```

| Ficheiro | Função |
|----------|--------|
| `app/Http/Controllers/HorizonteController.php` | Entrada HTTP |
| `app/Services/Horizonte/HorizonteMapService.php` | Agregação e cache |
| `app/Services/Horizonte/HorizonteOpportunityScorer.php` | Scores |
| `app/Support/Horizonte/HorizonteMapPresenter.php` | Cores e legenda |
| `app/Support/Brazil/IbgeMunicipalityCatalog.php` | Metadados IBGE |
| `app/Support/Horizonte/HorizonteMunicipalSgeResolver.php` | SGE por IBGE (catálogo + registo) |
| `app/Services/Horizonte/HorizonteMunicipalSgeRegistryService.php` | Import JSON/URL do registo SGE |
| `resources/js/horizonteMap.js` | Mapa Alpine + busca |
| `resources/views/horizonte/index.blade.php` | UI |
| `app/Services/Horizonte/HorizonteFortnightlyFeedService.php` | Rotina quinzenal de dados públicos |
| `app/Console/Commands/HorizonteFortnightlyFeedCommand.php` | CLI `horizonte:fortnightly-feed` |

---

## 8. Variáveis de ambiente

| Variável | Default | Descrição |
|----------|---------|-----------|
| `HORIZONTE_ENABLED` | `true` | Activa o módulo |
| `HORIZONTE_CACHE_SECONDS` | `900` | TTL cache do payload |
| `HORIZONTE_REFERENCE_YEAR` | ano−1 | Exercício FUNDEB/Censo/SAEB |
| `HORIZONTE_HIGH_THRESHOLD` | `70` | Limiar alta propensão |
| `HORIZONTE_MEDIUM_THRESHOLD` | `40` | Limiar média propensão |
| `HORIZONTE_FORTNIGHTLY_FUNDEB_ALLOW_EMPTY` | `true` | Feed continua se FUNDEB vier vazio |
| `HORIZONTE_SGE_ENABLED` | `true` | Activa fase SGE no feed |
| `HORIZONTE_SGE_REGISTRY_PATH` | `horizonte/sge_registry.json` | JSON local IBGE→SGE |
| `HORIZONTE_SGE_REGISTRY_URL` | — | URL remota alternativa (opcional) |
| `HORIZONTE_SGE_REGISTRY_HTTP_TIMEOUT` | `15` | Timeout HTTP do registo remoto |
| `HORIZONTE_SGE_REGISTRY_CACHE_TTL` | `604800` | TTL cache do índice SGE (s) |

---

## 9. Operacionalização

### 9.1 Rotina bimestral (abastecimento automático)

Comando: **`horizonte:fortnightly-feed`** · agendamento: **bimestral** — dia **1** às **03:00** nos meses **1, 3, 5, 7, 9, 11** (início do ciclo) + **passos a cada N minutos** enquanto o pipeline estiver activo.

Por defeito corre **em etapas** (`HORIZONTE_FORTNIGHTLY_FEED_STAGED=true`): cada invocação executa **uma fase**, libertando memória entre processos. Admins recebem **notificação por fase** e ao concluir o ciclo. Estado visível em **Filas** (`#fila-horizonte`) e no hub Horizonte.

| Fase | O que faz |
|------|-----------|
| **FUNDEB** | CSV nacional «Receita total do Fundeb por ente federado» (FNDE) → `fundeb_municipio_references` por IBGE |
| **Censo** | Indexa matrículas municipais a partir do microdados INEP (`inep_censo_municipio_matriculas`) |
| **SAEB** | Planilhas oficiais INEP — **1 ano por passo** por defeito (`HORIZONTE_FORTNIGHTLY_SAEB_YEARS_PER_STEP=1`) |
| **IBGE** | Aquece catálogo de centroides (**1 UF por invocação** por defeito — `HORIZONTE_FORTNIGHTLY_IBGE_UFS_PER_STEP=1`) |
| **SGE** | Sincroniza registo opcional de sistemas de gestão educacional (JSON local ou URL) — **não bloqueia** se ausente |
| **Verificação** | `public-data:check-official --no-notify` (cache no hub, sem notificação) |

```bash
# Manual — etapas (recomendado em produção)
php artisan horizonte:fortnightly-feed --staged --reset
php artisan horizonte:fortnightly-feed --staged --continue
php artisan horizonte:fortnightly-feed --phase=fundeb_receita

# Manual — tudo numa invocação (verbose activo; retomar se interrompido)
php artisan horizonte:fortnightly-feed --all
php artisan horizonte:fortnightly-feed --all --continue
php artisan horizonte:fortnightly-feed --all --reset

php artisan horizonte:fortnightly-feed --dry-run
php artisan horizonte:fortnightly-feed --skip-saeb --skip-censo --skip-sge

# VPS com pouca RAM — só IBGE + SGE (1 UF por passo; repetir --continue)
php artisan horizonte:fortnightly-feed --staged --reset --skip-fundeb --skip-censo --skip-saeb --skip-verify
php artisan horizonte:fortnightly-feed --staged --continue   # até concluir IBGE, depois SGE

# Uma UF manualmente
php artisan horizonte:fortnightly-feed --phase=ibge_catalog --uf=SP

# Confirmar agendamento
php artisan schedule:list | grep horizonte
```

Variáveis: `HORIZONTE_FORTNIGHTLY_FEED_*` — ver [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §11b.

**Hub admin:** `/admin/dados-publicos?hub=horizonte` · painel `#horizonte-hub` — cobertura nacional, botão «Abastecer Horizonte» e ligações a cada fonte. Ver [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) §11.

O cache do mapa invalida-se automaticamente quando `imported_at` / contagens nas tabelas fonte mudam (fingerprint em `HorizonteMapService`).

### 9.2 Uso comercial (gestores)

1. **Enriquecer dados:** garantir rotina quinzenal activa + importações pontuais em Dados públicos.
2. **Actualizar mapa:** abrir `/dashboard/horizonte` — shell rápido + JSON assíncrono; overlay de carregamento.
3. **Priorizar expansão:** modo **Calor**, segmentos «Onde buscar clientes», filtros de propensão/FUNDEB/Censo/SAEB.
4. **Onboarding:** criar cidade no catálogo → configurar conexão → tier passa a `catalog_pending` → `consultoria_active`.

### 9.3 Abastecimento offline (local → produção, sem git)

Quando o feed em produção morre com `Killed` (OOM), processe os dados **localmente** (máquina com RAM e acesso às APIs) e transfira um pacote ZIP:

```bash
# Local — gerar dados completos (feed ou importações no hub)
php artisan horizonte:fortnightly-feed --all
# ou fases individuais com RAM suficiente

# Exportar pacote
php artisan horizonte:export-data-bundle
# Ficheiro: storage/app/horizonte/bundles/horizonte-YYYYMMDD-HHMMSS.zip
# Cópia: storage/app/horizonte/bundles/latest.zip

# Enviar para produção (exemplo)
scp storage/app/horizonte/bundles/latest.zip user@servidor:/var/www/servlitcys/storage/app/horizonte/bundles/

# Produção — importar (sem git)
php artisan horizonte:import-data-bundle storage/app/horizonte/bundles/latest.zip
php artisan horizonte:import-data-bundle storage/app/horizonte/bundles/latest.zip --dry-run
php artisan horizonte:import-data-bundle storage/app/horizonte/bundles/latest.zip --only=fundeb,censo
```

O pacote inclui: `fundeb_municipio_references`, `inep_censo_municipio_matriculas`, `saeb_indicator_points` (municipal), cache IBGE (centroides) e registo SGE. Variável `HORIZONTE_FORTNIGHTLY_IBGE_UFS_PER_STEP` controla quantas UFs aquecem por passo no feed (defeito `1`).

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
