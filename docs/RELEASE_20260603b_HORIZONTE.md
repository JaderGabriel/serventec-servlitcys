# Release `20260603b-Horizonte` — ServLitcys 5.0.0

**Data:** 2026-06-03 · **Ramo:** `main` · **Marco:** **5.0** — módulo **Horizonte** (mapa de oportunidade municipal).

## Resumo

Major **5.0.0** sobre **4.4.8** ([RELEASE_20260603a_CLEODORA.md](RELEASE_20260603a_CLEODORA.md)). Foco na **expansão territorial e inteligência comercial**: onde a Consultoria ainda não chegou, quais municípios têm déficits educacionais indicativos (dados públicos) e onde o impacto de i-Educar + SERVLITCYS é maior.

### Horizonte (novo)

- Rota **`/dashboard/horizonte`** — mapa Leaflet com busca por nome/UF/IBGE.
- Scores indicativos: **propensão a sucesso**, **benefício territorial**, pressão FUNDEB, déficit SAEB, escala Censo.
- Tiers: Consultoria activa · catálogo pendente · alta/média/baixa propensão · sem dados públicos.
- Painéis: **regiões mais afectadas** (ranking UF) e **mais propensos a sucesso** (top prospectos).
- Fontes: `fundeb_municipio_references`, `inep_censo_municipio_matriculas`, `saeb_indicator_points`, catálogo `cities`, IBGE (coordenadas).
- Documentação: [HORIZONTE.md](HORIZONTE.md) · config `config/horizonte.php`.

### Início e operação (5.0)

- KPIs do Início realinhados (bases i-Educar, RX/FUNDEB, consultoria, filas) — sem duplicar atalhos ao catálogo.
- Monitor de módulos modernizado (KPIs, URLs, educacenso).
- Verificação diária **`public-data:check-official`** (07:00) + notificação admins.
- Menus e documentação em pt europeu; notificações completas **só no sino**.

### Educacenso (herdado 4.4.8)

- Conferência 1ª etapa, painel detalhado na aba Censo, fix parser/upload.

## Deploy

```bash
git fetch --tags && git checkout 20260603b-Horizonte
composer install --no-dev
npm ci && npm run build
php artisan migrate --force
php artisan view:clear && php artisan config:clear
php artisan public-data:check-official   # opcional — validar probes
```

## Variáveis novas

Ver [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §11–12:

- `HORIZONTE_*` — mapa oportunidade
- `PUBLIC_DATA_DAILY_CHECK_*` — rotina diária

## Testes

```bash
php artisan test --filter='HorizonteOpportunityScorerTest|PublicDataDailyCheckScheduleTest|ModuleMonitorCatalogTest'
```

## Documentação

| Área | Documento / rota |
|------|------------------|
| Horizonte | [HORIZONTE.md](HORIZONTE.md) · `/dashboard/horizonte` |
| Início | [INICIO_DASHBOARD.md](INICIO_DASHBOARD.md) |
| Dados públicos | [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) |
| Comandos | [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) §3.2–3.3 |
| Histórico | [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) |

## Notas

- Scores Horizonte são **indicativos** — após activar Consultoria, usar Diagnóstico (`compliance_score` real).
- Cobertura do mapa = municípios no **catálogo** + IBGE com **dados públicos importados**; expandir via hub Dados públicos.
