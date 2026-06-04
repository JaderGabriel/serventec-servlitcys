# Release `20260604-Gaia` — ServLitcys 3.9.0

**Data:** 2026-06-04 · **Ramo:** `main` · **Figura:** *Gaia* (pressão territorial — CadÚnico, Censo e malha IBGE).

## Resumo

Marco **3.9.0** sobre **3.8.0** ([RELEASE_20260603_ARTEMIS.md](RELEASE_20260603_ARTEMIS.md)):

- **CadÚnico previsão (3 fases):** lacuna por faixa etária com base `min(mat, alunos)`; vulnerabilidade Misocial (PBF); cenários financeiros NEE/AEE/VAAR sobre a lacuna.
- **Mapa territorial:** ranking e Leaflet com pressão = lacuna rateada × vulnerabilidade × distância à escola.
- **Demanda × oferta:** bloco indicativo (ligação parcial INT-01).
- **Importação oficial IBGE:** Censo 2022 (FTP agregados bairro/setor) + WFS GeoServer; rateio do CadÚnico municipal por território.
- **Admin / CLI:** upload CSV territorial; `cadunico:import-territorio`; `cadunico:sync-territorio`.

## Destaques

### Fase 1 — Lacuna refinada

| Serviço | Função |
|---------|--------|
| `CadunicoRedeGapAnalyzer` | Lacuna por faixa, base rede, impacto FUNDEB |
| `CadunicoFinanceScenarioBuilder` | Cenários NEE/AEE/VAAR proporcionais à rede |
| `CadunicoVulnerabilidadeIndicators` | KPIs PBF a partir do snapshot Misocial |

### Fase 2–3 — Território e mapa

| Componente | Função |
|------------|--------|
| `cadunico_territorio_snapshots` | Agregados por bairro/setor (sem CPF/NIS) |
| `CadunicoTerritorialPressureBuilder` | Pressão, ranking, marcadores |
| `cadunicoTerritoryMap.js` | Mapa Leaflet na aba CadÚnico |
| `CadunicoTerritorioOfficialImportService` | IBGE FTP + WFS + rateio CadÚnico |
| `CadunicoIbgeCensoAgregadosCache` | Cache ZIP Censo 2022 (~15 MB) |

### UI / exportação

- Aba `cadunico_previsao`: fluxo Demanda, Cenários, Mapa, Faixas com lacuna, Territórios.
- PDF/CSV/Excel: faixas, cenários, vulnerabilidade, ranking territorial.

## Deploy

```bash
git fetch --tags
git checkout 20260604-Gaia   # ou `main` após este commit
composer install --no-dev
npm run build
php artisan migrate
php artisan cadunico:sync-city --all --ano=2025
php artisan cadunico:sync-territorio --all --ano=2025
php artisan config:clear
php artisan view:clear
```

**Migração obrigatória:** `2026_06_04_100000_create_cadunico_territorio_snapshots_table`.

## Documentação

- [CADUNICO_PREVISAO_TERRITORIAL.md](CADUNICO_PREVISAO_TERRITORIAL.md) — guia completo das 3 fases
- [CADUNICO_CECAD.md](CADUNICO_CECAD.md) — pipeline municipal
- [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) — `cadunico:sync-territorio`, `cadunico:import-territorio`
- [ANALYTICS_NAVEGACAO_UI.md](ANALYTICS_NAVEGACAO_UI.md) — aba CadÚnico

## Testes

```bash
php artisan test --filter='CadunicoRedeGapAnalyzerTest|CadunicoFinanceScenarioBuilderTest|CadunicoTerritorioCsvImportServiceTest|CadunicoPrevisaoExportRowsBuilderTest'
```

## Limitações conhecidas

- CadÚnico **por território** no mapa é **estimativa** (rateio municipal via população Censo IBGE), não microdado MDS por bairro.
- Municípios sem bairros formais no IBGE usam **setores censitários** como unidade territorial.
