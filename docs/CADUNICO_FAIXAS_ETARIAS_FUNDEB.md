# CadÚnico — faixas etárias e indicadores FUNDEB (servlitcys)

**Versão do produto:** 6.0.0 · **Última revisão:** 2026-06-03

> **Índice:** [README.md](README.md) · **Relacionado:** [CADUNICO_CECAD.md](CADUNICO_CECAD.md) · [CADUNICO_PREVISAO_TERRITORIAL.md](CADUNICO_PREVISAO_TERRITORIAL.md) · [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md)

---

## 1. Resumo

A aba **CadÚnico: previsão fora da rede** cruza agregados **Cecad/Misocial** (MDS) com matrículas **i-Educar** para estimar crianças e jovens **cadastradas no CadÚnico** que podem não estar reflectidas na rede municipal filtrada.

Por defeito o painel adopta a faixa **4–17 anos** (escolaridade obrigatória e alinhamento com a população escolar do Cecad). **0–3 anos (creche)** não entram na lacuna principal nem no impacto VAAF desta aba — ver §4.

Todos os valores financeiros são **indicativos**; repasses oficiais seguem portarias FNDE, Simec e consolidação do exercício.

---

## 2. Faixas etárias adoptadas no painel

### 2.1 O que entra no cálculo (4–17)

| Faixa no painel | Chave no snapshot | Origem típica |
|-----------------|-------------------|---------------|
| Pré-escola (4–5) | `criancas_4_5` | Cecad / Misocial (inclui 0–4 e 5–6 no agregado MIS quando não há CSV fino) |
| Fundamental — anos iniciais (6–10) | `criancas_6_10` | Cecad / Misocial |
| Fundamental — anos finais (11–14) | `criancas_11_14` | Cecad / Misocial |
| Ensino médio (15–17) | `criancas_15_17` | Cecad / Misocial |

O total municipal (`cadunico_total_escolar`) é:

- `populacao_escolar_estimada`, quando preenchida no import; ou
- soma de `criancas_4_5` + `criancas_6_10` + `criancas_11_14` + `criancas_15_17`.

Configuração: `config/ieducar.php` → `cadunico.faixas_etarias`.

### 2.2 Porquê 4–17 e não 0–3?

| Motivo | Explicação |
|--------|------------|
| **Escopo legal e pedagógico** | A escolaridade obrigatória abrange, na prática, o ensino fundamental e médio e a pré-escola a partir dos 4 anos; a **creche (0–3)** é política de **educação infantil**, com metas e financiamento distintos. |
| **Dados Cecad/Misocial** | A API Misocial (padrão) expõe faixa **0–4 anos** agregada, não 0–3 isolada. A coluna `criancas_0_3` existe na base do SERVLITCYS mas **não é preenchida** pelo sync Misocial automático. |
| **Cruzamento i-Educar** | O painel compara com **matrículas totais** e etapas amplas; não há hoje contagem separada **creche 0–3** vs **pré 4–5** na lacuna CadÚnico. |
| **Interpretação** | Criança 0–3 no CadÚnico não implica obrigatoriedade de vaga municipal (cuidado familiar, creche privada, etc.). A lacuna 4–17 apoia **busca ativa** na escolaridade obrigatória; 0–3 seria **demanda de EI**, outro indicador. |

### 2.3 O que existe mas não entra na lacuna principal

| Faixa | Coluna DB | Estado no produto |
|-------|-----------|-------------------|
| 0–3 (creche) | `criancas_0_3` | Importável via **CSV Cecad** com coluna `criancas_0_3` / `pop_0_3`; **excluída** de `totalCriancasEscolaridade()` e da tabela «Faixas etárias» |
| Território 0–3 | — | Mapa territorial rateia só **4–17** (IBGE + WFS) |

---

## 3. Indicadores FUNDEB afectados nesta aba

### 3.1 O que o painel calcula (indicativo)

| Indicador na UI | Fórmula conceptual | Ponderação FUNDEB |
|-----------------|-------------------|-------------------|
| **Lacuna total** | `max(0, CadÚnico 4–17 − base rede)` | — |
| **Cobertura** | `base rede / CadÚnico 4–17` | — |
| **FUNDEB indicativo (lacuna)** | `lacuna × VAAF` | **VAAF** (valor-aluno-ano de referência municipal) |
| **Lacuna por faixa** | Rateio da lacuna ou gap por faixa CadÚnico vs rede estimada | VAAF na mesma base |
| **Por etapa (FUNDEB)** | CadÚnico estimado por etapa i-Educar × matrículas | VAAF |
| **Cenários NEE** | Proporção NEE na rede aplicada à lacuna | VAAF × **peso educação especial** (`InclusionFundebImpact`) |
| **Cenários AEE** | Proporção AEE sem cadastro na rede | Idem NEE (ponderação adicional) |
| **VAAR (cenário)** | Percentagem configurada sobre base VAAF | **Complementação VAAR** (estimativa grossa, não substitui portaria) |

Serviços: `CadunicoRedeGapAnalyzer`, `CadunicoFinanceScenarioBuilder`, `DiscrepanciesFundingImpact::multiplyVaaf`.

### 3.2 O que **não** é afectado directamente por esta aba

| Indicador FUNDEB | Relação com CadÚnico 4–17 |
|------------------|---------------------------|
| **VAAT** (esforço fiscal / complementação) | Não calculado na lacuna CadÚnico; ver aba FUNDEB e import CSV VAAT. |
| **IEI** (educação infantil no VAAT) | Pondera **matrículas em EI** na rede (creche/pré), não a lacuna CadÚnico 0–3. Ver `FundebResourceProjection` e painel Finanças. |
| **Complementação VAAR oficial** | Valores de portaria FNDE; o painel só mostra **cenário indicativo** proporcional. |
| **Repasse real** | Simec / Tesouro — fora do escopo desta estimativa. |

### 3.3 Resumo visual

```
CadÚnico 4–17 (Cecad)  −  Rede municipal (i-Educar)  =  Lacuna
                                    │
                                    ▼
                         Lacuna × VAAF  →  Impacto indicativo anual
                                    │
                    ┌───────────────┼───────────────┐
                    ▼               ▼               ▼
              Por faixa      Cenários NEE/AEE   VAAR (cenário)
              (4-5…15-17)    (peso EE)         (% config)
```

**0–3 anos:** eventual demanda CadÚnico → futuro bloco **EI / IEI**; não entra na fórmula VAAF desta aba.

---

## 4. Como medir 0–3 anos (evolução possível)

### 4.1 Lado CadÚnico

1. Extração **Cecad** municipal com filtro **0 a 3 anos** e coluna `criancas_0_3` no CSV.
2. Import: `cadunico:import-cecad` ou upload em Admin → CadÚnico (`column_map` já reconhece `criancas_0_3`).
3. Misocial: campos `qtd_pes_*_idade_0_e_4_*` são **0–4** — exigiria mapper dedicado ou Cecad para 0–3 fino.

### 4.2 Lado i-Educar

Contar matrículas em **creche** (séries Educacenso / berçário / maternal), não toda a educação infantil (que mistura pré 4–5).

### 4.3 Lacuna e FUNDEB para EI

```
lacuna_0_3 = max(0, CadÚnico 0–3 − matrículas creche rede)
impacto_EI = lacuna_0_3 × referência IEI / VAAT (indicativo)
```

Implementação futura: nova faixa em `faixas_etarias`, KPI separado, documentação cruzada com aba **Educação infantil** e `ei_censo_etapa` no PDF.

---

## 5. Limitações de leitura

- CadÚnico mede **famílias em vulnerabilidade no município**; parte das crianças está na rede **estadual**, **privada** ou **EJA** — a lacuna não é meta automática de matrícula.
- API Misocial agrupa **0–4** em `criancas_4_5` — a pré-escola no painel pode incluir crianças abaixo dos 4 anos na fonte automática.
- Impacto **VAAF × lacuna** assume integração hipotética à rede municipal; cenários NEE/AEE/VAAR são **stress tests**, não previsão de repasse.
- Mapa territorial e **pressão** aplicam-se à lacuna **4–17** rateada por território.

---

## 6. Ficheiros de código

| Ficheiro | Função |
|----------|--------|
| `app/Services/Cadunico/CadunicoRedeGapAnalyzer.php` | Lacuna, faixas, cobertura |
| `app/Services/Cadunico/CadunicoFinanceScenarioBuilder.php` | Cenários NEE/AEE/VAAR |
| `app/Repositories/Ieducar/CadunicoPrevisaoRepository.php` | Montagem do painel |
| `app/Models/CadunicoMunicipioSnapshot.php` | Colunas por faixa |
| `app/Services/Cadunico/CadunicoMisocialSnapshotMapper.php` | Sync Misocial (0–3 = 0) |
| `config/ieducar.php` | `cadunico.faixas_etarias`, `cecad.column_map` |

---

## 7. Ver também

- [CADUNICO_CECAD.md](CADUNICO_CECAD.md) — importação municipal
- [CADUNICO_PREVISAO_TERRITORIAL.md](CADUNICO_PREVISAO_TERRITORIAL.md) — mapa e pressão territorial
- [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) — VAAF, VAAT, VAAR
- [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) §6 — indicadores indicativos vs oficiais
