# Release `20260602-Hermes` — ServLitcys 3.5.1

**Data:** 2026-06-02 · **Ramo:** `main` · **Figura:** *Hermes* (correção de rotas entre abas e filas — mensageiro).

## Resumo

Patch **3.5.1** sobre **3.5.0** ([RELEASE_20260601_ATLAS.md](RELEASE_20260601_ATLAS.md)):

- **Analytics — CadÚnico:** aba deixava de renderizar (lazy-load pesado, faixa de impacto, estado vazio e links admin condicionais).
- **Analytics — FUNDEB / Financiamentos:** erro `Undefined array key "comparativoData"` no lazy-load das abas financeiras.
- **Admin — CadÚnico:** matriz anual de snapshots no `/admin/cadunico-sync`; fila admin domínio **cadastro**; fluxo «Fontes públicas» com Cecad/CadÚnico.

## Destaques

### Correções Analytics

| Aba / área | Correção |
|------------|----------|
| CadÚnico (`cadunico_previsao`) | Sem pré-carga de contexto FUNDEB/discrepâncias; `tabPayload` `cadunicoPrevisaoData`; metodologia em erros; placeholder de carregamento |
| FUNDEB, Financiamentos | `comparativoData` opcional no retorno de `preloadFundebTab` / `preloadFinanceStripTab` |
| Fontes públicas (Cadastro) | `PublicDataSourcesCatalog::categoryCadunicoCecad`; links admin só para perfil com dashboard admin |

### Admin

| Item | Detalhe |
|------|---------|
| `CadunicoMunicipioSnapshotRepository::yearlyMatrix()` | Matriz por ano no admin CadÚnico |
| `AdminSyncQueueIndexPresenter` | Cartão domínio **cadastro** (CadÚnico/Cecad) |
| `AdminSystemFlowStatus` | Nó CadÚnico no fluxo «Fontes públicas e federais» |

## Deploy

```bash
git fetch --tags
git checkout 20260602-Hermes   # ou deploy de `main` após este commit
composer install --no-dev
npm run build
php artisan migrate --force
php artisan config:clear
php artisan view:clear
php artisan cache:clear
```

Sem migração nova obrigatória face a 3.5.0.

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) — linha 3.5.1
- [CADUNICO_CECAD.md](CADUNICO_CECAD.md)
