# Release `20260601-Atlas` — ServLitcys 3.5.0

**Data:** 2026-06-01 · **Ramo:** `main` · **Figura:** *Atlas* (sustentar previsão municipal — CadÚnico, lacuna na rede e comparativo financeiro).

## Resumo

Minor **3.5.0** sobre **3.4.0** ([RELEASE_20260531_NEMESIS.md](RELEASE_20260531_NEMESIS.md)):

- **Aba Comparativo** (Finanças): ano base vs anterior, FUNDEB, informes narrativos, exportação PDF/CSV/Excel.
- **Aba CadÚnico** (Cadastro): lacuna CadÚnico vs matrículas i-Educar, impacto estimado VAAF, exportação consultoria.
- **Pipeline CadÚnico**: importação nacional por URL, API/CSV municipal, fila `cadastro`, cron semanal, observer ao gravar IBGE, admin `/admin/cadunico-sync`.

## Destaques

### Analytics — Comparativo (Finanças)

| Item | Detalhe |
|------|---------|
| Serviço | `FinanceComparativoService` + `FinanceComparativoInformeBuilder` |
| UI | `partials/comparativo.blade.php`, informes em `comparativo-informe.blade.php` |
| Export | `GET dashboard.analytics.comparativo.export?format=pdf\|csv\|xlsx` |
| Ano base | Query `ano_base` ou filtro global do painel |

### Analytics — CadÚnico (Cadastro)

| Item | Detalhe |
|------|---------|
| Aba | `cadunico_previsao` no grupo **Cadastro e rede** |
| Motor | `CadunicoRedeGapAnalyzer`, `CadunicoPrevisaoRepository` |
| Dados | Tabela `cadunico_municipio_snapshots` (agregados 4–17 anos, sem CPF/NIS) |
| Export | `GET dashboard.analytics.cadunico-previsao.export?format=pdf\|csv\|xlsx` |

### Importação e automação

| Canal | Descrição |
|-------|-----------|
| `IEDUCAR_CADUNICO_NACIONAL_CSV_URL` | Download `nacional_{ano}.csv` → import em lote |
| API / CKAN / dados.gov.br | Lacunas por município (opcional) |
| `cadunico:auto-sync` | Pipeline completo; `--queue` no cron |
| Fila `admin-sync` | Domínio `Cadastro`: `import_city_year`, `import_csv`, `auto_sync`, … |
| Observer | `CityCadunicoSyncObserver` ao definir IBGE |
| Massiva | Fase `cadunico_snapshots` em `weekly-mass-sync` |
| Admin | `/admin/cadunico-sync` (automático em destaque; upload CSV opcional) |

Documentação operacional: [CADUNICO_CECAD.md](CADUNICO_CECAD.md), [CADUNICO_AUTOMACAO.md](CADUNICO_AUTOMACAO.md).

## Deploy

```bash
git fetch --tags
git checkout 20260601-Atlas   # ou deploy de `main` após este commit
composer install --no-dev
npm run build
php artisan migrate --force
php artisan config:clear
php artisan view:clear
php artisan cache:clear
```

**Migração obrigatória:** `2026_05_28_120000_create_cadunico_municipio_snapshots_table.php`.

**Cron:** manter `schedule:run` + worker `admin-sync` (ou `ADMIN_SYNC_SCHEDULE_*`).

## Variáveis `.env`

Mínimo para automação nacional (endpoint seu com export Cecad):

```env
IEDUCAR_CADUNICO_NACIONAL_CSV_URL=https://SEU_ENDPOINT/cadunico/nacional_{ano}.csv
IEDUCAR_CADUNICO_AUTO_SYNC_ENABLED=true
IEDUCAR_CADUNICO_SCHEDULE_ENABLED=true
```

Lista completa: [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §12c e `.env.example`.

## Testes

```bash
php artisan test --filter=Cadunico
php artisan test --filter=Comparativo
php artisan test --filter=FinanceComparativo
php artisan test --filter=AnalyticsTabCatalog
php artisan test --filter=PublicDataImportCatalog
```

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) — linha 3.5.0
- [ANALYTICS_NAVEGACAO_UI.md](ANALYTICS_NAVEGACAO_UI.md) — CadÚnico + Comparativo
- [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) — hub admin (CadÚnico)
